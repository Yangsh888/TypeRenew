<?php

namespace Widget;

use Typecho\Cookie;
use Typecho\Db\Exception;
use Typecho\Validate;
use Utils\Password;
use Widget\Base\Users;
use Widget\Users\EditTrait;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Register extends Users implements ActionInterface
{
    use EditTrait;

    /**
     * @throws Exception
     */
    public function action()
    {
        $this->security->protect();

        if ($this->user->hasLogin() || !$this->options->allowRegister) {
            $this->response->redirect($this->options->index);
        }

        $validator = new Validate();
        $validator->addRule('name', 'required', _t('必须填写用户名称'));
        $validator->addRule('name', 'minLength', _t('用户名至少包含2个字符'), 2);
        $validator->addRule('name', 'maxLength', _t('用户名最多包含32个字符'), 32);
        $validator->addRule('name', 'xssCheck', _t('请不要在用户名中使用特殊字符'));
        $validator->addRule('name', [$this, 'nameExists'], _t('用户名已经存在'));
        $validator->addRule('mail', 'required', _t('必须填写电子邮箱'));
        $validator->addRule('mail', [$this, 'mailExists'], _t('电子邮箱地址已经存在'));
        $validator->addRule('mail', 'email', _t('电子邮箱格式错误'));
        $validator->addRule('mail', 'maxLength', _t('电子邮箱最多包含150个字符'), 150);

        $validator->addRule('password', 'required', _t('必须填写密码'));
        $validator->addRule(
            'password',
            [Password::class, 'validateLength'],
            _t('密码长度需在 %d-%d 位之间', Password::minLength(), Password::maxLength())
        );
        $validator->addRule('confirm', 'confirm', _t('两次输入的密码不一致'), 'password');

        if ($error = $validator->run($this->request->from('name', 'password', 'mail', 'confirm'))) {
            Cookie::set('__typecho_remember_name', $this->request->get('name'));
            Cookie::set('__typecho_remember_mail', $this->request->get('mail'));

            Notice::alloc()->set($error);
            $this->response->goBack();
        }

        $password = (string) $this->request->get('password');

        $dataStruct = [
            'name' => $this->request->get('name'),
            'mail' => $this->request->get('mail'),
            'screenName' => $this->request->get('name'),
            'password' => Password::hash($password),
            'created' => $this->options->time,
            'group' => 'subscriber'
        ];

        $dataStruct = self::pluginHandle()->filter('register', $dataStruct);

        try {
            $insertId = $this->insert($dataStruct);
        } catch (\Throwable $e) {
            $conflict = $this->userWriteConflict($e);
            if ($conflict !== null) {
                Cookie::set('__typecho_remember_name', $this->request->get('name'));
                Cookie::set('__typecho_remember_mail', $this->request->get('mail'));
                Notice::alloc()->set($conflict);
                $this->response->goBack();
            }

            throw $e;
        }
        $this->db->fetchRow($this->select()->where('uid = ?', $insertId)
            ->limit(1), [$this, 'push']);

        self::pluginHandle()->call('finishRegister', $this);

        $this->user->login($this->request->get('name'), $password);

        Cookie::delete('__typecho_first_run');
        Cookie::delete('__typecho_remember_name');
        Cookie::delete('__typecho_remember_mail');

        Notice::alloc()->set(_t('用户 <strong>%s</strong> 已经成功注册', $this->screenName), 'success');
        $this->response->redirect($this->options->adminUrl);
    }
}
