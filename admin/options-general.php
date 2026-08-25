<?php
include 'common.php';
include 'header.php';
include 'menu.php';

$loginGuard = \Utils\LoginGuard::panel(\Typecho\Db::get());
?>

<main class="main">
    <div class="body container">
        <div class="row typecho-page-main">
            <div class="col-mb-12 col-tb-8 col-tb-offset-2 tr-panel">
                <?php include 'options-tabs.php'; ?>
                <div class="tr-settings-body">
                <?php \Widget\Options\General::alloc()->form()->render(); ?>
                </div>
            </div>
        </div>

        <div class="row typecho-page-main tr-mt-16">
            <div class="col-mb-12 col-tb-8 col-tb-offset-2">
                <div class="tr-card">
                    <div class="tr-card-b tr-cache-actions">
                        <div>
                            <div class="tr-section-title"><?php _e('登录保护'); ?></div>
                            <?php if (!$loginGuard['available']): ?>
                                <div class="tr-help"><?php _e('登录尝试记录表不可用，限流已自动放行，请在升级页执行结构修复'); ?></div>
                            <?php else: ?>
                                <div class="tr-help">
                                    <?php _e('生效中的锁定 %d 条，跟踪中的记录 %d 条', (int) $loginGuard['locked'], (int) $loginGuard['tracked']); ?>
                                </div>
                                <?php if ((int) $loginGuard['until'] > 0): ?>
                                    <div class="tr-help">
                                        <?php _e('最晚解除时间 %s', htmlspecialchars((new \Typecho\Date((int) $loginGuard['until']))->format('Y-m-d H:i'), ENT_QUOTES, 'UTF-8')); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="tr-help"><?php _e('登录与密码找回按 IP 以及 IP + 账号两个维度限流，IP 和账号只保存摘要，不保存明文'); ?></div>
                            <?php endif; ?>
                        </div>
                        <form action="<?php echo htmlspecialchars($security->getIndex('/action/options-general'), ENT_QUOTES, 'UTF-8'); ?>" method="post">
                            <input type="hidden" name="do" value="releaseLoginLocks">
                            <button class="tr-btn tr-btn-warn" type="submit"><?php _e('解除全部锁定'); ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
include 'copyright.php';
include 'common-js.php';
include 'form-js.php';
?>
<script>
(function () {
    'use strict';
    function bindCustom(selectName, inputName, customValue) {
        var select = document.querySelector('select[name=' + selectName + ']');
        var input = document.querySelector('input[name=' + inputName + ']');
        if (!select || !input) return;

        var option = input.closest('.typecho-option');
        if (!option) return;

        function toggle() {
            option.style.display = select.value === customValue ? '' : 'none';
        }

        toggle();
        select.addEventListener('change', toggle);
    }

    bindCustom('ipSource', 'ipSourceCustom', 'custom');
    bindCustom('githubRawMirror', 'githubRawMirrorCustom', '_');
})();
</script>
<?php
include 'footer.php';
?>
