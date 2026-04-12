<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class VditorRenew_Bridge
{
    public static function post($post): void
    {
        self::render($post, 'post');
    }

    public static function page($page): void
    {
        self::render($page, 'page');
    }

    private static function render($content, string $type): void
    {
        $settings = VditorRenew_Plugin::getSettings();
        $hasContent = !empty($content->have());
        $isMarkdown = $hasContent && !empty($content->isMarkdown);
        $isLegacy = $hasContent && !$isMarkdown;
        $options = \Widget\Options::alloc();
        $markdownEnabled = !empty($options->markdown);

        if (empty($settings['enabled'])) {
            self::fallback($content, $type);
            return;
        }

        if (!VditorRenew_Plugin::hasDist()) {
            self::fallback($content, $type);
            return;
        }

        if (!$markdownEnabled && !$isMarkdown) {
            self::fallback($content, $type);
            return;
        }

        $cdn = VditorRenew_Plugin::assetUrl('assets/vditor');

        $toolbar = self::toolbar($settings);
        $legacy = $isLegacy ? $settings['legacy'] : 'raw';
        $allowMarkdown = true;
        $forceMarkdown = true;

        $user = \Widget\User::alloc();
        $isNew = empty($content->cid);
        
        $lastModified = 0;
        if ($hasContent && !empty($content->modified)) {
            $lastModified = (int) $content->modified;
        } elseif ($hasContent && !empty($content->created)) {
            $lastModified = (int) $content->created;
        }
        
        $config = [
            'mode' => $settings['mode'],
            'modeSwitch' => (bool) $settings['modeSwitch'],
            'outline' => (bool) $settings['outline'],
            'counter' => (bool) $settings['counter'],
            'emoji' => (bool) $settings['emoji'],
            'localCache' => (bool) $settings['localCache'],
            'followTheme' => (bool) $settings['followTheme'],
            'hljs' => (bool) $settings['hljs'],
            'lang' => $settings['lang'],
            'icon' => $settings['icon'],
            'editorHeight' => (int) $settings['editorHeight'],
            'fullStrategy' => (string) $settings['fullStrategy'],
            'legacy' => $legacy,
            'isMarkdown' => $isMarkdown,
            'isNew' => $isNew,
            'markdownEnabled' => $markdownEnabled,
            'allowMarkdown' => $allowMarkdown,
            'forceMarkdown' => $forceMarkdown,
            'lastModified' => $lastModified,
            'draftScope' => 'vditorrenew:' . $type,
            'cacheId' => 'vditorrenew:' . $type . ':' . ($isNew ? 'new' : ($content->cid ?? 0)),
            'toolbar' => $toolbar,
            'cdn' => $cdn
        ];

        $configJson = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>
<link rel="stylesheet" href="<?php echo VditorRenew_Plugin::assetUrl('assets/vditor/dist/index.css'); ?>">
<link rel="stylesheet" href="<?php echo VditorRenew_Plugin::assetUrl('assets/css/editor.css'); ?>">
<script src="<?php echo VditorRenew_Plugin::assetUrl('assets/vditor/dist/index.min.js'); ?>"></script>
<script>
window.VditorRenewConfig = <?php echo $configJson ?: '{}'; ?>;
</script>
<script src="<?php echo VditorRenew_Plugin::assetUrl('assets/js/editor.js'); ?>"></script>
        <?php
    }

    private static function toolbar(array $settings): array
    {
        $toolbar = ['emoji', 'headings', 'bold', 'italic', 'strike', '|', 'list', 'ordered-list', 'check', 'outdent', 'indent', '|', 'quote', 'line', 'code', 'inline-code', 'insert-before', 'insert-after', '|', 'link', 'table', '|', 'undo', 'redo', '|', 'edit-mode'];

        if (empty($settings['modeSwitch'])) {
            $toolbar = array_values(array_filter($toolbar, static fn($item) => $item !== 'edit-mode'));
        }

        if (($settings['fullStrategy'] ?? 'compat') !== 'off') {
            $toolbar[] = 'fullscreen';
        }

        if (!empty($settings['outline'])) {
            $toolbar[] = 'outline';
        }

        if (!empty($settings['counter'])) {
            $toolbar[] = 'record';
        }

        if (empty($settings['emoji'])) {
            $toolbar = array_values(array_filter($toolbar, static fn($item) => $item !== 'emoji'));
        }

        if (!empty($settings['toolbar'])) {
            $custom = json_decode((string) $settings['toolbar'], true);
            if (is_array($custom) && !empty($custom)) {
                $validated = self::validateToolbar($custom);
                if (!empty($validated)) {
                    $toolbar = $validated;
                }
            }
        }

        if (($settings['fullStrategy'] ?? 'compat') === 'off') {
            $toolbar = array_values(array_filter($toolbar, static fn($item) => !(is_string($item) && $item === 'fullscreen')));
        }

        return $toolbar;
    }

    private static function validateToolbar(array $items): array
    {
        $allowed = [
            'emoji', 'headings', 'bold', 'italic', 'strike', 'link', 'list', 'ordered-list',
            'check', 'outdent', 'indent', 'quote', 'line', 'code', 'inline-code',
            'insert-after', 'insert-before', 'undo', 'redo', 'upload', 'table',
            'record', 'edit-mode', 'both', 'preview', 'fullscreen', 'outline',
            'export', 'devtools', 'info', 'help', 'br'
        ];

        $result = [];
        foreach ($items as $item) {
            if ($item === '|') {
                $result[] = '|';
            } elseif (is_string($item) && in_array($item, $allowed, true)) {
                $result[] = $item;
            } elseif (is_array($item) && isset($item['name']) && is_string($item['name'])) {
                $name = $item['name'];
                if (in_array($name, $allowed, true)) {
                    $result[] = self::sanitizeToolbarItem($item);
                } elseif (str_starts_with($name, 'custom-')) {
                    $sanitized = self::sanitizeCustomToolbarItem($item);
                    if ($sanitized !== null) {
                        $result[] = $sanitized;
                    }
                }
            }
        }
        return $result;
    }

    private static function sanitizeToolbarItem(array $item): array
    {
        $result = ['name' => $item['name']];
        if (isset($item['tip']) && is_string($item['tip'])) {
            $result['tip'] = self::sanitizeTip($item['tip']);
        }
        return $result;
    }

    private static function sanitizeCustomToolbarItem(array $item): ?array
    {
        $name = (string) ($item['name'] ?? '');
        if (!preg_match('/^custom-[a-zA-Z0-9_-]+$/', $name)) {
            return null;
        }

        $result = ['name' => $name];

        if (isset($item['tip']) && is_string($item['tip'])) {
            $result['tip'] = self::sanitizeTip($item['tip']);
        }

        if (isset($item['icon']) && is_string($item['icon'])) {
            $icon = $item['icon'];
            if (preg_match('/^<svg[^>]*>.*<\/svg>$/s', $icon)) {
                $result['icon'] = $icon;
            }
        }

        if (isset($item['click']) && is_string($item['click'])) {
            $click = $item['click'];
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $click)) {
                $result['click'] = $click;
            }
        }

        return $result;
    }

    private static function sanitizeTip(string $tip): string
    {
        return htmlspecialchars($tip, ENT_QUOTES, 'UTF-8');
    }

    private static function fallback($content, string $type): void
    {
        if ($type === 'post') {
            $post = $content;
        } else {
            $page = $content;
        }

        include __TYPECHO_ROOT_DIR__ . '/admin/editor-js.php';
    }
}
