<?php

namespace Utils\Migration;

use Typecho\Common;
use Typecho\Db;
use Utils\Comment;
use Utils\Defaults;
use Utils\Schema;
use Widget\Base\Options as OptionsStorage;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class SchemaManager
{
    public static function syncCurrentRelease(Db $db, array $activatedPlugins = []): array
    {
        $messages = [_t('当前版本所需的数据库结构已同步')];
        self::ensureMailInfrastructure($db);
        Schema::ensureCoreIndexes($db);
        Schema::ensureUserPasswordStorage($db);
        $syncedComments = self::syncCommentAuthors($db);
        if ($syncedComments > 0) {
            $messages[] = _t('已同步 %d 条历史评论的作者昵称', $syncedComments);
        }

        if (in_array('RenewGo', $activatedPlugins, true)) {
            Schema::ensureRenewGo($db);
        }

        if (in_array('RenewSEO', $activatedPlugins, true)) {
            Schema::ensureRenewSeo($db);
        }

        self::updateGenerator($db, Common::VERSION);

        return [
            'messages' => $messages,
        ];
    }

    public static function inspectCriticalSchema(Db $db): array
    {
        $items = [];
        $missing = [];
        $prefix = (string) $db->getPrefix();
        $dialect = Schema::dialect($db);
        $expectedCollation = $dialect === 'mysql' ? Schema::detectMysqlCollation($db) : '';
        $tables = Schema::criticalSchema();

        foreach ($tables as $key => $meta) {
            $exists = self::tableExists($db, 'table.' . $key);
            $missingColumns = $exists
                ? self::missingColumns($db, $prefix . $key, Schema::criticalColumns($key))
                : [];
            $missingIndexes = $exists
                ? self::missingIndexes($db, $prefix . $key, Schema::criticalIndexes($db, $key, $prefix . $key))
                : [];
            $typeMismatches = ($exists && $dialect === 'mysql')
                ? Schema::mysqlTypeMismatches($db, $prefix . $key, (array) (($meta['mysql']['definitions'] ?? [])))
                : [];
            $tableCollation = ($exists && $dialect === 'mysql')
                ? Schema::mysqlTableCollation($db, $prefix . $key)
                : '';
            $collationOk = $tableCollation === '' || $expectedCollation === ''
                ? true
                : strtolower($tableCollation) === strtolower($expectedCollation);
            $schemaOk = $exists
                && $missingColumns === []
                && $missingIndexes === []
                && $typeMismatches === []
                && $collationOk;
            $item = [
                'key' => $key,
                'label' => (string) ($meta['label'] ?? $key),
                'table' => $prefix . $key,
                'exists' => $exists,
                'missingColumns' => $missingColumns,
                'missingIndexes' => $missingIndexes,
                'typeMismatches' => $typeMismatches,
                'tableCollation' => $tableCollation,
                'expectedCollation' => $expectedCollation,
                'collationOk' => $collationOk,
                'status' => !$exists
                    ? 'missing_table'
                    : (($missingColumns === [])
                        ? ($schemaOk ? 'ok' : 'schema_mismatch')
                        : 'missing_columns'),
            ];
            $items[] = $item;

            if (!$exists || !$schemaOk) {
                $missing[] = $item;
            }
        }

        return [
            'healthy' => empty($missing),
            'items' => $items,
            'missing' => $missing
        ];
    }

    public static function repairCriticalSchema(Db $db): array
    {
        self::ensureMailInfrastructure($db);
        Schema::repairMailInfra($db);
        $after = self::inspectCriticalSchema($db);
        $syncedComments = self::syncCommentAuthors($db);

        return [
            'healthy' => $after['healthy'],
            'after' => $after,
            'syncedComments' => $syncedComments
        ];
    }

    public static function inspectMysqlUpgradeRisks(Db $db): array
    {
        if (Schema::dialect($db) !== 'mysql') {
            return [
                'supported' => false,
                'healthy' => true,
                'items' => []
            ];
        }

        $rawVersion = $db->getVersion(Db::READ);
        $version = \Utils\DbInfo::extractVersion($rawVersion);
        $collation = Schema::detectMysqlCollation($db);
        $minimum = \Utils\DbInfo::minimumMysqlVersion($rawVersion);
        $label = \Utils\DbInfo::mysqlLabel($rawVersion);
        $items = [];

        $items[] = [
            'key' => 'mysql_version',
            'label' => '数据库版本',
            'status' => $version !== '' ? 'ok' : 'warning',
            'detail' => $rawVersion !== '' ? $rawVersion : '无法识别当前版本',
            'repairRelated' => false,
        ];

        $legacyIndexLimit = $version !== '' && version_compare($version, $minimum, '<');
        $items[] = [
            'key' => 'legacy_index_limit',
            'label' => '数据库版本不满足结构修复要求',
            'status' => $legacyIndexLimit ? 'warning' : 'ok',
            'detail' => $legacyIndexLimit
                ? '当前 ' . $label . ' 版本为 ' . $rawVersion . '，低于结构修复建议的最低版本 ' . $minimum
                : '当前版本满足结构修复的最低要求',
            'repairRelated' => true,
        ];

        $mailUnsubTable = $db->getPrefix() . 'mail_unsub';
        $mailUnsubExists = self::tableExists($db, 'table.mail_unsub');
        $mailUnsubCollation = $mailUnsubExists ? Schema::mysqlTableCollation($db, $mailUnsubTable) : '';
        $mailUnsubMismatch = $mailUnsubExists && $mailUnsubCollation !== '' && strtolower($mailUnsubCollation) !== strtolower($collation);
        $items[] = [
            'key' => 'mail_unsub_collation',
            'label' => 'mail_unsub 排序规则',
            'status' => !$mailUnsubExists || !$mailUnsubMismatch ? 'ok' : 'warning',
            'detail' => !$mailUnsubExists
                ? '表不存在，升级时会按当前版本创建'
                : ($mailUnsubMismatch
                    ? '当前为 ' . $mailUnsubCollation . '，目标推荐为 ' . $collation
                    : '当前已与目标排序规则一致'),
            'repairRelated' => true,
        ];

        $mailUnsubDuplicates = self::mailUnsubDuplicateGroups($db, $collation);
        $items[] = [
            'key' => 'mail_unsub_duplicates',
            'label' => 'mail_unsub 唯一值冲突',
            'status' => $mailUnsubDuplicates['error'] !== null
                ? 'warning'
                : ($mailUnsubDuplicates['rows'] === [] ? 'ok' : 'warning'),
            'detail' => $mailUnsubDuplicates['error'] !== null
                ? '重复值检查失败：' . $mailUnsubDuplicates['error']
                : ($mailUnsubDuplicates['rows'] === []
                ? '未发现按目标排序规则归一后的 email + scope 冲突'
                : '发现 ' . count($mailUnsubDuplicates['rows']) . ' 组按目标排序规则归一后的 email + scope 重复，修复索引前需先清理'),
            'samples' => $mailUnsubDuplicates['rows'],
            'repairRelated' => true,
        ];

        $userDuplicates = self::usersMailDuplicateGroups($db, $collation);
        $items[] = [
            'key' => 'users_mail_duplicates',
            'label' => 'users 邮箱唯一值冲突',
            'status' => $userDuplicates['error'] !== null
                ? 'warning'
                : ($userDuplicates['rows'] === [] ? 'ok' : 'warning'),
            'detail' => $userDuplicates['error'] !== null
                ? '重复值检查失败：' . $userDuplicates['error']
                : ($userDuplicates['rows'] === []
                ? '未发现按目标排序规则归一后的 users.mail 重复'
                : '发现 ' . count($userDuplicates['rows']) . ' 组按目标排序规则归一后的重复邮箱，排序规则升级后可能触发唯一键冲突'),
            'samples' => $userDuplicates['rows'],
            'repairRelated' => false,
        ];

        $slugDuplicates = self::contentsSlugDuplicateGroups($db, $collation);
        $items[] = [
            'key' => 'contents_slug_duplicates',
            'label' => 'contents 缩略名唯一值冲突',
            'status' => $slugDuplicates['error'] !== null
                ? 'warning'
                : ($slugDuplicates['rows'] === [] ? 'ok' : 'warning'),
            'detail' => $slugDuplicates['error'] !== null
                ? '重复值检查失败：' . $slugDuplicates['error']
                : ($slugDuplicates['rows'] === []
                ? '未发现按目标排序规则归一后的 contents.slug 重复'
                : '发现 ' . count($slugDuplicates['rows']) . ' 组按目标排序规则归一后的重复缩略名，升级后可能触发唯一键冲突'),
            'samples' => $slugDuplicates['rows'],
            'repairRelated' => false,
        ];

        $userNameDuplicates = self::usersNameDuplicateGroups($db, $collation);
        $items[] = [
            'key' => 'users_name_duplicates',
            'label' => 'users 用户名唯一值冲突',
            'status' => $userNameDuplicates['error'] !== null
                ? 'warning'
                : ($userNameDuplicates['rows'] === [] ? 'ok' : 'warning'),
            'detail' => $userNameDuplicates['error'] !== null
                ? '重复值检查失败：' . $userNameDuplicates['error']
                : ($userNameDuplicates['rows'] === []
                ? '未发现按目标排序规则归一后的 users.name 重复'
                : '发现 ' . count($userNameDuplicates['rows']) . ' 组按目标排序规则归一后的重复用户名，升级后可能触发唯一键冲突'),
            'samples' => $userNameDuplicates['rows'],
            'repairRelated' => false,
        ];

        $healthy = true;
        foreach ($items as $item) {
            if (($item['status'] ?? 'ok') !== 'ok') {
                $healthy = false;
                break;
            }
        }

        return [
            'supported' => true,
            'healthy' => $healthy,
            'version' => $rawVersion,
            'collation' => $collation,
            'items' => $items,
        ];
    }

    private static function ensureMailInfrastructure(Db $db): void
    {
        Schema::ensureMailInfra($db);
        $defaults = self::defaultMailOptions();
        $existing = $db->fetchAll(
            $db->select('name')
                ->from('table.options')
                ->where('user = ? AND name IN ?', 0, array_keys($defaults))
        );
        $existingNames = array_flip(array_map('strval', array_column($existing, 'name')));
        $missing = [];

        foreach ($defaults as $name => $value) {
            if (isset($existingNames[$name])) {
                continue;
            }
            $missing[$name] = $value;
        }

        if (!empty($missing)) {
            OptionsStorage::alloc()->saveOptions($missing);
        }
    }

    private static function defaultMailOptions(): array
    {
        return Defaults::repairableOptions([
            'mailCronKey' => Common::randString(32),
        ]);
    }

    private static function updateGenerator(Db $db, string $version): void
    {
        $db->query(
            $db->update('table.options')
                ->rows(['value' => Common::generator($version)])
                ->where('name = ?', 'generator')
        );
    }

    public static function syncCommentAuthors(Db $db): int
    {
        if (!self::tableExists($db, 'table.users') || !self::tableExists($db, 'table.comments')) {
            return 0;
        }

        return Comment::syncAllAuthors($db, self::commentCacheFlush());
    }

    private static function tableExists(Db $db, string $tableAlias): bool
    {
        try {
            $db->fetchRow($db->select('1')->from($tableAlias)->limit(1));
            return true;
        } catch (\Typecho\Db\Adapter\SQLException $e) {
            return false;
        }
    }

    private static function commentCacheFlush(): int
    {
        try {
            return (int) (Options::alloc()->cacheCommentFlush ?? 1);
        } catch (\Throwable) {
            return 1;
        }
    }

    private static function missingColumns(Db $db, string $table, array $columns): array
    {
        $missing = [];

        foreach ($columns as $column) {
            if (!Schema::columnExists($db, $table, (string) $column)) {
                $missing[] = (string) $column;
            }
        }

        return $missing;
    }

    private static function missingIndexes(Db $db, string $table, array $indexes): array
    {
        $missing = [];

        foreach ($indexes as $index) {
            if (!Schema::indexExists($db, $table, (string) $index)) {
                $missing[] = (string) $index;
            }
        }

        return $missing;
    }


    private static function mailUnsubDuplicateGroups(Db $db, string $collation): array
    {
        if (!self::tableExists($db, 'table.mail_unsub')) {
            return ['rows' => [], 'error' => null];
        }

        try {
            $normalizedEmail = self::mysqlNormalizedText('email', $collation);
            $normalizedScope = self::mysqlNormalizedText('scope', $collation);
            $rows = $db->fetchAll(
                'SELECT MIN(email) AS email, MIN(scope) AS scope, COUNT(*) AS num'
                . ' FROM ' . $db->getPrefix() . 'mail_unsub'
                . ' GROUP BY ' . $normalizedEmail . ', ' . $normalizedScope
                . ' HAVING COUNT(*) > 1'
                . ' ORDER BY num DESC, email ASC'
                . ' LIMIT 5'
            );
        } catch (\Throwable $e) {
            return ['rows' => [], 'error' => $e->getMessage()];
        }

        return [
            'rows' => array_map(static function (array $row): array {
                return [
                    'email' => (string) ($row['email'] ?? ''),
                    'scope' => (string) ($row['scope'] ?? ''),
                    'count' => (int) ($row['num'] ?? 0),
                ];
            }, $rows),
            'error' => null,
        ];
    }

    private static function usersMailDuplicateGroups(Db $db, string $collation): array
    {
        if (!self::tableExists($db, 'table.users')) {
            return ['rows' => [], 'error' => null];
        }

        try {
            $normalizedMail = self::mysqlNormalizedText('mail', $collation);
            $rows = $db->fetchAll(
                'SELECT MIN(mail) AS mail, COUNT(*) AS num'
                . ' FROM ' . $db->getPrefix() . 'users'
                . ' WHERE mail IS NOT NULL AND mail <> \'\''
                . ' GROUP BY ' . $normalizedMail
                . ' HAVING COUNT(*) > 1'
                . ' ORDER BY num DESC, mail ASC'
                . ' LIMIT 5'
            );
        } catch (\Throwable $e) {
            return ['rows' => [], 'error' => $e->getMessage()];
        }

        return [
            'rows' => array_map(static function (array $row): array {
                return [
                    'mail' => (string) ($row['mail'] ?? ''),
                    'count' => (int) ($row['num'] ?? 0),
                ];
            }, $rows),
            'error' => null,
        ];
    }

    private static function contentsSlugDuplicateGroups(Db $db, string $collation): array
    {
        if (!self::tableExists($db, 'table.contents')) {
            return ['rows' => [], 'error' => null];
        }

        try {
            $normalizedSlug = self::mysqlNormalizedText('slug', $collation);
            $rows = $db->fetchAll(
                'SELECT MIN(slug) AS slug, COUNT(*) AS num'
                . ' FROM ' . $db->getPrefix() . 'contents'
                . ' WHERE slug IS NOT NULL AND slug <> \'\''
                . ' GROUP BY ' . $normalizedSlug
                . ' HAVING COUNT(*) > 1'
                . ' ORDER BY num DESC, slug ASC'
                . ' LIMIT 5'
            );
        } catch (\Throwable $e) {
            return ['rows' => [], 'error' => $e->getMessage()];
        }

        return [
            'rows' => array_map(static function (array $row): array {
                return [
                    'slug' => (string) ($row['slug'] ?? ''),
                    'count' => (int) ($row['num'] ?? 0),
                ];
            }, $rows),
            'error' => null,
        ];
    }

    private static function usersNameDuplicateGroups(Db $db, string $collation): array
    {
        if (!self::tableExists($db, 'table.users')) {
            return ['rows' => [], 'error' => null];
        }

        try {
            $normalizedName = self::mysqlNormalizedText('name', $collation);
            $rows = $db->fetchAll(
                'SELECT MIN(name) AS name, COUNT(*) AS num'
                . ' FROM ' . $db->getPrefix() . 'users'
                . ' WHERE name IS NOT NULL AND name <> \'\''
                . ' GROUP BY ' . $normalizedName
                . ' HAVING COUNT(*) > 1'
                . ' ORDER BY num DESC, name ASC'
                . ' LIMIT 5'
            );
        } catch (\Throwable $e) {
            return ['rows' => [], 'error' => $e->getMessage()];
        }

        return [
            'rows' => array_map(static function (array $row): array {
                return [
                    'name' => (string) ($row['name'] ?? ''),
                    'count' => (int) ($row['num'] ?? 0),
                ];
            }, $rows),
            'error' => null,
        ];
    }

    private static function mysqlNormalizedText(string $column, string $collation): string
    {
        $safeCollation = preg_match('/^[A-Za-z0-9_]+$/', $collation) === 1
            ? $collation
            : 'utf8mb4_unicode_ci';

        return 'CONVERT(' . $column . ' USING utf8mb4) COLLATE ' . $safeCollation;
    }
}
