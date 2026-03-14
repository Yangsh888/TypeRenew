<?php if(!defined('__TYPECHO_ADMIN__')) exit; ?>
<?php \Typecho\Plugin::factory('admin/footer.php')->call('begin'); ?>
    <?php if (!empty($trAdminEnabled)): ?>
        <div class="tr-overlay"></div>
        <div class="tr-cmd" id="trCmd" aria-hidden="true">
            <div class="tr-cmd-overlay" id="trCmdOverlay"></div>
            <div class="tr-cmd-dialog" role="dialog" aria-modal="true" aria-label="<?php _e('快捷命令'); ?>">
                <div class="tr-cmd-head">
                    <input class="tr-cmd-input" id="trCmdInput" type="text" autocomplete="off" placeholder="<?php _e('输入以搜索或执行命令'); ?>" />
                    <div class="tr-cmd-kbd">
                        <span class="tr-kbd">Esc</span>
                    </div>
                </div>
                <div class="tr-cmd-body">
                    <div class="tr-cmd-hint" id="trCmdHint"></div>
                    <div class="tr-cmd-list" id="trCmdList" role="listbox" aria-label="<?php _e('结果'); ?>"></div>
                </div>
            </div>
        </div>
        <?php \Typecho\Palette::outputConfig(); ?>
        <?php \Typecho\Plugin::factory('admin/footer.php')->call('palette'); ?>
        <script src="<?php $options->adminStaticUrl('js', 'renew-ui.js'); ?>"></script>
        <script src="<?php $options->adminStaticUrl('js', 'tr-theme.js'); ?>"></script>
        <script src="<?php $options->adminStaticUrl('js', 'tr-palette.js'); ?>"></script>
    <?php endif; ?>
    </body>
</html>
<?php
\Typecho\Plugin::factory('admin/footer.php')->call('end');
