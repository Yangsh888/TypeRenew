<?php

namespace Widget\Plugins;

use Typecho\Config as TypechoConfig;
use Typecho\Plugin;
use Typecho\Widget\Exception;
use Typecho\Widget\Helper\Form;
use Widget\Base\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 插件配置组件
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Config extends Options
{
    /**
     * 获取插件信息
     *
     * @var array
     */
    public array $info;

    /**
     * 插件文件路径
     *
     * @var string
     */
    private string $pluginFileName;

    /**
     * 插件类
     *
     * @var string
     */
    private string $className;

    /**
     * @throws Plugin\Exception
     * @throws Exception|\Typecho\Db\Exception
     */
    public function execute()
    {
        $this->user->pass('administrator');
        $config = Plugin::normalizeName((string) $this->request->get('config'));
        if (empty($config)) {
            throw new Exception(_t('插件不存在'), 404);
        }

        [$this->pluginFileName, $this->className] = Plugin::portal($config, $this->options->pluginDir);
        $this->info = Plugin::parseInfo($this->pluginFileName);
    }

    /**
     * 获取菜单标题
     *
     * @return string
     */
    public function getMenuTitle(): string
    {
        return _t('设置插件 %s', $this->info['title']);
    }

    /**
     * 配置插件
     *
     * @return Form
     * @throws Exception|Plugin\Exception
     */
    public function config(): Form
    {
        $pluginName = Plugin::normalizeName((string) $this->request->get('config'));

        $plugins = Plugin::export();
        $activatedPlugins = $plugins['activated'];

        if (!$this->info['config'] || !array_key_exists($pluginName, $activatedPlugins)) {
            throw new Exception(_t('无法配置插件'), 500);
        }

        require_once $this->pluginFileName;
        $form = new Form($this->security->getIndex('/action/plugins-edit?config=' . $pluginName), Form::POST_METHOD);
        call_user_func([$this->className, 'config'], $form);

        try {
            $options = $this->options->plugin($pluginName);
        } catch (\Typecho\Plugin\Exception $e) {
            $options = new TypechoConfig([]);
        }

        if (!empty($options)) {
            foreach ($options as $key => $val) {
                $input = $form->getInput($key);
                if ($input !== null) {
                    $input->value($val);
                }
            }
        }

        $submit = new Form\Element\Submit(null, null, _t('保存设置'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);
        return $form;
    }
}
