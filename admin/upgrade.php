<?php
include 'common.php';
include 'header.php';
include 'menu.php';

$dbUpgradeUrl = $security->getTokenUrl(
    \Typecho\Router::url('do', ['action' => 'upgrade', 'widget' => 'Upgrade'], \Typecho\Common::url('index.php', $options->rootUrl))
);
$packageActionUrl = $security->getTokenUrl(
    \Typecho\Router::url('do', ['action' => 'upgrade-package', 'widget' => 'Upgrade\\Package'], \Typecho\Common::url('index.php', $options->rootUrl))
);
$needDbUpgrade = version_compare(\Typecho\Common::VERSION, $options->version, '>');
$schemaStatus = \Utils\Migration\SchemaManager::inspectCriticalSchema(\Typecho\Db::get());
$needSchemaRepair = !$schemaStatus['healthy'];
$mysqlRiskStatus = \Utils\Migration\SchemaManager::inspectMysqlUpgradeRisks(\Typecho\Db::get());
$hasMysqlRisk = (bool) ($mysqlRiskStatus['supported'] ?? false) && !(bool) ($mysqlRiskStatus['healthy'] ?? true);
$upgradeReport = \Typecho\Upgrade\Runner::inspect();
$state = $upgradeReport['state'];
$upgradeBlocking = $upgradeReport['blocking'];
$upgradeWarning = $upgradeReport['warning'];
$upgradeItems = $upgradeReport['items'];
$upgradeAvailable = (bool) $upgradeReport['available'];
$upgradeRoot = (string) $upgradeReport['root'];
$upgradeLockBusy = (bool) $upgradeReport['lockBusy'];
$artifactCount = (int) ($upgradeReport['artifactCount'] ?? 0);

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
$hasArtifacts = $packageId !== '' || $artifactCount > 0;
$statusMap = [
    'ready' => _t('已就绪'),
    'applying' => _t('执行中'),
    'applied' => _t('已完成'),
    'failed' => _t('失败')
];
$statusLabel = $statusMap[$status] ?? ($status !== '' ? $status : _t('无'));
$packageActionLocked = !$upgradeAvailable || $upgradeLockBusy;
?>

