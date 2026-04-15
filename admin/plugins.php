<?php
include 'common.php';
$menu->addLink = 'https://github.com/Yangsh888/TypeRenew-plugins';
$menu->addText = '插件仓库';
$menu->addTarget = '_blank';
include 'header.php';
include 'menu.php';
$pluginAction = htmlspecialchars($options->index . '/action/plugins-edit', ENT_QUOTES, 'UTF-8');
$pluginToken = htmlspecialchars($security->getToken($options->index . '/action/plugins-edit'), ENT_QUOTES, 'UTF-8');
$pluginVersionUrl = \Typecho\Common::url('/action/ajax?do=pluginVersion', $options->index);
$pluginHomepage = static function ($value): string {
    $candidate = trim((string) $value);

    if ($candidate !== '' && preg_match('#^https?://#i', $candidate)) {
        return htmlspecialchars($candidate, ENT_QUOTES, 'UTF-8');
    }

    return '';
};
$pluginIsOfficial = static function ($author, $homepage): bool {
    $author = strtolower(trim((string) $author));
    if ($author !== 'typerenew') {
        return false;
    }

    $candidate = trim((string) $homepage);
    if ($candidate === '') {
        return false;
    }

    $parts = \Typecho\Common::parseUrl($candidate);
    $host = strtolower((string) ($parts['host'] ?? ''));

    return in_array($host, ['www.typerenew.com', 'typerenew.com'], true);
};
$pluginVersionMeta = static function ($name, $version, $author, $homepage, $mobile = false) use ($pluginIsOfficial): string {
    $pluginName = htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8');
    $versionText = trim((string) $version);
    $versionText = htmlspecialchars($versionText !== '' ? $versionText : '-', ENT_QUOTES, 'UTF-8');
    $loadingText = htmlspecialchars((string) _t('正在检测版本状态'), ENT_QUOTES, 'UTF-8');
    $unofficialText = htmlspecialchars((string) _t('这并非 TypeRenew 官方插件，无法在官方插件仓库中检测版本状态。'), ENT_QUOTES, 'UTF-8');
    $official = $pluginIsOfficial($author, $homepage);
    $defaultStatus = $official ? 'loading' : 'unofficial';
    $defaultTip = $official ? $loadingText : $unofficialText;
    $mobileAttr = $mobile ? ' data-mobile-only="1"' : '';

    return '<span class="tr-plugin-version-wrap' . ($mobile ? ' is-mobile' : '') . '">'
        . ($mobile ? '' : '<span class="tr-plugin-version-text">' . $versionText . '</span>')
        . '<span class="tr-plugin-version-badge is-' . $defaultStatus . '"'
        . ' data-plugin-name="' . $pluginName . '"'
        . ' data-default-status="' . $defaultStatus . '"'
        . ' data-default-tip="' . $defaultTip . '"'
        . ' data-tip="' . $defaultTip . '"'
        . ' tabindex="0"'
        . ' role="img"'
        . $mobileAttr
        . ' aria-label="' . $defaultTip . '">' . ($official ? '...' : '?') . '</span>'
        . '</span>';
};
$pluginVersionToolbar = '<div class="tr-plugin-version-toolbar">'
    . '<div class="tr-plugin-version-toolbar-actions">'
    . '<button type="button" class="btn btn-s" id="trPluginVersionRefresh">' . _t('刷新检测') . '</button>'
    . '<span class="tr-plugin-version-hint" id="trPluginVersionHint" role="status" aria-live="polite">' . _t('插件版本状态缓存 2 小时') . '</span>'
    . '</div>'
    . '</div>';
