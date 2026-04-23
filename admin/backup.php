<?php
include 'common.php';
include 'header.php';
include 'menu.php';

$actionUrl = $security->getTokenUrl(
    \Typecho\Router::url('do', array('action' => 'backup', 'widget' => 'Backup'),
        \Typecho\Common::url('index.php', $options->rootUrl)), $request->getRequestUrl());

$backupFiles = \Widget\Backup::alloc()->listFiles();
$reportLines = \Widget\Backup::consumeReport();
if (!is_array($reportLines)) {
    $noticeMessages = json_decode((string) \Typecho\Cookie::get('__typecho_notice', '[]'), true);
    $noticeType = (string) \Typecho\Cookie::get('__typecho_notice_type', '');
    $reportLines = ['blocking' => [], 'warning' => [], 'info' => []];
    if (is_array($noticeMessages) && !empty($noticeMessages) && in_array($noticeType, ['success', 'error', 'notice'], true)) {
        foreach ($noticeMessages as $line) {
            $line = trim((string) $line);
            if ('' === $line) {
                continue;
            }

            if (strpos($line, '阻断：') === 0) {
                $reportLines['blocking'][] = trim(substr($line, strlen('阻断：')));
            } elseif (strpos($line, '预警：') === 0) {
                $reportLines['warning'][] = trim(substr($line, strlen('预警：')));
            } else {
                $reportLines['info'][] = $line;
            }
        }
    }
}
?>

