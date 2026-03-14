<?php if(!defined('__TYPECHO_ADMIN__')) exit; ?>
<footer class="typecho-foot" role="contentinfo">
    <div class="tr-foot-inner">
        <div class="copyright">
            <a href="https://typecho.org" class="i-logo-s">Typecho</a>
            <p><?php _e('由 <a href="https://github.com/Yangsh888/TypeRenew">TypeRenew</a> 焕新呈现，基于 <a href="https://typecho.org">%s</a> 开发，版本 %s', $options->software, $options->version); ?></p>
        </div>
        <nav class="resource">
            <a href="https://github.com/Yangsh888/TypeRenew"><?php _e('项目地址'); ?></a> &bull;
            <a href="https://github.com/Yangsh888/TypeRenew/discussions"><?php _e('交流社区'); ?></a> &bull;
            <a href="https://github.com/Yangsh888/TypeRenew/issues"><?php _e('报告错误'); ?></a> &bull;
            <a href="https://github.com/Yangsh888/TypeRenew/releases"><?php _e('资源下载'); ?></a>
        </nav>
    </div>
</footer>
