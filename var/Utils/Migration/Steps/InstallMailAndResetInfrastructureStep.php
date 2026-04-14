<?php

namespace Utils\Migration\Steps;

use Typecho\Common;
use Typecho\Db;
use Utils\Schema;
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
        Schema::ensureMailInfra($db);

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
            'mailAsyncIps' => '',
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
