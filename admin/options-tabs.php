<?php if (!defined('__TYPECHO_ADMIN__')) exit; ?>
<?php $tabs = $menu->getTabs('settings'); ?>
<?php
$tabTextMap = [
    'options-general.php' => _t('基本设置'),
    'options-discussion.php' => _t('评论设置'),
    'options-reading.php' => _t('阅读设置'),
    'options-permalink.php' => _t('永久链接'),
    'options-cache.php' => _t('缓存设置'),
    'options-mail.php' => _t('邮件通知'),
];
?>
<?php if (!empty($tabs)): ?>
<div class="tr-tabs-wrap" role="navigation" aria-label="<?php _e('设置导航'); ?>">
    <ul class="tr-settings-tabs" role="tablist">
        <?php foreach ($tabs as $tab): ?>
            <?php $tabFile = basename((string) parse_url((string) ($tab['url'] ?? ''), PHP_URL_PATH)); ?>
            <?php $tabText = $tabTextMap[$tabFile] ?? trim(strip_tags((string) ($tab['name'] ?? ''))); ?>
            <li<?php if (!empty($tab['active'])): ?> class="current"<?php endif; ?>>
                <a href="<?php echo htmlspecialchars((string) ($tab['url'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($tabText, ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
