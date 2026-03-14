<?php

namespace Widget\Options;

use Typecho\Common;
use Typecho\Db;
use Typecho\Widget\Helper\Form;
use Typecho\Mail\Queue;
use Typecho\Mail\Message;
use Typecho\Mail\Notify;
use Utils\Cipher;
use Widget\ActionInterface;
use Widget\Base\Options;
use Widget\Notice;
use Typecho\Mail\Template;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Mail extends Options implements ActionInterface
{
    use EditTrait;

    private const PASS_PLACEHOLDER = '********';

    public static function decryptPassword(?string $encrypted, string $secret): string
    {
        $value = trim((string) $encrypted);
        if ($value === '') {
            return '';
        }
        return Cipher::decrypt($value, $secret);
    }

    public function updateMailSettings()
    {
        if ($this->form()->validate()) {
            $this->response->goBack();
        }

        $settings = $this->request->from(
            'mailEnable',
            'mailTransport',
            'mailAdmin',
            'mailFrom',
            'mailFromName',
            'mailSmtpHost',
            'mailSmtpPort',
            'mailSmtpUser',
            'mailSmtpPass',
            'mailSmtpPassChanged',
            'mailSmtpSecure',
            'mailQueueMode',
            'mailBatchSize',
            'mailMaxAttempts',
            'mailKeepDays',
            'mailNotifyOwner',
            'mailNotifyGuest',
            'mailNotifyPending',
            'mailNotifyMe',
            'mailSubjectOwner',
            'mailSubjectGuest',
            'mailSubjectPending',
            'mailCronKey'
        );

        $settings['mailEnable'] = (int) (!empty($settings['mailEnable']) ? 1 : 0);
        $settings['mailTransport'] = in_array((string) $settings['mailTransport'], ['smtp', 'mail'], true)
            ? (string) $settings['mailTransport'] : 'smtp';

        $settings['mailAdmin'] = trim((string) $settings['mailAdmin']);
        $settings['mailFrom'] = trim((string) $settings['mailFrom']);
        $settings['mailFromName'] = trim((string) $settings['mailFromName']);

        $settings['mailSmtpHost'] = trim((string) $settings['mailSmtpHost']);
        $settings['mailSmtpPort'] = max(1, min(65535, (int) $settings['mailSmtpPort']));
        $settings['mailSmtpUser'] = trim((string) $settings['mailSmtpUser']);

        $passChanged = !empty($settings['mailSmtpPassChanged']);
        $pass = trim((string) ($settings['mailSmtpPass'] ?? ''));

        if ($passChanged && $pass !== '' && $pass !== self::PASS_PLACEHOLDER) {
            $settings['mailSmtpPass'] = Cipher::encrypt($pass, (string) $this->options->secret);
        } else {
            $settings['mailSmtpPass'] = (string) ($this->options->mailSmtpPass ?? '');
        }

        $secure = (string) ($settings['mailSmtpSecure'] ?? '');
        $settings['mailSmtpSecure'] = in_array($secure, ['', 'ssl', 'tls'], true) ? $secure : '';

        $mode = (string) ($settings['mailQueueMode'] ?? 'async');
        $settings['mailQueueMode'] = in_array($mode, ['sync', 'async', 'cron'], true) ? $mode : 'async';
        $settings['mailBatchSize'] = max(1, min(200, (int) ($settings['mailBatchSize'] ?? 50)));
        $settings['mailMaxAttempts'] = max(1, min(10, (int) ($settings['mailMaxAttempts'] ?? 3)));
        $settings['mailKeepDays'] = max(1, min(365, (int) ($settings['mailKeepDays'] ?? 30)));

        $settings['mailNotifyOwner'] = (int) (!empty($settings['mailNotifyOwner']) ? 1 : 0);
        $settings['mailNotifyGuest'] = (int) (!empty($settings['mailNotifyGuest']) ? 1 : 0);
        $settings['mailNotifyPending'] = (int) (!empty($settings['mailNotifyPending']) ? 1 : 0);
        $settings['mailNotifyMe'] = (int) (!empty($settings['mailNotifyMe']) ? 1 : 0);

        $settings['mailSubjectOwner'] = trim((string) $settings['mailSubjectOwner']);
        $settings['mailSubjectGuest'] = trim((string) $settings['mailSubjectGuest']);
        $settings['mailSubjectPending'] = trim((string) $settings['mailSubjectPending']);

        $settings['mailCronKey'] = trim((string) $settings['mailCronKey']);
        if ($settings['mailCronKey'] === '') {
            $settings['mailCronKey'] = (string) ($this->options->mailCronKey ?? Common::randString(32));
        }

        if ($settings['mailFrom'] === '' && $settings['mailSmtpUser'] !== '') {
            $settings['mailFrom'] = $settings['mailSmtpUser'];
        }

        foreach ($settings as $name => $value) {
            $this->saveOption($name, $value);
        }

        Notice::alloc()->set(_t('设置已经保存'), 'success');
        $this->response->goBack();
    }

    public function testSend()
    {
        if ($this->form()->validate()) {
            $this->response->goBack();
        }

        $to = trim((string) $this->request->get('testTo'));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Notice::alloc()->set(_t('请填写正确的测试收件邮箱'), 'error');
            $this->response->goBack();
        }

        $tpl = Template::normalizeName((string) $this->request->get('tpl'));
        $vars = $this->mockTemplateVars($tpl);
        $subject = (string) ($vars['subject'] ?? _t('TypeRenew 邮件测试'));
        $html = Template::render($tpl, $vars, $this->options);

        $from = (string) ($this->options->mailFrom ?? $this->options->mailSmtpUser ?? '');
        $fromName = (string) ($this->options->mailFromName ?? $this->options->title ?? 'TypeRenew');
        $msg = new Message($to, $subject, $html, $from, $fromName, '');

        $transport = (string) ($this->options->mailTransport ?? 'smtp');
        $res = $transport === 'mail'
            ? (new \Typecho\Mail\Native())->send($msg)
            : (new \Typecho\Mail\Smtp([
                'host' => (string) ($this->options->mailSmtpHost ?? ''),
                'port' => (int) ($this->options->mailSmtpPort ?? 25),
                'user' => (string) ($this->options->mailSmtpUser ?? ''),
                'pass' => self::decryptPassword($this->options->mailSmtpPass ?? '', (string) $this->options->secret),
                'secure' => (string) ($this->options->mailSmtpSecure ?? ''),
                'timeout' => 10
            ]))->send($msg);

        if ($res === true) {
            Notice::alloc()->set(_t('测试邮件已发送'), 'success');
        } else {
            Notice::alloc()->set(_t('测试邮件发送失败：%s', (string) $res), 'error');
        }

        $this->response->goBack();
    }

    public function previewTemplate()
    {
        $tpl = Template::normalizeName((string) $this->request->get('tpl'));
        $vars = $this->mockTemplateVars($tpl);
        $html = Template::render($tpl, $vars, $this->options);

        $this->response->setContentType('text/html');
        echo $html;
        exit;
    }

    public function deliverNow()
    {
        $result = Queue::deliverBatch(Db::get(), $this->options, (int) ($this->options->mailBatchSize ?? 50));
        Notice::alloc()->set(
            _t('投递完成：成功 %d，失败 %d', (int) $result['sent'], (int) $result['failed']),
            ((int) $result['failed'] > 0) ? 'notice' : 'success'
        );
        $this->response->goBack();
    }

    public function cleanupQueue()
    {
        $count = Queue::cleanup(Db::get(), (int) ($this->options->mailKeepDays ?? 30));
        Notice::alloc()->set(_t('已清理 %d 条已发送记录', $count), 'success');
        $this->response->goBack();
    }

    public function retryFailed()
    {
        $limit = (int) ($this->options->mailBatchSize ?? 50);
        $db = Db::get();
        $failed = Queue::retry($db, 'failed', $limit);
        $dead = Queue::retry($db, 'dead', $limit);
        $count = $failed + $dead;
        Notice::alloc()->set(_t('已重试 %d 条任务（失败 %d，已放弃 %d）', $count, $failed, $dead), $count > 0 ? 'success' : 'notice');
        $this->response->goBack();
    }

    public function retryDead()
    {
        $count = Queue::retry(Db::get(), 'dead', (int) ($this->options->mailBatchSize ?? 50));
        Notice::alloc()->set(_t('已重试 %d 条已放弃任务', $count), $count > 0 ? 'success' : 'notice');
        $this->response->goBack();
    }

    public function regenerateCronKey()
    {
        $key = Common::randString(32);
        $this->saveOption('mailCronKey', $key);
        Notice::alloc()->set(_t('投递密钥已更新'), 'success');
        $this->response->goBack();
    }

    public function saveTemplate()
    {
        $name = (string) $this->request->get('tpl');
        $content = (string) $this->request->get('content');
        $result = Template::writeOverride($name, $content, $this->options);

        if ($result === true) {
            Notice::alloc()->set(_t('模板已保存'), 'success');
        } else {
            Notice::alloc()->set(_t('模板保存失败：%s', (string) $result), 'error');
        }

        $this->response->goBack();
    }

    public function resetTemplate()
    {
        $name = (string) $this->request->get('tpl');
        $result = Template::deleteOverride($name, $this->options);

        if ($result === true) {
            Notice::alloc()->set(_t('模板已重置'), 'success');
        } else {
            Notice::alloc()->set(_t('模板重置失败：%s', (string) $result), 'error');
        }

        $this->response->goBack();
    }

    public function panel(): array
    {
        $panel = Queue::stats(Db::get());
        $panel['runtimeError'] = trim((string) ($this->options->mailRuntimeError ?? ''));
        $panel['runtimeErrorAt'] = (int) ($this->options->mailRuntimeErrorAt ?? 0);
        return $panel;
    }

    public function form(): Form
    {
        $form = new Form($this->security->getIndex('/action/options-mail'), Form::POST_METHOD);

        $mailEnable = new Form\Element\Radio(
            'mailEnable',
            ['0' => _t('关闭'), '1' => _t('开启')],
            (string) ($this->options->mailEnable ?? '0'),
            _t('邮件通知')
        );
        $form->addInput($mailEnable);

        $mailTransport = new Form\Element\Select(
            'mailTransport',
            ['smtp' => 'SMTP', 'mail' => 'mail()'],
            (string) ($this->options->mailTransport ?? 'smtp'),
            _t('发送方式')
        );
        $form->addInput($mailTransport);

        $mailAdmin = new Form\Element\Text(
            'mailAdmin',
            null,
            (string) ($this->options->mailAdmin ?? ''),
            _t('站长邮箱'),
            _t('用于接收待审评论通知，留空则回退到文章作者邮箱')
        );
        $mailAdmin->input->setAttribute('class', 'w-100 mono');
        $form->addInput($mailAdmin);

        $mailFrom = new Form\Element\Text(
            'mailFrom',
            null,
            (string) ($this->options->mailFrom ?? ''),
            _t('发件邮箱'),
            _t('SMTP 模式下建议与账号一致，留空则使用 SMTP 用户名')
        );
        $mailFrom->input->setAttribute('class', 'w-100 mono');
        $form->addInput($mailFrom);

        $mailFromName = new Form\Element\Text(
            'mailFromName',
            null,
            (string) ($this->options->mailFromName ?? ''),
            _t('发件人名称')
        );
        $mailFromName->input->setAttribute('class', 'w-60');
        $form->addInput($mailFromName);

        $mailSmtpHost = new Form\Element\Text(
            'mailSmtpHost',
            null,
            (string) ($this->options->mailSmtpHost ?? ''),
            _t('SMTP 主机')
        );
        $mailSmtpHost->input->setAttribute('class', 'w-100 mono');
        $form->addInput($mailSmtpHost);

        $mailSmtpPort = new Form\Element\Number(
            'mailSmtpPort',
            null,
            (int) ($this->options->mailSmtpPort ?? 25),
            _t('SMTP 端口')
        );
        $mailSmtpPort->input->setAttribute('class', 'w-20');
        $form->addInput($mailSmtpPort->addRule('isInteger', _t('请填入一个数字')));

        $mailSmtpUser = new Form\Element\Text(
            'mailSmtpUser',
            null,
            (string) ($this->options->mailSmtpUser ?? ''),
            _t('SMTP 用户名')
        );
        $mailSmtpUser->input->setAttribute('class', 'w-100 mono');
        $form->addInput($mailSmtpUser);

        $existingPass = trim((string) ($this->options->mailSmtpPass ?? ''));
        $passPlaceholder = $existingPass !== '' ? self::PASS_PLACEHOLDER : '';
        $mailSmtpPass = new Form\Element\Password(
            'mailSmtpPass',
            null,
            $passPlaceholder,
            _t('SMTP 密码'),
            $existingPass !== '' ? _t('已保存密码，修改请输入新密码') : ''
        );
        $mailSmtpPass->input->setAttribute('class', 'w-100 mono');
        $mailSmtpPass->input->setAttribute('autocomplete', 'new-password');
        $form->addInput($mailSmtpPass);

        $mailSmtpPassChanged = new Form\Element\Hidden('mailSmtpPassChanged', null, '0');
        $mailSmtpPassChanged->input->setAttribute('id', 'mailSmtpPassChanged');
        $form->addInput($mailSmtpPassChanged);

        $mailSmtpSecure = new Form\Element\Select(
            'mailSmtpSecure',
            ['' => _t('无'), 'ssl' => 'SSL', 'tls' => 'STARTTLS'],
            (string) ($this->options->mailSmtpSecure ?? ''),
            _t('SMTP 加密')
        );
        $form->addInput($mailSmtpSecure);

        $mailQueueMode = new Form\Element\Select(
            'mailQueueMode',
            ['async' => _t('自动异步'), 'cron' => _t('定时投递'), 'sync' => _t('同步投递')],
            (string) ($this->options->mailQueueMode ?? 'async'),
            _t('投递模式'),
            _t('自动异步为默认推荐方案；定时投递适合用 Cron 批量处理')
        );
        $form->addInput($mailQueueMode);

        $mailCronKey = new Form\Element\Text(
            'mailCronKey',
            null,
            (string) ($this->options->mailCronKey ?? ''),
            _t('投递密钥'),
            _t('用于定时投递签名，建议定期轮换')
        );
        $mailCronKey->input->setAttribute('class', 'w-100 mono');
        $form->addInput($mailCronKey);

        $mailBatchSize = new Form\Element\Number(
            'mailBatchSize',
            null,
            (int) ($this->options->mailBatchSize ?? 50),
            _t('批量大小')
        );
        $mailBatchSize->input->setAttribute('class', 'w-20');
        $form->addInput($mailBatchSize->addRule('isInteger', _t('请填入一个数字')));

        $mailMaxAttempts = new Form\Element\Number(
            'mailMaxAttempts',
            null,
            (int) ($this->options->mailMaxAttempts ?? 3),
            _t('最大重试次数')
        );
        $mailMaxAttempts->input->setAttribute('class', 'w-20');
        $form->addInput($mailMaxAttempts->addRule('isInteger', _t('请填入一个数字')));

        $mailKeepDays = new Form\Element\Number(
            'mailKeepDays',
            null,
            (int) ($this->options->mailKeepDays ?? 30),
            _t('记录保留天数')
        );
        $mailKeepDays->input->setAttribute('class', 'w-20');
        $form->addInput($mailKeepDays->addRule('isInteger', _t('请填入一个数字')));

        $mailNotifyOwner = new Form\Element\Radio(
            'mailNotifyOwner',
            ['1' => _t('开启'), '0' => _t('关闭')],
            (string) ($this->options->mailNotifyOwner ?? 1),
            _t('通知作者')
        );
        $form->addInput($mailNotifyOwner);

        $mailNotifyGuest = new Form\Element\Radio(
            'mailNotifyGuest',
            ['1' => _t('开启'), '0' => _t('关闭')],
            (string) ($this->options->mailNotifyGuest ?? 1),
            _t('通知访客')
        );
        $form->addInput($mailNotifyGuest);

        $mailNotifyPending = new Form\Element\Radio(
            'mailNotifyPending',
            ['1' => _t('开启'), '0' => _t('关闭')],
            (string) ($this->options->mailNotifyPending ?? 1),
            _t('待审提醒')
        );
        $form->addInput($mailNotifyPending);

        $mailNotifyMe = new Form\Element\Radio(
            'mailNotifyMe',
            ['1' => _t('开启'), '0' => _t('关闭')],
            (string) ($this->options->mailNotifyMe ?? 0),
            _t('允许自回复通知'),
            _t('开启后允许“自己回复自己的评论”也发邮件')
        );
        $form->addInput($mailNotifyMe);

        $mailSubjectOwner = new Form\Element\Text(
            'mailSubjectOwner',
            null,
            (string) ($this->options->mailSubjectOwner ?? ''),
            _t('作者邮件标题'),
            _t('支持 {title}')
        );
        $mailSubjectOwner->input->setAttribute('class', 'w-100 mono');
        $form->addInput($mailSubjectOwner);

        $mailSubjectGuest = new Form\Element\Text(
            'mailSubjectGuest',
            null,
            (string) ($this->options->mailSubjectGuest ?? ''),
            _t('访客邮件标题'),
            _t('支持 {title}')
        );
        $mailSubjectGuest->input->setAttribute('class', 'w-100 mono');
        $form->addInput($mailSubjectGuest);

        $mailSubjectPending = new Form\Element\Text(
            'mailSubjectPending',
            null,
            (string) ($this->options->mailSubjectPending ?? ''),
            _t('待审邮件标题'),
            _t('支持 {title}')
        );
        $mailSubjectPending->input->setAttribute('class', 'w-100 mono');
        $form->addInput($mailSubjectPending);

        $submit = new Form\Element\Submit('submit', null, _t('保存设置'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        return $form;
    }

    public function action()
    {
        $this->user->pass('administrator');
        if ($this->request->isPost()) {
            $this->security->protect();
        }

        if ($this->request->isPost()) {
            $do = (string) $this->request->get('do');
            if ($do === 'deliver') {
                $this->deliverNow();
                return;
            }
            if ($do === 'cleanup') {
                $this->cleanupQueue();
                return;
            }
            if ($do === 'retry_failed') {
                $this->retryFailed();
                return;
            }
            if ($do === 'retry_dead') {
                $this->retryDead();
                return;
            }
            if ($do === 'regen_key') {
                $this->regenerateCronKey();
                return;
            }
            if ($do === 'test') {
                $this->testSend();
                return;
            }
            if ($do === 'tpl_preview') {
                $this->previewTemplate();
                return;
            }
            if ($do === 'tpl_save') {
                $this->saveTemplate();
                return;
            }
            if ($do === 'tpl_reset') {
                $this->resetTemplate();
                return;
            }
        }

        $this->on($this->request->isPost())->updateMailSettings();
        $this->response->redirect($this->options->adminUrl);
    }

    private function mockTemplateVars(string $tpl): array
    {
        $now = time();
        $title = _t('模板联调示例文章');
        $author = _t('示例评论者');
        $mail = 'user@example.com';
        $ip = '203.0.113.10';
        $siteUrl = (string) ($this->options->siteUrl ?? '');
        $index = (string) ($this->options->index ?? $siteUrl);
        $permalink = Common::url('/archives/mail-template-preview/', $index);
        $manageUrl = Common::url('manage-comments.php', (string) ($this->options->adminUrl ?? ''));
        $siteTitle = (string) ($this->options->title ?? 'TypeRenew');
        $resetUrl = Common::url('reset.php?token=demo', (string) ($this->options->adminUrl ?? ''));
        $expiresAt = date('Y-m-d H:i:s', $now + 1800);

        if ($tpl === 'reset') {
            return [
                'subject' => _t('密码重置请求'),
                'siteTitle' => $siteTitle,
                'siteUrl' => $siteUrl,
                'author' => $author,
                'mail' => $mail,
                'ip' => $ip,
                'resetUrl' => $resetUrl,
                'expiresAt' => $expiresAt
            ];
        }

        $comment = (object) [
            'title' => $title,
            'author' => $author,
            'mail' => $mail,
            'ip' => $ip,
            'status' => $tpl === 'notice' ? 'waiting' : 'approved',
            'created' => $now,
            'content' => _t("这是一条用于模板联调的示例评论。\n可用于检查段落、特殊字符与链接显示。"),
            'permalink' => $permalink,
            'parent' => 0,
            'cid' => 1,
            'authorId' => 2,
            'ownerId' => 1
        ];

        $settings = [
            'subjectOwner' => (string) ($this->options->mailSubjectOwner ?? ''),
            'subjectGuest' => (string) ($this->options->mailSubjectGuest ?? ''),
            'subjectPending' => (string) ($this->options->mailSubjectPending ?? '')
        ];
        $vars = Notify::vars($tpl, $comment, $this->options, $settings);
        $vars['siteTitle'] = $siteTitle;
        $vars['siteUrl'] = $siteUrl;
        $vars['permalink'] = $permalink;
        $vars['manageurl'] = $manageUrl;
        $vars['author'] = $author;
        $vars['mail'] = $mail;
        $vars['ip'] = $ip;
        $vars['commentText'] = (string) $comment->content;
        $vars['Ptext'] = _t("这里是父评论示例内容。\n用于模拟访客回复通知。");
        $vars['title'] = $title;
        $vars['resetUrl'] = $resetUrl;
        $vars['expiresAt'] = $expiresAt;
        if (empty($vars['unsubUrl'])) {
            $vars['unsubUrl'] = Common::url('/action/mail?do=unsub&token=demo', $index);
        }

        return $vars;
    }

    private function saveOption(string $name, $value): void
    {
        $exists = $this->db->fetchRow($this->db->select('name')->from('table.options')->where('name = ?', $name));
        $value = is_array($value) ? json_encode($value) : (string) $value;

        if ($exists) {
            $this->update(['value' => $value], $this->db->sql()->where('name = ?', $name));
            return;
        }

        $this->insert([
            'name' => $name,
            'user' => 0,
            'value' => $value
        ]);
    }
}
