<?php

namespace Widget;

use Typecho\Common;
use Typecho\Db;
use Typecho\Exception;
use Utils\Schema;
use Utils\Migration\SchemaManager;
use Throwable;
use Widget\Base\Options as BaseOptions;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 备份工具
 *
 * @package Widget
 */
class Backup extends BaseOptions implements ActionInterface
{
    public const HEADER = '%TYPECHO_BACKUP_XXXX%';
    public const HEADER_VERSION = '0001';
    private const REPORT_SESSION_KEY = '__typecho_backup_report';

    private array $types = [
        'contents'      => 1,
        'comments'      => 2,
        'metas'         => 3,
        'relationships' => 4,
        'users'         => 5,
        'fields'        => 6
    ];

    private array $fields = [
        'contents'      => [
            'cid', 'title', 'slug', 'created', 'modified', 'text', 'order', 'authorId',
            'template', 'type', 'status', 'password', 'commentsNum', 'allowComment', 'allowPing', 'allowFeed', 'parent'
        ],
        'comments'      => [
            'coid', 'cid', 'created', 'author', 'authorId', 'ownerId',
            'mail', 'url', 'ip', 'agent', 'text', 'type', 'status', 'parent'
        ],
        'metas'         => [
            'mid', 'name', 'slug', 'type', 'description', 'count', 'order', 'parent'
        ],
        'relationships' => ['cid', 'mid'],
        'users'         => [
            'uid', 'name', 'password', 'mail', 'url', 'screenName',
            'created', 'activated', 'logged', 'group', 'authCode'
        ],
        'fields'        => [
            'cid', 'name', 'type', 'str_value', 'int_value', 'float_value'
        ]
    ];

    private array $lastIds = [];

    private array $cleared = [];

    private ?array $pendingLoginUser = null;

    private ?array $currentOperator = null;

    private array $statusWhitelist = ['approved', 'waiting', 'spam'];

    private array $repairResult = [
        'ownerFixed' => 0,
        'statusFixed' => 0,
        'orphanMoved' => 0,
        'commentsRecounted' => 0,
        'commentAuthorsSynced' => 0
    ];

    private array $runtimeWarnings = [];

    private bool $inTransaction = false;

    private ?bool $transactionSupported = null;

    /**
     * 列出已有备份文件
     *
     * @return array
     */
    public function listFiles(): array
    {
        if (!is_dir(__TYPECHO_BACKUP_DIR__)) {
            $parent = dirname(__TYPECHO_BACKUP_DIR__);
            if (!is_dir($parent) || !is_writable($parent)) {
                return [];
            }

            if (!mkdir(__TYPECHO_BACKUP_DIR__, 0755, true) && !is_dir(__TYPECHO_BACKUP_DIR__)) {
                return [];
            }
        }

        $files = glob(__TYPECHO_BACKUP_DIR__ . '/*.dat');
        if (!is_array($files)) {
            return [];
        }

        return array_map('basename', $files);
    }

    public function action()
    {
        $this->user->pass('administrator');
        $this->security->protect();
        $action = (string) $this->request->filter('trim')->get('do');

        if ('' === $action) {
            Notice::alloc()->set(_t('请求缺少必要动作参数，请重新提交恢复请求'), 'error');
            $this->finish();
            return;
        }

        if ('export' === $action) {
            $this->export();
            return;
        }

        if ('import' === $action) {
            $this->import();
            return;
        }

        Notice::alloc()->set(_t('未知恢复动作: %s', $action), 'error');
        $this->finish();
    }

    /**
     * 导出数据
     *
     * @throws \Typecho\Db\Exception
     */
    private function export()
    {
        $backupFile = tempnam(sys_get_temp_dir(), 'backup_');
        if ($backupFile === false) {
            throw new Exception(_t('无法创建临时备份文件'));
        }

        $fp = fopen($backupFile, 'wb');
        if (!$fp) {
            @unlink($backupFile);
            throw new Exception(_t('无法写入临时备份文件'));
        }

        $host = (string) (parse_url($this->options->siteUrl, PHP_URL_HOST) ?: 'site');
        $this->response->setContentType('application/octet-stream');
        $this->response->setHeader('Content-Disposition', 'attachment; filename="'
            . date('Ymd') . '_' . $host . '_' . uniqid() . '.dat"');

        try {
            $this->writeBackupFile($fp);
            $size = filesize($backupFile);
            if ($size !== false) {
                $this->response->setHeader('Content-Length', $size);
            }
        } catch (Throwable $e) {
            @unlink($backupFile);
            throw $e;
        }

        $this->response->throwCallback(function () use ($backupFile) {
            try {
                readfile($backupFile);
            } finally {
                if (is_file($backupFile)) {
                    @unlink($backupFile);
                }
            }
        }, 'application/octet-stream');
    }