?>
<main class="main">
    <div class="body container">
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 typecho-list">
                <?php \Widget\Plugins\Rows::allocWithAlias('activated', 'activated=1')->to($activatedPlugins); ?>
                <?php if ($activatedPlugins->have() || !empty($activatedPlugins->activatedPlugins)): ?>
                    <div class="tr-plugin-section-head">
                        <h4 class="typecho-list-table-title"><?php _e('启用的插件'); ?></h4>
                        <?php echo $pluginVersionToolbar; ?>
                    </div>
                    <table class="typecho-list-table">
                        <colgroup>
                            <col width="25%"/>
                            <col width="45%"/>
                            <col width="8%" class="kit-hidden-mb"/>
                            <col width="10%" class="kit-hidden-mb"/>
                            <col width=""/>
                        </colgroup>
                        <thead>
                        <tr>
                            <th><?php _e('名称'); ?></th>
                            <th><?php _e('描述'); ?></th>
                            <th class="kit-hidden-mb"><?php _e('版本'); ?></th>
                            <th class="kit-hidden-mb"><?php _e('作者'); ?></th>
                            <th><?php _e('操作'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php while ($activatedPlugins->next()): ?>
                            <tr id="plugin-<?php $activatedPlugins->name(); ?>">
                                <td><?php $activatedPlugins->title(); ?>
                                    <?php echo $pluginVersionMeta($activatedPlugins->name, $activatedPlugins->version, $activatedPlugins->author, $activatedPlugins->homepage, true); ?>
                                    <?php if (!$activatedPlugins->dependence): ?>
                                        <i class="i-delete"
                                           title="<?php _e('%s 无法在此版本的typecho下正常工作', $activatedPlugins->title); ?>"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?php $activatedPlugins->description(); ?></td>
                                <td class="kit-hidden-mb"><?php echo $pluginVersionMeta($activatedPlugins->name, $activatedPlugins->version, $activatedPlugins->author, $activatedPlugins->homepage); ?></td>
                                <td class="kit-hidden-mb"><?php $homepage = $pluginHomepage($activatedPlugins->homepage); ?>
                                    <?php echo $homepage === '' ? $activatedPlugins->author : '<a href="' . $homepage . '" target="_blank" rel="noopener noreferrer">' . $activatedPlugins->author . '</a>'; ?></td>
                                <td>
                                    <?php if ($activatedPlugins->activate || $activatedPlugins->deactivate || $activatedPlugins->config || $activatedPlugins->personalConfig): ?>
                                        <div class="tr-plugin-actions">
                                            <?php if ($activatedPlugins->config): ?>
                                                <a class="btn btn-link" href="<?php $options->adminUrl('options-plugin.php?config=' . $activatedPlugins->name); ?>"><?php _e('设置'); ?></a>
                                            <?php endif; ?>
                                            <form action="<?php echo $pluginAction; ?>" method="post" class="inline-operate-form">
                                                <input type="hidden" name="_" value="<?php echo $pluginToken; ?>">
                                                <input type="hidden" name="deactivate" value="<?php echo htmlspecialchars($activatedPlugins->name, ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="btn btn-link" lang="<?php _e('你确认要禁用插件 %s 吗?', $activatedPlugins->name); ?>"><?php _e('禁用'); ?></button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="important"><?php _e('即插即用'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                        <?php if (!empty($activatedPlugins->activatedPlugins)): ?>
                            <?php foreach ($activatedPlugins->activatedPlugins as $key => $val): ?>
                                <tr>
                                    <td><?php echo $key; ?></td>
                                    <td colspan="3"><span
                                            class="warning"><?php _e('此插件文件已经损坏或者被不安全移除, 强烈建议你禁用它'); ?></span></td>
                                    <td><form action="<?php echo $pluginAction; ?>" method="post" class="inline-operate-form">
                                            <input type="hidden" name="_" value="<?php echo $pluginToken; ?>">
                                            <input type="hidden" name="deactivate" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="btn btn-link" lang="<?php _e('你确认要禁用插件 %s 吗?', $key); ?>"><?php _e('禁用'); ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        </tbody>
                    </table>
                <?php endif; ?>

                <?php \Widget\Plugins\Rows::allocWithAlias('unactivated', 'activated=0')->to($deactivatedPlugins); ?>
                <?php if ($deactivatedPlugins->have() || !$activatedPlugins->have()): ?>
                    <div class="tr-plugin-section-head">
                        <h4 class="typecho-list-table-title"><?php _e('禁用的插件'); ?></h4>
                        <?php if (!$activatedPlugins->have() && empty($activatedPlugins->activatedPlugins)): ?>
                            <?php echo $pluginVersionToolbar; ?>
                        <?php endif; ?>
                    </div>
                    <table class="typecho-list-table deactivate">
                        <colgroup>
                            <col width="25%"/>
                            <col width="45%"/>
                            <col width="8%" class="kit-hidden-mb"/>
                            <col width="10%" class="kit-hidden-mb"/>
                            <col width=""/>
                        </colgroup>
                        <thead>
                        <tr>
                            <th><?php _e('名称'); ?></th>
                            <th><?php _e('描述'); ?></th>
                            <th class="kit-hidden-mb"><?php _e('版本'); ?></th>
                            <th class="kit-hidden-mb"><?php _e('作者'); ?></th>
                            <th class="typecho-radius-topright"><?php _e('操作'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($deactivatedPlugins->have()): ?>
                            <?php while ($deactivatedPlugins->next()): ?>
                                <tr id="plugin-<?php $deactivatedPlugins->name(); ?>">
                                    <td><?php $deactivatedPlugins->title(); ?>
                                        <?php echo $pluginVersionMeta($deactivatedPlugins->name, $deactivatedPlugins->version, $deactivatedPlugins->author, $deactivatedPlugins->homepage, true); ?>
                                    </td>
                                    <td><?php $deactivatedPlugins->description(); ?></td>
                                    <td class="kit-hidden-mb"><?php echo $pluginVersionMeta($deactivatedPlugins->name, $deactivatedPlugins->version, $deactivatedPlugins->author, $deactivatedPlugins->homepage); ?></td>
                                    <td class="kit-hidden-mb"><?php $homepage = $pluginHomepage($deactivatedPlugins->homepage); ?>
                                        <?php echo $homepage === '' ? $deactivatedPlugins->author : '<a href="' . $homepage . '" target="_blank" rel="noopener noreferrer">' . $deactivatedPlugins->author . '</a>'; ?></td>
                                    <td>
                                        <div class="tr-plugin-actions">
                                            <form action="<?php echo $pluginAction; ?>" method="post" class="inline-operate-form">
                                                <input type="hidden" name="_" value="<?php echo $pluginToken; ?>">
                                                <input type="hidden" name="activate" value="<?php echo htmlspecialchars($deactivatedPlugins->name, ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="btn btn-link"><?php _e('启用'); ?></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5"><h6 class="typecho-list-table-title"><?php _e('没有安装插件'); ?></h6>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

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
        var badges = document.querySelectorAll('.tr-plugin-version-badge[data-plugin-name]');
        var refreshBtn = document.getElementById('trPluginVersionRefresh');
        var hint = document.getElementById('trPluginVersionHint');
        if (!badges.length || !hint || !window.jQuery) {
            return;
        }

        var $ = window.jQuery;
        var store = window.TypechoStore || null;
        var requestUrl = <?php echo json_encode($pluginVersionUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        var cacheKey = 'trPluginVersionPayload';
        var ttlMs = 2 * 60 * 60 * 1000;
        var texts = <?php echo json_encode([
            'refresh' => _t('刷新检测'),
            'refreshing' => _t('刷新中...'),
            'loadingHint' => _t('正在检测官方插件版本...'),
            'refreshingHint' => _t('正在强制刷新官方插件版本...'),
            'cachedHint' => _t('已读取插件版本缓存'),
            'updatedHint' => _t('已刷新插件版本状态'),
            'failedHint' => _t('版本检测失败，请稍后重试。'),
            'failedRequest' => _t('版本检测失败，当前请求未成功返回。'),
            'missing' => _t('当前插件状态未返回，暂时无法显示版本检测结果。'),
            'loadingBadge' => _t('正在检测版本状态'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        var symbols = {
            latest: '√',
            update: '!',
            failed: '×',
            unofficial: '?',
            loading: '...'
        };
        var badgeMap = {};

        if (typeof requestUrl !== 'string' || requestUrl === '') {
            Array.prototype.forEach.call(badges, function (node) {
                var defaultState = String(node.getAttribute('data-default-status') || 'failed');
                var defaultTip = String(node.getAttribute('data-default-tip') || texts.failedHint);
                var state = defaultState === 'unofficial' ? 'unofficial' : 'failed';
                var message = defaultState === 'unofficial'
                    ? defaultTip
                    : '版本检测地址生成失败，暂时无法发起检测请求。';

                node.textContent = symbols[state] || symbols.failed;
                node.className = 'tr-plugin-version-badge is-' + state;
                node.setAttribute('data-tip', message);
                node.setAttribute('aria-label', message);
            });

            setHint('版本检测地址生成失败，已停止自动检测。', 'error');
            return;
        }

        Array.prototype.forEach.call(badges, function (node) {
            var name = String(node.getAttribute('data-plugin-name') || '');
            if (!name) {
                return;
            }

            if (!badgeMap[name]) {
                badgeMap[name] = [];
            }

            badgeMap[name].push(node);
        });

        function formatTime(ts) {
            var stamp = parseInt(ts || 0, 10);
            if (!stamp) {
                return '';
            }

            var date = new Date(stamp * 1000);
            if (Number.isNaN(date.getTime())) {
                return '';
            }

            var pad = function (value) {
                return value < 10 ? '0' + value : String(value);
            };

            return date.getFullYear()
                + '-' + pad(date.getMonth() + 1)
                + '-' + pad(date.getDate())
                + ' ' + pad(date.getHours())
                + ':' + pad(date.getMinutes());
        }

        function setHint(text, tone) {
            hint.textContent = text;
            hint.className = 'tr-plugin-version-hint' + (tone ? ' is-' + tone : '');
        }

        function setLoading(active) {
            Array.prototype.forEach.call(badges, function (node) {
                if (!active) {
                    return;
                }

                if (String(node.getAttribute('data-default-status') || '') === 'unofficial') {
                    node.textContent = symbols.unofficial;
                    node.className = 'tr-plugin-version-badge is-unofficial';
                    node.setAttribute('data-tip', node.getAttribute('data-default-tip') || texts.missing);
                    node.setAttribute('aria-label', node.getAttribute('data-default-tip') || texts.missing);
                    return;
                }

                node.textContent = symbols.loading;
                node.className = 'tr-plugin-version-badge is-loading';
                node.setAttribute('data-tip', texts.loadingBadge);
                node.setAttribute('aria-label', texts.loadingBadge);
            });

            if (!refreshBtn) {
                return;
            }

            refreshBtn.disabled = active;
            refreshBtn.setAttribute('aria-busy', active ? 'true' : 'false');
            refreshBtn.textContent = active ? texts.refreshing : texts.refresh;
        }

        function applyStatus(name, payload) {
            var nodes = badgeMap[name] || [];
            var state = payload && payload.status ? String(payload.status) : '';
            var message = payload && payload.message ? String(payload.message) : '';

            nodes.forEach(function (node) {
                var defaultState = String(node.getAttribute('data-default-status') || 'failed');
                var defaultMessage = String(node.getAttribute('data-default-tip') || texts.missing);
                var finalState = state || defaultState;
                var finalMessage = message || defaultMessage;
                var symbol = symbols[finalState] || symbols.failed;

                node.textContent = symbol;
                node.className = 'tr-plugin-version-badge is-' + finalState;
                node.setAttribute('data-tip', finalMessage);
                node.setAttribute('aria-label', finalMessage);
            });
        }

        function applyStatuses(statuses) {
            Object.keys(badgeMap).forEach(function (name) {
                applyStatus(name, statuses && typeof statuses === 'object' ? statuses[name] : null);
            });
        }

        function readSessionCache() {
            if (!store) {
                return null;
            }

            var payload = store.sessionGetJson(cacheKey, null);
            if (!payload || typeof payload !== 'object' || !payload.ok || !payload.statuses) {
                return null;
            }

            var checkedAt = parseInt(payload.checkedAt || 0, 10);
            if (!checkedAt || (Date.now() - checkedAt * 1000) >= ttlMs) {
                return null;
            }

            return payload;
        }

        function writeSessionCache(payload) {
            if (!store || !payload || !payload.ok) {
                return;
            }

            store.sessionSetJson(cacheKey, payload);
        }

        function clearSessionCache() {
            if (store) {
                store.sessionRemove(cacheKey);
            }
        }

        function applySummary(payload, forced) {
            var checkedAt = formatTime(payload && payload.checkedAt);
            if (payload && payload.ok) {
                if (payload.cached) {
                    setHint(texts.cachedHint + (checkedAt ? ' · ' + checkedAt : ''), 'ok');
                    return;
                }

                setHint(texts.updatedHint + (checkedAt ? ' · ' + checkedAt : ''), forced ? 'ok' : '');
                return;
            }

            setHint((payload && payload.message) ? String(payload.message) : texts.failedHint, 'error');
        }

        function applyRequestFailure() {
            Object.keys(badgeMap).forEach(function (name) {
                var node = (badgeMap[name] || [])[0];
                if (!node) {
                    return;
                }

                applyStatus(name, String(node.getAttribute('data-default-status') || '') === 'unofficial' ? {
                    status: 'unofficial',
                    message: node.getAttribute('data-default-tip') || texts.missing
                } : {
                    status: 'failed',
                    message: texts.failedRequest
                });
            });
        }

        function loadVersions(forceRefresh) {
            setLoading(true);
            setHint(forceRefresh ? texts.refreshingHint : texts.loadingHint, '');

            var url = requestUrl + (requestUrl.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now();
            if (forceRefresh) {
                url += '&refresh=1';
            }

            $.get(url, function (payload) {
                payload = payload && typeof payload === 'object' ? payload : {};
                applyStatuses(payload.statuses || null);

                if (payload.ok) {
                    writeSessionCache(payload);
                } else {
                    clearSessionCache();
                }

                applySummary(payload, forceRefresh);
            }, 'json').fail(function () {
                clearSessionCache();
                applyRequestFailure();
                setHint(texts.failedRequest, 'error');
            }).always(function () {
                setLoading(false);
            });
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                loadVersions(true);
            });
        }

        var cachedPayload = readSessionCache();
        if (cachedPayload) {
            applyStatuses(cachedPayload.statuses);
            applySummary(cachedPayload, false);
            setLoading(false);
            return;
        }

        loadVersions(false);
    })();
</script>
<?php
include 'footer.php';
?>
