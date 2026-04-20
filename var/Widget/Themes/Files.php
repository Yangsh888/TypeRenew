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

    private function listEditableFiles(string $themeRoot): array
    {
        $files = [];
        $root = rtrim(str_replace('\\', '/', $themeRoot), '/');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($themeRoot, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $extension = strtolower((string) $file->getExtension());
            if (!in_array($extension, ['php', 'js', 'css', 'vbs'], true)) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $relative = ltrim(substr($path, strlen($root)), '/');
            if ($relative !== '') {
                $files[] = $relative;
            }
        }

        natcasesort($files);
        return array_values($files);
    }

    private function resolveCurrentPath(string $themeRoot, string $file): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $file), '/');
        if (
            $relative === ''
            || str_contains($relative, "\0")
            || str_contains($relative, '../')
            || str_contains($relative, '..\\')
        ) {
            return null;
        }

        $path = realpath($themeRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        $normalizedRoot = rtrim(str_replace('\\', '/', $themeRoot), '/') . '/';
        $normalizedPath = $path === false ? false : str_replace('\\', '/', $path);

        if ($normalizedPath === false || !str_starts_with($normalizedPath, $normalizedRoot) || !is_file($path)) {
            return null;
        }

        return $path;
    }

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
            $files = $this->listEditableFiles($dir);

            $this->currentFile = $this->request->get('file', 'index.php');
            if (!in_array($this->currentFile, $files, true) && !empty($files)) {
                $this->currentFile = in_array('index.php', $files, true) ? 'index.php' : $files[0];
            }

            if ($this->resolveCurrentPath($dir, $this->currentFile) !== null) {
                foreach ($files as $file) {
                    $this->push([
                        'file'    => $file,
                        'theme'   => $this->currentTheme,
                        'current' => ($file == $this->currentFile)
                    ]);
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
        $themeRoot = Options::alloc()->themeFile($this->currentTheme);
        $path = $this->resolveCurrentPath($themeRoot, $this->currentFile);
        $content = $path ? file_get_contents($path) : false;
        return htmlspecialchars($content !== false ? $content : '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * 获取文件是否可读
     *
     * @return bool
     */
    public function currentIsWriteable(): bool
    {
        $themeRoot = Options::alloc()->themeFile($this->currentTheme);
        $path = $this->resolveCurrentPath($themeRoot, $this->currentFile);
        return $path !== null && is_writable($path) && self::isWriteable();
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