    private function writeBackupFile($fp): void
    {
        try {
            $header = str_replace('XXXX', self::HEADER_VERSION, self::HEADER);
            fwrite($fp, $header);
            $db = $this->db;

            foreach ($this->types as $type => $val) {
                $page = 1;
                do {
                    $rows = $db->fetchAll($db->select()->from('table.' . $type)->page($page, 20));
                    $page++;

                    foreach ($rows as $row) {
                        fwrite($fp, $this->buildBuffer($val, $this->applyFields($type, $row, false)));
                    }
                } while (count($rows) == 20);
            }

            self::pluginHandle()->call('export', $fp);
            fwrite($fp, $header);
        } finally {
            if (is_resource($fp)) {
                fclose($fp);
            }
        }
    }

    private function buildBuffer($type, $data): string
    {
        $body = '';
        $schema = [];

        foreach ($data as $key => $val) {
            $value = null === $val ? null : (string) $val;
            $schema[$key] = null === $value ? null : strlen($value);
            $body .= $value ?? '';
        }

        $header = json_encode($schema);
        return Common::buildBackupBuffer($type, $header, $body);
    }

    private function applyFields($table, $data, bool $trackLastId = true): array
    {
        $result = [];

        foreach ($data as $key => $val) {
            $index = array_search($key, $this->fields[$table]);

            if ($index !== false) {
                $result[$key] = $val;

                if ($trackLastId && $index === 0 && !in_array($table, ['relationships', 'fields'], true)) {
                    $this->lastIds[$table] = isset($this->lastIds[$table])
                        ? max($this->lastIds[$table], $val) : $val;
                }
            }
        }

        return $result;
    }

    private function import(): void
    {
        $path = $this->resolveImportPath();
        if (null === $path) {
            return;
        }

        $doRepair = !$this->request->is('repair=0');
        $doSnapshot = !$this->request->is('snapshot=0');

        try {
            $payload = $this->readBackup($path);
            $preflight = $this->buildPreflight($payload);

            if (!empty($preflight['blocking'])) {
                $report = $this->buildReport($payload, $preflight, true, true, null, true, false, _t('恢复未开始：请先处理下列阻断项'));
                $messages = $this->reportMessages($report);
                $this->stashReport($report);
                Notice::alloc()->set($messages, 'error');
                $this->finish();
                return;
            }

            $this->resetImportState();
            $transactionSafe = $this->supportsTransaction();
            if (!$transactionSafe && !$doSnapshot) {
                $messages = [
                    _t('当前数据库恢复将以非事务方式执行，为避免清表后中断导致数据损坏，必须先创建恢复前快照')
                ];
                $this->stashReport(self::reportFromMessages($messages));
                Notice::alloc()->set($messages, 'error');
                $this->finish();
                return;
            }

            $this->captureCurrentOperator();

            $snapshotName = null;
            if ($doSnapshot) {
                $snapshotName = $this->makeSnapshot();
                if (null === $snapshotName) {
                    if ($transactionSafe) {
                        $this->runtimeWarnings[] = _t('恢复前快照创建失败，已继续执行恢复');
                    } else {
                        $messages = [
                            _t('恢复前快照创建失败，当前数据库又不支持事务回滚，已中止恢复以避免数据损坏')
                        ];
                        $this->stashReport(self::reportFromMessages($messages));
                        Notice::alloc()->set($messages, 'error');
                        $this->finish();
                        return;
                    }
                }
            }

            $this->beginImportTransaction();
            $this->clearAllCoreTables();
            $this->importPayload($payload);

            if ($doRepair) {
                $this->repairResult = $this->repairData();
            }

            $this->pendingLoginUser = $this->resolveLoginUser();
            $this->commitImportTransaction();

            if ($this->pendingLoginUser !== null) {
                try {
                    $this->reLogin($this->pendingLoginUser);
                } catch (Throwable $e) {
                    $this->runtimeWarnings[] = _t(
                        '恢复完成，但自动恢复当前登录态失败：%s',
                        $e->getMessage()
                    );
                }
            } else {
                $this->runtimeWarnings[] = _t('恢复完成，但未能自动恢复当前登录态，请使用恢复后的账号重新登录');
            }

            $report = $this->buildReport($payload, $preflight, false, false, $snapshotName, $doRepair, $doSnapshot);
            $messages = $this->reportMessages($report);
            $this->stashReport($report);
            Notice::alloc()->set($messages, 'success');
        } catch (Throwable $e) {
            $rolledBack = $this->rollbackImportTransaction();
            $messages = [_t('恢复过程中遇到如下错误: %s', $e->getMessage())];
            if (!empty($this->cleared)) {
                if ($rolledBack) {
                    $messages[] = _t('恢复事务已自动回滚，数据库保持原状态');
                } else {
                    $messages[] = _t('恢复在清表后中断，数据库可能处于部分恢复状态，请使用快照或备份回滚');
                }
            }
            $this->stashReport(self::reportFromMessages($messages));
            Notice::alloc()->set($messages, 'error');
        }

        $this->finish();
    }

