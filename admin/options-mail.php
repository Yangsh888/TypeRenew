<?php
include 'common.php';
include 'header.php';
include 'menu.php';

$mailWidget = \Widget\Options\Mail::alloc();
$panel = $mailWidget->panel();
$pending = (int) ($panel['pending'] ?? 0);
$failed = (int) ($panel['failed'] ?? 0);
$dead = (int) ($panel['dead'] ?? 0);
$sent = (int) ($panel['sent'] ?? 0);
$lastFail = $panel['lastFail'] ?? null;
$recentFails = is_array($panel['recentFails'] ?? null) ? $panel['recentFails'] : [];
$runtimeError = trim((string) ($panel['runtimeError'] ?? ''));
$runtimeErrorAt = (int) ($panel['runtimeErrorAt'] ?? 0);

$cronEndpoint = \Typecho\Common::url('/action/mail?do=deliver', (string) $options->index);
$tplName = isset($_GET['tpl']) ? preg_replace('/[^a-z0-9_]/i', '', (string) $_GET['tpl']) : 'owner';
if (!in_array($tplName, ['owner', 'guest', 'notice', 'reset'], true)) {
    $tplName = 'owner';
}
$tplContent = \Typecho\Mail\Template::load($tplName, $options);
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
                                    <div class="tr-kpi-label"><?php _e('待投递'); ?></div>
                                    <div class="tr-kpi-value"><?php echo $pending; ?></div>
                                </div>
                                <div class="tr-kpi-icon tr-tone-blue" aria-hidden="true">
                                    <svg class="tr-ico"><use href="<?php echo htmlspecialchars($options->adminStaticUrl('img', 'icons.svg', true), ENT_QUOTES, 'UTF-8'); ?>#i-list"></use></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tr-card">
                        <div class="tr-card-b">
                            <div class="tr-kpi">
                                <div>
                                    <div class="tr-kpi-label"><?php _e('失败'); ?></div>
                                    <div class="tr-kpi-value"><?php echo $failed; ?></div>
                                </div>
                                <div class="tr-kpi-icon tr-tone-ink" aria-hidden="true">
                                    <svg class="tr-ico"><use href="<?php echo htmlspecialchars($options->adminStaticUrl('img', 'icons.svg', true), ENT_QUOTES, 'UTF-8'); ?>#i-alert"></use></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tr-card">
                        <div class="tr-card-b">
                            <div class="tr-kpi">
                                <div>
                                    <div class="tr-kpi-label"><?php _e('已放弃'); ?></div>
                                    <div class="tr-kpi-value"><?php echo $dead; ?></div>
                                </div>
                                <div class="tr-kpi-icon tr-tone-ink" aria-hidden="true">
                                    <svg class="tr-ico"><use href="<?php echo htmlspecialchars($options->adminStaticUrl('img', 'icons.svg', true), ENT_QUOTES, 'UTF-8'); ?>#i-x"></use></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tr-card">
                        <div class="tr-card-b">
                            <div class="tr-kpi">
                                <div>
                                    <div class="tr-kpi-label"><?php _e('已发送'); ?></div>
                                    <div class="tr-kpi-value"><?php echo $sent; ?></div>
                                </div>
                                <div class="tr-kpi-icon" aria-hidden="true">
                                    <svg class="tr-ico"><use href="<?php echo htmlspecialchars($options->adminStaticUrl('img', 'icons.svg', true), ENT_QUOTES, 'UTF-8'); ?>#i-mail"></use></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tr-grid cols-2 tr-mt-16">
                    <div class="tr-card">
                        <div class="tr-card-b">
                            <div class="tr-mail-head">
                                <div class="tr-minw-0">
                                    <div class="tr-section-title"><?php _e('队列操作'); ?></div>
                                    <div class="tr-help"><?php _e('手动投递、清理与重试队列任务'); ?></div>
                                </div>
                            </div>
                            <div class="tr-mail-actions tr-mt-12">
                                <form action="<?php echo htmlspecialchars($security->getIndex('/action/options-mail'), ENT_QUOTES, 'UTF-8'); ?>" method="post">
                                    <input type="hidden" name="do" value="deliver">
                                    <button class="tr-btn tr-btn-primary tr-mail-btn" type="submit"><?php _e('立即投递'); ?></button>
                                </form>
                                <form action="<?php echo htmlspecialchars($security->getIndex('/action/options-mail'), ENT_QUOTES, 'UTF-8'); ?>" method="post">
                                    <input type="hidden" name="do" value="cleanup">
                                    <button class="tr-btn tr-btn-warn tr-mail-btn" type="submit"><?php _e('清理已发送'); ?></button>
                                </form>
                                <form action="<?php echo htmlspecialchars($security->getIndex('/action/options-mail'), ENT_QUOTES, 'UTF-8'); ?>" method="post">
                                    <input type="hidden" name="do" value="retry_failed">
                                    <button class="tr-btn tr-mail-btn" type="submit"><?php _e('重试失败'); ?></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="tr-card">
                        <div class="tr-card-b">
                            <div class="tr-mail-head">
                                <div class="tr-minw-0">
                                    <div class="tr-section-title"><?php _e('测试发信'); ?></div>
                                    <div class="tr-help"><?php _e('使用当前模板与变量渲染后发送测试邮件'); ?></div>
                                </div>
                            </div>
                            <form action="<?php echo htmlspecialchars($security->getIndex('/action/options-mail'), ENT_QUOTES, 'UTF-8'); ?>" method="post" class="tr-mail-row">
                                <input type="hidden" name="do" value="test">
                                <input type="hidden" name="tpl" value="<?php echo htmlspecialchars($tplName, ENT_QUOTES, 'UTF-8'); ?>">
                                <input class="text w-100 mono" type="email" name="testTo" placeholder="<?php _e('收件邮箱'); ?>">
                                <button class="tr-btn tr-btn-primary tr-mail-btn" type="submit"><?php _e('发送模板测试'); ?></button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="tr-card tr-mt-16">
                    <div class="tr-card-b">
                        <div class="tr-mail-head">
                            <div class="tr-minw-0">
                                <div class="tr-section-title"><?php _e('定时投递'); ?></div>
                                <div class="tr-help"><?php _e('当投递模式选择“定时投递”时，请使用 POST 请求并携带签名'); ?></div>
                            </div>
                            <form action="<?php echo htmlspecialchars($security->getIndex('/action/options-mail'), ENT_QUOTES, 'UTF-8'); ?>" method="post">
                                <input type="hidden" name="do" value="regen_key">
                                <button class="tr-btn tr-mail-btn" type="submit"><?php _e('重置投递密钥'); ?></button>
                            </form>
                        </div>
                        <div class="tr-mail-sub">
                            <div class="tr-mail-code">
                                <div class="tr-mail-code-label"><?php _e('投递地址'); ?></div>
                                <input class="text w-100 mono tr-mail-code-input" readonly value="<?php echo htmlspecialchars($cronEndpoint, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="tr-mail-code tr-mt-8">
                                <div class="tr-mail-code-label"><?php _e('签名规则'); ?></div>
                                <input class="text w-100 mono tr-mail-code-input" readonly value='sign = sha256_hmac("{ts}|deliver", 投递密钥)'>
                            </div>
                            <div class="tr-mail-code tr-mt-8">
                                <div class="tr-mail-code-label"><?php _e('请求头'); ?></div>
                                <input class="text w-100 mono tr-mail-code-input" readonly value="X-Typecho-Mail-Ts={ts}, X-Typecho-Mail-Sign={sign}">
                            </div>
                            <div class="tr-help tr-mt-8"><?php _e('建议仅使用签名头方式调用，避免密钥明文透出'); ?></div>
                        </div>
                    </div>
                </div>

                <?php if (is_array($lastFail) && !empty($lastFail['lastError'])): ?>
                    <div class="tr-card tr-mt-16">
                        <div class="tr-card-b">
                            <div class="tr-section-title"><?php _e('最近失败'); ?></div>
                            <div class="tr-help"><?php echo htmlspecialchars((string) $lastFail['lastError'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($runtimeError !== ''): ?>
                    <div class="tr-card tr-mt-16">
                        <div class="tr-card-b">
                            <div class="tr-section-title"><?php _e('运行告警'); ?></div>
                            <div class="tr-help"><?php echo htmlspecialchars($runtimeError, ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php if ($runtimeErrorAt > 0): ?>
                                <div class="tr-help"><?php echo date('Y-m-d H:i:s', $runtimeErrorAt); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($recentFails)): ?>
                    <div class="tr-card tr-mt-16">
                        <div class="tr-card-b">
                            <div class="tr-section-title"><?php _e('失败任务列表'); ?></div>
                            <div class="tr-help"><?php _e('展示最近失败或已放弃的任务'); ?></div>
                            <table class="typecho-list-table striped" style="margin-top:10px;">
                                <thead>
                                <tr>
                                    <th><?php _e('ID'); ?></th>
                                    <th><?php _e('类型'); ?></th>
                                    <th><?php _e('状态'); ?></th>
                                    <th><?php _e('次数'); ?></th>
                                    <th><?php _e('错误'); ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recentFails as $row): ?>
                                    <tr>
                                        <td><?php echo (int) ($row['id'] ?? 0); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($row['type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int) ($row['attempts'] ?? 0); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($row['lastError'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="tr-card tr-mt-16">
                    <div class="tr-card-b">
                        <div class="tr-section-title"><?php _e('邮件模板'); ?></div>
                        <div class="tr-help"><?php _e('保存后会写入当前主题 mail 目录，优先级高于系统默认模板'); ?></div>
                        <div class="tr-help"><?php _e('变量示例：{siteTitle}、{title}、{author}、{commentTextPlain}、{commentHtml}、{permalink}、{manageurl}、{unsubUrl}、{resetUrl}、{expiresAt}'); ?></div>
                        <div class="tr-help"><?php _e('默认自动转义，使用 {raw:变量名} 可输出原始值'); ?></div>
                        <div class="tr-mt-12">
                            <div class="tr-tabs-wrap">
                                <ul class="tr-settings-tabs">
                                    <li<?php if ($tplName === 'owner'): ?> class="current"<?php endif; ?>><a href="<?php $options->adminUrl('options-mail.php?tpl=owner'); ?>"><?php _e('作者'); ?></a></li>
                                    <li<?php if ($tplName === 'guest'): ?> class="current"<?php endif; ?>><a href="<?php $options->adminUrl('options-mail.php?tpl=guest'); ?>"><?php _e('访客'); ?></a></li>
                                    <li<?php if ($tplName === 'notice'): ?> class="current"<?php endif; ?>><a href="<?php $options->adminUrl('options-mail.php?tpl=notice'); ?>"><?php _e('待审'); ?></a></li>
                                    <li<?php if ($tplName === 'reset'): ?> class="current"<?php endif; ?>><a href="<?php $options->adminUrl('options-mail.php?tpl=reset'); ?>"><?php _e('重置'); ?></a></li>
                                </ul>
                            </div>
                            <form id="tpl-save-form" action="<?php echo htmlspecialchars($security->getIndex('/action/options-mail'), ENT_QUOTES, 'UTF-8'); ?>" method="post" class="tr-mt-12">
                                <input type="hidden" name="do" value="tpl_save">
                                <input type="hidden" name="tpl" value="<?php echo htmlspecialchars($tplName, ENT_QUOTES, 'UTF-8'); ?>">
                                <textarea class="text w-100 mono" name="content" rows="14"><?php echo htmlspecialchars($tplContent, ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </form>
                            <div class="tr-tpl-btns tr-mt-12">
                                <button class="tr-btn tr-btn-primary" type="submit" form="tpl-save-form"><?php _e('保存模板'); ?></button>
                                <form action="<?php echo htmlspecialchars($security->getIndex('/action/options-mail'), ENT_QUOTES, 'UTF-8'); ?>" method="post" target="_blank">
                                    <input type="hidden" name="do" value="tpl_preview">
                                    <input type="hidden" name="tpl" value="<?php echo htmlspecialchars($tplName, ENT_QUOTES, 'UTF-8'); ?>">
                                    <button class="tr-btn" type="submit"><?php _e('预览模板'); ?></button>
                                </form>
                                <form action="<?php echo htmlspecialchars($security->getIndex('/action/options-mail'), ENT_QUOTES, 'UTF-8'); ?>" method="post">
                                    <input type="hidden" name="do" value="tpl_reset">
                                    <input type="hidden" name="tpl" value="<?php echo htmlspecialchars($tplName, ENT_QUOTES, 'UTF-8'); ?>">
                                    <button class="tr-btn tr-btn-warn" type="submit"><?php _e('重置模板'); ?></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row typecho-page-main tr-mt-16" role="form">
            <div class="col-mb-12 col-tb-8 col-tb-offset-2 tr-panel">
                <div class="tr-settings-body">
                    <?php $mailWidget->form()->render(); ?>
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
<script>
(function(){
    var passInput = document.querySelector('input[name="mailSmtpPass"]');
    var changedInput = document.getElementById('mailSmtpPassChanged');
    if (passInput && changedInput) {
        passInput.addEventListener('input', function(){
            changedInput.value = '1';
        });
        passInput.addEventListener('focus', function(){
            if (this.value === '********') {
                this.value = '';
            }
        });
    }
})();
</script>
