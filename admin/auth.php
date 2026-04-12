<?php
if (!defined('__TYPECHO_ADMIN__')) {
    exit;
}

if (!function_exists('tr_auth_open')) {
    function tr_auth_open(array $config): void
    {
        global $options;

        $label = (string) ($config['label'] ?? '');
        $heading = (string) ($config['heading'] ?? '');
        $description = (string) ($config['description'] ?? '');
        $heroTitle = (string) ($config['heroTitle'] ?? (string) $options->title);
        $heroSubtitle = (string) ($config['heroSubtitle'] ?? '');
        $heroFoot = (string) ($config['heroFoot'] ?? ('&copy; ' . date('Y') . ' TypeRenew Team'));
        ?>
        <div class="tr-auth" role="main" aria-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="tr-auth-switch" aria-label="<?php _e('主题切换'); ?>">
                <button type="button" class="tr-auth-switch-btn" id="trAuthThemeBtn" aria-haspopup="true" aria-expanded="false"></button>
                <div class="tr-auth-switch-menu" id="trAuthThemeMenu" role="menu" aria-label="<?php _e('主题'); ?>"></div>
            </div>
            <section class="tr-auth-hero" aria-hidden="true">
                <div class="tr-auth-hero-inner">
                    <div class="tr-auth-hero-title"><?php echo htmlspecialchars($heroTitle, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="tr-auth-hero-subtitle"><?php echo htmlspecialchars($heroSubtitle, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="tr-auth-hero-foot"><?php echo $heroFoot; ?></div>
            </section>
            <section class="tr-auth-panel">
                <div class="tr-auth-box">
                    <div class="tr-auth-heading">
                        <h1><?php echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?></h1>
                        <p><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
        <?php
    }
}

if (!function_exists('tr_auth_close')) {
    function tr_auth_close(): void
    {
        ?>
                </div>
            </section>
        </div>
        <?php
    }
}
