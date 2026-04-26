<?php

namespace Widget\Users;

use Typecho\Common;
use Typecho\Db\Exception;
use Typecho\Plugin;
use Typecho\Widget\Helper\Form;
use Utils\Password;
use Widget\ActionInterface;
use Widget\Base\Options;
use Widget\Base\Users;
use Widget\Notice;
use Widget\Plugins\Rows;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 编辑用户组件
 *
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Profile extends Users implements ActionInterface
{
    use EditTrait;

    public function execute()
    {
        $this->user->pass('subscriber');
        $this->request->setParam('uid', $this->user->uid);
    }

    /**
     * @return Form
     */
    public function optionsForm(): Form
    {
        $form = new Form($this->security->getIndex('/action/users-profile'), Form::POST_METHOD);
        $form->setAttribute('id', 'tr-profile-form-options');

        $markdown = new Form\Element\Radio(
            'markdown',
            ['0' => _t('关闭'), '1' => _t('打开')],
            $this->options->markdown,
            _t('使用 Markdown 语法编辑和解析内容'),
            _t('使用 <a href="https://daringfireball.net/projects/markdown/">Markdown</a> 语法能够使您的撰写过程更加简便直观')
            . '<br />' . _t('此功能开启不会影响以前没有使用 Markdown 语法编辑的内容')
        );
        $form->addInput($markdown);

        $xmlrpcMarkdown = new Form\Element\Radio(
            'xmlrpcMarkdown',
            ['0' => _t('关闭'), '1' => _t('打开')],
            $this->options->xmlrpcMarkdown,
            _t('在 XMLRPC 接口中使用 Markdown 语法'),
            _t('对于完全支持 <a href="https://daringfireball.net/projects/markdown/">Markdown</a> 语法写作的离线编辑器，打开此选项后将避免内容被转换为 HTML')
        );
        $form->addInput($xmlrpcMarkdown);

        $autoSave = new Form\Element\Radio(
            'autoSave',
            ['0' => _t('关闭'), '1' => _t('打开')],
            $this->options->autoSave,
            _t('自动保存'),
            _t('自动保存功能可以更好地保护你的文章不会丢失')
        );
        $form->addInput($autoSave);

        $allow = [];
        if ($this->options->defaultAllowComment) {
            $allow[] = 'comment';
        }

        if ($this->options->defaultAllowPing) {
            $allow[] = 'ping';
        }

        if ($this->options->defaultAllowFeed) {
            $allow[] = 'feed';
        }

        $defaultAllow = new Form\Element\Checkbox(
            'defaultAllow',
            ['comment' => _t('可以被评论'), 'ping' => _t('可以被引用'), 'feed' => _t('出现在聚合中')],
            $allow,
            _t('默认允许'),
            _t('设置你经常使用的默认允许权限')
        );
        $form->addInput($defaultAllow);

        $do = new Form\Element\Hidden('do', null, 'options');
        $form->addInput($do);

        $submit = new Form\Element\Submit('submit', null, _t('保存设置'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        return $form;
    }

    /**
     * 自定义设置列表
     *
     * @throws Plugin\Exception
     */
    public function personalFormList()
    {
        $plugins = Rows::alloc('activated=1');

        while ($plugins->next()) {
            if ($plugins->personalConfig) {
                [$pluginFileName, $className] = Plugin::portal($plugins->name, $this->options->pluginDir);

                $form = $this->personalForm($plugins->name, $className, $pluginFileName, $group);
                if ($this->user->pass($group, true)) {
                    echo '<section id="personal-' . $plugins->name . '" class="tr-card tr-form-card tr-profile-plugin" data-tr-form-card>';
                    echo '<div class="tr-card-h"><div class="tr-card-h-inner"><h3>' . $plugins->title . '</h3><div class="tr-card-actions" data-tr-form-actions></div></div></div>';
                    echo '<div class="tr-card-b">';
                    $form->render();
                    echo '</div>';
                    echo '</section>';
                }
            }
        }
    }

    /**
     * 输出自定义设置选项
     * @param string $pluginName 插件名称
     * @param string $className 类名称
     * @param string $pluginFileName 插件文件名
     * @param string|null $group 用户组
     * @throws Plugin\Exception
     */
    public function personalForm(string $pluginName, string $className, string $pluginFileName, ?string &$group): Form
    {
        $form = new Form($this->security->getIndex('/action/users-profile'), Form::POST_METHOD);
        $form->setAttribute('name', $pluginName);
        $form->setAttribute('id', $pluginName);

        require_once $pluginFileName;
        $group = call_user_func([$className, 'personalConfig'], $form);
        $group = $group ?: 'subscriber';

        $options = $this->options->personalPlugin($pluginName);

        if (!empty($options)) {
            foreach ($options as $key => $val) {
                $input = $form->getInput($key);
                if ($input !== null) {
                    $input->value($val);
                }
            }
        }

        $form->addItem(new Form\Element\Hidden('do', null, 'personal'));
        $form->addItem(new Form\Element\Hidden('plugin', null, $pluginName));
        $submit = new Form\Element\Submit('submit', null, _t('保存设置'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);
        return $form;
    }

    /**
     * 更新用户
     *
     * @throws Exception
     */
    public function updateProfile()
    {
        if ($this->profileForm()->validate()) {
            $this->response->goBack();
        }

        $currentScreenName = (string) $this->user->screenName;
        $user = $this->request->from('mail', 'screenName', 'url');
        $user['screenName'] = Common::strBy($user['screenName'] ?? null, $this->user->name);

        try {
            $updateRows = $this->update($user, $this->db->sql()->where('uid = ?', $this->user->uid));
        } catch (\Throwable $e) {
            $conflict = $this->userWriteConflict($e);
            if ($conflict !== null) {
                Notice::alloc()->set($conflict);
                $this->response->goBack();
            }

            throw $e;
        }
        if ($updateRows > 0 && $currentScreenName !== $user['screenName']) {
            $this->syncCommentAuthor($this->user->uid, $user['screenName']);
        }

        Notice::alloc()->highlight('user-' . $this->user->uid);

        Notice::alloc()->set(_t('您的档案已经更新'), 'success');

        $this->response->goBack();
    }

    /**
     * 生成表单
     *
     * @return Form
     */
    public function profileForm(): Form
    {
        $form = new Form($this->security->getIndex('/action/users-profile'), Form::POST_METHOD);
        $form->setAttribute('id', 'tr-profile-form-profile');

        $screenName = new Form\Element\Text('screenName', null, null, _t('昵称'), _t('你可以设置独立的用户昵称（与用户名不同），仅用于前台显示')
            . '<br />' . _t('此项留空时，系统会自动使用用户名'));
        $form->addInput($screenName);

        $url = new Form\Element\Url('url', null, null, _t('个人主页地址'), _t('请填写个人主页地址，需以 <code>https://</code> 开头'));
        $form->addInput($url);

        $mail = new Form\Element\Text('mail', null, null, _t('邮件地址') . ' *', _t('此邮箱为用户主要联系方式，请勿使用系统已存在的邮箱地址'));
        $form->addInput($mail);

        $do = new Form\Element\Hidden('do', null, 'profile');
        $form->addInput($do);

        $submit = new Form\Element\Submit('submit', null, _t('更新我的档案'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        $screenName->value($this->user->screenName);
        $url->value($this->user->url);
        $mail->value($this->user->mail);

        /** 给表单增加规则 */
        $screenName->addRule([$this, 'screenNameExists'], _t('昵称已经存在'));
        $screenName->addRule('xssCheck', _t('请不要在昵称中使用特殊字符'));
        $screenName->addRule('maxLength', _t('昵称最多包含32个字符'), 32);
        $url->addRule('url', _t('个人主页地址格式错误'));
        $url->addRule('maxLength', _t('个人主页地址最多包含150个字符'), 150);
        $mail->addRule('required', _t('必须填写电子邮箱'));
        $mail->addRule([$this, 'mailExists'], _t('电子邮箱地址已经存在'));
        $mail->addRule('email', _t('电子邮箱格式错误'));
        $mail->addRule('maxLength', _t('电子邮箱最多包含150个字符'), 150);

        return $form;
    }

    /**
     * 执行更新动作
     *
     * @throws Exception
     */
    public function updateOptions()
    {
        $settings['autoSave'] = $this->request->is('autoSave=1') ? 1 : 0;
        $settings['markdown'] = $this->request->is('markdown=1') ? 1 : 0;
        $settings['xmlrpcMarkdown'] = $this->request->is('xmlrpcMarkdown=1') ? 1 : 0;
        $defaultAllow = $this->request->getArray('defaultAllow');

        $settings['defaultAllowComment'] = in_array('comment', $defaultAllow) ? 1 : 0;
        $settings['defaultAllowPing'] = in_array('ping', $defaultAllow) ? 1 : 0;
        $settings['defaultAllowFeed'] = in_array('feed', $defaultAllow) ? 1 : 0;

        foreach ($settings as $name => $value) {
            if (
                $this->db->fetchObject($this->db->select(['COUNT(*)' => 'num'])
                    ->from('table.options')->where('name = ? AND user = ?', $name, $this->user->uid))->num > 0
            ) {
                Options::alloc()
                    ->update(
                        ['value' => $value],
                        $this->db->sql()->where('name = ? AND user = ?', $name, $this->user->uid)
                    );
            } else {
                Options::alloc()->insert([
                    'name'  => $name,
                    'value' => $value,
                    'user'  => $this->user->uid
                ]);
            }
        }

        Notice::alloc()->set(_t("设置已经保存"), 'success');
        $this->response->goBack();
    }

    /**
     * 更新密码
     *
     * @throws Exception
     */
    public function updatePassword()
    {
        if ($this->passwordForm()->validate()) {
            $this->response->goBack();
        }

        $password = Password::hash($this->request->password);

        $this->update(
            ['password' => $password],
            $this->db->sql()->where('uid = ?', $this->user->uid)
        );

        Notice::alloc()->highlight('user-' . $this->user->uid);

        Notice::alloc()->set(_t('密码已经成功修改'), 'success');

        $this->response->goBack();
    }

    /**
     * 生成表单
     *
     * @return Form
     */
    public function passwordForm(): Form
    {
        $form = new Form($this->security->getIndex('/action/users-profile'), Form::POST_METHOD);
        $form->setAttribute('id', 'tr-profile-form-password');

        $password = new Form\Element\Password('password', null, null, _t('用户密码'), _t('为此用户分配一个密码')
            . '<br />' . _t('建议使用特殊字符与字母、数字的混编样式，以增加系统安全性'));
        $password->input->setAttribute('class', 'w-60');
        $form->addInput($password);

        $confirm = new Form\Element\Password('confirm', null, null, _t('用户密码确认'), _t('请确认你的密码，与上面输入的密码保持一致'));
        $confirm->input->setAttribute('class', 'w-60');
        $form->addInput($confirm);

        $do = new Form\Element\Hidden('do', null, 'password');
        $form->addInput($do);

        $submit = new Form\Element\Submit('submit', null, _t('更新密码'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        $password->addRule('required', _t('必须填写密码'));
        $password->addRule(
            [Password::class, 'validateLength'],
            _t('密码长度需在 %d-%d 位之间', Password::minLength(), Password::maxLength())
        );
        $confirm->addRule('confirm', _t('两次输入的密码不一致'), 'password');

        return $form;
    }

    /**
     * 更新个人设置
     *
     * @throws \Typecho\Widget\Exception
     */
    public function updatePersonal()
    {
        $pluginName = Plugin::normalizeName((string) $this->request->get('plugin'));

        $plugins = Plugin::export();
        $activatedPlugins = $plugins['activated'];

        [$pluginFileName, $className] = Plugin::portal(
            $pluginName,
            __TYPECHO_ROOT_DIR__ . '/' . __TYPECHO_PLUGIN_DIR__
        );
        $info = Plugin::parseInfo($pluginFileName);

        if (!$info['personalConfig'] || !array_key_exists($pluginName, $activatedPlugins)) {
            throw new \Typecho\Widget\Exception(_t('无法配置插件'), 500);
        }

        $form = $this->personalForm($pluginName, $className, $pluginFileName, $group);
        $this->user->pass($group);

        if ($form->validate()) {
            $this->response->goBack();
        }

        $settings = $form->getAllRequest();
        unset($settings['do'], $settings['plugin']);
        $name = '_plugin:' . $pluginName;

        if (!$this->personalConfigHandle($className, $settings)) {
            if (
                $this->db->fetchObject($this->db->select(['COUNT(*)' => 'num'])
                    ->from('table.options')->where('name = ? AND user = ?', $name, $this->user->uid))->num > 0
            ) {
                Options::alloc()
                    ->update(
                        ['value' => Common::jsonEncode($settings, 0, '{}')],
                        $this->db->sql()->where('name = ? AND user = ?', $name, $this->user->uid)
                    );
            } else {
                Options::alloc()->insert([
                    'name'  => $name,
                    'value' => Common::jsonEncode($settings, 0, '{}'),
                    'user'  => $this->user->uid
                ]);
            }
        }

        Notice::alloc()->set(_t("%s 设置已经保存", $info['title']), 'success');

        $this->response->redirect(Common::url('profile.php', $this->options->adminUrl));
    }

    /**
     * 用自有函数处理自定义配置信息
     *
     * @param string $className 类名
     * @param array $settings 配置值
     */
    public function personalConfigHandle(string $className, array $settings): bool
    {
        if (method_exists($className, 'personalConfigHandle')) {
            call_user_func([$className, 'personalConfigHandle'], $settings, false);
            return true;
        }

        return false;
    }

    /**
     * 入口函数
     * @return void
     */
    public function action()
    {
        $this->security->protect();
        $this->on($this->request->is('do=profile'))->updateProfile();
        $this->on($this->request->is('do=options'))->updateOptions();
        $this->on($this->request->is('do=password'))->updatePassword();
        $this->on($this->request->is('do=personal&plugin'))->updatePersonal();
        $this->response->redirect($this->options->siteUrl);
    }
}
