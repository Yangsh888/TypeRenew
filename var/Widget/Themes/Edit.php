<?php

namespace Widget\Themes;

use Typecho\Common;
use Typecho\Widget\Exception;
use Typecho\Widget\Helper\Form;
use Widget\ActionInterface;
use Widget\Base\Options;
use Widget\Notice;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Edit extends Options implements ActionInterface
{
    private function writeThemeFile(string $path, string $content, string $file): void
    {
        $directory = dirname($path);
        $tempPath = $directory . DIRECTORY_SEPARATOR . '.' . basename($path) . '.tmp.' . bin2hex(random_bytes(6));
        $backupPath = $directory . DIRECTORY_SEPARATOR . '.' . basename($path) . '.bak.' . bin2hex(random_bytes(6));
        $backupCreated = false;
        $suppressFsWarning = static function (callable $callback): void {
            set_error_handler(static fn(): bool => true);
            try {
                $callback();
            } finally {
                restore_error_handler();
            }
        };

        $written = file_put_contents($tempPath, $content, LOCK_EX);
        if ($written === false) {
            throw new Exception(_t('文件 %s 无法被写入', $file));
        }

        try {
            if (is_file($path)) {
                if (!rename($path, $backupPath)) {
                    throw new Exception(_t('文件 %s 无法被写入', $file));
                }
                $backupCreated = true;
            }

            if (!rename($tempPath, $path)) {
                if ($backupCreated && is_file($backupPath)) {
                    rename($backupPath, $path);
                }
                throw new Exception(_t('文件 %s 无法被写入', $file));
            }

            if ($backupCreated && is_file($backupPath) && !unlink($backupPath) && is_file($backupPath)) {
                throw new Exception(_t('文件 %s 无法被写入', $file));
            }
        } finally {
            if (is_file($tempPath)) {
                $suppressFsWarning(static function () use ($tempPath): void {
                    unlink($tempPath);
                });
            }
            if (is_file($backupPath) && !is_file($path)) {
                $suppressFsWarning(static function () use ($backupPath, $path): void {
                    rename($backupPath, $path);
                });
            }
        }
    }

    /**
     * 解析主题内文件真实路径，阻止目录穿越到主题目录外
     */
    private function resolveThemePath(string $theme, ?string $file = null): string
    {
        $theme = trim($theme, './');
        $themeRoot = realpath($this->options->themeFile($theme));

        if ($themeRoot === false || !is_dir($themeRoot)) {
            throw new Exception(_t('您选择的风格不存在'));
        }

        if ($file === null || $file === '') {
            return $themeRoot;
        }

        $path = Files::resolveFilePath($themeRoot, $file);
        if ($path === null) {
            throw new Exception(_t('您编辑的文件不存在'));
        }

        return $path;
    }

    public function changeTheme(string $theme)
    {
        $theme = trim($theme, './');
        $this->resolveThemePath($theme);
        $oldTheme = $this->options->missingTheme ?: $this->options->theme;
        $this->delete($this->db->sql()->where('name = ?', 'theme:' . $oldTheme));

        $this->update(['value' => $theme], $this->db->sql()->where('name = ?', 'theme'));

        if (0 === strpos((string) $this->options->frontPage, 'file:')) {
            \Utils\Helper::resetFrontPage($this->options->routingTable);
        }

        $this->options->themeUrl = $this->options->themeUrl(null, $theme);

        $configFile = $this->options->themeFile($theme, 'functions.php');

        if (file_exists($configFile)) {
            require_once $configFile;

            if (function_exists('themeConfig')) {
                $form = new Form();
                themeConfig($form);
                $options = $form->getValues();

                if ($options && !$this->configHandle($options, true)) {
                    $this->insert([
                        'name'  => 'theme:' . $theme,
                        'value' => Common::jsonEncode($options, 0, '{}'),
                        'user'  => 0
                    ]);
                }
            }
        }

        Notice::alloc()->highlight('theme-' . $theme);
        Notice::alloc()->set(_t("外观已经改变"), 'success');
        $this->response->goBack();
    }

    public function configHandle(array $settings, bool $isInit): bool
    {
        if (function_exists('themeConfigHandle')) {
            $function = new \ReflectionFunction('themeConfigHandle');
            if ($function->getNumberOfParameters() < 2) {
                themeConfigHandle($settings);
            } else {
                themeConfigHandle($settings, $isInit);
            }
            return true;
        }

        return false;
    }

    public function editThemeFile(string $theme, string $file)
    {
        $path = $this->resolveThemePath($theme, $file);

        if (
            is_writable($path)
            && (!defined('__TYPECHO_THEME_WRITEABLE__') || __TYPECHO_THEME_WRITEABLE__)
        ) {
            try {
                $this->writeThemeFile($path, (string) $this->request->get('content'), $file);
                Notice::alloc()->set(_t("文件 %s 的更改已经保存", $file), 'success');
            } catch (Exception) {
                Notice::alloc()->set(_t("文件 %s 无法被写入", $file), 'error');
            }
            $this->response->goBack();
        } else {
            throw new Exception(_t('您编辑的文件不存在'));
        }
    }

    public function config(string $theme)
    {
        $form = Config::alloc()->config();

        if (!Config::isExists($theme) || $form->validate()) {
            $this->response->goBack();
        }

        $settings = $form->getAllRequest();

        if (!$this->configHandle($settings, false)) {
            $name = 'theme:' . $theme;
            $saved = json_decode((string) $this->options->__get($name), true);
            $saved = is_array($saved) ? $saved : [];
            $settings = array_merge($saved, $settings);
            $value = Common::jsonEncode($settings, 0, '{}');

            if ($this->options->__get('theme:' . $theme)) {
                $this->update(
                    ['value' => $value],
                    $this->db->sql()->where('name = ?', $name)
                );
            } else {
                $this->insert([
                    'name'  => $name,
                    'value' => $value,
                    'user'  => 0
                ]);
            }
        }

        Notice::alloc()->highlight('theme-' . $theme);

        Notice::alloc()->set(_t("外观设置已经保存"), 'success');

        $this->response->redirect(Common::url('options-theme.php', $this->options->adminUrl));
    }

    public function action()
    {
        $this->user->pass('administrator');
        if (!$this->request->isPost()) {
            $this->response->setStatus(405);
            $this->response->goBack();
        }
        $this->security->protect();
        $this->on($this->request->is('change'))->changeTheme($this->request->filter('slug')->get('change'));
        $this->on($this->request->is('edit&theme'))
            ->editThemeFile($this->request->filter('slug')->get('theme'), $this->request->get('edit'));
        $this->on($this->request->is('config'))->config($this->request->filter('slug')->get('config'));
        $this->response->redirect($this->options->adminUrl);
    }
}
