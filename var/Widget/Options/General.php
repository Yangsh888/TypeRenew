<?php

namespace Widget\Options;

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
        return Zone::normalizeStoredId($timezoneId) !== null;
    }

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

    public function removeShell(string $ext): bool
    {
        return preg_match('/^[a-z0-9]+$/i', $ext) === 1
            && !preg_match("/^(php|php3|php4|php5|php7|php8|phtml|pht|phar|cgi|shtml|html|htm|sh|asp|aspx|jsp|rb|py|pl|dll|exe|bat|cmd|com)$/i", $ext);
    }

    public static function ipSourcePresets(): array
    {
        return [
            'REMOTE_ADDR'           => _t('REMOTE_ADDR（默认，直连）'),
            'HTTP_X_FORWARDED_FOR'  => _t('X-Forwarded-For（腾讯云、阿里云、七牛等）'),
            'HTTP_X_REAL_IP'        => _t('X-Real-IP（又拍云、百度云加速等）'),
            'HTTP_CF_CONNECTING_IP' => _t('CF-Connecting-IP（Cloudflare）'),
            'HTTP_CLIENT_IP'        => _t('Client-IP（通用代理）'),
        ];
    }

    public function normalizeIpSource(string $source): string
    {
        $source = strtoupper(trim($source));
        if ($source === '') {
            return 'REMOTE_ADDR';
        }

        $source = preg_replace('/[^A-Z0-9_]/', '_', $source) ?? '';
        if ($source === '' || $source === 'REMOTE_ADDR') {
            return 'REMOTE_ADDR';
        }

        if (!str_starts_with($source, 'HTTP_')) {
            $source = 'HTTP_' . $source;
        }

        return $source;
    }

    private function ipSourcePreview(string $source): string
    {
        $value = (string) $this->request->getServer($source, '');
        if ($value === '') {
            return '';
        }

        foreach (explode(',', $value) as $candidate) {
            $candidate = trim($candidate);
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
                return $candidate;
            }
        }

        return '';
    }

    private function addIpSourceInput(Form $form): void
    {
        $current = $this->normalizeIpSource((string) ($this->options->ipSource ?? 'REMOTE_ADDR'));
        $presets = self::ipSourcePresets();
        $isCustom = !array_key_exists($current, $presets);

        $options = [];
        foreach ($presets as $key => $label) {
            $preview = $this->ipSourcePreview($key);
            $options[$key] = $preview !== '' ? $label . ' — ' . $preview : $label;
        }
        $options['custom'] = _t('自定义请求头');

        $ipSource = new Form\Element\Select(
            'ipSource',
            $options,
            $isCustom ? 'custom' : $current,
            _t('客户端 IP 获取来源'),
            _t('当站点位于 CDN 或反向代理之后时，请选择对应的来源头以获取访客真实 IP。')
            . '<br />' . _t('默认 REMOTE_ADDR 最安全；其余来源头可被伪造，请仅在确认前端代理会覆盖该头时选用。')
        );
        $form->addInput($ipSource);

        $ipSourceCustom = new Form\Element\Text(
            'ipSourceCustom',
            null,
            $isCustom ? $current : '',
            _t('自定义请求头名称'),
            _t('当选择“自定义请求头”时填写，例如 HTTP_TRUE_CLIENT_IP、HTTP_ALI_CDN_REAL_IP。可省略 HTTP_ 前缀。')
        );
        $ipSourceCustom->input->setAttribute('class', 'w-100 mono');
        $form->addInput($ipSourceCustom);
    }

    private function resolveIpSourceInput(): string
    {
        $choice = (string) $this->request->get('ipSource', 'REMOTE_ADDR');

        if ($choice === 'custom') {
            return $this->normalizeIpSource((string) $this->request->get('ipSourceCustom', ''));
        }

        return array_key_exists($choice, self::ipSourcePresets()) ? $choice : 'REMOTE_ADDR';
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
        $settings['ipSource'] = $this->resolveIpSourceInput();
        $settings['timezone'] = Zone::offsetAt(
            $settings['timezoneId'],
            (int) ($this->options->timezone ?? 0),
            \Typecho\Date::time()
        );

        if (!defined('__TYPECHO_SITE_URL__')) {
            $settings['siteUrl'] = rtrim((string) $this->request->get('siteUrl', ''), '/');
        }

        $attachmentTypes = [];
        $settings['allowXmlRpc'] = in_array((string) ($settings['allowXmlRpc'] ?? '0'), ['0', '1', '2'], true)
            ? (int) $settings['allowXmlRpc']
            : 0;

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
            _t('XMLRPC 接口'),
            _t('如无远程写作、离线客户端或第三方同步需求，建议保持关闭') . '<br />'
            . _t('“仅关闭 Pingback 接口”可保留常规 XML-RPC，同时避免站点接收 Pingback')
        );
        $form->addInput($allowXmlRpc);

        $this->addIpSourceInput($form);

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
            _t('请输入 IANA 时区标识符，例如 Asia/Shanghai、America/New_York、Europe/Berlin。系统会自动兼容旧版配置')
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
            _t('用逗号 "," 将后缀名隔开, 例如: %s', '<code>cpp,h,mak</code>') . '<br />'
            . _t('核心会校验扩展名，并对明显的危险内容类型做基础拦截；如需更严格策略，建议配合 RenewShield 插件')
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
