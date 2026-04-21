<?php if (!defined('__TYPECHO_ADMIN__')) exit; ?>
<?php \Typecho\Plugin::factory('admin/write-js.php')->call('write'); ?>
<?php \Widget\Metas\Tag\Cloud::alloc('sort=count&desc=1&limit=200')->to($tags); ?>

<script src="<?php $options->adminStaticUrl('js', 'timepicker.js'); ?>"></script>
<script src="<?php $options->adminStaticUrl('js', 'tokeninput.js'); ?>"></script>
<script>
$(document).ready(function() {
    $('#date').mask('9999-99-99 99:99').datetimepicker({
        currentText     :   '<?php _e('现在'); ?>',
        prevText        :   '<?php _e('上一月'); ?>',
        nextText        :   '<?php _e('下一月'); ?>',
        monthNames      :   ['<?php _e('一月'); ?>', '<?php _e('二月'); ?>', '<?php _e('三月'); ?>', '<?php _e('四月'); ?>',
            '<?php _e('五月'); ?>', '<?php _e('六月'); ?>', '<?php _e('七月'); ?>', '<?php _e('八月'); ?>',
            '<?php _e('九月'); ?>', '<?php _e('十月'); ?>', '<?php _e('十一月'); ?>', '<?php _e('十二月'); ?>'],
        dayNames        :   ['<?php _e('星期日'); ?>', '<?php _e('星期一'); ?>', '<?php _e('星期二'); ?>',
            '<?php _e('星期三'); ?>', '<?php _e('星期四'); ?>', '<?php _e('星期五'); ?>', '<?php _e('星期六'); ?>'],
        dayNamesShort   :   ['<?php _e('周日'); ?>', '<?php _e('周一'); ?>', '<?php _e('周二'); ?>', '<?php _e('周三'); ?>',
            '<?php _e('周四'); ?>', '<?php _e('周五'); ?>', '<?php _e('周六'); ?>'],
        dayNamesMin     :   ['<?php _e('日'); ?>', '<?php _e('一'); ?>', '<?php _e('二'); ?>', '<?php _e('三'); ?>',
            '<?php _e('四'); ?>', '<?php _e('五'); ?>', '<?php _e('六'); ?>'],
        closeText       :   '<?php _e('完成'); ?>',
        timeOnlyTitle   :   '<?php _e('选择时间'); ?>',
        timeText        :   '<?php _e('时间'); ?>',
        hourText        :   '<?php _e('时'); ?>',
        amNames         :   ['<?php _e('上午'); ?>', 'A'],
        pmNames         :   ['<?php _e('下午'); ?>', 'P'],
        minuteText      :   '<?php _e('分'); ?>',
        secondText      :   '<?php _e('秒'); ?>',

        dateFormat      :   'yy-mm-dd',
        timezone        :   <?php $options->timezone(); ?> / 60,
        hour            :   (new Date()).getHours(),
        minute          :   (new Date()).getMinutes()
    });

    $('#title').select();

    Typecho.editorResize('text', '<?php $security->index('/action/ajax?do=editorResize'); ?>');

    const tags = $('#tags'), tagsPre = [];

    if (tags.length > 0) {
        const items = tags.val().split(',');
        for (let i = 0; i < items.length; i ++) {
            const tag = items[i];

            if (!tag) {
                continue;
            }

            tagsPre.push({
                id      :   tag,
                tags    :   tag
            });
        }

        tags.tokenInput(<?php
        $data = array();
        while ($tags->next()) {
            $data[] = array(
                'id'    =>  $tags->name,
                'tags'  =>  $tags->name
            );
        }
        echo json_encode($data);
        ?>, {
            propertyToSearch:   'tags',
            tokenValue      :   'tags',
            searchDelay     :   0,
            preventDuplicates   :   true,
            animateDropdown :   false,
            hintText        :   '<?php _e('请输入标签名'); ?>',
            noResultsText   :   '<?php _e('此标签不存在, 按回车创建'); ?>',
            prePopulate     :   tagsPre,

            onResult        :   function (result, query, val) {
                val = val.replace(/<|>|&|"|'/g, '');

                if (!query) {
                    return result;
                }

                if (!result) {
                    result = [];
                }

                if (!result[0] || result[0]['id'] !== query) {
                    result.unshift({
                        id      :   val,
                        tags    :   val
                    });
                }

                return result.slice(0, 5);
            }
        });

        $('#token-input-tags').focus(function() {
            const t = $('.token-input-dropdown'),
                offset = t.outerWidth() - t.width();
            t.width($('.token-input-list').outerWidth() - offset);
        });
    }

    const slug = $('#slug');

    if (slug.length > 0) {
        const wrap = $('<div />').css({
            'position'  :   'relative',
            'display'   :   'inline-block'
        }),
        justifySlug = $('<pre />').css({
            'display'   :   'block',
            'visibility':   'hidden',
            'height'    :   slug.height(),
            'padding'   :   '0 2px',
            'margin'    :   0
        }).insertAfter(slug.wrap(wrap).css({
            'left'      :   0,
            'top'       :   0,
            'minWidth'  :   '5px',
            'position'  :   'absolute',
            'width'     :   '100%'
        }));

        function justifySlugWidth() {
            const val = slug.val();
            justifySlug.text(val.length > 0 ? val : '     ');
        }

        slug.bind('input propertychange', justifySlugWidth);
        justifySlugWidth();
    }

    const form = $('form[name=write_post],form[name=write_page]'),
        idInput = $('input[name=cid]'),
        draft = $('input[name=draft]'),
        btnPreview = $('#btn-preview'),
        autoSave = $('<span id="auto-save-message"></span>').prependTo('.left');

    let cid = idInput.val(),
        draftId = draft.length > 0 ? draft.val() : 0,
        changed = false,
        written = false,
        lastSaveTime = null;

    form.on('write', function () {
        written = true;
        form.trigger('datachange');
    });

    form.on('change', function () {
        if (written) {
            form.trigger('datachange');
        }
    });

    $('button[name=do]').click(function () {
        $('input[name=do]').val($(this).val());
    });

    function showNotice(text, type) {
        if (window.TypechoNotice && typeof window.TypechoNotice.show === 'function') {
            window.TypechoNotice.show(type || 'error', [text]);
            return;
        }
        alert(text);
    }

    $(window).bind('beforeunload', function () {
        if (changed && !form.hasClass('submitting')) {
            return '<?php _e('内容已经改变尚未保存, 您确认要离开此页面吗?'); ?>';
        }
    });

    function hasPendingUploads() {
        if (!window.Typecho || typeof window.Typecho.getUploadPending !== 'function') {
            return false;
        }

        return (parseInt(window.Typecho.getUploadPending() || '0', 10) || 0) > 0;
    }

    function canSave() {
        if (!hasPendingUploads()) {
            return true;
        }

        showNotice('<?php _e('正在上传附件，请等待上传完成后再提交'); ?>', 'notice');
        return false;
    }

    function applySaveResult(o) {
        lastSaveTime = o.time;
        cid = o.cid;
        draftId = o.draftId;
        idInput.val(cid);
        autoSave.text('<?php _e('已保存'); ?>' + ' (' + o.time + ')').effect('highlight', 1000);
    }

    function requestSave(cb, markClean) {
        if (!canSave()) {
            return false;
        }

        if (markClean) {
            changed = false;
        }

        autoSave.text('<?php _e('正在保存'); ?>');
        const data = new FormData(form.get(0));
        data.append('do', 'save');

        $.ajax({
            url: form.attr('action'),
            processData: false,
            contentType: false,
            type: 'POST',
            data: data,
            success: function (o) {
                applySaveResult(o);
                cb && cb(o);
            },
            error: function () {
                if (markClean) {
                    changed = true;
                }
                autoSave.text('<?php _e('保存失败, 请重试'); ?>');
            },
            complete: function () {
                form.trigger('submitted');
            }
        });

        return true;
    }

    Typecho.savePost = function(cb) {
        if (!changed) {
            cb && cb();
            return;
        }

        requestSave(function () {
            cb && cb();
        }, true);
    };

    Typecho.ensureCid = function (cb) {
        const cidNow = parseInt(idInput.val() || '0', 10) || 0;
        if (cidNow > 0) {
            cb && cb(cidNow);
            return;
        }

        requestSave(function (o) {
            cb && cb(o.cid);
        }, changed);
    };

    <?php if ($options->autoSave): ?>
    let saveTimer = null;
    let stopAutoSave = false;

    form.on('datachange', function () {
        changed = true;
        autoSave.text('<?php _e('尚未保存'); ?>' + (lastSaveTime ? ' (<?php _e('上次保存时间'); ?>: ' + lastSaveTime + ')' : ''));

        if (saveTimer) {
            clearTimeout(saveTimer);
        }

        saveTimer = setTimeout(function () {
            !stopAutoSave && Typecho.savePost();
        }, 3000);
    }).on('submit', function () {
        stopAutoSave = true;
    }).on('submitted', function () {
        stopAutoSave = false;
    });
    <?php else: ?>
    form.on('datachange', function () {
        changed = true;
    });
    <?php endif; ?>

    form.on('submit', function (e) {
        if (!canSave()) {
            e.preventDefault();
            return false;
        }

        form.addClass('submitting');
    });

    const dstOffset = (function () {
        const d = new Date(),
            jan = new Date(d.getFullYear(), 0, 1),
            jul = new Date(d.getFullYear(), 6, 1),
            stdOffset = Math.max(jan.getTimezoneOffset(), jul.getTimezoneOffset());

        return stdOffset - d.getTimezoneOffset();
    })();

    if (dstOffset > 0) {
        $('<input name="dst" type="hidden" />').appendTo(form).val(dstOffset);
    }

    $('<input name="timezone" type="hidden" />').appendTo(form).val(- (new Date).getTimezoneOffset() * 60);

    let isFullScreen = false;

    function getPreviewFrame() {
        return $('.preview-frame').get(0) || null;
    }

    function previewData(cid) {
        if (getPreviewFrame()) {
            return;
        }

        isFullScreen = $(document.body).hasClass('fullscreen');
        $(document.body).addClass('fullscreen preview');

        const frame = $('<iframe frameborder="0" class="preview-frame preview-loading"></iframe>')
            .attr('src', './preview.php?cid=' + cid)
            .attr('sandbox', 'allow-same-origin allow-scripts')
            .appendTo(document.body);

        frame.load(function () {
            frame.removeClass('preview-loading');
        });
    }

    function cancelPreview() {
        if (!isFullScreen) {
            $(document.body).removeClass('fullscreen');
        }

        $(document.body).removeClass('preview');
        $('.preview-frame').remove();
    }

    $('#btn-cancel-preview').click(cancelPreview);

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && getPreviewFrame()) {
            cancelPreview();
        }
    });

    $(window).bind('message', function (e) {
        const frame = getPreviewFrame();
        const source = e.originalEvent && e.originalEvent.source ? e.originalEvent.source : null;

        if (
            frame
            && source === frame.contentWindow
            && e.originalEvent.data === 'cancelPreview'
        ) {
            cancelPreview();
        }
    });

    btnPreview.click(function () {
        if (changed) {
            if (confirm('<?php _e('修改后的内容需要保存后才能预览, 是否保存?'); ?>')) {
                Typecho.savePost(function () {
                    previewData(draftId);
                });
            }
        } else if (!!draftId) {
            previewData(draftId);
        } else if (!!cid) {
            previewData(cid);
        }
    });

    $('#edit-secondary .typecho-option-tabs li').click(function() {
        $('#edit-secondary .typecho-option-tabs li.active').removeClass('active');
        $('#edit-secondary .tab-content').addClass('hidden');

        const activeTab = $(this).addClass('active').find('a').attr('href');
        $(activeTab).removeClass('hidden');

        return false;
    });

    $('#visibility').change(function () {
        const val = $(this).val(), password = $('#post-password');

        if ('password' === val) {
            password.removeClass('hidden');
        } else {
            password.addClass('hidden');
        }
    });

    $('.edit-draft-notice a').click(function () {
        if (confirm('<?php _e('您确认要删除这份草稿吗?'); ?>')) {
            window.location.href = $(this).attr('href');
        }

        return false;
    });
});
</script>
