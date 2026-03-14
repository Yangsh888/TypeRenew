<?php

namespace Utils;

use Typecho\Common;
use Typecho\Db;
use Widget\Options;

/**
 * 升级程序
 *
 * @category typecho
 * @package Upgrade
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Upgrade
{
    private static array $backupStack = [];

    private static function tryUnserialize(string $value)
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $result = @unserialize($value, ['allowed_classes' => false]);
        if ($result === false && $value !== 'b:0;' && $value !== 'N;') {
            return null;
        }
        return $result;
    }

    public static function backup(Db $db, string $table, string $where = ''): bool
    {
        try {
            $rows = $db->fetchAll($db->select()->from($table)->where($where ?: '1=1'));
            if (empty($rows)) {
                return true;
            }
            $key = $table . ':' . ($where ?: 'all');
            self::$backupStack[$key] = [
                'table' => $table,
                'where' => $where,
                'data' => $rows,
                'time' => time()
            ];
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function rollback(): int
    {
        $count = 0;
        foreach (self::$backupStack as $key => $backup) {
            try {
                $table = $backup['table'];
                $where = $backup['where'];
                $db = Db::get();
                if ($where !== '' && $where !== '1=1') {
                    $db->query($db->update($table)->rows($backup['data'][0])->where($where));
                } else {
                    foreach ($backup['data'] as $row) {
                        $db->query($db->update($table)->rows($row)->where('id = ?', $row['id'] ?? 0));
                    }
                }
                $count++;
            } catch (\Throwable $e) {
                continue;
            }
        }
        self::$backupStack = [];
        return $count;
    }

    public static function clearBackup(): void
    {
        self::$backupStack = [];
    }

    /**
     * @param Db $db
     * @param Options $options
     */
    public static function v1_3_0(Db $db, Options $options)
    {
        $routingTable = $options->routingTable;

        $routingTable['comment_page'] = [
            'url'    => '[permalink:string]/comment-page-[commentPage:digital]',
            'widget' => '\Widget\CommentPage',
            'action' => 'action'
        ];

        $routingTable['feed'] = [
            'url'    => '/feed[feed:string:0]',
            'widget' => '\Widget\Feed',
            'action' => 'render'
        ];

        unset($routingTable[0]);

        $db->query($db->update('table.options')
            ->rows(['value' => json_encode($routingTable)])
            ->where('name = ?', 'routingTable'));

        // fix options->commentsRequireURL
        $db->query($db->update('table.options')
            ->rows(['name' => 'commentsRequireUrl'])
            ->where('name = ?', 'commentsRequireURL'));

        // fix draft
        $db->query($db->update('table.contents')
            ->rows(['type' => 'revision'])
            ->where('parent <> 0 AND (type = ? OR type = ?)', 'post_draft', 'page_draft'));

        // fix attachment serialize
        $lastId = 0;
        do {
            $rows = $db->fetchAll(
                $db->select('cid', 'text')->from('table.contents')
                    ->where('cid > ?', $lastId)
                    ->where('type = ?', 'attachment')
                    ->order('cid', Db::SORT_ASC)
                    ->limit(100)
            );

            foreach ($rows as $row) {
                if (strpos($row['text'], 'a:') !== 0) {
                    continue;
                }

                $value = self::tryUnserialize((string) $row['text']);
                if ($value !== null) {
                    $db->query($db->update('table.contents')
                        ->rows(['text' => json_encode($value)])
                        ->where('cid = ?', $row['cid']));
                }

                $lastId = $row['cid'];
            }
        } while (count($rows) === 100);

        $rows = $db->fetchAll($db->select()->from('table.options'));

        foreach ($rows as $row) {
            if (
                in_array($row['name'], ['plugins', 'actionTable', 'panelTable'])
                || strpos($row['name'], 'plugin:') === 0
                || strpos($row['name'], 'theme:') === 0
            ) {
                $value = self::tryUnserialize((string) $row['value']);
                if ($value !== null) {
                    $db->query($db->update('table.options')
                        ->rows(['value' => json_encode($value)])
                        ->where('name = ?', $row['name']));
                }
            }
        }
    }

    public static function v1_3_1(Db $db, Options $options)
    {
        $prefix = $db->getPrefix();
        $adapter = $db->getAdapterName();
        $type = explode('_', $adapter);
        $type = array_pop($type);

        $tables = [
            'mail_queue' => $prefix . 'mail_queue',
            'mail_unsub' => $prefix . 'mail_unsub'
        ];

        if ($type === 'Mysql' || $type === 'Mysqli') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $tables['mail_queue'] . '` ('
                . '`id` bigint unsigned NOT NULL auto_increment,'
                . '`type` varchar(16) NOT NULL,'
                . '`status` varchar(16) NOT NULL,'
                . '`attempts` int unsigned NOT NULL default 0,'
                . '`lockedUntil` int unsigned NOT NULL default 0,'
                . '`sendAt` int unsigned NOT NULL default 0,'
                . '`created` int unsigned NOT NULL default 0,'
                . '`updated` int unsigned NOT NULL default 0,'
                . '`lastError` varchar(500) NOT NULL default "",'
                . '`dedupeKey` char(40) NOT NULL default "",'
                . '`payload` longtext,'
                . 'PRIMARY KEY (`id`),'
                . 'KEY `idx_status_sendat` (`status`,`sendAt`),'
                . 'KEY `idx_locked` (`lockedUntil`),'
                . 'UNIQUE KEY `uniq_dedupe` (`dedupeKey`)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                Db::WRITE
            );

            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $tables['mail_unsub'] . '` ('
                . '`id` bigint unsigned NOT NULL auto_increment,'
                . '`email` varchar(255) NOT NULL,'
                . '`scope` varchar(32) NOT NULL,'
                . '`created` int unsigned NOT NULL default 0,'
                . 'PRIMARY KEY (`id`),'
                . 'UNIQUE KEY `uniq_email_scope` (`email`,`scope`)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                Db::WRITE
            );
        } elseif ($type === 'SQLite') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $tables['mail_queue'] . '" ('
                . '"id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,'
                . '"type" varchar(16) NOT NULL,'
                . '"status" varchar(16) NOT NULL,'
                . '"attempts" int(10) NOT NULL default 0,'
                . '"lockedUntil" int(10) NOT NULL default 0,'
                . '"sendAt" int(10) NOT NULL default 0,'
                . '"created" int(10) NOT NULL default 0,'
                . '"updated" int(10) NOT NULL default 0,'
                . '"lastError" varchar(500) NOT NULL default "",'
                . '"dedupeKey" varchar(40) NOT NULL default "",'
                . '"payload" text'
                . ')',
                Db::WRITE
            );
            $db->query('CREATE INDEX IF NOT EXISTS "' . $tables['mail_queue'] . '_status_sendat" ON "' . $tables['mail_queue'] . '" ("status","sendAt")', Db::WRITE);
            $db->query('CREATE INDEX IF NOT EXISTS "' . $tables['mail_queue'] . '_lockedUntil" ON "' . $tables['mail_queue'] . '" ("lockedUntil")', Db::WRITE);
            $db->query('CREATE UNIQUE INDEX IF NOT EXISTS "' . $tables['mail_queue'] . '_dedupeKey" ON "' . $tables['mail_queue'] . '" ("dedupeKey")', Db::WRITE);

            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $tables['mail_unsub'] . '" ('
                . '"id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,'
                . '"email" varchar(255) NOT NULL,'
                . '"scope" varchar(32) NOT NULL,'
                . '"created" int(10) NOT NULL default 0'
                . ')',
                Db::WRITE
            );
            $db->query('CREATE UNIQUE INDEX IF NOT EXISTS "' . $tables['mail_unsub'] . '_email_scope" ON "' . $tables['mail_unsub'] . '" ("email","scope")', Db::WRITE);
        } else {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $tables['mail_queue'] . '" ('
                . '"id" BIGSERIAL PRIMARY KEY,'
                . '"type" VARCHAR(16) NOT NULL,'
                . '"status" VARCHAR(16) NOT NULL,'
                . '"attempts" INT NOT NULL DEFAULT 0,'
                . '"lockedUntil" INT NOT NULL DEFAULT 0,'
                . '"sendAt" INT NOT NULL DEFAULT 0,'
                . '"created" INT NOT NULL DEFAULT 0,'
                . '"updated" INT NOT NULL DEFAULT 0,'
                . '"lastError" VARCHAR(500) NOT NULL DEFAULT \'\','
                . '"dedupeKey" VARCHAR(40) NOT NULL DEFAULT \'\','
                . '"payload" TEXT NULL'
                . ')',
                Db::WRITE
            );
            $db->query('CREATE INDEX IF NOT EXISTS "' . $tables['mail_queue'] . '_status_sendat" ON "' . $tables['mail_queue'] . '" ("status","sendAt")', Db::WRITE);
            $db->query('CREATE INDEX IF NOT EXISTS "' . $tables['mail_queue'] . '_lockedUntil" ON "' . $tables['mail_queue'] . '" ("lockedUntil")', Db::WRITE);
            $db->query('CREATE UNIQUE INDEX IF NOT EXISTS "' . $tables['mail_queue'] . '_dedupeKey" ON "' . $tables['mail_queue'] . '" ("dedupeKey")', Db::WRITE);

            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $tables['mail_unsub'] . '" ('
                . '"id" BIGSERIAL PRIMARY KEY,'
                . '"email" VARCHAR(255) NOT NULL,'
                . '"scope" VARCHAR(32) NOT NULL,'
                . '"created" INT NOT NULL DEFAULT 0,'
                . 'UNIQUE ("email","scope")'
                . ')',
                Db::WRITE
            );
        }

        $defaults = [
            'mailEnable' => '0',
            'mailTransport' => 'smtp',
            'mailAdmin' => '',
            'mailFrom' => '',
            'mailFromName' => '',
            'mailSmtpHost' => '',
            'mailSmtpPort' => '25',
            'mailSmtpUser' => '',
            'mailSmtpPass' => '',
            'mailSmtpSecure' => '',
            'mailQueueMode' => 'async',
            'mailCronKey' => Common::randString(32),
            'mailBatchSize' => '50',
            'mailMaxAttempts' => '3',
            'mailKeepDays' => '30',
            'mailNotifyOwner' => '1',
            'mailNotifyGuest' => '1',
            'mailNotifyPending' => '1',
            'mailNotifyMe' => '0',
            'mailSubjectOwner' => '',
            'mailSubjectGuest' => '',
            'mailSubjectPending' => ''
        ];

        foreach ($defaults as $name => $value) {
            $exists = $db->fetchRow($db->select('name')->from('table.options')->where('name = ? AND user = 0', $name)->limit(1));
            if ($exists) {
                continue;
            }
            $db->query($db->insert('table.options')->rows(['name' => $name, 'user' => 0, 'value' => $value]));
        }

        return _t('邮件通知模块已安装');
    }

}
