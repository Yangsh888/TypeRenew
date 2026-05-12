<?php

namespace Widget\Themes;

use Typecho\Widget\Exception;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Submit;
use Widget\Base\Options as BaseOptions;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Config extends BaseOptions
{
    /**
     * @throws Exception|\Typecho\Db\Exception
     */
    public function execute()
    {
        $this->user->pass('administrator');

        if (!self::isExists()) {
            throw new Exception(_t('外观配置功能不存在'), 404);
        }
    }

    public static function isExists(?string $theme = null): bool
    {
        $options = Options::alloc();
        $theme = $theme ?? $options->theme;
        $configFile = $options->themeFile($theme, 'functions.php');

        if (!$options->missingTheme && file_exists($configFile)) {
            require_once $configFile;

            if (function_exists('themeConfig')) {
                return true;
            }
        }

        return false;
    }

    public function config(): Form
    {
        $theme = Options::alloc()->theme;
        $form = new Form(
            $this->security->getIndex('/action/themes-edit?config=' . $theme),
            Form::POST_METHOD
        );
        themeConfig($form);
        $inputs = $form->getInputs();
        $saved = json_decode((string) $this->options->__get('theme:' . $theme), true);
        $saved = is_array($saved) ? $saved : [];

        if (!empty($inputs)) {
            foreach ($inputs as $key => $val) {
                $input = $form->getInput($key);
                if ($input === null) {
                    continue;
                }

                if (array_key_exists((string) $key, $saved)) {
                    $input->value($saved[(string) $key]);
                    continue;
                }

                if (isset($this->options->{$key})) {
                    $input->value($this->options->{$key});
                }
            }
        }

        $submit = new Submit(null, null, _t('保存设置'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);
        return $form;
    }
}
