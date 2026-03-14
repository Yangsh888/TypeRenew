<?php
include 'common.php';
$bodyClass = trim(($bodyClass ?? '') . ' tr-page-upgrade');
include 'header.php';
include 'menu.php';

$dbUpgradeUrl = $security->getTokenUrl(
    \Typecho\Router::url('do', ['action' => 'upgrade', 'widget' => 'Upgrade'], \Typecho\Common::url('index.php', $options->rootUrl))
);
$packageActionUrl = $security->getTokenUrl(
    \Typecho\Router::url('do', ['action' => 'upgrade-package', 'widget' => 'Upgrade\\Package'], \Typecho\Common::url('index.php', $options->rootUrl))
);
$needDbUpgrade = version_compare(\Typecho\Common::VERSION, $options->version, '>');
$state = null;

try {
    $store = new \Typecho\Upgrade\Store();
    $state = $store->readState();
} catch (\Throwable $e) {
    $state = null;
}

$manifest = is_array($state) ? ($state['manifest'] ?? []) : [];
$packageId = is_array($state) ? (string) ($state['id'] ?? '') : '';
$fromVersion = is_array($manifest) ? (string) ($manifest['from'] ?? '') : '';
$toVersion = is_array($manifest) ? (string) ($manifest['to'] ?? '') : '';
$build = is_array($manifest) ? (string) ($manifest['build'] ?? '') : '';
$filesCount = is_array($state) && isset($state['files']) && is_array($state['files']) ? count($state['files']) : 0;
$status = is_array($state) ? (string) ($state['status'] ?? '') : '';
$mode = is_array($state) ? (string) ($state['mode'] ?? '') : '';
$allowInstall = is_array($state) ? (bool) ($state['allowInstall'] ?? false) : false;
$error = is_array($state) ? (string) ($state['error'] ?? '') : '';
$progress = is_array($state) ? ($state['progress'] ?? null) : null;
?>

