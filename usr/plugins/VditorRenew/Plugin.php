<?php
/**
 * 【TypeRenew 专用】Vditor 编辑器拓展
 *
 * @package VditorRenew
 * @author TypeRenew
 * @link https://github.com/Yangsh888/TypeRenew
 * @version 1.0.0
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use Typecho\Cache;
use Typecho\Common;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Utils\Helper;
use Utils\NoPersonal;
use Utils\Pref;

class VditorRenew_Plugin implements PluginInterface
{
    use NoPersonal;

    private const NAME = 'VditorRenew';
    private const CACHE_KEY = 'vditorrenew:settings:v2';
    private const DIST_REL = 'assets/vditor/dist';

    public static function activate()
    {
        if (!self::hasDist()) {
            throw new Typecho_Plugin_Exception(_t('Vditor 资源目录不完整，请重新上传插件文件'));
        }
        Typecho_Plugin::factory('admin/write-post.php')->richEditor = ['VditorRenew_Bridge', 'post'];
        Typecho_Plugin::factory('admin/write-page.php')->richEditor = ['VditorRenew_Bridge', 'page'];
        self::ensureConfigStored();
        self::clearConfigCache();
        return _t('Vditor 编辑器插件已启用');
    }

    public static function deactivate()
    {
        self::clearConfigCache();
    }

    public static function config(Form $form)
    {
        $defaults = self::defaults();

        $enabled = new Form\Element\Radio(
            'enabled',
            ['1' => _t('启用 Vditor'), '0' => _t('回退旧编辑器')],
            (string) $defaults['enabled'],
            _t('编辑器状态')
        );
        $form->addInput($enabled);

        $mode = new Form\Element\Select(
            'mode',
            ['wysiwyg' => _t('所见即所得'), 'ir' => _t('即时渲染'), 'sv' => _t('分屏预览')],
            (string) $defaults['mode'],
            _t('默认编辑模式')
        );
        $form->addInput($mode);

        $legacy = new Form\Element\Select(
            'legacy',
            [
                'raw' => _t('旧文按原样内容编辑（不推荐）'),
                'convert' => _t('旧文自动转换为 Markdown')
            ],
            (string) $defaults['legacy'],
            _t('旧文章兼容策略')
        );
        $form->addInput($legacy);

        $featureValues = (array) ($defaults['features'] ?? []);

        $features = new Form\Element\Checkbox(
            'features',
            [
                'modeSwitch' => _t('允许在编辑页切换三种模式'),
                'outline' => _t('启用大纲面板'),
                'counter' => _t('启用字数统计'),
                'emoji' => _t('启用表情输入'),
                'localCache' => _t('启用浏览器本地草稿缓存（推荐）'),
                'followTheme' => _t('跟随 Renew UI 深浅主题'),
                'hljs' => _t('启用代码高亮'),
                'typechoCache' => _t('启用 TypeRenew 缓存配置快照')
            ],
            $featureValues,
            _t('功能开关')
        );
        $form->addInput($features->multiMode());

        $lang = new Form\Element\Select(
            'lang',
            ['zh_CN' => 'zh_CN', 'en_US' => 'en_US'],
            (string) $defaults['lang'],
            _t('界面语言')
        );
        $form->addInput($lang);

        $icon = new Form\Element\Select(
            'icon',
            ['ant' => 'ant', 'material' => 'material'],
            (string) $defaults['icon'],
            _t('图标集')
        );
        $form->addInput($icon);

        $height = new Form\Element\Number(
            'editorHeight',
            null,
            (int) $defaults['editorHeight'],
            _t('编辑区高度')
        );
        $height->input->setAttribute('class', 'w-20');
        $form->addInput($height->addRule('isInteger', _t('请填入一个数字')));

        $full = new Form\Element\Select(
            'fullStrategy',
            ['compat' => _t('兼容桥接模式'), 'native' => _t('Vditor 原生模式'), 'off' => _t('禁用全屏按钮')],
            (string) $defaults['fullStrategy'],
            _t('全屏策略')
        );
        $form->addInput($full);

        $ttl = new Form\Element\Number(
            'cacheTtl',
            null,
            (int) $defaults['cacheTtl'],
            _t('配置缓存秒数')
        );
        $ttl->input->setAttribute('class', 'w-20');
        $form->addInput($ttl->addRule('isInteger', _t('请填入一个数字')));

        $toolbar = new Form\Element\Textarea(
            'toolbar',
            null,
            (string) $defaults['toolbar'],
            _t('工具栏扩展 JSON'),
            _t('留空使用默认工具栏；填写 JSON 数组可扩展，如 ["|","table","code-theme"]')
        );
        $toolbar->input->setAttribute('class', 'mono');
        $form->addInput($toolbar);

    }

    public static function configHandle(array &$settings, bool $isInit)
    {
        $normalized = self::normalize(array_merge(self::defaults(), $settings));
        $settings = [
            'enabled' => (string) ($normalized['enabled'] ? '1' : '0'),
            'mode' => $normalized['mode'],
            'legacy' => $normalized['legacy'],
            'features' => array_keys(array_filter([
                'modeSwitch' => $normalized['modeSwitch'],
                'outline' => $normalized['outline'],
                'counter' => $normalized['counter'],
                'emoji' => $normalized['emoji'],
                'localCache' => $normalized['localCache'],
                'followTheme' => $normalized['followTheme'],
                'hljs' => $normalized['hljs'],
                'typechoCache' => $normalized['typechoCache']
            ])),
            'lang' => $normalized['lang'],
            'icon' => $normalized['icon'],
            'editorHeight' => (string) $normalized['editorHeight'],
            'fullStrategy' => $normalized['fullStrategy'],
            'cacheTtl' => (string) $normalized['cacheTtl'],
            'toolbar' => $normalized['toolbar']
        ];

        \Widget\Plugins\Edit::configPlugin(self::NAME, $settings);
        self::clearConfigCache();
    }

    public static function getSettings(): array
    {
        static $runtime = null;
        return Pref::load(
            $runtime,
            self::CACHE_KEY,
            self::defaults(),
            static fn() => (array) Helper::options()->plugin(self::NAME)->toArray(),
            static fn(array $settings): array => self::normalize($settings),
            static fn() => self::ensureConfigStored(),
            static function (string $scope, Throwable $e): void {
                self::reportException('getSettings.' . $scope, $e);
            }
        );
    }

    public static function assetUrl(string $path): string
    {
        $options = Helper::options();
        return Common::url(self::NAME . '/' . ltrim($path, '/'), $options->pluginUrl);
    }

    public static function distDir(): string
    {
        return rtrim(Helper::options()->pluginDir(self::NAME), '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::DIST_REL);
    }

    public static function hasDist(): bool
    {
        $dist = self::distDir();
        return is_file($dist . DIRECTORY_SEPARATOR . 'index.min.js')
            && is_file($dist . DIRECTORY_SEPARATOR . 'index.css')
            && is_file($dist . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'i18n' . DIRECTORY_SEPARATOR . 'zh_CN.js');
    }

    private static function clearConfigCache(): void
    {
        try {
            Cache::getInstance()->delete(self::CACHE_KEY);
        } catch (Throwable $e) {
            self::reportException('clearConfigCache', $e);
        }
    }

    private static function ensureConfigStored(): void
    {
        $defaults = self::normalize(self::defaults());
        try {
            $existing = (array) Helper::options()->plugin(self::NAME)->toArray();
            $merged = self::normalize(array_merge($defaults, $existing));
            \Widget\Plugins\Edit::configPlugin(self::NAME, [
                'enabled' => (string) $merged['enabled'],
                'mode' => (string) $merged['mode'],
                'legacy' => (string) $merged['legacy'],
                'features' => (array) $merged['features'],
                'lang' => (string) $merged['lang'],
                'icon' => (string) $merged['icon'],
                'editorHeight' => (string) $merged['editorHeight'],
                'fullStrategy' => (string) $merged['fullStrategy'],
                'cacheTtl' => (string) $merged['cacheTtl'],
                'toolbar' => (string) $merged['toolbar']
            ]);
        } catch (Throwable $e) {
            self::reportException('ensureConfigStored', $e);
        }
    }

    private static function defaults(): array
    {
        return [
            'enabled' => 1,
            'mode' => 'ir',
            'legacy' => 'convert',
            'features' => ['modeSwitch', 'outline', 'counter', 'emoji', 'localCache', 'followTheme', 'typechoCache'],
            'lang' => 'zh_CN',
            'icon' => 'ant',
            'editorHeight' => 520,
            'fullStrategy' => 'compat',
            'cacheTtl' => 300,
            'toolbar' => ''
        ];
    }

    private static function normalize(array $settings): array
    {
        $featureSet = [];
        if (isset($settings['features'])) {
            $featureSet = is_array($settings['features']) ? $settings['features'] : [(string) $settings['features']];
        }

        if (!empty($featureSet)) {
            $sortedFeatures = array_values(array_filter(array_map('strval', $featureSet)));
            sort($sortedFeatures, SORT_STRING);
            $legacyDefault = ['counter', 'emoji', 'followTheme', 'modeSwitch', 'outline', 'typechoCache'];
            sort($legacyDefault, SORT_STRING);
            if ($sortedFeatures === $legacyDefault) {
                $featureSet[] = 'localCache';
            }
        }

        $featureOn = static function (string $name) use ($featureSet, $settings): int {
            if (!empty($featureSet)) {
                return (int) in_array($name, $featureSet, true);
            }
            return (int) !empty($settings[$name]);
        };

        $mode = (string) ($settings['mode'] ?? 'ir');
        if (!in_array($mode, ['wysiwyg', 'ir', 'sv'], true)) {
            $mode = 'ir';
        }

        $legacy = (string) ($settings['legacy'] ?? 'convert');
        if ($legacy === 'classic') {
            $legacy = 'convert';
        }
        if (!in_array($legacy, ['raw', 'convert'], true)) {
            $legacy = 'convert';
        }

        $lang = (string) ($settings['lang'] ?? 'zh_CN');
        if (!in_array($lang, ['zh_CN', 'en_US'], true)) {
            $lang = 'zh_CN';
        }

        $icon = (string) ($settings['icon'] ?? 'ant');
        if (!in_array($icon, ['ant', 'material'], true)) {
            $icon = 'ant';
        }

        $fullStrategy = (string) ($settings['fullStrategy'] ?? 'compat');
        if (!in_array($fullStrategy, ['compat', 'native', 'off'], true)) {
            $fullStrategy = 'compat';
        }

        $toolbar = trim((string) ($settings['toolbar'] ?? ''));
        if ($toolbar !== '') {
            json_decode($toolbar, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $toolbar = '';
            }
        }

        return [
            'enabled' => (int) ((string) ($settings['enabled'] ?? '1') === '1'),
            'mode' => $mode,
            'legacy' => $legacy,
            'modeSwitch' => $featureOn('modeSwitch'),
            'outline' => $featureOn('outline'),
            'counter' => $featureOn('counter'),
            'emoji' => $featureOn('emoji'),
            'localCache' => $featureOn('localCache'),
            'followTheme' => $featureOn('followTheme'),
            'hljs' => $featureOn('hljs'),
            'typechoCache' => $featureOn('typechoCache'),
            'lang' => $lang,
            'icon' => $icon,
            'editorHeight' => max(420, min(1200, (int) ($settings['editorHeight'] ?? 520))),
            'fullStrategy' => $fullStrategy,
            'cacheTtl' => max(60, min(86400, (int) ($settings['cacheTtl'] ?? 300))),
            'toolbar' => $toolbar
        ];
    }

    private static function reportException(string $scope, Throwable $e): void
    {
        error_log(self::NAME . '.' . $scope . ': ' . $e->getMessage());
    }
}