    private function resolveImportPath(): ?string
    {
        if (!empty($_FILES)) {
            $file = $_FILES['file'] ?? null;
            $file = is_array($file) ? $file : (count($_FILES) === 1 ? reset($_FILES) : null);

            if (!is_array($file)) {
                return $this->failAndFinish(_t('没有选择任何备份文件'));
            }

            if (UPLOAD_ERR_NO_FILE == $file['error']) {
                return $this->failAndFinish(_t('没有选择任何备份文件'));
            }

            if (UPLOAD_ERR_OK == $file['error'] && is_uploaded_file($file['tmp_name'])) {
                return $file['tmp_name'];
            }

            return $this->failAndFinish(Common::uploadErrorMessage((int) $file['error'], '备份文件上传'));
        }

        if (!$this->request->is('file')) {
            return $this->failAndFinish(_t('没有选择任何备份文件'));
        }

        $file = basename((string) $this->request->filter('trim')->get('file'));
        if (!preg_match('/^[A-Za-z0-9._-]+\.dat$/', $file)) {
            return $this->failAndFinish(_t('备份文件名不合法'));
        }

        $base = realpath(__TYPECHO_BACKUP_DIR__);
        $path = __TYPECHO_BACKUP_DIR__ . '/' . $file;
        $real = realpath($path);

        if (!$base || !$real || strpos($real, $base) !== 0 || !is_file($real)) {
            return $this->failAndFinish(_t('备份文件不存在'));
        }

        return $real;
    }

