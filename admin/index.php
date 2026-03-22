<?php
include 'common.php';
include 'header.php';
include 'menu.php';

$stat = \Widget\Stat::alloc();

$db = \Typecho\Db::get();
$days = [];
$postsData = [];
$commentsData = [];
$dayCount = 14;

for ($i = $dayCount - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $label = date('m/d', strtotime("-{$i} days"));
    $days[] = $label;

    $startTime = strtotime($date . ' 00:00:00');
    $endTime = strtotime($date . ' 23:59:59');

    $postCount = $db->fetchObject($db->select(['COUNT(cid)' => 'num'])
        ->from('table.contents')
        ->where('type = ?', 'post')
        ->where('status = ?', 'publish')
        ->where('created >= ?', $startTime)
        ->where('created <= ?', $endTime))->num;

    $commentCount = $db->fetchObject($db->select(['COUNT(coid)' => 'num'])
        ->from('table.comments')
        ->where('created >= ?', $startTime)
        ->where('created <= ?', $endTime))->num;

    $postsData[] = (int) $postCount;
    $commentsData[] = (int) $commentCount;
}

$trSpark = function (array $values, int $w = 240, int $h = 44): string {
    static $n = 0;
    $n++;
    $gid = 'trg' . $n;
    $values = array_values(array_map('intval', $values));
    $count = count($values);
    if ($count < 2) {
        return '';
    }

    $min = min($values);
    $max = max($values);
    $range = max(1, $max - $min);
    $stepX = ($w - 2) / max(1, $count - 1);

    $points = [];
    for ($i = 0; $i < $count; $i++) {
        $x = 1 + $i * $stepX;
        $y = 1 + ($h - 2) * (1 - (($values[$i] - $min) / $range));
        $points[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
    }
    $poly = implode(' ', $points);

    $area = '1,' . ($h - 1) . ' ' . $poly . ' ' . ($w - 1) . ',' . ($h - 1);

    return '<svg class="tr-spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" aria-hidden="true">'
        . '<defs><linearGradient id="' . $gid . '" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0%" stop-color="rgba(37, 99, 235, 0.26)"/>'
        . '<stop offset="100%" stop-color="rgba(37, 99, 235, 0)"/>'
        . '</linearGradient></defs>'
        . '<polygon points="' . $area . '" fill="url(#' . $gid . ')"/>'
        . '<polyline points="' . $poly . '" fill="none" stroke="rgba(37, 99, 235, 0.95)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
        . '</svg>';
};

$postsTotal = (int) $stat->publishedPostsNum;
$pagesTotal = (int) $stat->publishedPagesNum;
$commentsApproved = (int) $stat->publishedCommentsNum;
$commentsWaiting = (int) $stat->waitingCommentsNum;
$commentsSpam = (int) $stat->spamCommentsNum;
$commentsTotal = $commentsApproved + $commentsWaiting + $commentsSpam;
$donutTotal = max(1, $postsTotal + $pagesTotal + $commentsTotal);

$donut = function (int $posts, int $pages, int $comments, int $total): string {
    $r = 62;
    $circ = 2 * pi() * $r;
    $gapLen = 6.0;

    $f1 = $posts / $total;
    $f2 = $pages / $total;
    $f3 = $comments / $total;

    $l1 = max(0.0, $circ * $f1 - $gapLen);
    $l2 = max(0.0, $circ * $f2 - $gapLen);
    $l3 = max(0.0, $circ * $f3 - $gapLen);

    $o1 = 0.0;
    $o2 = -($circ * $f1);
    $o3 = -($circ * ($f1 + $f2));

    $d = '<svg viewBox="0 0 160 160" aria-hidden="true">'
        . '<circle cx="80" cy="80" r="' . $r . '" fill="none" stroke="rgba(15, 23, 42, 0.08)" stroke-width="14"/>'
        . '<circle cx="80" cy="80" r="' . $r . '" fill="none" stroke="rgba(37, 99, 235, 0.92)" stroke-width="14" stroke-linecap="round" stroke-dasharray="' . $l1 . ' ' . ($circ - $l1) . '" stroke-dashoffset="' . $o1 . '" transform="rotate(-90 80 80)"/>'
        . '<circle cx="80" cy="80" r="' . $r . '" fill="none" stroke="rgba(59, 130, 246, 0.52)" stroke-width="14" stroke-linecap="round" stroke-dasharray="' . $l2 . ' ' . ($circ - $l2) . '" stroke-dashoffset="' . $o2 . '" transform="rotate(-90 80 80)"/>'
        . '<circle cx="80" cy="80" r="' . $r . '" fill="none" stroke="rgba(15, 23, 42, 0.30)" stroke-width="14" stroke-linecap="round" stroke-dasharray="' . $l3 . ' ' . ($circ - $l3) . '" stroke-dashoffset="' . $o3 . '" transform="rotate(-90 80 80)"/>'
        . '</svg>';
    return $d;
};
?>
<main class="main">
    <div class="body container">
        <div class="tr-grid cols-2">
            <div class="tr-card">
                <div class="tr-card-b">
                    <div class="tr-kpi tr-kpi-start">
                        <div class="tr-minw-0">
                            <div class="tr-kpi-label"><?php _e('欢迎回来'); ?></div>
                            <div class="tr-welcome-name">
                                <?php _e('%s', $user->screenName); ?>
                            </div>
                            <div class="tr-welcome-desc">
                                <?php _e('目前有 %s 篇文章，%s 条评论，%s 个分类', $stat->publishedPostsNum, $commentsTotal, $stat->categoriesNum); ?>
                            </div>
                            <div class="tr-actions tr-actions-grid">
                                <?php if ($user->pass('contributor', true)): ?>
                                    <a class="tr-btn primary tr-btn-square" href="<?php $options->adminUrl('write-post.php'); ?>">
                                        <svg class="tr-ico" aria-hidden="true"><use href="<?php echo htmlspecialchars($options->adminStaticUrl('img', 'icons.svg', true), ENT_QUOTES, 'UTF-8'); ?>#i-pencil"></use></svg>
                                        <span><?php _e('写新文章'); ?></span>
                                    </a>
                                <?php endif; ?>
                                <a class="tr-btn tr-btn-square" href="<?php $options->adminUrl('manage-posts.php'); ?>">
                                    <svg class="tr-ico" aria-hidden="true"><use href="<?php echo htmlspecialchars($options->adminStaticUrl('img', 'icons.svg', true), ENT_QUOTES, 'UTF-8'); ?>#i-file-text"></use></svg>
                                    <span><?php _e('管理文章'); ?></span>
                                </a>
                                <a class="tr-btn tr-btn-square" href="<?php $options->adminUrl('manage-comments.php'); ?>">
                                    <svg class="tr-ico" aria-hidden="true"><use href="<?php echo htmlspecialchars($options->adminStaticUrl('img', 'icons.svg', true), ENT_QUOTES, 'UTF-8'); ?>#i-message"></use></svg>
                                    <span><?php _e('管理评论'); ?></span>
                                </a>
                                <?php if ($user->pass('administrator', true)): ?>
                                    <a class="tr-btn tr-btn-square" href="<?php $options->adminUrl('options-general.php'); ?>">
                                        <svg class="tr-ico" aria-hidden="true"><use href="<?php echo htmlspecialchars($options->adminStaticUrl('img', 'icons.svg', true), ENT_QUOTES, 'UTF-8'); ?>#i-gear"></use></svg>
                                        <span><?php _e('系统设置'); ?></span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="tr-stack tr-align-end tr-spark-stack">
                            <div class="tr-spark-card">
                                <div class="tr-between tr-gap-12">
                                    <span class="tr-chip"><?php _e('近 %s 天文章', $dayCount); ?></span>
                                    <strong class="tr-spark-num"><?php echo array_sum($postsData); ?></strong>
                                </div>
                                <div class="tr-spark-wrap">
                                    <?php echo $trSpark($postsData, 240, 32); ?>
                                </div>
                            </div>
                            <div class="tr-spark-card">
                                <div class="tr-between tr-gap-12">
                                    <span class="tr-chip"><?php _e('近 %s 天评论', $dayCount); ?></span>
                                    <strong class="tr-spark-num"><?php echo array_sum($commentsData); ?></strong>
                                </div>
                                <div class="tr-spark-wrap">
                                    <?php echo $trSpark($commentsData, 240, 32); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tr-card">
                <div class="tr-card-b">
                    <div class="tr-between tr-gap-12">
                        <div>
                            <div class="tr-kpi-label"><?php _e('内容分布'); ?></div>
                            <div class="tr-subtitle"><?php _e('文章 / 页面 / 评论'); ?></div>
                        </div>
                        <div class="tr-chip"><?php _e('已发布'); ?></div>
                    </div>
                    <div class="tr-dist">
                        <div class="tr-donut tr-relative">
                            <?php echo $donut($postsTotal, $pagesTotal, $commentsTotal, $donutTotal); ?>
                            <div class="tr-donut-center">
                                <div class="tr-donut-total"><?php echo (int) ($postsTotal + $pagesTotal + $commentsTotal); ?></div>
                                <div class="tr-donut-label"><?php _e('总量'); ?></div>
                            </div>
                        </div>
                        <div class="tr-legend">
                            <div class="tr-legend-row">
                                <span class="tr-legend-label"><span class="tr-dot tr-dot-post"></span><?php _e('文章'); ?></span>
                                <strong><?php echo $postsTotal; ?></strong>
                            </div>
                            <div class="tr-legend-row">
                                <span class="tr-legend-label"><span class="tr-dot tr-dot-page"></span><?php _e('页面'); ?></span>
                                <strong><?php echo $pagesTotal; ?></strong>
                            </div>
                            <div class="tr-legend-row">
                                <span class="tr-legend-label"><span class="tr-dot tr-dot-comment"></span><?php _e('评论'); ?></span>
                                <strong><?php echo $commentsTotal; ?></strong>
                            </div>
                            <div class="tr-help">
                                <?php _e('评论构成：通过 %s，待审核 %s，垃圾 %s', $commentsApproved, $commentsWaiting, $commentsSpam); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tr-grid cols-4 tr-mt-16">
            <div class="tr-card">
                <div class="tr-card-b">
                    <div class="tr-kpi">
                        <div>
                            <div class="tr-kpi-label"><?php _e('文章'); ?></div>
                            <div class="tr-kpi-value"><?php echo (int) $stat->publishedPostsNum; ?></div>
                        </div>
                        <div class="tr-kpi-icon" aria-hidden="true">
                            <svg class="tr-ico"><use href="<?php echo htmlspecialchars($options->adminStaticUrl('img', 'icons.svg', true), ENT_QUOTES, 'UTF-8'); ?>#i-file-text"></use></svg>
                        </div>
                    </div>
                    <div class="tr-help tr-mt-10">
                        <?php _e('已发布文章总数'); ?>
                    </div>
                </div>
            </div>
            <div class="tr-card">
                <div class="tr-card-b">
                    <div class="tr-kpi">
                        <div>
                            <div class="tr-kpi-label"><?php _e('评论'); ?></div>
                            <div class="tr-kpi-value"><?php echo (int) $commentsTotal; ?></div>
                        </div>
                        <div class="tr-kpi-icon tr-tone-blue" aria-hidden="true">
                            <svg class="tr-ico"><use href="<?php echo htmlspecialchars($options->adminStaticUrl('img', 'icons.svg', true), ENT_QUOTES, 'UTF-8'); ?>#i-message"></use></svg>
                        </div>
                    </div>
                    <div class="tr-help tr-mt-10">
                        <?php _e('全部评论总数（含待审核与垃圾）'); ?>
                    </div>
                </div>
            </div>
            <div class="tr-card">
                <div class="tr-card-b">
                    <div class="tr-kpi">
                        <div>
                            <div class="tr-kpi-label"><?php _e('待审核'); ?></div>
                            <div class="tr-kpi-value"><?php echo (int) $stat->waitingCommentsNum; ?></div>
                        </div>
                        <div class="tr-kpi-icon tr-tone-ink" aria-hidden="true">
                            <svg class="tr-ico"><use href="<?php echo htmlspecialchars($options->adminStaticUrl('img', 'icons.svg', true), ENT_QUOTES, 'UTF-8'); ?>#i-bell"></use></svg>
                        </div>
                    </div>
                    <div class="tr-help tr-mt-10">
                        <?php _e('建议定期处理待审核与垃圾评论'); ?>
                    </div>
                </div>
            </div>
            <div class="tr-card">
                <div class="tr-card-b">
                    <div class="tr-kpi">
                        <div>
                            <div class="tr-kpi-label"><?php _e('分类'); ?></div>
                            <div class="tr-kpi-value"><?php echo (int) $stat->categoriesNum; ?></div>
                        </div>
                        <div class="tr-kpi-icon tr-tone-blue" aria-hidden="true">
                            <svg class="tr-ico"><use href="<?php echo htmlspecialchars($options->adminStaticUrl('img', 'icons.svg', true), ENT_QUOTES, 'UTF-8'); ?>#i-folder"></use></svg>
                        </div>
                    </div>
                    <div class="tr-help tr-mt-10">
                        <?php _e('清晰的分类结构有助于内容管理'); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="tr-grid cols-2 tr-mt-16">
            <div class="tr-card">
                <div class="tr-card-b">
                    <div class="tr-section-head">
                        <div class="tr-section-title"><?php _e('最近文章'); ?></div>
                        <?php if ($user->pass('contributor', true)): ?>
                            <a class="tr-pill" href="<?php $options->adminUrl('write-post.php'); ?>"><?php _e('写文章'); ?></a>
                        <?php endif; ?>
                    </div>
                    <div class="tr-mt-8">
                        <?php \Widget\Contents\Post\Recent::alloc('pageSize=8')->to($posts); ?>
                        <table class="typecho-list-table tr-compact-table">
                            <tbody>
                                <?php if ($posts->have()): ?>
                                    <?php while ($posts->next()): ?>
                                        <tr>
                                            <td class="tr-td">
                                                <a class="tr-link-strong" href="<?php $posts->permalink(); ?>" target="_blank" rel="noopener noreferrer"><?php $posts->title(); ?></a>
                                                <div class="tr-subtext"><?php $posts->date('Y-m-d'); ?></div>
                                            </td>
                                            <td class="tr-td tr-td-right">
                                                <a class="tr-pill" href="<?php $options->adminUrl('write-post.php?cid=' . $posts->cid); ?>"><?php _e('编辑'); ?></a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td class="tr-empty"><?php _e('暂时没有文章'); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="tr-card">
                <div class="tr-card-b">
                    <div class="tr-section-head">
                        <div class="tr-section-title"><?php _e('最新回复'); ?></div>
                        <a class="tr-pill" href="<?php $options->adminUrl('manage-comments.php'); ?>"><?php _e('查看全部'); ?></a>
                    </div>
                    <div class="tr-mt-8">
                        <?php \Widget\Comments\Recent::alloc('pageSize=8')->to($comments); ?>
                        <table class="typecho-list-table tr-compact-table">
                            <tbody>
                                <?php if ($comments->have()): ?>
                                    <?php while ($comments->next()): ?>
                                        <tr>
                                            <td class="tr-td">
                                                <a class="tr-link-strong" href="<?php $comments->permalink(); ?>" target="_blank" rel="noopener noreferrer"><?php $comments->author(false); ?></a>
                                                <div class="tr-subtext tr-ellipsis"><?php $comments->excerpt(52, '...'); ?></div>
                                            </td>
                                            <td class="tr-td tr-td-right tr-subtext">
                                                <?php $comments->date('m/d'); ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td class="tr-empty"><?php _e('暂时没有回复'); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tr-grid cols-1 tr-mt-16">
            <div class="tr-card">
                <div class="tr-card-b">
                    <div class="tr-section-head">
                        <div class="tr-section-title"><?php _e('官方动态'); ?></div>
                        <a class="tr-pill" href="https://github.com/Yangsh888/TypeRenew/discussions" target="_blank" rel="noopener noreferrer"><?php _e('访问讨论区'); ?></a>
                    </div>
                    <div class="tr-mt-8">
                        <ul class="tr-feed-list" id="trFeedList"></ul>
                        <div class="tr-help tr-mt-8" id="trFeedHint"><?php _e('读取官方动态中...'); ?></div>
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
        var list = document.getElementById('trFeedList');
        var hint = document.getElementById('trFeedHint');
        if (!list || !hint || !window.jQuery) {
            return;
        }

        var cache = window.sessionStorage;
        var cacheKey = 'trDashboardFeedDataV3';
        var tsKey = 'trDashboardFeedTsV3';
        var ttlMs = 6 * 60 * 60 * 1000;
        var now = Date.now ? Date.now() : +new Date();
        var iconUrl = <?php echo json_encode($options->adminStaticUrl('img', 'icons.svg', true)); ?>;
        var emptyText = <?php echo json_encode(_t('暂无动态')); ?>;
        var failText = <?php echo json_encode(_t('暂时无法访问 GitHub，请检查网络后重试。')); ?>;
        var cacheText = <?php echo json_encode(_t('当前网络不可达，已展示上次缓存内容。')); ?>;

        function esc(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function buildHtml(items) {
            var html = '';
            var maxItems = 9;
            for (var i = 0; i < items.length && maxItems > 0; i++) {
                var item = items[i] || {};
                var title = esc(item.title || '');
                var link = esc(item.link || '');
                var date = esc(item.date || '');
                if (!title || !link) {
                    continue;
                }
                maxItems--;
                html += '<li>'
                    + '<a class="tr-feed-item" href="' + link + '" target="_blank" rel="noopener noreferrer">'
                    + '<span class="tr-feed-mark" aria-hidden="true"><svg class="tr-ico"><use href="' + esc(iconUrl) + '#i-globe"></use></svg></span>'
                    + '<span class="tr-feed-body"><span class="tr-feed-title tr-ellipsis">' + title + '</span><span class="tr-feed-meta">' + date + '</span></span>'
                    + '</a>'
                    + '</li>';
            }

            return html;
        }

        function applyItems(items) {
            var html = buildHtml(items);
            if (!html) {
                list.innerHTML = '';
                return false;
            }

            list.innerHTML = html;
            hint.style.display = 'none';
            return true;
        }

        function showHint(text) {
            hint.textContent = text;
            hint.style.display = '';
        }

        function readCache() {
            if (!cache) {
                return [];
            }

            try {
                var raw = cache.getItem(cacheKey) || '[]';
                var parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return [];
            }
        }

        function saveCache(items) {
            if (!cache) {
                return;
            }

            try {
                cache.setItem(cacheKey, JSON.stringify(items || []));
                cache.setItem(tsKey, String(now));
            } catch (e) {}
        }

        function applyCacheWithHint(text) {
            var cached = readCache();
            if (cached.length > 0 && applyItems(cached)) {
                if (text) {
                    showHint(text);
                }
                return true;
            }
            return false;
        }

        try {
            var ts = cache ? parseInt(cache.getItem(tsKey) || '0', 10) : 0;
            var cached = readCache();
            if (cached.length > 0 && ts && (now - ts) < ttlMs && applyItems(cached)) {
                return;
            }
        } catch (e) {}

        $.get('<?php $options->index('/action/ajax?do=feed'); ?>', function (o) {
            var items = [];
            var message = '';

            if (Array.isArray(o)) {
                items = o;
            } else if (o && typeof o === 'object') {
                items = Array.isArray(o.items) ? o.items : [];
                message = String(o.message || '');
            }

            if (items.length > 0 && applyItems(items)) {
                saveCache(items);
                if (message) {
                    showHint(message);
                }
                return;
            }

            if (message && applyCacheWithHint(message)) {
                return;
            }

            if (applyCacheWithHint(cacheText)) {
                return;
            }

            showHint(message || emptyText);
        }, 'json').fail(function () {
            if (!applyCacheWithHint(cacheText)) {
                showHint(failText);
            }
        });
    })();
</script>
<?php include 'footer.php'; ?>
