<?php

namespace Widget\Users;

use Typecho\Common;
use Typecho\Widget\Exception;
use Typecho\Widget\Helper\Form;
use Utils\Password;
use Widget\ActionInterface;
use Widget\Base\Users;
use Widget\Notice;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Edit extends Users implements ActionInterface
{
    use EditTrait;

    /**
     * @return void
     * @throws Exception|\Typecho\Db\Exception
     */
    public function execute()
    {
        $this->user->pass('administrator');

        if (($this->request->is('uid') && 'delete' != $this->request->getAction()) || $this->request->is('do=update')) {
            $this->db->fetchRow($this->select()
                ->where('uid = ?', $this->request->get('uid'))->limit(1), [$this, 'push']);

            if (!$this->have()) {
                throw new Exception(_t('用户不存在'), 404);
            }
        }
    }

    /**
     * 获取菜单标题
     *
     * @return string
     */
    public function getMenuTitle(): string
    {
        return _t('编辑用户 %s', $this->name);
    }

    /**
     * 判断用户是否存在
     *
     * @param integer $uid 用户主键
     * @throws \Typecho\Db\Exception
     */
    public function userExists(int $uid): bool
    {
        $user = $this->db->fetchRow($this->db->select()
            ->from('table.users')
            ->where('uid = ?', $uid)->limit(1));

        return !empty($user);
    }

    /**
     * 增加用户
     *
     * @throws \Typecho\Db\Exception
     */
    public function insertUser()
    {
        if ($this->form('insert')->validate()) {
            $this->response->goBack();
        }

        $user = $this->request->fromInput('name', 'mail', 'screenName', 'password', 'url', 'group');
        $user['screenName'] = Common::strBy($user['screenName'] ?? null, $user['name']);
        $password = $user['password'] ?? null;
        if (!is_string($password) || $password === '') {
            Notice::alloc()->set(_t('必须填写密码'));
            $this->response->goBack();
        }
        $user['password'] = Password::hash($password);
        $user['created'] = $this->options->time;

        try {
            $user['uid'] = $this->insert($user);
        } catch (\Throwable $e) {
            $conflict = $this->userWriteConflict($e);
            if ($conflict !== null) {
                Notice::alloc()->set($conflict);
                $this->response->goBack();
            }

            throw $e;
        }

        Notice::alloc()->highlight('user-' . $user['uid']);

        Notice::alloc()->set(_t('用户 %s 已经被增加', $user['screenName']), 'success');

        $this->response->redirect(Common::url('manage-users.php', $this->options->adminUrl));
    }

    /**
     * 生成表单
     * @param string|null $action 表单动作
     * @return Form
     */
    public function form(?string $action = null): Form
    {
        $form = new Form($this->security->getIndex('/action/users-edit'), Form::POST_METHOD);

        $name = new Form\Element\Text('name', null, null, _t('用户名') . ' *', _t('此用户名将作为用户登录时所用的名称.')
            . '<br />' . _t('请不要与系统中现有的用户名重复.'));
        $form->addInput($name);

        $mail = new Form\Element\Text('mail', null, null, _t('邮件地址') . ' *', _t('电子邮箱地址将作为此用户的主要联系方式.')
            . '<br />' . _t('请不要与系统中现有的电子邮箱地址重复.'));
        $form->addInput($mail);

        $screenName = new Form\Element\Text('screenName', null, null, _t('用户昵称'), _t('你可以设置独立的用户昵称（与用户名不同），仅用于前台显示')
            . '<br />' . _t('此项留空时，系统会自动使用用户名'));
        $form->addInput($screenName);

        $password = new Form\Element\Password('password', null, null, _t('用户密码'), _t('为此用户分配一个密码.')
            . '<br />' . _t('建议使用特殊字符与字母、数字的混编样式,以增加系统安全性.'));
        $password->input->setAttribute('class', 'w-60');
        $form->addInput($password);

        $confirm = new Form\Element\Password('confirm', null, null, _t('用户密码确认'), _t('请确认你的密码, 与上面输入的密码保持一致.'));
        $confirm->input->setAttribute('class', 'w-60');
        $form->addInput($confirm);

        $url = new Form\Element\Text('url', null, null, _t('个人主页地址'), _t('此用户的个人主页地址, 请用 <code>https://</code> 开头.'));
        $form->addInput($url);

        $group = new Form\Element\Select(
            'group',
            [
                'subscriber'  => _t('关注者'),
                'contributor' => _t('贡献者'), 'editor' => _t('编辑'), 'administrator' => _t('管理员')
            ],
            null,
            _t('用户组'),
            _t('不同的用户组拥有不同的权限.') . '<br />' . _t('具体的权限分配表请<a href="https://docs.typecho.org/develop/acl">参考这里</a>.')
        );
        $form->addInput($group);

        $do = new Form\Element\Hidden('do');
        $form->addInput($do);

        $uid = new Form\Element\Hidden('uid');
        $form->addInput($uid);

        $submit = new Form\Element\Submit();
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        if ($this->request->is('uid')) {
            $submit->value(_t('编辑用户'));
            $name->value($this->name);
            $screenName->value($this->screenName);
            $url->value($this->url);
            $mail->value($this->mail);
            $group->value($this->group);
            $do->value('update');
            $uid->value($this->uid);
            $_action = 'update';
        } else {
            $submit->value(_t('增加用户'));
            $do->value('insert');
            $_action = 'insert';
        }

        if (empty($action)) {
            $action = $_action;
        }

        if ('insert' == $action || 'update' == $action) {
            $screenName->addRule([$this, 'screenNameExists'], _t('昵称已经存在'));
            $screenName->addRule('xssCheck', _t('请不要在昵称中使用特殊字符'));
            $screenName->addRule('maxLength', _t('昵称最多包含32个字符'), 32);
            $url->addRule('url', _t('个人主页地址格式错误'));
            $url->addRule('maxLength', _t('个人主页地址最多包含150个字符'), 150);
            $mail->addRule('required', _t('必须填写电子邮箱'));
            $mail->addRule([$this, 'mailExists'], _t('电子邮箱地址已经存在'));
            $mail->addRule('email', _t('电子邮箱格式错误'));
            $mail->addRule('maxLength', _t('电子邮箱最多包含150个字符'), 150);
            $password->addRule(
                [Password::class, 'validateLength'],
                _t('密码长度需在 %d-%d 位之间', Password::minLength(), Password::maxLength())
            );
            $confirm->addRule('confirm', _t('两次输入的密码不一致'), 'password');
        }

        if ('insert' == $action) {
            $name->addRule('required', _t('必须填写用户名称'));
            $name->addRule('xssCheck', _t('请不要在用户名中使用特殊字符'));
            $name->addRule([$this, 'nameExists'], _t('用户名已经存在'));
            $password->label(_t('用户密码') . ' *');
            $confirm->label(_t('用户密码确认') . ' *');
            $password->addRule('required', _t('必须填写密码'));
        }

        if ('update' == $action) {
            $name->input->setAttribute('disabled', 'disabled');
            $uid->addRule('required', _t('用户主键不存在'));
            $uid->addRule([$this, 'userExists'], _t('用户不存在'));
        }

        return $form;
    }

    /**
     * 更新用户
     *
     * @throws \Typecho\Db\Exception
     */
    public function updateUser()
    {
        if ($this->form('update')->validate()) {
            $this->response->goBack();
        }

        $currentScreenName = (string) $this->screenName;
        $user = $this->request->fromInput('mail', 'screenName', 'password', 'url', 'group');
        $user['screenName'] = Common::strBy($user['screenName'] ?? null, $this->name);
        $password = $user['password'] ?? null;
        if (!is_string($password) || $password === '') {
            unset($user['password']);
        } else {
            $user['password'] = Password::hash($password);
        }

        try {
            $updateRows = $this->update($user, $this->db->sql()->where('uid = ?', $this->request->get('uid')));
        } catch (\Throwable $e) {
            $conflict = $this->userWriteConflict($e);
            if ($conflict !== null) {
                Notice::alloc()->set($conflict);
                $this->response->goBack();
            }

            throw $e;
        }
        if ($updateRows > 0 && $currentScreenName !== $user['screenName']) {
            $this->syncCommentAuthor((int) $this->request->get('uid'), $user['screenName']);
        }

        Notice::alloc()->highlight('user-' . $this->request->get('uid'));

        Notice::alloc()->set(_t('用户 %s 已经被更新', $user['screenName']), 'success');

        $this->response->redirect(Common::url('manage-users.php?' .
            'page=' . $this->getPageOffset('uid', (int) $this->request->get('uid')), $this->options->adminUrl));
    }

    /**
     * 删除用户
     *
     * @throws \Typecho\Db\Exception
     */
    public function deleteUser()
    {
        $users = $this->request->filter('int')->getArray('uid');
        $row = $this->db->fetchObject($this->db->select(['MIN(uid)' => 'num'])->from('table.users'));
        $masterUserId = (int) ($row->num ?? 0);
        $deleteCount = 0;
        $blockedCount = 0;

        foreach ($users as $user) {
            if ($masterUserId == $user || $user == $this->user->uid) {
                continue;
            }

            if ($this->hasOwnedContents((int) $user)) {
                $blockedCount++;
                continue;
            }

            $this->cleanupDeletedUserReferences((int) $user);

            if ($this->delete($this->db->sql()->where('uid = ?', $user))) {
                $deleteCount++;
            }
        }

        if ($deleteCount > 0 && $blockedCount > 0) {
            Notice::alloc()->set(_t('部分用户已删除，包含文章或页面的用户未执行删除'), 'notice');
        } elseif ($deleteCount > 0) {
            Notice::alloc()->set(_t('用户已经删除'), 'success');
        } elseif ($blockedCount > 0) {
            Notice::alloc()->set(_t('部分用户包含文章或页面，未执行删除'), 'notice');
        } else {
            Notice::alloc()->set(_t('没有用户被删除'), 'notice');
        }

        $this->response->redirect(Common::url('manage-users.php', $this->options->adminUrl));
    }

    private function hasOwnedContents(int $uid): bool
    {
        $row = $this->db->fetchRow(
            $this->db->select(['COUNT(cid)' => 'num'])
                ->from('table.contents')
                ->where('authorId = ?', $uid)
                ->where('type IN ?', ['post', 'page'])
                ->limit(1)
        );

        return (int) ($row['num'] ?? 0) > 0;
    }

    private function cleanupDeletedUserReferences(int $uid): void
    {
        $this->db->query(
            $this->db->update('table.contents')
                ->rows(['authorId' => 0])
                ->where('authorId = ?', $uid)
                ->where('type NOT IN ?', ['post', 'page'])
        );

        $this->db->query(
            $this->db->update('table.comments')
                ->rows(['authorId' => 0])
                ->where('authorId = ?', $uid)
        );

        $this->db->query(
            $this->db->update('table.comments')
                ->rows(['ownerId' => 0])
                ->where('ownerId = ?', $uid)
        );

        $this->db->query(
            $this->db->delete('table.options')
                ->where('user = ?', $uid)
        );
    }

    public function action()
    {
        $this->user->pass('administrator');
        if (!$this->request->isPost()) {
            $this->response->setStatus(405)->throwContent(_t('Method Not Allowed'), 'text/plain');
            return;
        }
        $this->security->protect();
        $this->on($this->request->is('do=insert'))->insertUser();
        $this->on($this->request->is('do=update'))->updateUser();
        $this->on($this->request->is('do=delete'))->deleteUser();
        $this->response->redirect($this->options->adminUrl);
    }
}
