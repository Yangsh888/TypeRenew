<?php if (!defined('__TYPECHO_ADMIN__')) exit; ?>
<?php $tabs = $menu->getTabs('theme', true); ?>
<?php if (!empty($tabs)): ?>
<ul class="typecho-option-tabs fix-tabs">
    <?php foreach ($tabs as $tab): ?>
        <?php
        $tabFile = basename((string) parse_url((string) ($tab['url'] ?? ''), PHP_URL_PATH));
        $tabName = $tab['name'] ?? '';
        $tabText = is_string($tabName) ? trim(strip_tags($tabName)) : '';
        if ($tabFile === 'themes.php') {
            $tabText = _t('可以使用的外观');
        } elseif ($tabFile === 'theme-editor.php') {
            $editingTheme = isset($files) ? $files->currentTheme() : $options->theme;
            $tabText = $options->theme == $editingTheme
                ? _t('编辑当前外观')
                : _t('编辑%s外观', ' ' . $editingTheme . ' ');
        } elseif ($tabFile === 'options-theme.php') {
            $tabText = _t('设置外观');
        }
        ?>
        <li<?php if (!empty($tab['active'])): ?> class="current"<?php endif; ?>>
            <a href="<?php echo htmlspecialchars((string) ($tab['url'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($tabText, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
