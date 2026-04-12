<?php

namespace Utils\Migration\Steps;

use Typecho\Common;
use Typecho\Db;
use Utils\Migration\StepInterface;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class InstallMailAndResetInfrastructureStep implements StepInterface
{
    public function version(): string
    {
        return '1.3.1';
    }

    public function up(Db $db, Options $options)
    {
        $prefix = $db->getPrefix();
        $adapter = $db->getAdapterName();
        $type = explode('_', $adapter);
        $type = array_pop($type);

        $tables = [
            'mail_queue' => $prefix . 'mail_queue',
            'mail_unsub' => $prefix . 'mail_unsub',
            'password_resets' => $prefix . 'password_resets'
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

            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $tables['password_resets'] . '` ('
                . '`id` bigint unsigned NOT NULL auto_increment,'
                . '`email` varchar(150) NOT NULL,'
                . '`token` varchar(64) NOT NULL,'
                . '`created` int unsigned NOT NULL default 0,'
                . '`expires` int unsigned NOT NULL default 0,'
                . '`used` tinyint unsigned NOT NULL default 0,'
                . 'PRIMARY KEY (`id`),'
                . 'KEY `idx_email` (`email`),'
                . 'KEY `idx_token` (`token`),'
                . 'KEY `idx_expires` (`expires`)'
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

            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $tables['password_resets'] . '" ('
                . '"id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,'
                . '"email" varchar(150) NOT NULL,'
                . '"token" varchar(64) NOT NULL,'
                . '"created" int(10) NOT NULL default 0,'
                . '"expires" int(10) NOT NULL default 0,'
                . '"used" int(10) NOT NULL default 0'
                . ')',
                Db::WRITE
            );
            $db->query('CREATE INDEX IF NOT EXISTS "' . $tables['password_resets'] . '_email" ON "' . $tables['password_resets'] . '" ("email")', Db::WRITE);
            $db->query('CREATE INDEX IF NOT EXISTS "' . $tables['password_resets'] . '_token" ON "' . $tables['password_resets'] . '" ("token")', Db::WRITE);
            $db->query('CREATE INDEX IF NOT EXISTS "' . $tables['password_resets'] . '_expires" ON "' . $tables['password_resets'] . '" ("expires")', Db::WRITE);
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

            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $tables['password_resets'] . '" ('
                . '"id" BIGSERIAL PRIMARY KEY,'
                . '"email" VARCHAR(150) NOT NULL,'
                . '"token" VARCHAR(64) NOT NULL,'
                . '"created" INT NOT NULL DEFAULT 0,'
                . '"expires" INT NOT NULL DEFAULT 0,'
                . '"used" INT NOT NULL DEFAULT 0'
                . ')',
                Db::WRITE
            );
            $db->query('CREATE INDEX IF NOT EXISTS "' . $tables['password_resets'] . '_email" ON "' . $tables['password_resets'] . '" ("email")', Db::WRITE);
            $db->query('CREATE INDEX IF NOT EXISTS "' . $tables['password_resets'] . '_token" ON "' . $tables['password_resets'] . '" ("token")', Db::WRITE);
            $db->query('CREATE INDEX IF NOT EXISTS "' . $tables['password_resets'] . '_expires" ON "' . $tables['password_resets'] . '" ("expires")', Db::WRITE);
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
            $exists = $db->fetchRow(
                $db->select('name')->from('table.options')->where('name = ? AND user = 0', $name)->limit(1)
            );
            if ($exists) {
                continue;
            }
            $db->query($db->insert('table.options')->rows(['name' => $name, 'user' => 0, 'value' => $value]));
        }

        return _t('邮件通知模块已安装');
    }
}
