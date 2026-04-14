<?php if (!defined('__TYPECHO_ADMIN__')) exit; ?>
<?php
$iconsUrl = $options->adminStaticUrl('img', 'icons.svg', true);

$trIconMap = [
    'index.php' => 'i-home',
    'write-post.php' => 'i-pencil',
    'write-page.php' => 'i-file',
    'manage-posts.php' => 'i-file-text',
    'manage-pages.php' => 'i-files',
    'manage-comments.php' => 'i-message',
    'manage-medias.php' => 'i-image',
    'manage-categories.php' => 'i-folder',
    'manage-tags.php' => 'i-tag',
    'manage-users.php' => 'i-users',
    'plugins.php' => 'i-puzzle',
    'themes.php' => 'i-palette',
    'backup.php' => 'i-download',
    'upgrade.php' => 'i-upload',
    'options-general.php' => 'i-gear',
    'options-discussion.php' => 'i-bell',
    'options-reading.php' => 'i-book',
    'options-permalink.php' => 'i-link',
    'options-cache.php' => 'i-spark',
    'options-mail.php' => 'i-mail',
    'profile.php' => 'i-user',
    'extending.php' => 'i-puzzle',
];

$trPanelIconMap = [
    'RenewGo/Panel.php' => 'i-external',
    'RenewSEO/Panel.php' => 'i-spark',
];

$trIconOf = function (string $href) use ($trIconMap, $trPanelIconMap): ?string {
    $parts = \Typecho\Common::parseUrl($href);
    $path = $parts['path'] ?? '';
    $base = $path !== '' ? basename($path) : basename($href);
    if ($base === 'extending.php') {
        $query = $parts['query'] ?? '';
        if ($query !== '') {
            parse_str($query, $params);
            $panel = isset($params['panel']) ? (string) $params['panel'] : '';
            if ($panel !== '' && isset($trPanelIconMap[$panel])) {
                return $trPanelIconMap[$panel];
            }
        }
    }
    return $trIconMap[$base] ?? null;
};

$trText = function ($value): string {
    $text = trim(strip_tags((string) $value));
    return $text;
};

