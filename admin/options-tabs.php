<?php if (!defined('__TYPECHO_ADMIN__')) exit; ?>
<?php $current = method_exists($menu, 'getCurrentMenuUrl') ? $menu->getCurrentMenuUrl() : ''; ?>
<div class="tr-tabs-wrap" role="navigation" aria-label="<?php _e('设置导航'); ?>">
    <ul class="tr-settings-tabs" role="tablist">
        <li<?php if ($current === 'options-general.php'): ?> class="current"<?php endif; ?>><a href="<?php $options->adminUrl('options-general.php'); ?>"><?php _e('基本设置'); ?></a></li>
        <li<?php if ($current === 'options-discussion.php'): ?> class="current"<?php endif; ?>><a href="<?php $options->adminUrl('options-discussion.php'); ?>"><?php _e('评论设置'); ?></a></li>
        <li<?php if ($current === 'options-reading.php'): ?> class="current"<?php endif; ?>><a href="<?php $options->adminUrl('options-reading.php'); ?>"><?php _e('阅读设置'); ?></a></li>
        <li<?php if ($current === 'options-permalink.php'): ?> class="current"<?php endif; ?>><a href="<?php $options->adminUrl('options-permalink.php'); ?>"><?php _e('永久链接'); ?></a></li>
        <li<?php if ($current === 'options-cache.php'): ?> class="current"<?php endif; ?>><a href="<?php $options->adminUrl('options-cache.php'); ?>"><?php _e('缓存设置'); ?></a></li>
        <li<?php if ($current === 'options-mail.php'): ?> class="current"<?php endif; ?>><a href="<?php $options->adminUrl('options-mail.php'); ?>"><?php _e('邮件通知'); ?></a></li>
    </ul>
</div>
