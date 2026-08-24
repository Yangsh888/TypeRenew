<?php if(!defined('__TYPECHO_ADMIN__')) exit; ?>
<script>
$(document).ready(function () {
    var nextFieldId = $('#custom-field .fields .field').length;

    function attachDeleteEvent (el) {
        $('button.btn-xs', el).click(function () {
            if (confirm('<?php _e('确认要删除此字段吗?'); ?>')) {
                $(this).parents('li').fadeOut(function () {
                    $(this).remove();
                });

                $(this).parents('form').trigger('change');
            }
        });
    }

    $('#custom-field .fields .field').each(function () {
        attachDeleteEvent(this);
    });

    $('#custom-field button.operate-add').click(function () {
        var id = 'custom-field-' + nextFieldId++;
        var html = '<li class="field"><div class="field-name"><label class="sr-only" for="' + id + '-name"><?php _e('字段名称'); ?></label><input id="' + id + '-name" type="text" name="fieldNames[]" placeholder="<?php _e('字段名称'); ?>" pattern="^[_a-zA-Z][_a-zA-Z0-9]*$" oninput="this.reportValidity()" class="text-s w-100">'
                + '<label class="sr-only" for="' + id + '-type"><?php _e('字段类型'); ?></label><select name="fieldTypes[]" id="' + id + '-type">'
                + '<option value="str"><?php _e('字符'); ?></option>'
                + '<option value="int"><?php _e('整数'); ?></option>'
                + '<option value="float"><?php _e('小数'); ?></option>'
                + '<option value="json"><?php _e('JSON 结构'); ?></option>'
                + '</select></div>'
                + '<div class="field-value"><label class="sr-only" for="' + id + '-value"><?php _e('字段值'); ?></label><textarea id="' + id + '-value" name="fieldValues[]" placeholder="<?php _e('字段值'); ?>" class="text-s w-100" rows="2"></textarea>'
                + '<button type="button" class="btn btn-xs"><?php _e('删除'); ?></button></div></li>',
            el = $(html).hide().appendTo('#custom-field .fields').fadeIn();

        attachDeleteEvent(el);
    });
});
</script>
