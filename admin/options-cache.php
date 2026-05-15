<?php
include 'common.php';
include 'header.php';
include 'menu.php';

\Widget\Options\Cache::alloc()->to($cacheWidget);
$cachePanel = $cacheWidget->panel();
$cacheOn = $cachePanel['status'] === 'enabled';
?>

<main class="main">
    <div class="body container">
        <div class="row typecho-page-main">
            <div class="col-mb-12 col-tb-8 col-tb-offset-2">
                <?php include 'options-tabs.php'; ?>
            </div>
        </div>

        <div class="row typecho-page-main tr-mt-16">
            <div class="col-mb-12 col-tb-8 col-tb-offset-2">
                <div class="tr-grid cols-4 tr-cache-kpi-grid">
                    <div class="tr-card">
                        <div class="tr-card-b">
                            <div class="tr-kpi">
                                <div>
                                    <div class="tr-kpi-label"><?php _e('缓存状态'); ?></div>
                                    <div class="tr-kpi-value"><?php echo $cacheOn ? _t('开启') : _t('关闭'); ?></div>
                                </div>
                                <div class="tr-kpi-icon" aria-hidden="true">
                                    <svg class="tr-ico"><use href="<?php echo htmlspecialchars($options->adminStaticUrl('img', 'icons.svg', true), ENT_QUOTES, 'UTF-8'); ?>#i-activity"></use></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tr-card">
                        <div class="tr-card-b">
                            <div class="tr-kpi">
                                <div>
                                    <div class="tr-kpi-label"><?php _e('缓存驱动'); ?></div>
                                    <div class="tr-kpi-value"><?php echo htmlspecialchars((string) $cachePanel['driver'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="tr-kpi-icon tr-tone-blue" aria-hidden="true">
                                    <svg class="tr-ico"><use href="<?php echo htmlspecialchars($options->adminStaticUrl('img', 'icons.svg', true), ENT_QUOTES, 'UTF-8'); ?>#i-database"></use></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tr-card">
                        <div class="tr-card-b">
                            <div class="tr-kpi">
                                <div>
                                    <div class="tr-kpi-label"><?php _e('平均耗时'); ?></div>
                                    <div class="tr-kpi-value"><?php echo (float) $cachePanel['avg']; ?>ms</div>
                                </div>
                                <div class="tr-kpi-icon tr-tone-ink" aria-hidden="true">
                                    <svg class="tr-ico"><use href="<?php echo htmlspecialchars($options->adminStaticUrl('img', 'icons.svg', true), ENT_QUOTES, 'UTF-8'); ?>#i-clock"></use></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tr-card">
                        <div class="tr-card-b">
                            <div class="tr-kpi">
                                <div>
                                    <div class="tr-kpi-label"><?php _e('缓存条目'); ?></div>
                                    <div class="tr-kpi-value"><?php echo (int) $cachePanel['count']; ?></div>
                                </div>
                                <div class="tr-kpi-icon tr-tone-blue" aria-hidden="true">
                                    <svg class="tr-ico"><use href="<?php echo htmlspecialchars($options->adminStaticUrl('img', 'icons.svg', true), ENT_QUOTES, 'UTF-8'); ?>#i-list"></use></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tr-card tr-mt-16">
                    <div class="tr-card-b tr-cache-actions">
                        <div>
                            <div class="tr-section-title"><?php _e('缓存操作'); ?></div>
                            <div class="tr-help"><?php _e('清空当前索引前缀下的全部缓存内容'); ?></div>
                        </div>
                        <form action="<?php echo htmlspecialchars($security->getIndex('/action/options-cache'), ENT_QUOTES, 'UTF-8'); ?>" method="post">
                            <input type="hidden" name="do" value="flush">
                            <button class="tr-btn tr-btn-warn" type="submit"><?php _e('清空缓存'); ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row typecho-page-main tr-mt-16" role="form">
            <div class="col-mb-12 col-tb-8 col-tb-offset-2 tr-panel">
                <div class="tr-settings-body">
                    <?php $cacheWidget->form()->render(); ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
include 'copyright.php';
include 'common-js.php';
include 'form-js.php';
include 'footer.php';
?>