$trRenderMenu = function () use ($menu, $iconsUrl, $trIconOf, $trText): string {
    $tree = method_exists($menu, 'getMenuTree') ? $menu->getMenuTree() : [];
    if (empty($tree)) {
        return '';
    }

    $out = '<ul class="tr-menu-root">';
    foreach ($tree as $parent) {
        $parentUrl = (string) ($parent['url'] ?? '#');
        $parentName = $trText($parent['name'] ?? '');
        $parentActive = !empty($parent['active']);
        $parentIconId = $trIconOf($parentUrl) ?? 'i-layers';
        $parentIcon = $parentIconId ? '<svg class="tr-ico" aria-hidden="true"><use href="' . htmlspecialchars($iconsUrl, ENT_QUOTES, 'UTF-8') . '#' . $parentIconId . '"></use></svg>' : '';

        $out .= '<li' . ($parentActive ? ' class="tr-parent-active"' : '') . '>';
        $out .= '<a href="' . htmlspecialchars($parentUrl, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($parentName, ENT_QUOTES, 'UTF-8') . '">'
            . $parentIcon . '<span>' . htmlspecialchars($parentName, ENT_QUOTES, 'UTF-8') . '</span></a>';

        $children = $parent['children'] ?? [];
        $out .= '<menu>';
        foreach ($children as $child) {
            $childUrl = (string) ($child['url'] ?? '#');
            $childName = $trText($child['name'] ?? '');
            $childActive = !empty($child['active']);
            $childIconId = $trIconOf($childUrl) ?? 'i-file';
            $childIcon = $childIconId ? '<svg class="tr-ico" aria-hidden="true"><use href="' . htmlspecialchars($iconsUrl, ENT_QUOTES, 'UTF-8') . '#' . $childIconId . '"></use></svg>' : '';

            $out .= '<li' . ($childActive ? ' class="tr-child-active"' : '') . '>';
            $out .= '<a href="' . htmlspecialchars($childUrl, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($childName, ENT_QUOTES, 'UTF-8') . '">'
                . $childIcon . '<span>' . htmlspecialchars($childName, ENT_QUOTES, 'UTF-8') . '</span></a>';
            $out .= '</li>';
        }
        $out .= '</menu>';
        $out .= '</li>';
    }
    $out .= '</ul>';
    return $out;
};

$userAvatarUrl = \Typecho\Common::gravatarUrl($user->mail, 38);
?>
<aside id="trSidebar" class="tr-shell" aria-label="<?php _e('侧边栏'); ?>">
    <div class="tr-shell-inner">
        <div class="tr-shell-head">
            <a class="tr-brand" href="<?php $options->adminUrl('index.php'); ?>">
                <span class="tr-brand-mark" aria-hidden="true">
                    <svg class="tr-ico tr-ico-invert" aria-hidden="true"><use href="<?php echo htmlspecialchars($iconsUrl, ENT_QUOTES, 'UTF-8'); ?>#i-pencil"></use></svg>
                </span>
                <span class="tr-brand-name"><?php echo htmlspecialchars($options->title, ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
        </div>
        <nav class="tr-nav" role="navigation">
            <?php echo $trRenderMenu(); ?>
        </nav>
        <div class="tr-user">
            <div class="tr-user-card">
                <img class="tr-user-avatar" src="<?php echo htmlspecialchars($userAvatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($user->screenName, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="tr-user-meta">
                    <div class="tr-user-name"><a href="<?php $options->adminUrl('profile.php'); ?>"><?php $user->screenName(); ?></a></div>
                    <div class="tr-user-role"><?php echo htmlspecialchars((string) $user->group, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="tr-user-actions">
                    <a href="<?php $options->siteUrl(); ?>" target="_blank" rel="noopener noreferrer" title="<?php _e('查看网站'); ?>">
                        <svg class="tr-ico" aria-hidden="true"><use href="<?php echo htmlspecialchars($iconsUrl, ENT_QUOTES, 'UTF-8'); ?>#i-globe"></use></svg>
                    </a>
                    <a href="<?php $options->logoutUrl(); ?>" title="<?php _e('登出'); ?>">
                        <svg class="tr-ico" aria-hidden="true"><use href="<?php echo htmlspecialchars($iconsUrl, ENT_QUOTES, 'UTF-8'); ?>#i-log-out"></use></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</aside>

<header class="typecho-head-nav tr-topbar" role="navigation" aria-label="<?php _e('顶部栏'); ?>">
    <div class="tr-topbar-left">
        <button type="button" class="tr-btn-icon" data-tr-nav aria-controls="trSidebar" aria-expanded="false" aria-label="<?php _e('菜单'); ?>">
            <svg class="tr-ico" aria-hidden="true"><use href="<?php echo htmlspecialchars($iconsUrl, ENT_QUOTES, 'UTF-8'); ?>#i-grid"></use></svg>
        </button>
        <?php
        $parentLabel = method_exists($menu, 'getCurrentParentLabel') ? $menu->getCurrentParentLabel() : null;
        $currentTitle = $menu->title ?? _t('控制台');
        $rootTitle = _t('控制台');
        $subtitle = null;
        if (!empty($parentLabel) && $parentLabel !== $currentTitle && $parentLabel !== $rootTitle) {
            $subtitle = $parentLabel;
        }
        ?>
        <div class="tr-topbar-heading">
            <div class="tr-topbar-title"><?php echo htmlspecialchars($currentTitle, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php if (!empty($subtitle)): ?>
                <div class="tr-topbar-subtitle"><?php echo htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="tr-topbar-right">
        <?php if (!empty($menu->addLink)): ?>
            <a class="tr-pill tr-pill-accent" href="<?php echo htmlspecialchars((string) $menu->addLink, ENT_QUOTES, 'UTF-8'); ?>"><?php _e('新增'); ?></a>
        <?php endif; ?>
        <div class="tr-topbar-tools" aria-label="<?php _e('快捷工具'); ?>">
            <button type="button" class="tr-pill tr-pill-btn" id="trThemeBtn" aria-haspopup="true" aria-expanded="false" title="<?php _e('主题'); ?>">
                <svg class="tr-ico" aria-hidden="true"><use href="<?php echo htmlspecialchars($iconsUrl, ENT_QUOTES, 'UTF-8'); ?>#i-monitor"></use></svg>
            </button>
            <div class="tr-pop" id="trThemePop" role="menu" aria-label="<?php _e('主题'); ?>">
                <button type="button" class="tr-pop-item" role="menuitemradio" data-tr-theme="light" aria-checked="false">
                    <svg class="tr-ico" aria-hidden="true"><use href="<?php echo htmlspecialchars($iconsUrl, ENT_QUOTES, 'UTF-8'); ?>#i-sun"></use></svg>
                    <span><?php _e('浅色'); ?></span>
                </button>
                <button type="button" class="tr-pop-item" role="menuitemradio" data-tr-theme="dark" aria-checked="false">
                    <svg class="tr-ico" aria-hidden="true"><use href="<?php echo htmlspecialchars($iconsUrl, ENT_QUOTES, 'UTF-8'); ?>#i-moon"></use></svg>
                    <span><?php _e('深色'); ?></span>
                </button>
                <button type="button" class="tr-pop-item" role="menuitemradio" data-tr-theme="system" aria-checked="false">
                    <svg class="tr-ico" aria-hidden="true"><use href="<?php echo htmlspecialchars($iconsUrl, ENT_QUOTES, 'UTF-8'); ?>#i-monitor"></use></svg>
                    <span><?php _e('跟随系统'); ?></span>
                </button>
            </div>
            <button type="button" class="tr-pill tr-pill-btn tr-pill-kbd" id="trCmdBtn" title="<?php _e('快捷命令'); ?>">
                <svg class="tr-ico" aria-hidden="true"><use href="<?php echo htmlspecialchars($iconsUrl, ENT_QUOTES, 'UTF-8'); ?>#i-command"></use></svg>
                <span class="tr-kbd"><?php echo stripos(PHP_OS, 'darwin') !== false ? '⌘K' : 'Ctrl K'; ?></span>
            </button>
        </div>
        <div class="tr-topbar-ext" aria-label="<?php _e('扩展操作'); ?>">
            <?php \Typecho\Plugin::factory('admin/menu.php')->call('navBar'); ?>
        </div>
        <a class="tr-pill" href="<?php $options->adminUrl('profile.php'); ?>" title="<?php _e('个人资料'); ?>">
            <svg class="tr-ico" aria-hidden="true"><use href="<?php echo htmlspecialchars($iconsUrl, ENT_QUOTES, 'UTF-8'); ?>#i-user"></use></svg>
            <span><?php $user->screenName(); ?></span>
        </a>
    </div>
</header>
