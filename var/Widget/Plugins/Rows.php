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
    private static ?array $installedPluginsCache = null;

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

    public static function collectInstalledPlugins(): array
    {
        if (self::$installedPluginsCache !== null) {
            return self::$installedPluginsCache;
        }

        $plugins = [];
        $entries = self::getPlugins();

        foreach ($entries as $entry) {
            $parts = self::getPlugin($entry);
            if ($parts === null) {
                continue;
            }

            [$pluginName, $pluginFileName] = $parts;
            if (!is_file($pluginFileName)) {
                continue;
            }

            $info = Plugin::parseInfo($pluginFileName);
            $info['name'] = $pluginName;
            $plugins[$pluginName] = $info;
        }

        self::$installedPluginsCache = $plugins;

        return $plugins;
    }

    public function execute()
    {
        $this->parameter->setDefault(['activated' => null]);

        $plugins = Plugin::export();
        $this->activatedPlugins = $plugins['activated'];

        foreach (self::collectInstalledPlugins() as $pluginName => $info) {
            $info['dependence'] = Plugin::checkDependence($info['since']);
            $info['activated'] = true;

            if ($info['activate'] || $info['deactivate'] || $info['config'] || $info['personalConfig']) {
                $info['activated'] = array_key_exists($pluginName, $this->activatedPlugins);

                if (array_key_exists($pluginName, $this->activatedPlugins)) {
                    unset($this->activatedPlugins[$pluginName]);
                }
            }

            if ($info['activated'] === $this->parameter->activated) {
                $this->push($info);
            }
        }
    }

    private static function getPlugins(): array
    {
        $entries = glob(__TYPECHO_ROOT_DIR__ . '/' . __TYPECHO_PLUGIN_DIR__ . '/*') ?: [];
        natcasesort($entries);

        return $entries;
    }

    private static function getPlugin(string $plugin): ?array
    {
        if (is_dir($plugin)) {
            $pluginName = basename($plugin);

            $pluginFileName = $plugin . '/Plugin.php';
        } elseif (file_exists($plugin) && 'index.php' !== basename($plugin)) {
            $pluginFileName = $plugin;
            $part = explode('.', basename($plugin));
            if (count($part) === 2 && $part[1] === 'php') {
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
