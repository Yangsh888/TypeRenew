<?php

namespace Widget\Plugins;

use Typecho\Common;
use Typecho\Db;
use Typecho\Db\Adapter\SQLException;
use Typecho\Plugin;
use Typecho\Widget\Exception;
use Typecho\Widget\Helper\Form;
use Widget\ActionInterface;
use Widget\Base\Options;
use Widget\Notice;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 插件管理组件
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Edit extends Options implements ActionInterface
{
    /**
     * @var bool
     */
    private bool $configNoticed = false;

    /**
     * 启用插件
     *
     * @param $pluginName
     * @throws Exception|Db\Exception|Plugin\Exception
     */
    public function activate($pluginName)
    {
        $pluginName = Plugin::normalizeName((string) $pluginName);
        [$pluginFileName, $className] = Plugin::portal($pluginName, $this->options->pluginDir);
        $info = Plugin::parseInfo($pluginFileName);

        if (Plugin::checkDependence($info['since'])) {
            $plugins = Plugin::export();
            $activatedPlugins = $plugins['activated'];

            require_once $pluginFileName;

            if (
                array_key_exists($pluginName, $activatedPlugins) || !class_exists($className)
                || !method_exists($className, 'activate')
            ) {
                throw new Exception(_t('无法启用插件'), 500);
            }

            $activated = false;
            $persisted = false;

            try {
                $result = call_user_func([$className, 'activate']);
                $activated = true;

                $form = new Form();
                call_user_func([$className, 'config'], $form);

                $personalForm = new Form();
                call_user_func([$className, 'personalConfig'], $personalForm);

                $options = $form->getValues();
                $personalOptions = $personalForm->getValues();

                if ($options && !$this->configHandle($pluginName, $options, true)) {
                    self::configPlugin($pluginName, $options);
                }

                if ($personalOptions && !$this->personalConfigHandle($className, $personalOptions)) {
                    self::configPlugin($pluginName, $personalOptions, true);
                }

                Plugin::activate($pluginName);
                $persisted = true;
                $this->update(
                    ['value' => Common::jsonEncode(Plugin::export(), 0, '{}')],
                    $this->db->sql()->where('name = ?', 'plugins')
                );
            } catch (\Throwable $e) {
                $rollbackErrors = [];
                $this->rollbackActivateStep(
                    _t('删除全局配置'),
                    function () use ($pluginName): void {
                        $this->db->query($this->db->delete('table.options')->where('name = ?', 'plugin:' . $pluginName));
                    },
                    $rollbackErrors
                );
                $this->rollbackActivateStep(
                    _t('删除个人配置'),
                    function () use ($pluginName): void {
                        $this->db->query($this->db->delete('table.options')->where('name = ?', '_plugin:' . $pluginName));
                    },
                    $rollbackErrors
                );

                if ($activated) {
                    $this->rollbackActivateStep(
                        _t('执行插件停用回滚'),
                        function () use ($className): void {
                            call_user_func([$className, 'deactivate']);
                        },
                        $rollbackErrors
                    );
                }

                if ($persisted) {
                    $this->rollbackActivateStep(
                        _t('回滚插件激活状态'),
                        function () use ($pluginName): void {
                            Plugin::deactivate($pluginName);
                            $this->update(
                                ['value' => Common::jsonEncode(Plugin::export(), 0, '{}')],
                                $this->db->sql()->where('name = ?', 'plugins')
                            );
                        },
                        $rollbackErrors
                    );
                }

                $message = $e->getMessage();
                if ($rollbackErrors !== []) {
                    $message .= "\n" . _t('回滚阶段附加错误：%s', implode('；', $rollbackErrors));
                }

                Notice::alloc()->set($message, 'error');
                $this->response->goBack();
                return;
            }
        } else {
            $result = _t('<a href="%s">%s</a> 无法在此版本的typecho下正常工作', $info['homepage'], $info['title']);
        }

        Notice::alloc()->highlight('plugin-' . $pluginName);

        if (isset($result) && is_string($result)) {
            Notice::alloc()->set($result, 'notice');
        } else {
            Notice::alloc()->set(_t('插件已经被启用'), 'success');
        }
        $this->response->goBack();
    }

    /**
     * 用自有函数处理配置信息
     * @param string $pluginName 插件名称
     * @param array $settings 配置值
     * @param boolean $isInit 是否为初始化
     * @return boolean
     * @throws Plugin\Exception
     */
    public function configHandle(string $pluginName, array $settings, bool $isInit): bool
    {
        [$pluginFileName, $className] = Plugin::portal($pluginName, $this->options->pluginDir);

        if (!$isInit && method_exists($className, 'configCheck')) {
            $result = call_user_func([$className, 'configCheck'], $settings);

            if (!empty($result) && is_string($result)) {
                Notice::alloc()->set($result);
                $this->configNoticed = true;
            }
        }

        if (method_exists($className, 'configHandle')) {
            $className::configHandle($settings, $isInit);
            return true;
        }

        return false;
    }

    /**
     * 手动配置插件变量
     *
     * @param string $pluginName 插件名称
     * @param array $settings 变量键值对
     * @param bool $isPersonal 是否为私人变量
     * @throws Db\Exception
     */
    public static function configPlugin(string $pluginName, array $settings, bool $isPersonal = false)
    {
        $db = Db::get();
        $pluginName = ($isPersonal ? '_' : '') . 'plugin:' . $pluginName;

        $select = $db->select()->from('table.options')
            ->where('name = ?', $pluginName);

        $options = $db->fetchAll($select);

        if (empty($settings)) {
            $db->query($db->delete('table.options')->where('name = ?', $pluginName));
        } else {
            $encodedSettings = Common::jsonEncode($settings, 0, '{}');
            if (empty($options)) {
                try {
                    $db->query($db->insert('table.options')
                        ->rows([
                            'name'  => $pluginName,
                            'value' => $encodedSettings,
                            'user'  => 0
                        ]));
                } catch (SQLException $e) {
                    if (!self::isDuplicateOptionInsert($e)) {
                        throw $e;
                    }

                    $db->query($db->update('table.options')
                        ->rows(['value' => $encodedSettings])
                        ->where('name = ?', $pluginName)
                        ->where('user = ?', 0));
                }
            } else {
                foreach ($options as $option) {
                    $value = json_decode($option['value'], true);
                    $value = is_array($value) ? $value : [];
                    $value = array_merge($value, $settings);
                    $encodedValue = Common::jsonEncode($value, 0, '{}');

                    $db->query($db->update('table.options')
                        ->rows(['value' => $encodedValue])
                        ->where('name = ?', $pluginName)
                        ->where('user = ?', $option['user']));
                }
            }
        }
    }

    private static function isDuplicateOptionInsert(\Throwable $e): bool
    {
        $code = (string) $e->getCode();
        $message = strtolower($e->getMessage());

        return $code === '1062'
            || $code === '23000'
            || $code === '23505'
            || str_contains($message, 'duplicate')
            || str_contains($message, 'unique constraint')
            || str_contains($message, '1062')
            || str_contains($message, '23000')
            || str_contains($message, '23505');
    }

    private function rollbackActivateStep(string $label, callable $callback, array &$errors): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            $errors[] = _t('%s失败: %s', $label, $e->getMessage());
            error_log('Widget.Plugins.Edit.activateRollback ' . $label . ': ' . $e->getMessage());
        }
    }

    /**
     * 用自有函数处理自定义配置信息
     *
     * @param string $className 类名
     * @param array $settings 配置值
     * @return boolean
     */
    public function personalConfigHandle(string $className, array $settings): bool
    {
        if (method_exists($className, 'personalConfigHandle')) {
            call_user_func([$className, 'personalConfigHandle'], $settings, true);
            return true;
        }

        return false;
    }

    /**
     * 禁用插件
     *
     * @param string $pluginName
     * @throws Db\Exception
     * @throws Exception
     * @throws Plugin\Exception
     */
    public function deactivate(string $pluginName)
    {
        $pluginName = Plugin::normalizeName($pluginName);
        $plugins = Plugin::export();
        $activatedPlugins = $plugins['activated'];
        $pluginFileExist = true;

        try {
            [$pluginFileName, $className] = Plugin::portal($pluginName, $this->options->pluginDir);
        } catch (Plugin\Exception $e) {
            $pluginFileExist = false;

            if (!array_key_exists($pluginName, $activatedPlugins)) {
                throw $e;
            }
        }

        if (!array_key_exists($pluginName, $activatedPlugins)) {
            throw new Exception(_t('无法禁用插件'), 500);
        }

        if ($pluginFileExist) {

            require_once $pluginFileName;

            if (
                !array_key_exists($pluginName, $activatedPlugins) || !class_exists($className)
                || !method_exists($className, 'deactivate')
            ) {
                throw new Exception(_t('无法禁用插件'), 500);
            }

            try {
                $result = call_user_func([$className, 'deactivate']);
            } catch (Plugin\Exception $e) {
                Notice::alloc()->set($e->getMessage(), 'error');
                $this->response->goBack();
            }

            Notice::alloc()->highlight('plugin-' . $pluginName);
        }

        Plugin::deactivate($pluginName);
        $this->update(['value' => Common::jsonEncode(Plugin::export(), 0, '{}')], $this->db->sql()->where('name = ?', 'plugins'));

        $this->delete($this->db->sql()->where('name = ?', 'plugin:' . $pluginName));
        $this->delete($this->db->sql()->where('name = ?', '_plugin:' . $pluginName));

        if (isset($result) && is_string($result)) {
            Notice::alloc()->set($result);
        } else {
            Notice::alloc()->set(_t('插件已经被禁用'), 'success');
        }
        $this->response->goBack();
    }

    /**
     * 配置插件
     *
     * @param string $pluginName
     * @throws Db\Exception
     * @throws Exception
     * @throws Plugin\Exception
     */
    public function config(string $pluginName)
    {
        $form = Config::alloc()->config();

        if ($form->validate()) {
            $this->response->goBack();
        }

        $settings = $form->getAllRequest();

        if (!$this->configHandle($pluginName, $settings, false)) {
            self::configPlugin($pluginName, $settings);
        }

        Notice::alloc()->highlight('plugin-' . $pluginName);

        if (!$this->configNoticed) {
            Notice::alloc()->set(_t("插件设置已经保存"), 'success');
        }

        $this->response->redirect(Common::url('plugins.php', $this->options->adminUrl));
    }

    public function action()
    {
        $this->user->pass('administrator');
        if (!$this->request->isPost()) {
            $this->response->setStatus(405);
            $this->response->goBack();
        }
        $this->security->protect();

        if ($this->request->is('activate')) {
            $this->activate(Plugin::normalizeName((string) $this->request->get('activate')));
            return;
        }

        if ($this->request->is('deactivate')) {
            $this->deactivate(Plugin::normalizeName((string) $this->request->get('deactivate')));
            return;
        }

        if ($this->request->is('config')) {
            $this->config(Plugin::normalizeName((string) $this->request->get('config')));
            return;
        }

        $this->response->redirect($this->options->adminUrl);
    }
}
