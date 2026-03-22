<?php if(!defined('__TYPECHO_ADMIN__')) exit; ?>
<footer class="typecho-foot" role="contentinfo">
    <div class="tr-foot-inner">
        <div class="copyright">
            <a href="https://www.typerenew.com" class="tr-logo-link"><img src="<?php $options->adminUrl('img/typerenew-icon.svg'); ?>" alt="TypeRenew" class="tr-foot-logo"></a>
            <p><?php _e('基于 <a href="https://typecho.org/">Typecho</a> 开发，由 <a href="https://www.typerenew.com">%s</a> 焕新呈现，版本 %s', $options->software, $options->version); ?></p>
        </div>
        <nav class="resource">
            <a href="https://www.typerenew.com"><?php _e('项目地址'); ?></a> &bull;
            <a href="https://github.com/Yangsh888/TypeRenew"><?php _e('GitHub 源码'); ?></a> &bull;
            <a href="https://github.com/Yangsh888/TypeRenew/discussions"><?php _e('交流社区'); ?></a> &bull;
            <a href="https://github.com/Yangsh888/TypeRenew/issues"><?php _e('报告错误'); ?></a>
        </nav>
    </div>
</footer>
