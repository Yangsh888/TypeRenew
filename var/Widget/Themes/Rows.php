<?php

namespace Widget\Themes;

use Typecho\Common;
use Typecho\Plugin;
use Typecho\Widget;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Rows extends Widget
{
    public function execute()
    {
        $themes = $this->getThemes();

        $options = Options::alloc();
        $activated = 0;
        $result = [];

        foreach ($themes as $key => $theme) {
            $themeFile = $theme . '/index.php';
            if (file_exists($themeFile)) {
                $info = Plugin::parseInfo($themeFile);
                $info['name'] = $this->getTheme($theme);

                $info['activated'] = ($options->theme === $info['name']);
                if ($info['activated']) {
                    $activated = $key;
                }

                $screen = array_filter(glob($theme . '/*') ?: [], function ($path) {
                    return preg_match("/screenshot\.(jpg|png|gif|bmp|jpeg|webp|avif|svg)$/i", $path);
                });

                if ($screen) {
                    $info['screen'] = $options->themeUrl(basename(current($screen)), $info['name']);
                } else {
                    $info['screen'] = Common::url('noscreen.png', $options->adminStaticUrl('img'));
                }

                $result[$key] = $info;
            }
        }

        if (!empty($result) && isset($result[$activated])) {
            $clone = $result[$activated];
            unset($result[$activated]);
            array_unshift($result, $clone);
        }

        foreach ($result as $theme) {
            $this->push($theme);
        }
    }

    protected function getThemes(): array
    {
        return glob(__TYPECHO_ROOT_DIR__ . __TYPECHO_THEME_DIR__ . '/*', GLOB_ONLYDIR) ?: [];
    }
    protected function getTheme(string $theme): string
    {
        return basename($theme);
    }
}
