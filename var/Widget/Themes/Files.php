<?php

namespace Widget\Themes;

use Typecho\Widget;
use Widget\Base;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 风格文件列表组件
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Files extends Base
{
    /**
     * 当前风格
     * @var string
     */
    private string $currentTheme;

    /**
     * 当前文件
     * @var string
     */
    private string $currentFile;

    /**
     * @throws Widget\Exception
     */
    public function execute()
    {
        $this->user->pass('administrator');
        $this->currentTheme = $this->request->filter('slug')->get('theme', Options::alloc()->theme);

        if (
            preg_match("/^([_0-9a-z-. ])+$/i", $this->currentTheme)
            && is_dir($dir = Options::alloc()->themeFile($this->currentTheme))
            && (!defined('__TYPECHO_THEME_WRITEABLE__') || __TYPECHO_THEME_WRITEABLE__)
        ) {
            $files = array_filter(glob($dir . '/*'), function ($path) {
                return preg_match("/\.(php|js|css|vbs)$/i", $path);
            });

            $this->currentFile = $this->request->get('file', 'index.php');

            if (
                preg_match("/^([_0-9a-z-. ])+$/i", $this->currentFile)
                && file_exists($dir . '/' . $this->currentFile)
            ) {
                foreach ($files as $file) {
                    if (file_exists($file)) {
                        $file = basename($file);
                        $this->push([
                            'file'    => $file,
                            'theme'   => $this->currentTheme,
                            'current' => ($file == $this->currentFile)
                        ]);
                    }
                }

                return;
            }
        }

        throw new Widget\Exception('风格文件不存在', 404);
    }

    /**
     * 判断是否拥有写入权限
     *
     * @return bool
     */
    public static function isWriteable(): bool
    {
        return (!defined('__TYPECHO_THEME_WRITEABLE__') || __TYPECHO_THEME_WRITEABLE__)
            && !Options::alloc()->missingTheme;
    }

    /**
     * 获取菜单标题
     *
     * @return string
     */
    public function getMenuTitle(): string
    {
        return _t('编辑文件 %s', $this->currentFile);
    }

    /**
     * 获取文件内容
     *
     * @return string
     */
    public function currentContent(): string
    {
        $content = file_get_contents(Options::alloc()->themeFile($this->currentTheme, $this->currentFile));
        return htmlspecialchars($content !== false ? $content : '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * 获取文件是否可读
     *
     * @return bool
     */
    public function currentIsWriteable(): bool
    {
        return is_writable(Options::alloc()
                ->themeFile($this->currentTheme, $this->currentFile))
            && self::isWriteable();
    }

    /**
     * 获取当前文件
     *
     * @return string
     */
    public function currentFile(): string
    {
        return $this->currentFile;
    }

    /**
     * 获取当前风格
     *
     * @return string
     */
    public function currentTheme(): string
    {
        return $this->currentTheme;
    }
}
