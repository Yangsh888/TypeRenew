<?php
include 'common.php';
$bodyClass = 'tr-page-write';
include 'header.php';
include 'menu.php';

$page = \Widget\Contents\Page\Edit::alloc()->prepare();

$parentPageId = $page->getParent();
$parentPages = [0 => _t('不选择')];
$parents = \Widget\Contents\Page\Admin::allocWithAlias(
    'options',
    'ignoreRequest=1' . ($request->is('cid') ? '&ignore=' . $request->get('cid') : '')
);

while ($parents->next()) {
    $parentPages[$parents->cid] = str_repeat('- ', (int) $parents->levels) . $parents->title;
}
?>
<main class="main">
    <div class="body container">
        <form class="row typecho-page-main typecho-post-area tr-write-shell" action="<?php $security->index('/action/contents-page-edit'); ?>" method="post" name="write_page">
            <?php
            $permalink = \Typecho\Common::url($options->routingTable['page']['url'], $options->index);
            $permalinkParts = explode(':', $permalink, 2);
            $permalink = count($permalinkParts) === 2 ? $permalinkParts[1] : $permalinkParts[0];
            $permalink = ltrim($permalink, '/');
            $permalink = preg_replace("/\[([_a-z0-9-]+)[^\]]*\]/i", "{\\1}", $permalink);
            if ($page->have()) {
                $permalink = preg_replace_callback(
                    "/\{(cid)\}/i",
                    function ($matches) use ($page) {
                        $key = $matches[1];
                        return $page->getRouterParam($key);
                    },
                    $permalink
                );
            }
            $input = '<input type="text" id="slug" name="slug" autocomplete="off" value="' . htmlspecialchars($page->slug ?? '') . '" class="mono" />';
            $write = [
                'content' => $page,
                'draftAction' => 'contents-page-edit',
                'hook' => 'write-page.php',
                'textLabel' => _t('页面内容'),
                'previewLabel' => _t('预览页面'),
                'publishLabel' => _t('发布页面'),
                'permalink' => preg_replace_callback("/\{(slug|directory)\}/i", function ($matches) use ($input) {
                    return $matches[1] == 'slug' ? $input : '{directory/' . $input . '}';
                }, $permalink)
            ];
            include 'write.php';
            ob_start();
            ?>
            <section class="typecho-post-option" role="application">
                <label for="date" class="typecho-label"><?php _e('发布日期'); ?></label>
                <p><input class="typecho-date w-100" type="text" name="date" id="date" autocomplete="off"
                          value="<?php echo $page->have() && $page->created > 0
                              ? $page->date('Y-m-d H:i')
                              : \Typecho\Timezone::format((int) $options->time, 'Y-m-d H:i'); ?>"/>
                </p>
            </section>

            <section class="typecho-post-option">
                <label for="order" class="typecho-label"><?php _e('页面顺序'); ?></label>
                <p><input type="number" id="order" name="order" value="<?php $page->order(); ?>"
                          class="w-100"/></p>
                <p class="description"><?php _e('为你的自定义页面设定一个序列值以后, 能够使得它们按此值从小到大排列'); ?></p>
            </section>

            <section class="typecho-post-option">
                <label for="template" class="typecho-label"><?php _e('自定义模板'); ?></label>
                <p>
                    <select name="template" id="template">
                        <option value=""><?php _e('不选择'); ?></option>
                        <?php $templates = $page->getTemplates();
                        foreach ($templates as $template => $name): ?>
                            <option
                                value="<?php echo htmlspecialchars((string) $template, ENT_QUOTES, 'UTF-8'); ?>"<?php if ($template == $page->template): ?> selected="true"<?php endif; ?>><?php echo htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p class="description"><?php _e('如果你为此页面选择了一个自定义模板, 系统将按照你选择的模板文件展现它'); ?></p>
            </section>

            <section class="typecho-post-option">
                <label for="parent" class="typecho-label"><?php _e('父级页面'); ?></label>
                <p>
                    <select name="parent" id="parent">
                        <?php foreach ($parentPages as $pageId => $pageTitle): ?>
                            <option
                                value="<?php echo $pageId; ?>"<?php if ($pageId == ($page->parent ?? $parentPageId)): ?> selected="true"<?php endif; ?>><?php echo htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p class="description"><?php _e('如果你设定了父级页面, 此页面将作为子页面呈现'); ?></p>
            </section>

            <?php \Typecho\Plugin::factory('admin/write-page.php')->call('option', $page); ?>

            <details id="advance-panel">
                <summary class="btn btn-xs"><?php _e('高级选项'); ?> <i class="i-caret-down"></i></summary>

                <section class="typecho-post-option visibility-option">
                    <label for="visibility" class="typecho-label"><?php _e('公开度'); ?></label>
                    <p>
                        <select id="visibility" name="visibility">
                            <option
                                value="publish"<?php if ($page->status == 'publish' || !$page->status): ?> selected<?php endif; ?>><?php _e('公开'); ?></option>
                            <option
                                value="hidden"<?php if ($page->status == 'hidden'): ?> selected<?php endif; ?>><?php _e('隐藏'); ?></option>
                        </select>
                    </p>
                </section>

                <section class="typecho-post-option allow-option">
                    <label class="typecho-label"><?php _e('权限控制'); ?></label>
                    <ul>
                        <li><input id="allowComment" name="allowComment" type="checkbox" value="1"
                                   <?php if ($page->allow('comment')): ?>checked="true"<?php endif; ?> />
                            <label for="allowComment"><?php _e('允许评论'); ?></label></li>
                        <li><input id="allowPing" name="allowPing" type="checkbox" value="1"
                                   <?php if ($page->allow('ping')): ?>checked="true"<?php endif; ?> />
                            <label for="allowPing"><?php _e('允许被引用'); ?></label></li>
                        <li><input id="allowFeed" name="allowFeed" type="checkbox" value="1"
                                   <?php if ($page->allow('feed')): ?>checked="true"<?php endif; ?> />
                            <label for="allowFeed"><?php _e('允许在聚合中出现'); ?></label></li>
                    </ul>
                </section>

                <?php \Typecho\Plugin::factory('admin/write-page.php')->call('advanceOption', $page); ?>
            </details>
            <?php if ($page->have()): ?>
                <?php $modified = new \Typecho\Date($page->modified); ?>
                <section class="typecho-post-option">
                    <p class="description">
                        <br>&mdash;<br>
                        <?php _e('本页面由 %s 创建', htmlspecialchars((string) $page->author->screenName, ENT_QUOTES, 'UTF-8')); ?>
                        <br>
                        <?php _e('最后更新于 %s', $modified->word()); ?>
                    </p>
                </section>
            <?php endif; ?>
            <?php
            $writeSide = ['advance' => ob_get_clean()];
            include 'write-side.php';
            ?>
        </form>
    </div>
</main>

<?php
$write = [
    'content' => $page,
    'hook' => 'write-page.php'
];
include 'write-foot.php';
?>