<main class="main">
    <div class="body container">
        <div class="tr-card">
            <div class="tr-card-b">
                <div class="tr-section-head">
                    <div class="tr-section-title"><?php _e('升级程序'); ?></div>
                </div>

                <div class="tr-stack tr-mt-16">
                    <div class="tr-card tr-tone-muted">
                        <div class="tr-card-b">
                            <div class="tr-subtitle"><?php _e('环境预检'); ?></div>
                            <div class="tr-help tr-mt-8"><?php _e('升级工作目录：%s', htmlspecialchars($upgradeRoot, ENT_QUOTES, 'UTF-8')); ?></div>
                            <ul class="tr-help tr-mt-8">
                                <?php foreach ($upgradeItems as $item): ?>
                                    <li>
                                        <?php echo htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8'); ?>
                                        ：<?php echo htmlspecialchars((string) $item['status'], ENT_QUOTES, 'UTF-8'); ?>
                                        <span class="tr-help">
                                            （<?php echo htmlspecialchars((string) $item['path'], ENT_QUOTES, 'UTF-8'); ?>，
                                            <?php echo htmlspecialchars((string) $item['detail'], ENT_QUOTES, 'UTF-8'); ?>）
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <?php if (!empty($upgradeBlocking)): ?>
                                <div class="tr-help tr-tone-warning tr-mt-12">
                                    <strong><?php _e('当前环境暂时无法执行在线升级'); ?></strong>
                                </div>
                                <ul class="tr-help">
                                    <?php foreach ($upgradeBlocking as $line): ?>
                                        <li><?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="tr-help tr-mt-12">
                                    <?php if (defined('__TYPECHO_UPGRADE_DIR__')): ?>
                                        <?php _e('请为上述目录开放写权限后重试。'); ?>
                                    <?php else: ?>
                                        <?php _e('请为上述目录开放写权限；若不便调整当前路径，也可在 config.inc.php 中定义 __TYPECHO_UPGRADE_DIR__ 指向一个可写目录。'); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($upgradeWarning)): ?>
                                <div class="tr-help tr-tone-warning tr-mt-12">
                                    <strong><?php _e('环境提醒'); ?></strong>
                                </div>
                                <ul class="tr-help">
                                    <?php foreach ($upgradeWarning as $line): ?>
                                        <li><?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

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
                                            <input id="upgrade-upload-file" name="package" type="file" class="file tr-dropzone-input" accept=".zip,application/zip" required<?php echo $packageActionLocked ? ' disabled aria-disabled="true"' : ''; ?>>
                                            <div class="tr-dropzone-inner">
                                                <strong class="tr-dropzone-title"><?php _e('点击选择升级包'); ?></strong>
                                                <p class="tr-dropzone-desc"><?php _e('支持拖拽到此区域'); ?></p>
                                            </div>
                                        </div>
                                        <div class="tr-help tr-mt-12">
                                            <label>
                                                <input type="checkbox" name="allowInstall" value="1"<?php echo $packageActionLocked ? ' disabled aria-disabled="true"' : ''; ?>>
                                                <?php _e('允许覆盖 install（谨慎操作）'); ?>
                                            </label>
                                        </div>
                                        <div class="tr-mt-12">
                                            <button class="tr-btn primary tr-block" type="submit"<?php echo $packageActionLocked ? ' disabled aria-disabled="true"' : ''; ?>><?php _e('上传升级包'); ?></button>
                                        </div>
                                    </form>

                                    <div class="tr-help tr-mt-12"><?php _e('当前版本 %s', \Typecho\Common::VERSION); ?></div>
                                    <?php if ($packageActionLocked): ?>
                                        <div class="tr-help tr-tone-warning tr-mt-12">
                                            <?php _e('环境未通过预检前，在线升级入口已禁用。'); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($hasArtifacts): ?>
                                        <div class="tr-card tr-tone-muted tr-mt-16">
                                            <div class="tr-card-b">
                                                <div class="tr-subtitle"><?php _e('升级状态'); ?></div>
                                                <ul class="tr-help">
                                                    <?php if ($packageId !== ''): ?>
                                                        <li><?php _e('标识：%s', htmlspecialchars($packageId, ENT_QUOTES, 'UTF-8')); ?></li>
                                                    <?php endif; ?>
                                                    <li><?php _e('状态：%s', htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8')); ?></li>
                                                    <?php if ($mode !== ''): ?>
                                                        <li><?php _e('模式：%s', $mode === 'full' ? _t('整包') : _t('补丁')); ?></li>
                                                    <?php endif; ?>
                                                    <?php if ($packageId !== ''): ?>
                                                        <li><?php _e('覆盖 install：%s', $allowInstall ? _t('允许') : _t('不允许')); ?></li>
                                                        <li><?php _e('版本：%s → %s', htmlspecialchars($fromVersion, ENT_QUOTES, 'UTF-8'), htmlspecialchars($toVersion, ENT_QUOTES, 'UTF-8')); ?></li>
                                                        <li><?php _e('文件数量：%s', $filesCount); ?></li>
                                                    <?php endif; ?>
                                                    <?php if ($artifactCount > 0): ?>
                                                        <li><?php _e('残留工件：%d', $artifactCount); ?></li>
                                                    <?php endif; ?>
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

                                        <?php if ($status === 'ready' && $packageId !== ''): ?>
                                            <form action="<?php echo $packageActionUrl; ?>" method="post" class="tr-mt-12">
                                                <input type="hidden" name="do" value="apply">
                                                <input type="hidden" name="package" value="<?php echo htmlspecialchars($packageId, ENT_QUOTES, 'UTF-8'); ?>">
                                                <button class="tr-btn primary tr-block" type="submit"<?php echo $packageActionLocked ? ' disabled aria-disabled="true"' : ''; ?>><?php _e('执行在线升级'); ?></button>
                                            </form>
                                        <?php endif; ?>

                                        <form action="<?php echo $packageActionUrl; ?>" method="post" class="tr-mt-12">
                                            <input type="hidden" name="do" value="clear">
                                            <button class="tr-btn tr-block" type="submit"<?php echo $upgradeLockBusy ? ' disabled aria-disabled="true"' : ''; ?>><?php _e('清理升级包'); ?></button>
                                        </form>
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
                                    <div class="tr-help tr-mt-12"><?php _e('关键结构状态：%s', $needSchemaRepair ? _t('需修复') : _t('正常')); ?></div>
                                    <?php if (!empty($mysqlRiskStatus['supported'])): ?>
                                        <div class="tr-help tr-mt-12"><?php _e('MySQL 兼容预检：%s', $hasMysqlRisk ? _t('需关注') : _t('正常')); ?></div>
                                    <?php endif; ?>

                                    <div class="tr-card tr-tone-muted tr-mt-16">
                                        <div class="tr-card-b">
                                            <div class="tr-subtitle"><?php _e('关键表自检'); ?></div>
                                            <ul class="tr-help">
                                                <?php foreach ($schemaStatus['items'] as $item): ?>
                                                    <li>
                                                        <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                                                        ：<?php
                                                        if (!$item['exists']) {
                                                            echo _t('缺表');
                                                        } elseif (!empty($item['missingColumns'])) {
                                                            echo _t('缺字段');
                                                        } elseif (($item['status'] ?? '') === 'schema_mismatch') {
                                                            echo _t('结构不一致');
                                                        } else {
                                                            echo _t('正常');
                                                        }
                                                        ?>
                                                        <span class="tr-help">(<?php echo htmlspecialchars($item['table'], ENT_QUOTES, 'UTF-8'); ?>)</span>
                                                        <?php if (!empty($item['missingColumns'])): ?>
                                                            <span class="tr-help">[<?php echo htmlspecialchars(implode(', ', $item['missingColumns']), ENT_QUOTES, 'UTF-8'); ?>]</span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($item['missingIndexes'])): ?>
                                                            <span class="tr-help">[<?php _e('索引'); ?>: <?php echo htmlspecialchars(implode(', ', $item['missingIndexes']), ENT_QUOTES, 'UTF-8'); ?>]</span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($item['typeMismatches'])): ?>
                                                            <span class="tr-help">[<?php _e('类型'); ?>: <?php echo htmlspecialchars(implode(', ', $item['typeMismatches']), ENT_QUOTES, 'UTF-8'); ?>]</span>
                                                        <?php endif; ?>
                                                        <?php if (isset($item['collationOk']) && !$item['collationOk']): ?>
                                                            <span class="tr-help">[<?php _e('排序规则'); ?>: <?php echo htmlspecialchars((string) ($item['tableCollation'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>]</span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>

                                    <?php if (!empty($mysqlRiskStatus['supported'])): ?>
                                        <div class="tr-card tr-tone-muted tr-mt-16">
                                            <div class="tr-card-b">
                                                <div class="tr-subtitle"><?php _e('MySQL 升级预检查'); ?></div>
                                                <ul class="tr-help">
                                                    <?php foreach (($mysqlRiskStatus['items'] ?? []) as $item): ?>
                                                        <li>
                                                            <?php echo htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                                            ：<?php echo (($item['status'] ?? 'ok') === 'ok') ? _t('正常') : _t('需处理'); ?>
                                                            <?php if (!empty($item['detail'])): ?>
                                                                <span class="tr-help">(<?php echo htmlspecialchars((string) $item['detail'], ENT_QUOTES, 'UTF-8'); ?>)</span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($item['samples']) && is_array($item['samples'])): ?>
                                                                <span class="tr-help">
                                                                    [<?php
                                                                    $samples = [];
                                                                    foreach ($item['samples'] as $sample) {
                                                                        if (isset($sample['email'], $sample['scope'])) {
                                                                            $samples[] = (string) $sample['email'] . ' / ' . (string) $sample['scope'] . ' x' . (int) ($sample['count'] ?? 0);
                                                                        } elseif (isset($sample['mail'])) {
                                                                            $samples[] = (string) $sample['mail'] . ' x' . (int) ($sample['count'] ?? 0);
                                                                        }
                                                                    }
                                                                    echo htmlspecialchars(implode('；', $samples), ENT_QUOTES, 'UTF-8');
                                                                    ?>]
                                                                </span>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                                <?php if ($hasMysqlRisk): ?>
                                                    <div class="tr-help tr-tone-warning tr-mt-12">
                                                        <?php _e('建议先处理上述重复值或排序规则风险，再执行数据库升级/结构修复，以免唯一索引或排序规则转换失败。'); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

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
                                    <?php elseif ($needSchemaRepair): ?>
                                        <div class="tr-help tr-tone-warning tr-mt-12">
                                            <strong><?php _e('检测到关键结构异常，建议先执行数据库修复。'); ?></strong>
                                        </div>
                                        <form action="<?php echo $dbUpgradeUrl; ?>" method="post" class="tr-mt-12">
                                            <input type="hidden" name="do" value="repairCriticalSchema">
                                            <button class="tr-btn primary tr-block" type="submit"><?php _e('修复关键数据库结构'); ?></button>
                                        </form>
                                        <div class="tr-help tr-mt-12">
                                            <?php _e('该操作会补齐邮件通知与密码找回依赖的关键表、索引、字段类型和排序规则，不会覆盖已有业务数据。'); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="tr-help tr-mt-12"><?php _e('数据库结构已是最新状态'); ?></div>
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
                                    <div class="tr-flow-desc"><?php _e('触发升级后，系统会按顺序覆盖文件；执行完成后会在页面展示结果与最近一次失败原因'); ?></div>
                                </li>
                                <li class="tr-flow-item">
                                    <div class="tr-flow-dot"><span>5</span></div>
                                    <div class="tr-flow-title"><?php _e('收尾'); ?></div>
                                    <div class="tr-flow-desc"><?php _e('升级成功后请刷新页面确认版本；如提示仍有版本差异，再执行数据库升级'); ?></div>
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
include 'dropzone-js.php';
include 'common-js.php';
?>
<?php include 'footer.php'; ?>
