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
    private static ?string $loadedTheme = null;

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
            return self::declaresFunction($configFile, 'themeConfig');
        }

        return false;
    }

    public static function loadThemeFunctions(?string $theme = null): bool
    {
        $options = Options::alloc();
        $theme = $theme ?? $options->theme;
        $configFile = $options->themeFile($theme, 'functions.php');

        if ($options->missingTheme || !file_exists($configFile)) {
            return false;
        }

        if (self::$loadedTheme !== $theme) {
            require_once $configFile;
            self::$loadedTheme = $theme;
        }

        return true;
    }

    public function config(): Form
    {
        $theme = Options::alloc()->theme;
        self::loadThemeFunctions($theme);
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

    private static function declaresFunction(string $file, string $functionName): bool
    {
        $source = file_get_contents($file);
        if (!is_string($source) || $source === '') {
            return false;
        }

        $tokens = token_get_all($source);
        $level = 0;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token === '{') {
                $level++;
                continue;
            }

            if ($token === '}') {
                $level = max(0, $level - 1);
                continue;
            }

            if (!is_array($token) || $token[0] !== T_FUNCTION || $level !== 0) {
                continue;
            }

            for ($j = $i + 1; $j < $count; $j++) {
                $next = $tokens[$j];

                if (!is_array($next)) {
                    continue;
                }

                if ($next[0] === T_STRING) {
                    if ($next[1] === $functionName) {
                        return true;
                    }

                    break;
                }

                if (!in_array($next[0], [T_WHITESPACE, T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG], true)) {
                    break;
                }
            }
        }

        return false;
    }
}
