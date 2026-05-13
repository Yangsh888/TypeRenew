<?php

namespace Widget\Plugins;

use Typecho\Common;
use Typecho\Plugin;
use Typecho\Widget;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Rows extends Widget
{
    public array $activatedPlugins = [];

    public static function isOfficialPlugin($author, $homepage): bool
    {
        $author = strtolower(trim((string) $author));
        if ($author !== 'typerenew') {
            return false;
        }

        $homepage = trim((string) $homepage);
        if ($homepage === '') {
            return false;
        }

        $parts = Common::parseUrl($homepage);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return in_array($host, ['www.typerenew.com', 'typerenew.com'], true);
    }

    public function execute()
    {
        $pluginDirs = $this->getPlugins();
        $this->parameter->setDefault(['activated' => null]);

        $plugins = Plugin::export();
        $this->activatedPlugins = $plugins['activated'];

        if (!empty($pluginDirs)) {
            foreach ($pluginDirs as $key => $pluginDir) {
                $parts = $this->getPlugin($pluginDir);
                if (empty($parts)) {
                    continue;
                }

                [$pluginName, $pluginFileName] = $parts;

                if (file_exists($pluginFileName)) {
                    $info = Plugin::parseInfo($pluginFileName);
                    $info['name'] = $pluginName;

                    $info['dependence'] = Plugin::checkDependence($info['since']);
                    $info['activated'] = true;

                    if ($info['activate'] || $info['deactivate'] || $info['config'] || $info['personalConfig']) {
                        $info['activated'] = array_key_exists($pluginName, $this->activatedPlugins);

                        if (array_key_exists($pluginName, $this->activatedPlugins)) {
                            unset($this->activatedPlugins[$pluginName]);
                        }
                    }

                    if ($info['activated'] == $this->parameter->activated) {
                        $this->push($info);
                    }
                }
            }
        }
    }

    protected function getPlugins(): array
    {
        return glob(__TYPECHO_ROOT_DIR__ . '/' . __TYPECHO_PLUGIN_DIR__ . '/*') ?: [];
    }
    protected function getPlugin(string $plugin): ?array
    {
        if (is_dir($plugin)) {
            $pluginName = basename($plugin);

            $pluginFileName = $plugin . '/Plugin.php';
        } elseif (file_exists($plugin) && 'index.php' != basename($plugin)) {
            $pluginFileName = $plugin;
            $part = explode('.', basename($plugin));
            if (2 == count($part) && 'php' == $part[1]) {
                $pluginName = $part[0];
            } else {
                return null;
            }
        } else {
            return null;
        }

        return [$pluginName, $pluginFileName];
    }
}
