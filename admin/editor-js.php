<?php if(!defined('__TYPECHO_ADMIN__')) exit; ?>
<?php $content = !empty($post) ? $post : $page; ?>
<script>
(function () {
    $('#text').on('change', function (e) {
        e.preventDefault();
        e.stopPropagation();
    }).on('input', function () {
        $(this).parents('form').trigger('write');
    });
})();
</script>
<?php if (!$options->markdown): ?>
<script>
(function () {
    const textarea = $('#text');

    Typecho.insertFileToEditor = function (file, url, isImage) {
        const sel = textarea.getSelection(),
            html = isImage ? '<img src="' + url + '" alt="' + file + '" />'
                : '<a href="' + url + '">' + file + '</a>',
            offset = (sel ? sel.start : 0) + html.length;

        textarea.replaceSelection(html);
        textarea.setSelection(offset, offset);
    };
})();
</script>
<?php else: ?>
<script src="<?php $options->adminStaticUrl('js', 'hyperdown.js'); ?>"></script>
<script src="<?php $options->adminStaticUrl('js', 'pagedown.js'); ?>"></script>
<script src="<?php $options->adminStaticUrl('js', 'purify.js'); ?>"></script>
<script>
$(document).ready(function () {
    const textarea = $('#text'),
        toolbar = $('<div class="editor" id="wmd-button-bar" />').insertBefore(textarea.parent()),
        preview = $('<div id="wmd-preview" class="wmd-hidetab" />').insertAfter('.editor');
    const form = textarea.closest('form');

    const options = {}, isMarkdown = <?php echo json_encode(!$content->have() || $content->isMarkdown); ?>;

    options.strings = {
        bold: '<?php _e('加粗'); ?> <strong> Ctrl+B',
        boldexample: '<?php _e('加粗文字'); ?>',
            
        italic: '<?php _e('斜体'); ?> <em> Ctrl+I',
        italicexample: '<?php _e('斜体文字'); ?>',

        link: '<?php _e('链接'); ?> <a> Ctrl+L',
        linkdescription: '<?php _e('请输入链接描述'); ?>',

        quote:  '<?php _e('引用'); ?> <blockquote> Ctrl+Q',
        quoteexample: '<?php _e('引用文字'); ?>',

        code: '<?php _e('代码'); ?> <pre><code> Ctrl+K',
        codeexample: '<?php _e('请输入代码'); ?>',

        image: '<?php _e('图片'); ?> <img> Ctrl+G',
        imagedescription: '<?php _e('请输入图片描述'); ?>',

        olist: '<?php _e('数字列表'); ?> <ol> Ctrl+O',
        ulist: '<?php _e('普通列表'); ?> <ul> Ctrl+U',
        litem: '<?php _e('列表项目'); ?>',

        heading: '<?php _e('标题'); ?> <h1>/<h2> Ctrl+H',
        headingexample: '<?php _e('标题文字'); ?>',

        hr: '<?php _e('分割线'); ?> <hr> Ctrl+R',
        more: '<?php _e('摘要分割线'); ?> <!--more--> Ctrl+M',

        undo: '<?php _e('撤销'); ?> - Ctrl+Z',
        redo: '<?php _e('重做'); ?> - Ctrl+Y',
        redomac: '<?php _e('重做'); ?> - Ctrl+Shift+Z',

        fullscreen: '<?php _e('全屏'); ?>',
        exitFullscreen: '<?php _e('退出全屏'); ?>',
        fullscreenUnsupport: '<?php _e('此浏览器不支持全屏操作'); ?>',

        imagedialog: '<p><b><?php _e('插入图片'); ?></b></p><p><?php _e('请在下方的输入框内输入要插入的远程图片地址'); ?></p><p><?php _e('您也可以使用附件功能插入上传的本地图片'); ?></p>',
        linkdialog: '<p><b><?php _e('插入链接'); ?></b></p><p><?php _e('请在下方的输入框内输入要插入的链接地址'); ?></p>',

        ok: '<?php _e('确定'); ?>',
        cancel: '<?php _e('取消'); ?>',

        help: '<?php _e('Markdown语法帮助'); ?>'
    };

    const converter = new HyperDown(),
        editor = new Markdown.Editor(converter, '', options);

    converter.enableHtml(true);
    converter.enableLine(true);
    const reloadScroll = scrollableEditor(textarea, preview);

    converter.hook('makeHtml', function (html) {
        html = html.replace('<p><!--more--></p>', '<!--more-->');
        
        if (html.indexOf('<!--more-->') > 0) {
            var parts = html.split(/\s*<\!\-\-more\-\->\s*/),
                summary = parts.shift(),
                details = parts.join('');

            html = '<div class="summary">' + summary + '</div>'
                + '<div class="details">' + details + '</div>';
        }

        html = html.replace(/<(iframe|embed)\s+([^>]*)>/ig, function (all, tag, src) {
            if (src[src.length - 1] === '/') {
                src = src.substring(0, src.length - 1);
            }

            return '<div class="embed"><strong>'
                + tag + '</strong> : ' + $.trim(src) + '</div>';
        });

        return DOMPurify.sanitize(html, {USE_PROFILES: {html: true}});
    });

    editor.hooks.chain('onPreviewRefresh', function () {
        const images = $('img', preview);
        let count = images.length;

        if (count === 0) {
            reloadScroll(true);
        } else {
            images.bind('load error', function () {
                count --;

                if (count === 0) {
                    reloadScroll(true);
                }
            });
        }
    });

    <?php \Typecho\Plugin::factory('admin/editor-js.php')->call('markdownEditor', $content); ?>

    let th = textarea.height(), ph = preview.height();
    const workspace = window.Typecho && window.Typecho.writeWorkspace ? window.Typecho.writeWorkspace : null;
    const fullscreenHeight = function () {
        const actionHeight = workspace && typeof workspace.actionHeight === 'function'
            ? workspace.actionHeight()
            : 72;
        return Math.max(320, $(window).height() - toolbar.outerHeight() - actionHeight - 32);
    };
    const applyFullscreenHeight = function () {
        const h = fullscreenHeight();
        textarea.css('height', h);
        preview.css('height', h);
    };
    const syncShellFullscreen = function (on) {
        if (on) {
            th = textarea.height();
            ph = preview.height();
            applyFullscreenHeight();
            return;
        }

        textarea.height(th);
        preview.height(ph);
    };
    const enterShellFullscreen = function () {
        workspace && workspace.setFullscreen(true);
        return false;
    };
    const findNativeFullscreenControls = function () {
        const labels = ['<?php _e('全屏'); ?>', '<?php _e('退出全屏'); ?>'];
        return toolbar.find('li, button, a, span').filter(function () {
            const node = $(this);
            const title = String(node.attr('title') || node.attr('aria-label') || '').trim();
            const text = String(node.text() || '').trim();
            return labels.includes(title) || labels.includes(text) || node.is('#wmd-fullscreen-button') || node.hasClass('wmd-fullscreen-button');
        });
    };
    const bindNativeFullscreenControl = function () {
        if (!workspace) {
            return;
        }

        findNativeFullscreenControls().each(function () {
            const node = $(this);
            const el = node.get(0);
            if (!el || el.dataset.trShellBound === '1') {
                return;
            }

            el.dataset.trShellBound = '1';
            el.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (typeof event.stopImmediatePropagation === 'function') {
                    event.stopImmediatePropagation();
                }
                workspace.toggleFullscreen();
            }, true);
        });
    };
    const syncNativeFullscreenControl = function (on) {
        const label = on ? '<?php _e('退出全屏'); ?>' : '<?php _e('全屏'); ?>';
        findNativeFullscreenControls()
            .toggleClass('tr-fullscreen-active', !!on)
            .attr('aria-pressed', on ? 'true' : 'false')
            .attr('title', label)
            .attr('aria-label', label);
    };
    const handleShellShortcut = function (event) {
        if (!workspace) {
            return;
        }

        const key = String(event.key || '').toLowerCase();
        if (!(event.ctrlKey || event.metaKey) || (key !== 'j' && key !== 'e')) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }

        if (key === 'j') {
            workspace.toggleFullscreen();
        } else if (workspace.isFullscreen()) {
            workspace.setFullscreen(false);
        }
    };
    $('<button type="button" class="btn btn-link">'
            + '<i class="i-upload"><?php _e('附件'); ?></i></button>')
            .prependTo('.submit .right')
            .click(function() {
                if (workspace && typeof workspace.showPanel === 'function') {
                    workspace.showPanel('#tab-files');
                } else {
                    $('#tab-files-btn').trigger('click');
                }
                return false;
            });

    editor.hooks.chain('enterFakeFullScreen', function () {
        return enterShellFullscreen();
    });

    editor.hooks.chain('enterFullScreen', function () {
        return enterShellFullscreen();
    });

    editor.hooks.chain('exitFullScreen', function () {
        workspace && workspace.setFullscreen(false);
        return false;
    });

    $(window).on('resize', function () {
        if (!(workspace && typeof workspace.isFullscreen === 'function' && workspace.isFullscreen())) {
            return;
        }

        applyFullscreenHeight();
    });

    editor.hooks.chain('commandExecuted', function () {
        textarea.trigger('input');
    });

    editor.hooks.chain('save', function () {
        Typecho.savePost();
    });

    function initMarkdown() {
        editor.run();
        bindNativeFullscreenControl();
        syncNativeFullscreenControl(workspace && typeof workspace.isFullscreen === 'function' && workspace.isFullscreen());

        const imageButton = $('#wmd-image-button'),
            linkButton = $('#wmd-link-button');

        Typecho.insertFileToEditor = function (file, url, isImage) {
            const button = isImage ? imageButton : linkButton;

            options.strings[isImage ? 'imagename' : 'linkname'] = file;
            button.trigger('click');

            let checkDialog = setInterval(function () {
                if ($('.wmd-prompt-dialog').length > 0) {
                    $('.wmd-prompt-dialog input').val(url).select();
                    clearInterval(checkDialog);
                }
            }, 10);
        };

        Typecho.uploadComplete = function (attachment) {
            Typecho.insertFileToEditor(attachment.title, attachment.url, attachment.isImage);
        };

        const edittab = $('.editor').append('<div class="wmd-edittab"><a href="#wmd-editarea" class="active"><?php _e('撰写'); ?></a><a href="#wmd-preview"><?php _e('预览'); ?></a></div>'),
            editarea = $(textarea.parent()).attr("id", "wmd-editarea");

        $(".wmd-edittab a").click(function() {
            $(".wmd-edittab a").removeClass('active');
            $(this).addClass("active");
            $("#wmd-editarea, #wmd-preview").addClass("wmd-hidetab");
        
            const selected_tab = $(this).attr("href");
            $(selected_tab).removeClass("wmd-hidetab");

            if (selected_tab === "#wmd-preview") {
                $("#wmd-button-row").addClass("wmd-visualhide");
            } else {
                $("#wmd-button-row").removeClass("wmd-visualhide");
            }

            $("#wmd-preview").outerHeight($("#wmd-editarea").innerHeight());

            return false;
        });

        textarea.bind('paste', function (e) {
            const items = (e.clipboardData || e.originalEvent.clipboardData).items;

            for (const item of items) {
                if (item.kind === 'file') {
                    const file = item.getAsFile();

                    if (file.size > 0) {
                        if (!file.name) {
                            file.name = (new Date()).toISOString().replace(/\..+$/, '')
                                + '.' + file.type.split('/').pop();
                        }

                        Typecho.uploadFile(file);
                    }
                }
            }
        });
    }

    if (workspace && typeof workspace.isFullscreen === 'function') {
        syncShellFullscreen(workspace.isFullscreen());
        const formNode = form.get(0);
        if (formNode) {
            formNode.addEventListener('tr:fullscreen-change', function (event) {
                const active = !!(event.detail && event.detail.active);
                syncShellFullscreen(active);
                syncNativeFullscreenControl(active);
            });
        }
        document.addEventListener('keydown', handleShellShortcut, true);
    }

    if (isMarkdown) {
        initMarkdown();
    } else {
        const notice = $('<div class="message notice"><?php _e('这篇文章不是由Markdown语法创建的, 继续使用Markdown编辑它吗?'); ?> '
            + '<button class="btn btn-xs primary yes"><?php _e('是'); ?></button> ' 
            + '<button class="btn btn-xs no"><?php _e('否'); ?></button></div>')
            .hide().insertBefore(textarea).slideDown();

        $('.yes', notice).click(function () {
            notice.remove();
            $('<input type="hidden" name="markdown" value="1" />').appendTo('.submit');
            initMarkdown();
        });

        $('.no', notice).click(function () {
            notice.remove();
        });
    }
});
</script>
<?php endif; ?>
