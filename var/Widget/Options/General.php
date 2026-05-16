<?php

namespace Widget\Options;

use Typecho\Db\Exception;
use Typecho\I18n\GetText;
use Typecho\Widget\Helper\Form;
use Utils\Zone;
use Widget\ActionInterface;
use Widget\Base\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class General extends Options implements ActionInterface
{
    use EditTrait;

    public function checkLang(string $lang): bool
    {
        $langs = self::getLangs();
        return isset($langs[$lang]);
    }

    public function checkTimezoneId(string $timezoneId): bool
    {
        return Zone::normalizeId($timezoneId) !== null;
    }

    /**
     * 获取语言列表
     *
     * @return array
     */
    public static function getLangs(): array
    {
        $dir = defined('__TYPECHO_LANG_DIR__') ? __TYPECHO_LANG_DIR__ : __TYPECHO_ROOT_DIR__ . '/usr/langs';
        $files = glob($dir . '/*.mo');
        $langs = ['zh_CN' => '简体中文'];

        if (!empty($files)) {
            foreach ($files as $file) {
                $getText = new GetText($file, false);
                [$name] = explode('.', basename($file));
                $title = $getText->translate('lang', $count);
                $langs[$name] = $count > - 1 ? $title : $name;
            }

            ksort($langs);
        }

        return $langs;
    }

    /**
     * 过滤掉可执行的后缀名
     *
     * @param string $ext
     */
    public function removeShell(string $ext): bool
    {
        return !preg_match("/^(php|php3|php4|php5|php7|php8|phtml|pht|phar|cgi|shtml|sh|asp|aspx|jsp|rb|py|pl|dll|exe|bat|cmd|com)$/i", $ext);
    }

    public function updateGeneralSettings()
    {
        $this->validateFormOrGoBack($this->form());

        $before = [
            'siteUrl' => (string) ($this->options->siteUrl ?? ''),
            'title' => (string) ($this->options->title ?? ''),
            'description' => (string) ($this->options->description ?? ''),
            'keywords' => (string) ($this->options->keywords ?? ''),
        ];

        $settings = $this->request->from(
            'title',
            'description',
            'keywords',
            'allowRegister',
            'allowXmlRpc',
            'lang',
            'timezoneId'
        );
        $settings['attachmentTypes'] = $this->request->getArray('attachmentTypes');
        $settings['timezoneId'] = (string) ($settings['timezoneId'] ?? '');
        $settings['timezone'] = Zone::offsetAt(
            $settings['timezoneId'],
            (int) ($this->options->timezone ?? 0),
            \Typecho\Date::time()
        );

        if (!defined('__TYPECHO_SITE_URL__')) {
            $settings['siteUrl'] = rtrim((string) $this->request->get('siteUrl', ''), '/');
        }

        $attachmentTypes = [];
        if ($this->isEnableByCheckbox($settings['attachmentTypes'], '@image@')) {
            $attachmentTypes[] = '@image@';
        }

        if ($this->isEnableByCheckbox($settings['attachmentTypes'], '@media@')) {
            $attachmentTypes[] = '@media@';
        }

        if ($this->isEnableByCheckbox($settings['attachmentTypes'], '@doc@')) {
            $attachmentTypes[] = '@doc@';
        }

        $attachmentTypesOther = $this->request->filter('trim', 'strtolower')->get('attachmentTypesOther');
        if ($this->isEnableByCheckbox($settings['attachmentTypes'], '@other@') && !empty($attachmentTypesOther)) {
            $types = implode(
                ',',
                array_filter(array_map('trim', explode(',', $attachmentTypesOther)), [$this, 'removeShell'])
            );

            if (!empty($types)) {
                $attachmentTypes[] = $types;
            }
        }

        $settings['attachmentTypes'] = implode(',', $attachmentTypes);
        $this->persistOptions($settings);
        self::pluginHandle()->call('finishUpdate', $before, array_merge($before, $settings), $this);

        $this->saveSuccessAndGoBack();
    }

    /**
     * @return Form
     */
    public function form(): Form
    {
        $form = new Form($this->security->getIndex('/action/options-general'), Form::POST_METHOD);

        $title = new Form\Element\Text('title', null, $this->options->title, _t('站点名称'), _t('您设置的站点名称，会展示在网页的标题位置'));
        $title->input->setAttribute('class', 'w-100');
        $form->addInput($title->addRule('required', _t('请填写站点名称'))
            ->addRule('xssCheck', _t('请不要在站点名称中使用特殊字符')));

        if (!defined('__TYPECHO_SITE_URL__')) {
            $siteUrl = new Form\Element\Url(
                'siteUrl',
                null,
                $this->options->originalSiteUrl,
                _t('站点地址'),
                _t('站点地址主要用于生成内容的永久链接') . ($this->options->originalSiteUrl == $this->options->rootUrl ?
                    '' : '</p><p class="message notice mono">'
                    . _t('当前地址 <strong>%s</strong> 与上述设定值不一致', $this->options->rootUrl))
            );
            $siteUrl->input->setAttribute('class', 'w-100 mono');
            $form->addInput($siteUrl->addRule('required', _t('请填写站点地址'))
                ->addRule('url', _t('请填写一个合法的URL地址')));
        }

        $description = new Form\Element\Text(
            'description',
            null,
            $this->options->description,
            _t('站点描述'),
            _t('站点描述将显示在网页代码的头部区域')
        );
        $form->addInput($description->addRule('xssCheck', _t('请不要在站点描述中使用特殊字符')));

        $keywords = new Form\Element\Text(
            'keywords',
            null,
            $this->options->keywords,
            _t('关键词'),
            _t('请以半角逗号 "," 分割多个关键字')
        );
        $form->addInput($keywords->addRule('xssCheck', _t('请不要在关键词中使用特殊字符')));

        $allowRegister = new Form\Element\Radio(
            'allowRegister',
            ['0' => _t('不允许'), '1' => _t('允许')],
            $this->options->allowRegister,
            _t('是否允许注册'),
            _t('允许访问者注册到你的网站，默认的注册用户不享有任何写入权限')
        );
        $form->addInput($allowRegister);

        $allowXmlRpc = new Form\Element\Radio(
            'allowXmlRpc',
            ['0' => _t('关闭'), '1' => _t('仅关闭 Pingback 接口'), '2' => _t('打开')],
            $this->options->allowXmlRpc,
            _t('XMLRPC 接口')
        );
        $form->addInput($allowXmlRpc);

        _t('lang');

        $langs = self::getLangs();

        if (count($langs) > 1) {
            $lang = new Form\Element\Select('lang', $langs, $this->options->lang, _t('语言'));
            $form->addInput($lang->addRule([$this, 'checkLang'], _t('所选择的语言包不存在')));
        }

        $timezone = new Form\Element\Text(
            'timezoneId',
            null,
            $this->options->timezoneId,
            _t('时区'),
            _t('请输入 IANA 时区标识符，例如 Asia/Shanghai、America/New_York、Europe/Berlin。系统会自动兼容旧版固定偏移配置。')
        );
        $timezone->input->setAttribute('class', 'w-100 mono');
        $form->addInput($timezone->addRule('required', _t('请填写时区'))
            ->addRule([$this, 'checkTimezoneId'], _t('请输入有效的 IANA 时区标识符')));

        $attachmentTypesOptionsResult = (null != trim((string) $this->options->attachmentTypes)) ?
            array_map('trim', explode(',', $this->options->attachmentTypes)) : [];
        $attachmentTypesOptionsValue = [];

        if (in_array('@image@', $attachmentTypesOptionsResult)) {
            $attachmentTypesOptionsValue[] = '@image@';
        }

        if (in_array('@media@', $attachmentTypesOptionsResult)) {
            $attachmentTypesOptionsValue[] = '@media@';
        }

        if (in_array('@doc@', $attachmentTypesOptionsResult)) {
            $attachmentTypesOptionsValue[] = '@doc@';
        }

        $attachmentTypesOther = array_diff($attachmentTypesOptionsResult, $attachmentTypesOptionsValue);
        $attachmentTypesOtherValue = '';
        if (!empty($attachmentTypesOther)) {
            $attachmentTypesOptionsValue[] = '@other@';
            $attachmentTypesOtherValue = implode(',', $attachmentTypesOther);
        }

        $attachmentTypesOptions = [
            '@image@' => _t('图片文件') . ' <code>(gif jpg jpeg png tiff bmp webp avif)</code>',
            '@media@' => _t('多媒体文件') . ' <code>(mp3 mp4 mov wmv wma rmvb rm avi flv ogg oga ogv)</code>',
            '@doc@'   => _t('常用档案文件') . ' <code>(txt doc docx xls xlsx ppt pptx zip rar pdf)</code>',
            '@other@' => _t(
                '其他格式 %s',
                ' <input type="text" class="w-50 text-s mono" name="attachmentTypesOther" value="'
                . htmlspecialchars($attachmentTypesOtherValue) . '" />'
            ),
        ];

        $attachmentTypes = new Form\Element\Checkbox(
            'attachmentTypes',
            $attachmentTypesOptions,
            $attachmentTypesOptionsValue,
            _t('允许上传的文件类型'),
            _t('用逗号 "," 将后缀名隔开, 例如: %s', '<code>cpp, h, mak</code>')
        );
        $form->addInput($attachmentTypes->multiMode());

        $submit = new Form\Element\Submit('submit', null, _t('保存设置'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        return $form;
    }

    public function action()
    {
        $this->user->pass('administrator');
        $this->security->protect();
        $this->on($this->request->isPost())->updateGeneralSettings();
        $this->response->redirect($this->options->adminUrl);
    }
}