<main class="main">
    <div class="body container">
        <div class="tr-card">
            <div class="tr-card-b">
                <div class="tr-section-head">
                    <div class="tr-section-title"><?php _e('数据备份与恢复'); ?></div>
                </div>
                <?php if (!empty($reportLines['blocking']) || !empty($reportLines['warning']) || !empty($reportLines['info'])): ?>
                <div class="tr-card tr-tone-muted tr-mt-16">
                    <div class="tr-card-b">
                        <div class="tr-subtitle"><?php _e('恢复报告'); ?></div>
                        <?php if (!empty($reportLines['blocking'])): ?>
                            <div class="tr-help tr-tone-warning tr-mt-8"><strong><?php _e('阻断项'); ?></strong></div>
                            <ul class="tr-help">
                                <?php foreach ($reportLines['blocking'] as $line): ?>
                                    <li><?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!empty($reportLines['warning'])): ?>
                            <div class="tr-help tr-tone-warning tr-mt-8"><strong><?php _e('预警项'); ?></strong></div>
                            <ul class="tr-help">
                                <?php foreach ($reportLines['warning'] as $line): ?>
                                    <li><?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!empty($reportLines['info'])): ?>
                            <div class="tr-help tr-mt-8"><strong><?php _e('报告摘要'); ?></strong></div>
                            <ul class="tr-help">
                                <?php foreach ($reportLines['info'] as $line): ?>
                                    <li><?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="tr-grid cols-2 tr-mt-16">
                    <div class="tr-card tr-tone-muted">
                        <div class="tr-card-b">
                            <div class="tr-kpi">
                                <div>
                                    <div class="tr-kpi-label"><?php _e('备份您的数据'); ?></div>
                                </div>
                                <div class="tr-kpi-icon" aria-hidden="true">
                                    <svg class="tr-ico"><use href="<?php echo htmlspecialchars($options->adminStaticUrl('img', 'icons.svg', true), ENT_QUOTES, 'UTF-8'); ?>#i-download"></use></svg>
                                </div>
                            </div>
                            
                            <div class="tr-stack tr-gap-8 tr-mt-12">
                                <div class="tr-stack tr-gap-4">
                                    <div class="tr-between tr-gap-8">
                                        <span class="tr-subtitle"><?php _e('备份说明'); ?></span>
                                    </div>
                                    <div class="tr-help">
                                        <?php _e('本备份功能 <strong>仅备份内容数据</strong>，不包含程序相关的 <strong>设置信息</strong>'); ?>
                                    </div>
                                    <div class="tr-help">
                                        <?php _e('如果您的数据量较大，直接使用面板备份可能会因执行时间过长而导致 <strong>操作超时</strong>'); ?>
                                    </div>
                                    <div class="tr-help">
                                        <?php _e('这种情况下 <strong>建议您使用数据库自带的官方备份工具</strong> 进行数据导出与备份'); ?>
                                    </div>
                                    <div class="tr-help tr-tone-warning">
                                        <strong><?php _e('另外，为了减小备份文件体积、提升备份速度，您可以在执行备份前，清理并删除不必要的数据'); ?></strong>
                                    </div>
                                </div>
                                
                                <form action="<?php echo $actionUrl; ?>" method="post" class="tr-mt-12">
                                    <button class="tr-btn primary tr-block" type="submit">
                                        <span><?php _e('开始备份'); ?></span>
                                    </button>
                                    <input type="hidden" name="do" value="export">
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tr-card tr-tone-muted" id="backup-secondary">
                        <div class="tr-card-b">
                            <div class="tr-kpi">
                                <div>
                                    <div class="tr-kpi-label"><?php _e('恢复数据'); ?></div>
                                </div>
                            </div>
                            
                            <ul class="typecho-option-tabs" role="tablist">
                                <li class="active"><a href="#from-upload" data-tab-target="from-upload" role="tab" aria-selected="true"><?php _e('上传文件'); ?></a></li>
                                <li><a href="#from-server" data-tab-target="from-server" role="tab" aria-selected="false"><?php _e('从服务器'); ?></a></li>
                            </ul>
                            
                            <div id="from-upload" class="tab-content" role="tabpanel">
                                <form action="<?php echo $actionUrl; ?>" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="do" value="import" data-do-field="1">
                                    <div class="tr-dropzone">
                                        <input id="backup-upload-file" name="file" type="file" class="file tr-dropzone-input">
                                        <div class="tr-dropzone-inner">
                                            <strong class="tr-dropzone-title"><?php _e('点击选择备份文件'); ?></strong>
                                            <p class="tr-dropzone-desc"><?php _e('支持拖拽到此区域'); ?></p>
                                        </div>
                                    </div>
                                    <div class="tr-stack tr-gap-8 tr-mt-12">
                                        <div class="tr-grid cols-2">
                                            <div class="tr-stack tr-gap-4">
                                                <label class="tr-label" for="upload-repair"><?php _e('迁移后修复'); ?></label>
                                                <select id="upload-repair" name="repair" class="tr-select">
                                                    <option value="1"><?php _e('自动修复评论与计数异常'); ?></option>
                                                    <option value="0"><?php _e('仅导入，不做修复'); ?></option>
                                                </select>
                                            </div>
                                            <div class="tr-stack tr-gap-4">
                                                <label class="tr-label" for="upload-snapshot"><?php _e('恢复前快照'); ?></label>
                                                <select id="upload-snapshot" name="snapshot" class="tr-select">
                                                    <option value="1"><?php _e('自动创建快照'); ?></option>
                                                    <option value="0"><?php _e('不创建快照'); ?></option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tr-grid cols-2 tr-mt-12">
                                        <button type="submit" class="tr-btn primary tr-block" data-submit="import">
                                            <span><?php _e('上传恢复'); ?></span>
                                        </button>
                                        <button type="submit" class="tr-btn primary tr-block" data-submit="check">
                                            <span><?php _e('仅预检'); ?></span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <div id="from-server" class="tab-content" role="tabpanel" hidden>
                                <form action="<?php echo $actionUrl; ?>" method="post">
                                    <input type="hidden" name="do" value="import" data-do-field="1">
                                    <?php if (empty($backupFiles)): ?>
                                    <div class="tr-empty tr-mt-12">
                                        <p><?php _e('将备份文件手动上传至服务器的 %s 目录后, 本页面会自动显示该备份文件选项', __TYPECHO_BACKUP_DIR__); ?></p>
                                    </div>
                                    <?php else: ?>
                                    <div class="tr-stack tr-gap-8">
                                        <label class="tr-label" for="backup-select-file"><?php _e('选择一个备份文件恢复数据'); ?></label>
                                        <select name="file" id="backup-select-file" class="tr-select">
                                            <?php foreach ($backupFiles as $file): ?>
                                                <option value="<?php echo $file; ?>"><?php echo $file; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="tr-stack tr-gap-8 tr-mt-12">
                                        <div class="tr-grid cols-2">
                                            <div class="tr-stack tr-gap-4">
                                                <label class="tr-label" for="server-repair"><?php _e('迁移后修复'); ?></label>
                                                <select id="server-repair" name="repair" class="tr-select">
                                                    <option value="1"><?php _e('自动修复评论与计数异常'); ?></option>
                                                    <option value="0"><?php _e('仅导入，不做修复'); ?></option>
                                                </select>
                                            </div>
                                            <div class="tr-stack tr-gap-4">
                                                <label class="tr-label" for="server-snapshot"><?php _e('恢复前快照'); ?></label>
                                                <select id="server-snapshot" name="snapshot" class="tr-select">
                                                    <option value="1"><?php _e('自动创建快照'); ?></option>
                                                    <option value="0"><?php _e('不创建快照'); ?></option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="tr-grid cols-2 tr-mt-12">
                                        <button type="submit" class="tr-btn primary tr-block" data-submit="import">
                                            <span><?php _e('选择恢复'); ?></span>
                                        </button>
                                        <button type="submit" class="tr-btn primary tr-block" data-submit="check">
                                            <span><?php _e('仅预检'); ?></span>
                                        </button>
                                    </div>
                                </form>
                            </div>
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
include 'form-js.php';
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('#backup-secondary .typecho-option-tabs a[data-tab-target]');
        const tabPanels = document.querySelectorAll('#backup-secondary .tab-content');

        tabs.forEach((tab) => {
            tab.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('data-tab-target');

                tabs.forEach((t) => {
                    t.setAttribute('aria-selected', 'false');
                    const li = t.closest('li');
                    if (li) {
                        li.classList.remove('active');
                    }
                });

                tabPanels.forEach((panel) => {
                    panel.setAttribute('hidden', '');
                });

                this.setAttribute('aria-selected', 'true');
                const currentLi = this.closest('li');
                if (currentLi) {
                    currentLi.classList.add('active');
                }

                const targetPanel = targetId ? document.getElementById(targetId) : null;
                if (targetPanel) {
                    targetPanel.removeAttribute('hidden');
                }
            });
        });

        const forms = document.querySelectorAll('#backup-secondary form');
        forms.forEach(form => {
            const hiddenDo = form.querySelector('input[data-do-field]');
            const submitButtons = form.querySelectorAll('button[data-submit]');
            submitButtons.forEach((button) => {
                button.addEventListener('click', function () {
                    if (hiddenDo) {
                        hiddenDo.value = this.getAttribute('data-submit') || 'import';
                    }
                });
            });

            form.addEventListener('submit', function(e) {
                const submitter = e.submitter;
                const action = submitter ? submitter.getAttribute('data-submit') : 'import';
                if (hiddenDo) {
                    hiddenDo.value = action || 'import';
                }
                if (action !== 'import') {
                    return;
                }

                if (!confirm('<?php _e('恢复操作将清除所有现有数据, 是否继续?'); ?>')) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    });
</script>
<?php include 'footer.php'; ?>
