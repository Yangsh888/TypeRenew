<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$user->pass('administrator');
$settings = RenewGo_Plugin::getSettings();
$db = Typecho_Db::get();
$prefix = $db->getPrefix();
$limit = (int) ($settings['panelSize'] ?? 40);
$limit = max(10, min(200, $limit));
$logs = [];
$summary = (object) ['total' => 0, 'jump_success' => 0, 'jump_block' => 0];

try {
    $summaryTotal = $db->fetchObject($db->select(['COUNT(*)' => 'num'])->from($prefix . 'renew_go_logs'));
    $summarySuccess = $db->fetchObject($db->select(['COUNT(*)' => 'num'])
        ->from($prefix . 'renew_go_logs')
        ->where('action = ? AND result = ?', 'jump', 'success'));
    $summaryBlock = $db->fetchObject($db->select(['COUNT(*)' => 'num'])
        ->from($prefix . 'renew_go_logs')
        ->where('result = ?', 'rate-limit'));
    $summary = (object) [
        'total' => (int) ($summaryTotal->num ?? 0),
        'jump_success' => (int) ($summarySuccess->num ?? 0),
        'jump_block' => (int) ($summaryBlock->num ?? 0)
    ];
    $logs = $db->fetchAll($db->select()->from($prefix . 'renew_go_logs')
        ->order('id', Typecho_Db::SORT_DESC)->limit($limit));
} catch (Throwable $e) {
    $logs = [];
}

$icons = $options->adminStaticUrl('img', 'icons.svg', true);
$urlTest = RenewGo_Plugin::apiUrl('test');
$urlPurge = RenewGo_Plugin::apiUrl('purge');
$urlExport = RenewGo_Plugin::apiUrl('export');
$urlImport = RenewGo_Plugin::apiUrl('import');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(RenewGo_Plugin::assetUrl('assets/panel.css'), ENT_QUOTES, 'UTF-8'); ?>">
<section class="tr-card">
    <div class="tr-card-b">
        <div class="tr-grid cols-2">
            <div>
                <div class="tr-section-title"><?php _e('RenewGo 外链安全中心'); ?></div>
                <div class="tr-help"><?php _e('统一管理外链改写、规则校验与跳转日志。'); ?></div>
            </div>
            <div class="renewgo-topbar">
                <span class="tr-pill"><?php echo htmlspecialchars((string) $settings['mode'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="tr-pill"><?php echo ((string) $settings['enabled'] === '1') ? _t('已启用') : _t('已停用'); ?></span>
            </div>
        </div>
    </div>
</section>

<section class="tr-card tr-mt-16">
    <div class="tr-card-b">
        <div class="tr-grid cols-3 tr-cache-kpi-grid">
            <div class="tr-card">
                <div class="tr-card-b">
                    <div class="tr-kpi">
                        <div>
                            <div class="tr-kpi-label"><?php _e('日志总数'); ?></div>
                            <div class="tr-kpi-value"><?php echo (int) $summary->total; ?></div>
                        </div>
                        <div class="tr-kpi-icon"><svg class="tr-ico"><use href="<?php echo htmlspecialchars($icons, ENT_QUOTES, 'UTF-8'); ?>#i-database"></use></svg></div>
                    </div>
                </div>
            </div>
            <div class="tr-card">
                <div class="tr-card-b">
                    <div class="tr-kpi">
                        <div>
                            <div class="tr-kpi-label"><?php _e('成功跳转'); ?></div>
                            <div class="tr-kpi-value"><?php echo (int) $summary->jump_success; ?></div>
                        </div>
                        <div class="tr-kpi-icon tr-tone-blue"><svg class="tr-ico"><use href="<?php echo htmlspecialchars($icons, ENT_QUOTES, 'UTF-8'); ?>#i-check"></use></svg></div>
                    </div>
                </div>
            </div>
            <div class="tr-card">
                <div class="tr-card-b">
                    <div class="tr-kpi">
                        <div>
                            <div class="tr-kpi-label"><?php _e('频率拦截'); ?></div>
                            <div class="tr-kpi-value"><?php echo (int) $summary->jump_block; ?></div>
                        </div>
                        <div class="tr-kpi-icon tr-tone-ink"><svg class="tr-ico"><use href="<?php echo htmlspecialchars($icons, ENT_QUOTES, 'UTF-8'); ?>#i-shield"></use></svg></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="tr-card tr-mt-16">
    <div class="tr-card-h">
        <h3><?php _e('规则测试'); ?></h3>
    </div>
    <div class="tr-card-b">
        <div class="renewgo-form">
            <input id="renewgoTestUrl" class="text w-100" type="text" placeholder="https://example.com/page">
            <button id="renewgoTestBtn" type="button" class="btn primary"><?php _e('测试规则'); ?></button>
        </div>
        <div id="renewgoTestResult" class="renewgo-result"></div>
    </div>
</section>

<section class="tr-card tr-mt-16">
    <div class="tr-card-h renewgo-head">
        <h3><?php _e('白名单导入导出'); ?></h3>
        <div class="renewgo-actions">
            <button id="renewgoExportBtn" type="button" class="btn btn-s"><?php _e('导出'); ?></button>
            <button id="renewgoImportBtn" type="button" class="btn btn-s primary"><?php _e('导入'); ?></button>
        </div>
    </div>
    <div class="tr-card-b">
        <textarea id="renewgoRules" class="mono w-100 renewgo-rules" spellcheck="false"><?php echo htmlspecialchars((string) ($settings['whitelist'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>
</section>

<section class="tr-card tr-mt-16">
    <div class="tr-card-h renewgo-head">
        <h3><?php _e('最近日志'); ?></h3>
        <button id="renewgoPurgeBtn" type="button" class="btn btn-s btn-warn"><?php _e('清空日志'); ?></button>
    </div>
    <div class="tr-card-b">
        <div class="typecho-table-wrap">
            <table class="typecho-list-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th><?php _e('IP'); ?></th>
                    <th><?php _e('动作'); ?></th>
                    <th><?php _e('结果'); ?></th>
                    <th><?php _e('目标'); ?></th>
                    <th><?php _e('时间'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ((array) $logs as $row): ?>
                    <tr>
                        <td><?php echo (int) $row['id']; ?></td>
                        <td><?php echo htmlspecialchars((string) $row['ip'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $row['action'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $row['result'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['target'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo !empty($row['created_at']) ? date('Y-m-d H:i:s', (int) $row['created_at']) : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
window.RenewGoPanel = <?php echo json_encode([
    'test' => $urlTest,
    'purge' => $urlPurge,
    'export' => $urlExport,
    'import' => $urlImport
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo htmlspecialchars(RenewGo_Plugin::assetUrl('assets/panel.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