<main class="main">
    <div class="body container">
        <div class="tr-card">
            <div class="tr-card-b">
                <div class="tr-section-head">
                    <div class="tr-section-title"><?php _e('升级程序'); ?></div>
                </div>

                <div class="tr-stack tr-mt-16">
                    <div class="tr-grid cols-2">
                            <div class="tr-card tr-tone-muted">
                                <div class="tr-card-b">
                                    <div class="tr-kpi">
                                        <div>
                                            <div class="tr-kpi-label"><?php _e('在线升级'); ?></div>
                                            <div class="tr-help tr-mt-8"><?php _e('上传升级包并自动覆盖程序文件'); ?></div>
                                        </div>
                                        <div class="tr-kpi-icon" aria-hidden="true">
                                            <svg class="tr-ico"><use href="<?php echo htmlspecialchars($options->adminStaticUrl('img', 'icons.svg', true), ENT_QUOTES, 'UTF-8'); ?>#i-upload"></use></svg>
                                        </div>
                                    </div>

                                    <form action="<?php echo $packageActionUrl; ?>" method="post" enctype="multipart/form-data" class="tr-mt-12">
                                        <input type="hidden" name="do" value="upload">
                                        <div class="tr-dropzone">
                                            <input id="upgrade-upload-file" name="package" type="file" class="file tr-dropzone-input" accept=".zip,application/zip" required>
                                            <div class="tr-dropzone-inner">
                                                <strong class="tr-dropzone-title"><?php _e('点击选择升级包'); ?></strong>
                                                <p class="tr-dropzone-desc"><?php _e('支持拖拽到此区域'); ?></p>
                                            </div>
                                        </div>
                                        <div class="tr-help tr-mt-12">
                                            <label>
                                                <input type="checkbox" name="allowInstall" value="1">
                                                <?php _e('允许覆盖 install（谨慎操作）'); ?>
                                            </label>
                                        </div>
                                        <div class="tr-mt-12">
                                            <button class="tr-btn primary tr-block" type="submit"><?php _e('上传升级包'); ?></button>
                                        </div>
                                    </form>

                                    <div class="tr-help tr-mt-12"><?php _e('当前版本 %s', \Typecho\Common::VERSION); ?></div>

                                    <?php if ($packageId !== ''): ?>
                                        <div class="tr-card tr-tone-muted tr-mt-16">
                                            <div class="tr-card-b">
                                                <div class="tr-subtitle"><?php _e('升级包信息'); ?></div>
                                                <ul class="tr-help">
                                                    <li><?php _e('标识：%s', htmlspecialchars($packageId, ENT_QUOTES, 'UTF-8')); ?></li>
                                                    <?php if ($mode !== ''): ?>
                                                        <li><?php _e('模式：%s', $mode === 'full' ? _t('整包') : _t('补丁')); ?></li>
                                                    <?php endif; ?>
                                                    <li><?php _e('覆盖 install：%s', $allowInstall ? _t('允许') : _t('不允许')); ?></li>
                                                    <li><?php _e('版本：%s → %s', htmlspecialchars($fromVersion, ENT_QUOTES, 'UTF-8'), htmlspecialchars($toVersion, ENT_QUOTES, 'UTF-8')); ?></li>
                                                    <li><?php _e('文件数量：%s', $filesCount); ?></li>
                                                    <?php if ($build !== ''): ?>
                                                        <li><?php _e('构建：%s', htmlspecialchars($build, ENT_QUOTES, 'UTF-8')); ?></li>
                                                    <?php endif; ?>
                                                    <?php if ($error !== ''): ?>
                                                        <li><?php _e('最近错误：%s', htmlspecialchars($error, ENT_QUOTES, 'UTF-8')); ?></li>
                                                    <?php endif; ?>
                                                    <?php if (is_array($progress) && isset($progress['done'], $progress['total']) && (int) $progress['total'] > 0): ?>
                                                        <li><?php _e('进度：%d / %d', (int) $progress['done'], (int) $progress['total']); ?></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>

                                        <?php if ($status === 'ready'): ?>
                                            <form action="<?php echo $packageActionUrl; ?>" method="post" class="tr-mt-12">
                                                <input type="hidden" name="do" value="apply">
                                                <input type="hidden" name="package" value="<?php echo htmlspecialchars($packageId, ENT_QUOTES, 'UTF-8'); ?>">
                                                <button class="tr-btn primary tr-block" type="submit"><?php _e('执行在线升级'); ?></button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="tr-card tr-tone-muted">
                                <div class="tr-card-b">
                                    <div class="tr-kpi">
                                        <div>
                                            <div class="tr-kpi-label"><?php _e('数据库升级'); ?></div>
                                            <div class="tr-help tr-mt-8"><?php _e('程序文件升级后，请执行数据库迁移'); ?></div>
                                        </div>
                                        <div class="tr-kpi-icon" aria-hidden="true">
                                            <svg class="tr-ico"><use href="<?php echo htmlspecialchars($options->adminStaticUrl('img', 'icons.svg', true), ENT_QUOTES, 'UTF-8'); ?>#i-database"></use></svg>
                                        </div>
                                    </div>

                                    <div class="tr-help tr-mt-12"><?php _e('数据库版本 %s', $options->version); ?></div>

                                    <?php if ($needDbUpgrade): ?>
                                        <div class="tr-help tr-tone-warning tr-mt-12">
                                            <strong><?php _e('检测到版本差异：%s → %s', $options->version, \Typecho\Common::VERSION); ?></strong>
                                        </div>
                                        <form action="<?php echo $dbUpgradeUrl; ?>" method="post" class="tr-mt-12">
                                            <button class="tr-btn primary tr-block" type="submit"><?php _e('完成数据库升级'); ?></button>
                                        </form>
                                        <div class="tr-help tr-mt-12">
                                            <?php _e('数据库升级会执行结构迁移，建议在低峰期操作并保持页面不刷新，直到完成。'); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="tr-help tr-mt-12"><?php _e('数据库结构已是最新状态'); ?></div>
                                        <div class="tr-help tr-mt-12">
                                            <?php _e('如果刚完成在线升级但这里仍显示无需升级，请刷新页面确认程序版本是否已更新。'); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                    </div>

                    <div class="tr-card tr-tone-muted">
                        <div class="tr-card-b">
                            <div class="tr-subtitle tr-mt-16"><?php _e('升级流程'); ?></div>
                            <ol class="tr-flow tr-mt-12" aria-label="<?php _e('升级流程'); ?>">
                                <li class="tr-flow-item">
                                    <div class="tr-flow-dot"><span>1</span></div>
                                    <div class="tr-flow-title"><?php _e('准备'); ?></div>
                                    <div class="tr-flow-desc"><?php _e('升级前请完整备份程序数据，避免升级失败导致数据丢失或无法恢复'); ?></div>
                                </li>
                                <li class="tr-flow-item">
                                    <div class="tr-flow-dot"><span>2</span></div>
                                    <div class="tr-flow-title"><?php _e('上传'); ?></div>
                                    <div class="tr-flow-desc"><?php _e('上传官方 zip 格式升级包，非官方包可能导致升级失败或数据风险'); ?></div>
                                </li>
                                <li class="tr-flow-item">
                                    <div class="tr-flow-dot"><span>3</span></div>
                                    <div class="tr-flow-title"><?php _e('覆盖策略'); ?></div>
                                    <div class="tr-flow-desc"><?php _e('默认不覆盖 install 目录及 install.php 文件；仅在确有必要时，勾选「允许覆盖 install（谨慎操作）」选项'); ?></div>
                                </li>
                                <li class="tr-flow-item">
                                    <div class="tr-flow-dot"><span>4</span></div>
                                    <div class="tr-flow-title"><?php _e('执行'); ?></div>
                                    <div class="tr-flow-desc"><?php _e('触发升级后，系统会逐个覆盖文件，执行中会展示进度与失败原因'); ?></div>
                                </li>
                                <li class="tr-flow-item">
                                    <div class="tr-flow-dot"><span>5</span></div>
                                    <div class="tr-flow-title"><?php _e('收尾'); ?></div>
                                    <div class="tr-flow-desc"><?php _e('升级成功后，系统自动清理升级包及临时目录；请刷新页面确认版本更新，必要时手动执行数据库升级操作'); ?></div>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
include 'copyright.php';
include 'common-js.php';
?>
<script>
    (function () {
        if (window.sessionStorage) {
            sessionStorage.removeItem('update');
        }
    })();
</script>
<?php include 'footer.php'; ?>