    private function readBackup(string $file): array
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new Exception(_t('无法读取备份文件'));
        }

        $fp = fopen($file, 'rb');
        if (!$fp) {
            throw new Exception(_t('无法读取备份文件'));
        }

        $fileSize = filesize($file);
        $headerSize = strlen(self::HEADER);
        if ($fileSize < $headerSize) {
            fclose($fp);
            throw new Exception(_t('备份文件格式错误'));
        }

        $headerVersion = '';
        $footerVersion = '';

        $fileHeader = fread($fp, $headerSize);
        if (!$this->parseHeader($fileHeader, $headerVersion)) {
            fclose($fp);
            throw new Exception(_t('备份文件格式错误'));
        }

        fseek($fp, $fileSize - $headerSize);
        $fileFooter = fread($fp, $headerSize);
        if (!$this->parseHeader($fileFooter, $footerVersion)) {
            fclose($fp);
            throw new Exception(_t('备份文件格式错误'));
        }

        if ($headerVersion !== $footerVersion) {
            fclose($fp);
            throw new Exception(_t('备份文件头尾版本不一致'));
        }

        $payload = [
            'version' => $headerVersion,
            'counts' => array_fill_keys(array_keys($this->types), 0),
            'rows' => [],
            'plugins' => [],
            'summary' => [
                'invalidUtf8' => 0,
                'invalidStatus' => 0,
                'orphanCids' => [],
                'adminUsers' => 0
            ]
        ];

        $contentCids = [];
        $commentCids = [];

        fseek($fp, $headerSize);
        $offset = $headerSize;

        while (!feof($fp) && $offset + $headerSize < $fileSize) {
            $data = Common::extractBackupBuffer($fp, $offset, $headerVersion);
            if (!$data) {
                fclose($fp);
                throw new Exception(_t('恢复数据出现错误'));
            }

            [$type, $header, $body] = $data;
            $table = array_search($type, $this->types, true);

            if ($table) {
                $schema = json_decode($header, true);
                if (!is_array($schema)) {
                    fclose($fp);
                    throw new Exception(_t('备份文件格式错误'));
                }

                $record = [];
                $cursor = 0;
                foreach ($schema as $key => $val) {
                    $record[$key] = null === $val ? null : substr($body, $cursor, (int) $val);
                    $cursor += (int) $val;
                }

                $record = $this->applyFields($table, $record, false);
                $payload['counts'][$table]++;

                $payload['rows'][$table][] = $record;

                if ('contents' === $table && isset($record['cid'])) {
                    $contentCids[(int) $record['cid']] = true;
                }

                if ('comments' === $table) {
                    if (isset($record['cid'])) {
                        $commentCids[(int) $record['cid']] = true;
                    }

                    if (isset($record['status']) && !in_array((string) $record['status'], $this->statusWhitelist, true)) {
                        $payload['summary']['invalidStatus']++;
                    }

                    foreach (['text', 'author', 'mail', 'url'] as $field) {
                        if (isset($record[$field]) && !$this->isUtf8((string) $record[$field])) {
                            $payload['summary']['invalidUtf8']++;
                            break;
                        }
                    }
                }

                if ('users' === $table && (($record['group'] ?? '') === 'administrator')) {
                    $payload['summary']['adminUsers']++;
                }
            } else {
                $payload['plugins'][] = [$type, $header, $body];
            }
        }

        fclose($fp);

        $orphanCids = [];
        foreach (array_keys($commentCids) as $cid) {
            if (!isset($contentCids[$cid])) {
                $orphanCids[] = $cid;
            }
        }
        $payload['summary']['orphanCids'] = $orphanCids;

        return $payload;
    }

    private function buildPreflight(array $payload): array
    {
        $blocking = [];
        $warnings = [];
        $infos = [];

        foreach ($this->fields as $table => $fields) {
            $columns = $this->fetchColumns($table);
            if (empty($columns)) {
                $blocking[] = _t('目标数据库缺少 %s 表或无法读取其结构', $table);
                continue;
            }

            $missing = array_values(array_diff($fields, $columns));
            if (!empty($missing)) {
                $blocking[] = _t('表 %s 缺少关键字段: %s', $table, implode(', ', $missing));
            }
        }

        if (($payload['counts']['users'] ?? 0) < 1) {
            $blocking[] = _t('备份文件未包含用户数据，无法安全恢复');
        }

        if (($payload['summary']['adminUsers'] ?? 0) < 1) {
            $blocking[] = _t('备份文件未检测到管理员账户，恢复后无法登录后台');
        }

        if ($payload['summary']['invalidStatus'] > 0) {
            $warnings[] = _t('发现异常评论状态值 %d 条，导入后将自动修复为 waiting', $payload['summary']['invalidStatus']);
        }

        if ($payload['summary']['invalidUtf8'] > 0) {
            $warnings[] = _t('发现疑似编码异常评论 %d 条，可能影响编辑回填', $payload['summary']['invalidUtf8']);
        }

        if (!empty($payload['summary']['orphanCids'])) {
            $warnings[] = _t('发现孤儿评论关联内容 %d 个，导入后将自动迁移到占位内容', count($payload['summary']['orphanCids']));
        }

        $warnings[] = _t('本恢复仅迁移内容数据，不包含设置项、主题文件和插件自建表数据');
        $infos[] = _t('预检备份版本: %s', $payload['version']);

        return [
            'blocking' => $blocking,
            'warnings' => $warnings,
            'infos' => $infos
        ];
    }

    private function parseHeader($str, &$version): bool
    {
        if (!$str || strlen($str) != strlen(self::HEADER)) {
            return false;
        }

        if (!preg_match("/%TYPECHO_BACKUP_[A-Z0-9]{4}%/", $str)) {
            return false;
        }

        $version = substr($str, 16, - 1);
        return true;
    }

    private function fetchColumns(string $table): array
    {
        $adapter = strtolower($this->db->getAdapterName());
        $tableName = $this->db->getPrefix() . $table;

        try {
            if (false !== strpos($adapter, 'mysql')) {
                $rows = $this->db->fetchAll($this->db->query('SHOW COLUMNS FROM ' . $this->quoteIdentifier($tableName)));
                return array_values(array_map(static fn($row) => (string) ($row['Field'] ?? ''), $rows));
            }

            if (false !== strpos($adapter, 'pgsql')) {
                $rows = $this->db->fetchAll(
                    $this->db->select('column_name')->from('information_schema.columns')
                        ->where('table_schema = current_schema()')
                        ->where('table_name = ?', $tableName)
                );
                return array_values(array_map(static fn($row) => (string) ($row['column_name'] ?? ''), $rows));
            }

            if (false !== strpos($adapter, 'sqlite')) {
                $rows = $this->db->fetchAll($this->db->query('PRAGMA table_info(' . $this->quoteIdentifier($tableName) . ')'));
                return array_values(array_map(static fn($row) => (string) ($row['name'] ?? ''), $rows));
            }
        } catch (Throwable) {
            return [];
        }

        return [];
    }

    private function quoteIdentifier(string $name): string
    {
        $adapter = $this->db->getAdapter();
        $parts = array_map(
            static fn(string $part): string => $adapter->quoteColumn($part),
            explode('.', $name)
        );

        return implode('.', $parts);
    }

    private function isUtf8(string $value): bool
    {
        if ($value === '') {
            return true;
        }

        if (function_exists('mb_check_encoding')) {
            return mb_check_encoding($value, 'UTF-8');
        }

        return (bool) preg_match('//u', $value);
    }

    private function makeSnapshot(): ?string
    {
        $fileName = date('Ymd_His') . '_before_import_' . uniqid() . '.dat';
        $path = __TYPECHO_BACKUP_DIR__ . '/' . $fileName;
        if (!is_dir(__TYPECHO_BACKUP_DIR__) || !is_writable(__TYPECHO_BACKUP_DIR__)) {
            return null;
        }

        $fp = fopen($path, 'wb');
        if (!$fp) {
            return null;
        }

        try {
            $this->writeBackupFile($fp);
            return $fileName;
        } catch (Throwable) {
            if (is_file($path)) {
                @unlink($path);
            }
            return null;
        }
    }

    private function resetImportState(): void
    {
        $this->lastIds = [];
        $this->cleared = [];
        $this->inTransaction = false;
        $this->transactionSupported = null;
        $this->pendingLoginUser = null;
        $this->currentOperator = null;
        $this->repairResult = [
            'ownerFixed' => 0,
            'statusFixed' => 0,
            'orphanMoved' => 0,
            'commentsRecounted' => 0,
            'commentAuthorsSynced' => 0
        ];
        $this->runtimeWarnings = [];
    }

    private function clearAllCoreTables(): void
    {
        $tables = array_keys($this->types);
        if ($this->inTransaction) {
            $tables = array_reverse($tables);
        }

        foreach ($tables as $table) {
            if ($this->inTransaction) {
                $this->db->query($this->db->delete('table.' . $table));
            } else {
                $this->db->truncate('table.' . $table);
            }
            $this->cleared[$table] = true;
        }
    }

    private function beginImportTransaction(): void
    {
        if (!$this->supportsTransaction()) {
            return;
        }

        $this->db->query('BEGIN');
        $this->inTransaction = true;
    }

    private function commitImportTransaction(): void
    {
        if (!$this->inTransaction) {
            return;
        }

        $this->db->query('COMMIT');
        $this->inTransaction = false;
    }

    private function rollbackImportTransaction(): bool
    {
        if (!$this->inTransaction) {
            return false;
        }

        try {
            $this->db->query('ROLLBACK');
            return true;
        } catch (Throwable) {
            return false;
        } finally {
            $this->inTransaction = false;
        }
    }

    private function supportsTransaction(): bool
    {
        if ($this->transactionSupported !== null) {
            return $this->transactionSupported;
        }

        $adapter = strtolower($this->db->getAdapterName());
        if (false !== strpos($adapter, 'pgsql') || false !== strpos($adapter, 'sqlite')) {
            return $this->transactionSupported = true;
        }

        if (false !== strpos($adapter, 'mysql')) {
            return $this->transactionSupported = $this->supportsMysqlTransaction();
        }

        return $this->transactionSupported = false;
    }

    private function supportsMysqlTransaction(): bool
    {
        foreach (array_keys($this->types) as $table) {
            $tableName = $this->db->getPrefix() . $table;

            try {
                $row = Schema::mysqlTableStatus($this->db, $tableName);
            } catch (Throwable $e) {
                $this->runtimeWarnings[] = _t('无法确认表 %s 的事务能力，恢复将按非事务方式执行: %s', $table, $e->getMessage());
                return false;
            }

            $engine = strtolower((string) ($row['Engine'] ?? ''));
            if ($engine === '') {
                $this->runtimeWarnings[] = _t('无法确认表 %s 的存储引擎，恢复将按非事务方式执行', $table);
                return false;
            }

            if (!in_array($engine, ['innodb', 'xtradb'], true)) {
                $this->runtimeWarnings[] = _t('表 %s 当前使用 %s 引擎，恢复将按非事务方式执行', $table, strtoupper($engine));
                return false;
            }
        }

        return true;
    }

    private function importPayload(array $payload): void
    {
        foreach (array_keys($this->types) as $table) {
            foreach ($payload['rows'][$table] ?? [] as $row) {
                $this->insertData($table, $row);
            }
        }

        $pluginRecords = $payload['plugins'] ?? [];
        if (!is_array($pluginRecords)) {
            $pluginRecords = [];
        }

        foreach ($pluginRecords as $record) {
            if (!is_array($record) || count($record) < 3) {
                throw new Exception(_t('插件扩展导入数据格式无效'));
            }

            [$type, $header, $body] = array_values($record);
            try {
                self::pluginHandle()->import($type, $header, $body);
            } catch (Throwable $e) {
                throw new Exception(_t('插件扩展导入失败(type=%s): %s', (string) $type, $e->getMessage()));
            }
        }

        if (false !== strpos(strtolower($this->db->getAdapterName()), 'pgsql')) {
            foreach ($this->lastIds as $table => $id) {
                $seq = $this->db->getPrefix() . $table . '_seq';
                $this->db->query('ALTER SEQUENCE ' . $this->quoteIdentifier($seq) . ' RESTART WITH ' . ((int) $id + 1));
            }
        }
    }

    private function insertData(string $table, array $data): void
    {
        $this->db->query($this->db->insert('table.' . $table)->rows($this->applyFields($table, $data, true)));
    }

    private function repairData(): array
    {
        $prefix = $this->db->getPrefix();
        $comments = $this->quoteIdentifier($prefix . 'comments');
        $contents = $this->quoteIdentifier($prefix . 'contents');
        $authorIdColumn = $this->quoteIdentifier('authorId');
        $cidColumn = $this->quoteIdentifier('cid');
        $coidColumn = $this->quoteIdentifier('coid');
        $ownerIdColumn = $this->quoteIdentifier('ownerId');
        $statusColumn = $this->quoteIdentifier('status');

        $ownerSql = 'UPDATE ' . $comments . ' SET ' . $ownerIdColumn . ' = (SELECT ' . $authorIdColumn . ' FROM ' . $contents
            . ' WHERE ' . $contents . '.' . $cidColumn . ' = ' . $comments . '.' . $cidColumn . ')'
            . ' WHERE (' . $ownerIdColumn . ' IS NULL OR ' . $ownerIdColumn . ' = 0)'
            . ' AND EXISTS (SELECT 1 FROM ' . $contents . ' WHERE ' . $contents . '.' . $cidColumn
            . ' = ' . $comments . '.' . $cidColumn . ')';
        $ownerFixed = (int) $this->db->query($ownerSql, Db::WRITE, Db::UPDATE);

        $statusSql = 'UPDATE ' . $comments . " SET " . $statusColumn . " = 'waiting'"
            . ' WHERE ' . $statusColumn . " IS NULL OR " . $statusColumn . " NOT IN ('approved','waiting','spam')";
        $statusFixed = (int) $this->db->query($statusSql, Db::WRITE, Db::UPDATE);

        $orphanRows = $this->db->fetchAll(
            $this->db->query(
                'SELECT c.' . $coidColumn . ' AS coid FROM ' . $comments . ' c LEFT JOIN ' . $contents . ' t'
                . ' ON t.' . $cidColumn . ' = c.' . $cidColumn . ' WHERE t.' . $cidColumn . ' IS NULL'
            )
        );

        $orphanMoved = 0;
        $holderCid = 0;
        if (!empty($orphanRows)) {
            $holderCid = $this->ensureHolderContent($this->resolveHolderAuthorId());
            $coids = array_map(static fn($row) => (int) ($row['coid'] ?? 0), $orphanRows);
            $coids = array_values(array_filter($coids, static fn($coid) => $coid > 0));

            foreach (array_chunk($coids, 200) as $chunk) {
                $sql = 'UPDATE ' . $comments . ' SET ' . $cidColumn . ' = ' . $holderCid
                    . ' WHERE ' . $coidColumn . ' IN (' . implode(',', $chunk) . ')';
                $orphanMoved += (int) $this->db->query($sql, Db::WRITE, Db::UPDATE);
            }
        }

        $this->db->query($this->db->update('table.contents')->rows(['commentsNum' => 0]));
        $countRows = $this->db->fetchAll(
            $this->db->query(
                'SELECT ' . $cidColumn . ' AS cid, COUNT(' . $coidColumn . ') AS num FROM ' . $comments
                . " WHERE " . $statusColumn . " = 'approved' GROUP BY " . $cidColumn
            )
        );
        foreach ($countRows as $row) {
            $this->db->query(
                $this->db->update('table.contents')
                    ->rows(['commentsNum' => (int) ($row['num'] ?? 0)])
                    ->where('cid = ?', (int) ($row['cid'] ?? 0))
            );
        }
        $commentsRecounted = count($countRows);
        $commentAuthorsSynced = SchemaManager::syncCommentAuthors($this->db);

        return [
            'ownerFixed' => $ownerFixed,
            'statusFixed' => $statusFixed,
            'orphanMoved' => $orphanMoved,
            'commentsRecounted' => $commentsRecounted,
            'commentAuthorsSynced' => $commentAuthorsSynced
        ];
    }

    private function ensureHolderContent(int $authorId): int
    {
        $existing = $this->db->fetchRow(
            $this->db->select('cid')
                ->from('table.contents')
                ->where('type = ?', 'page')
                ->where('slug = ?', 'migration-holder')
                ->limit(1)
        );

        if (!empty($existing['cid'])) {
            return (int) $existing['cid'];
        }

        $now = (int) $this->options->time;
        return (int) $this->db->query(
            $this->db->insert('table.contents')->rows([
                'title' => _t('迁移占位内容'),
                'slug' => 'migration-holder',
                'created' => $now,
                'modified' => $now,
                'text' => _t('该内容由迁移修复任务自动创建，用于承接原站点中未能匹配到文章的评论数据。'),
                'order' => 0,
                'authorId' => $authorId,
                'template' => null,
                'type' => 'page',
                'status' => 'hidden',
                'password' => null,
                'commentsNum' => 0,
                'allowComment' => '0',
                'allowPing' => '0',
                'allowFeed' => '0',
                'parent' => 0
            ])
        );
    }

    private function buildReport(
        array $payload,
        array $preflight,
        bool $isCheck,
        bool $withBlocking,
        ?string $snapshotName = null,
        bool $didRepair = true,
        bool $snapshotRequested = false,
        ?string $statusMessage = null
    ): array {
        $counts = $payload['counts'];
        $report = [
            'blocking' => [],
            'warning' => [],
            'info' => [],
        ];

        $report['info'][] = _t(
            '记录统计：文章 %d、评论 %d、分类与标签 %d、关系 %d、用户 %d、字段 %d',
            $counts['contents'] ?? 0,
            $counts['comments'] ?? 0,
            $counts['metas'] ?? 0,
            $counts['relationships'] ?? 0,
            $counts['users'] ?? 0,
            $counts['fields'] ?? 0
        );
        $report['info'][] = _t('预检阻断项：%d，预警项：%d', count($preflight['blocking']), count($preflight['warnings']));
        if (!empty($preflight['infos'])) {
            foreach ($preflight['infos'] as $line) {
                $report['info'][] = $line;
            }
        }

        if ($statusMessage !== null && $statusMessage !== '') {
            $report['info'][] = $statusMessage;
        } else {
            $report['info'][] = _t('恢复已完成：核心数据已导入');
        }

        if ($snapshotRequested) {
            if ($snapshotName) {
                $report['info'][] = _t('已生成恢复前快照：%s', $snapshotName);
            } else {
                $report['warning'][] = _t('恢复前快照创建失败');
            }
        }

        if (!$isCheck && $didRepair) {
            $report['info'][] = _t(
                '修复结果：ownerId %d 条，状态 %d 条，孤儿评论 %d 条，计数回填 %d 篇，作者昵称同步 %d 条',
                $this->repairResult['ownerFixed'],
                $this->repairResult['statusFixed'],
                $this->repairResult['orphanMoved'],
                $this->repairResult['commentsRecounted'],
                $this->repairResult['commentAuthorsSynced']
            );
        } elseif (!$isCheck) {
            $report['info'][] = _t('本次恢复未执行迁移后修复');
        }

        if ($withBlocking) {
            foreach ($preflight['blocking'] as $line) {
                $report['blocking'][] = (string) $line;
            }
        }

        foreach ($preflight['warnings'] as $line) {
            $report['warning'][] = (string) $line;
        }
        foreach ($this->runtimeWarnings as $line) {
            $report['warning'][] = (string) $line;
        }

        return $report;
    }

    private function reportMessages(array $report): array
    {
        $messages = array_values(array_filter((array) ($report['info'] ?? []), 'is_string'));

        foreach ((array) ($report['blocking'] ?? []) as $line) {
            $messages[] = _t('阻断：%s', $line);
        }

        foreach ((array) ($report['warning'] ?? []) as $line) {
            $messages[] = _t('预警：%s', $line);
        }

        return $messages;
    }

    public static function consumeReport(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return ['blocking' => [], 'warning' => [], 'info' => []];
        }

        $report = $_SESSION[self::REPORT_SESSION_KEY] ?? null;
        unset($_SESSION[self::REPORT_SESSION_KEY]);

        if (!is_array($report)) {
            return ['blocking' => [], 'warning' => [], 'info' => []];
        }

        return [
            'blocking' => array_values(array_filter((array) ($report['blocking'] ?? []), 'is_string')),
            'warning' => array_values(array_filter((array) ($report['warning'] ?? []), 'is_string')),
            'info' => array_values(array_filter((array) ($report['info'] ?? []), 'is_string')),
        ];
    }

    private function stashReport(array $report): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION[self::REPORT_SESSION_KEY] = [
            'blocking' => array_values(array_filter((array) ($report['blocking'] ?? []), 'is_string')),
            'warning' => array_values(array_filter((array) ($report['warning'] ?? []), 'is_string')),
            'info' => array_values(array_filter((array) ($report['info'] ?? []), 'is_string')),
        ];
    }

    public static function reportFromMessages(array $messages): array
    {
        $report = ['blocking' => [], 'warning' => [], 'info' => []];

        foreach ($messages as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (strpos($line, '阻断：') === 0) {
                $report['blocking'][] = trim(substr($line, strlen('阻断：')));
            } elseif (strpos($line, '预警：') === 0) {
                $report['warning'][] = trim(substr($line, strlen('预警：')));
            } else {
                $report['info'][] = $line;
            }
        }

        return $report;
    }

    private function finish(): void
    {
        $this->response->redirect($this->options->adminUrl('backup.php', true));
    }

    private function failAndFinish(string $message): ?string
    {
        $messages = [$message];
        $this->stashReport(self::reportFromMessages($messages));
        Notice::alloc()->set($messages, 'error');
        $this->finish();
        return null;
    }

    private function captureCurrentOperator(): void
    {
        $this->currentOperator = [
            'uid' => (int) ($this->user->uid ?? 0),
            'name' => (string) ($this->user->name ?? ''),
            'mail' => (string) ($this->user->mail ?? ''),
        ];
    }

    private function resolveLoginUser(): ?array
    {
        if (null === $this->currentOperator) {
            return null;
        }

        $uid = (int) ($this->currentOperator['uid'] ?? 0);
        if ($uid > 0) {
            $user = $this->findUserBy('uid', $uid);
            if (null !== $user) {
                return $user;
            }
        }

        $mail = trim((string) ($this->currentOperator['mail'] ?? ''));
        if ($mail !== '') {
            $user = $this->findUserBy('mail', $mail);
            if (null !== $user) {
                return $user;
            }
        }

        $name = trim((string) ($this->currentOperator['name'] ?? ''));
        if ($name !== '') {
            $user = $this->findUserBy('name', $name);
            if (null !== $user) {
                return $user;
            }
        }

        return null;
    }

    private function resolveHolderAuthorId(): int
    {
        $loginUser = $this->resolveLoginUser();
        if (!empty($loginUser['uid'])) {
            return (int) $loginUser['uid'];
        }

        $admin = $this->db->fetchRow(
            $this->db->select('uid')
                ->from('table.users')
                ->where('group = ?', 'administrator')
                ->order('uid', Db::SORT_ASC)
                ->limit(1)
        );
        if (!empty($admin['uid'])) {
            return (int) $admin['uid'];
        }

        $user = $this->db->fetchRow(
            $this->db->select('uid')
                ->from('table.users')
                ->order('uid', Db::SORT_ASC)
                ->limit(1)
        );
        if (!empty($user['uid'])) {
            return (int) $user['uid'];
        }

        throw new Exception(_t('恢复后的用户数据无有效作者，无法创建迁移占位内容'));
    }

    private function findUserBy(string $field, int|string $value): ?array
    {
        $user = $this->db->fetchRow(
            $this->db->select()
                ->from('table.users')
                ->where($field . ' = ?', $value)
                ->limit(1)
        );

        return empty($user) ? null : $user;
    }

    private function reLogin(array $user): void
    {
        User::alloc()->commitLogin($user);
        $this->pendingLoginUser = null;
    }
}
