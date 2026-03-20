<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<!DOCTYPE html>
<html lang="zh-Hans">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php $this->archiveTitle([
            'category' => _t('分类 %s 下的文章'),
            'search'   => _t('包含关键字 %s 的文章'),
            'tag'      => _t('标签 %s 下的文章'),
            'author'   => _t('%s 发布的文章')
        ], '', ' - '); ?><?php $this->options->title(); ?></title>
    
    <?php
        $defaultSchema = $this->options->colorSchema ?? 'auto';
        echo "<script>
            (function() {
                var stored = localStorage.getItem('theme');
                var def = '{$defaultSchema}';
                var isDark = false;
                if (stored === 'dark' || stored === 'light') {
                    isDark = (stored === 'dark');
                } else if (def === 'dark') {
                    isDark = true;
                } else if (def === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    isDark = true;
                }
                if (isDark) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                }
            })();
        </script>";
    ?>
    
    <link rel="stylesheet" href="<?php $this->options->themeUrl('style.css'); ?>">
    <?php $this->header(); ?>
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <div class="site-branding">
            <?php if ($this->options->logoUrl): ?>
                <a class="site-logo" href="<?php $this->options->siteUrl(); ?>">
                    <img src="<?php $this->options->logoUrl() ?>" alt="<?php $this->options->title() ?>"/>
                </a>
            <?php else: ?>
                <a class="site-title" href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title() ?></a>
            <?php endif; ?>
        </div>
        <nav class="site-nav" aria-label="<?php _e('全局导航'); ?>">
            <input type="checkbox" id="nav-toggle" class="nav-toggle-input" aria-label="<?php _e('展开导航'); ?>">
            <label for="nav-toggle" class="nav-toggle-label" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </label>
            <ul class="nav-menu" role="menubar">
                <li role="none"><a role="menuitem" <?php if ($this->is('index')): ?> class="active"<?php endif; ?> href="<?php $this->options->siteUrl(); ?>"><?php _e('首页'); ?></a></li>
                <?php \Widget\Contents\Page\Rows::alloc()->to($pages); ?>
                <?php while ($pages->next()): ?>
                    <li role="none"><a role="menuitem" <?php if ($this->is('page', $pages->slug)): ?> class="active"<?php endif; ?> href="<?php $pages->permalink(); ?>"><?php $pages->title(); ?></a></li>
                <?php endwhile; ?>
                
                <li role="none">
                    <form id="search" method="post" action="<?php $this->options->siteUrl(); ?>" role="search" class="search-form">
                        <svg class="search-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <?php $searchQuery = htmlspecialchars((string) ($this->request->keywords ?? $this->request->s ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        <input type="search" id="s" name="s" class="search-input" placeholder="<?php _e('搜索...'); ?>" aria-label="<?php _e('搜索'); ?>" value="<?php echo $searchQuery; ?>" required />
                    </form>
                </li>
                
                <li role="none">
                    <button id="theme-switch" class="theme-switch" aria-label="<?php _e('切换主题'); ?>" title="<?php _e('切换主题'); ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="theme-icon" aria-hidden="true">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                        </svg>
                    </button>
                </li>
            </ul>
        </nav>
    </div>
</header>
<main class="site-main container">
