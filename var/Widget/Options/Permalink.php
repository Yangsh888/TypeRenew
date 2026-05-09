<?php

namespace Widget\Options;

use Typecho\Common;
use Typecho\Cookie;
use Typecho\Db\Exception;
use Typecho\Router\Parser;
use Typecho\Widget\Helper\Form;
use Utils\Rewrite\Manager;
use Widget\ActionInterface;
use Widget\Base\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Permalink extends Options implements ActionInterface
{
    protected function routingTable(): array
    {
        $routingTable = $this->options->routingTable;

        if (isset($routingTable[0]) && is_array($routingTable[0])) {
            unset($routingTable[0]);
        }

        $defaults = array_intersect_key(
            \Utils\Defaults::routingTable(),
            array_flip(['post', 'page', 'category', 'category_page', 'archive', 'archive_page'])
        );

        foreach ($defaults as $key => $value) {
            if (!isset($routingTable[$key]) || !is_array($routingTable[$key])) {
                $routingTable[$key] = $value;
                continue;
            }

            if (!isset($routingTable[$key]['url']) || !is_string($routingTable[$key]['url']) || $routingTable[$key]['url'] === '') {
                $routingTable[$key]['url'] = $value['url'];
            }
        }

        return $routingTable;
    }

    /**
     * 检查pagePattern里是否含有必要参数
     *
     * @param mixed $value
     * @return bool
     */
    public function checkPagePattern($value): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        $value = (string) $value;

        return strpos($value, '{slug}') !== false
            || strpos($value, '{cid}') !== false
            || strpos($value, '{directory}') !== false;
    }

    /**
     * 检查categoryPattern里是否含有必要参数
     *
     * @param mixed $value
     * @return bool
     */
    public function checkCategoryPattern($value): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        $value = (string) $value;

        return strpos($value, '{slug}') !== false
            || strpos($value, '{mid}') !== false
            || strpos($value, '{directory}') !== false;
    }

    /**
     * 执行更新动作
     *
     * @throws Exception
     */
    public function updatePermalinkSettings()
    {
        $customPattern = $this->request->get('customPattern');
        $postPattern = $this->request->get('postPattern');
        $pagePattern = $this->request->get('pagePattern');
        $categoryPattern = $this->request->get('categoryPattern');
        $customPattern = is_scalar($customPattern) ? (string) $customPattern : '';
        $postPattern = is_scalar($postPattern) ? (string) $postPattern : '';
        $pagePattern = is_scalar($pagePattern) ? (string) $pagePattern : '';
        $categoryPattern = is_scalar($categoryPattern) ? (string) $categoryPattern : '';
        $rewriteServer = (string) $this->request->get('rewriteServer', 'nginx');
        $rewriteMode = (string) $this->request->get('rewriteMode', 'manual');

        $before = [
            'rewrite' => (string) ($this->options->rewrite ?? ''),
            'routingTable' => $this->serializeRoutingTable($this->options->routingTable),
            'rewriteServer' => (string) ($this->options->rewriteServer ?? ''),
            'rewriteMode' => (string) ($this->options->rewriteMode ?? ''),
            'rewriteStatus' => (string) ($this->options->rewriteStatus ?? ''),
        ];

        if ($this->form()->validate()) {
            $this->rememberCustomPattern($customPattern);
            $this->response->goBack();
        }

        if ('custom' == $postPattern) {
            $postPattern = '/' . ltrim($this->encodeRule($customPattern), '/');
        }

        $settings = defined('__TYPECHO_REWRITE__') ? [] : $this->request->from('rewrite');
        $rewrite = defined('__TYPECHO_REWRITE__')
            ? ((bool) __TYPECHO_REWRITE__ ? '1' : '0')
            : (string) ($settings['rewrite'] ?? '0');
        $routingTable = $this->routingTable();
        $routingTable['post']['url'] = $postPattern;
        $routingTable['page']['url'] = '/' . ltrim($this->encodeRule($pagePattern), '/');
        $routingTable['category']['url'] = '/' . ltrim($this->encodeRule($categoryPattern), '/');
        $routingTable['category_page']['url'] = rtrim($routingTable['category']['url'], '/') . '/[page:digital]/';

        if ($this->hasRouteConflict($routingTable, 'post')) {
            $this->failCustomPattern($customPattern, _t('当前文章固定链接与现有路由规则冲突，请调整自定义格式后再保存。'));
        }

        if ($this->hasRouteConflict($routingTable, 'page')) {
            $this->failCustomPattern($customPattern, _t('当前独立页面路径与现有路由规则冲突，请调整后再保存。'));
        }

        if ($this->hasRouteConflict($routingTable, 'category', ['category_page'])) {
            $this->failCustomPattern($customPattern, _t('当前分类路径与现有路由规则冲突，请调整后再保存。'));
        }
        $settings['routingTable'] = Common::jsonEncode($routingTable, 0, '{}');

        $settings = array_merge($settings, Manager::persistState([
            'rewriteServer' => $rewriteServer,
            'rewriteMode' => $rewriteMode,
        ], $rewrite));

        $apacheSnapshot = null;
        if (
            $settings['rewrite'] === $before['rewrite']
            && $settings['rewriteServer'] === $before['rewriteServer']
            && $settings['rewriteMode'] === $before['rewriteMode']
            && (string) ($settings['routingTable'] ?? $before['routingTable']) === $before['routingTable']
        ) {
            $settings['rewriteStatus'] = (string) ($this->options->rewriteStatus ?? $settings['rewriteStatus']);
            $settings['rewriteVerifiedAt'] = (string) ($this->options->rewriteVerifiedAt ?? $settings['rewriteVerifiedAt']);
            $settings['rewriteMessage'] = (string) ($this->options->rewriteMessage ?? $settings['rewriteMessage']);
        }

        $wasManaged = Manager::canManageApache(
            (string) ($this->options->rewriteServer ?? ''),
            (string) ($this->options->rewriteMode ?? '')
        );
        if ($settings['rewrite'] === '1' && Manager::canManageApache($settings['rewriteServer'], $settings['rewriteMode'])) {
            if (!Manager::canWriteApacheConfig()) {
                $this->noticeAndGoBack(_t('当前无法写入 .htaccess，请改为手动部署，或调整根目录 / .htaccess 的写权限。'), 'error');
            }

            $apacheSnapshot = Manager::snapshotApacheConfig();
            if (!Manager::writeManagedApache(Manager::basePath($this->options))) {
                $this->noticeAndGoBack(_t('写入 TypeRenew 的 Apache 重写规则失败，请检查 .htaccess 权限后重试。'), 'error');
            }
        } elseif ($wasManaged) {
            $apacheSnapshot = Manager::snapshotApacheConfig();
            if (!Manager::removeManagedApache()) {
                $this->noticeAndGoBack(_t('移除 TypeRenew 的 Apache 重写规则失败，请检查 .htaccess 权限后重试。'), 'error');
            }
        }

        try {
            $this->persistOptions($settings);
        } catch (\Throwable $throwable) {
            if (is_array($apacheSnapshot)) {
                Manager::restoreApacheConfig($apacheSnapshot);
            }
            throw $throwable;
        }
        self::pluginHandle()->call('finishUpdate', $before, [
            'rewrite' => (string) ($settings['rewrite'] ?? $before['rewrite']),
            'routingTable' => (string) ($settings['routingTable'] ?? $before['routingTable']),
            'rewriteServer' => (string) ($settings['rewriteServer'] ?? $before['rewriteServer']),
            'rewriteMode' => (string) ($settings['rewriteMode'] ?? $before['rewriteMode']),
            'rewriteStatus' => (string) ($settings['rewriteStatus'] ?? $before['rewriteStatus']),
        ], $this);

        $this->saveSuccessAndGoBack();
    }

    /**
     * @return Form
     */
    public function form(): Form
    {
        $form = new Form($this->security->getRootUrl('index.php/action/options-permalink'), Form::POST_METHOD);
        $rewriteState = Manager::status($this->options);

        if (!defined('__TYPECHO_REWRITE__')) {
            $rewrite = new Form\Element\Radio(
                'rewrite',
                ['0' => _t('不启用'), '1' => _t('启用')],
                $this->options->rewrite,
                _t('地址重写'),
                _t('启用后，系统将按照当前固定链接规则输出不含 <code>index.php</code> 的访问地址。')
                . '<br />' . _t('此设置不会修改已经保存的文章、页面和分类链接格式。')
            );
            $form->addInput($rewrite);
        }

        $rewriteServer = new Form\Element\Select(
            'rewriteServer',
            [
                'nginx' => _t('Nginx（推荐）'),
                'apache' => _t('Apache'),
                'other' => _t('其他服务器'),
            ],
            $rewriteState['server'],
            _t('Web 服务器'),
            _t('请选择当前站点使用的 Web 服务器，以便显示对应的配置示例。')
        );
        $form->addInput($rewriteServer);

        $modeOptions = [
            'manual' => _t('手动配置') . ' <span class="description">' . _t('请将下方配置示例写入服务器配置文件。') . '</span>',
            'managed' => _t('程序维护（Apache）') . ' <span class="description">' . _t('仅适用于 Apache，系统只维护 TypeRenew 写入的规则区块。') . '</span>',
        ];
        $rewriteMode = new Form\Element\Radio(
            'rewriteMode',
            $modeOptions,
            $rewriteState['managed'] ? 'managed' : 'manual',
            _t('规则部署方式'),
            $rewriteState['server'] === 'apache'
                ? _t('Apache 可选择手动配置，或由系统维护 TypeRenew 写入的规则区块。')
                : _t('Nginx 与其他服务器请根据下方示例手动完成配置。')
        );
        $form->addInput($rewriteMode->multiMode());

        $rewriteStatus = new Form\Element\Fake('rewriteStatusText', $this->rewriteStatusLabel($rewriteState['status']));
        $rewriteStatus->label(_t('重写状态'));
        $rewriteStatus->input->setAttribute('class', 'mono w-50');
        $rewriteStatus->input->setAttribute('readonly', 'readonly');
        $rewriteStatus->input->setAttribute('name', 'rewriteStatusText');
        $rewriteStatus->description($this->rewriteStatusDescription($rewriteState));
        $form->addInput($rewriteStatus);

        $nginxRules = new Form\Element\Textarea(
            'rewriteNginxRules',
            null,
            $rewriteState['nginxRules'],
            _t('Nginx 配置示例'),
            _t('请将以下内容加入当前站点的 Nginx 配置后保存生效。')
        );
        $nginxRules->input->setAttribute('class', 'mono w-100 tr-rewrite-code');
        $nginxRules->input->setAttribute('rows', '6');
        $nginxRules->input->setAttribute('readonly', 'readonly');
        $nginxRules->input->setAttribute('spellcheck', 'false');
        $form->addInput($nginxRules);

        $apacheRules = new Form\Element\Textarea(
            'rewriteApacheRules',
            null,
            $rewriteState['apacheRules'],
            _t('Apache 配置示例'),
            _t('可手动写入 <code>.htaccess</code>；如选择“程序维护（Apache）”，系统只维护 TypeRenew 写入的规则区块。')
        );
        $apacheRules->input->setAttribute('class', 'mono w-100 tr-rewrite-code');
        $apacheRules->input->setAttribute('rows', '7');
        $apacheRules->input->setAttribute('readonly', 'readonly');
        $apacheRules->input->setAttribute('spellcheck', 'false');
        $form->addInput($apacheRules);

        $patterns = [
            '/archives/[cid:digital]/'                                        => _t('默认结构')
                . ' <code>/archives/{cid}/</code>',
            '/archives/[slug].html'                                           => _t('别名结构')
                . ' <code>/archives/{slug}.html</code>',
            '/[year:digital:4]/[month:digital:2]/[day:digital:2]/[slug].html' => _t('日期结构')
                . ' <code>/{year}/{month}/{day}/{slug}.html</code>',
            '/[category]/[slug].html'                                         => _t('分类结构')
                . ' <code>/{category}/{slug}.html</code>'
        ];

        $routingTable = $this->routingTable();
        $postPatternValue = (string) $routingTable['post']['url'];

        $customPatternValue = null;
        if ($this->request->is('__typecho_form_item_postPattern')) {
            $customPatternValue = $this->request->get('__typecho_form_item_postPattern');
            Cookie::delete('__typecho_form_item_postPattern');
        } elseif (!isset($patterns[$postPatternValue])) {
            $customPatternValue = $this->decodeRule($postPatternValue);
        }
        $patterns['custom'] = _t('自定义结构') .
            ' <input type="text" class="w-50 text-s mono" name="customPattern" value="' . htmlspecialchars((string) $customPatternValue, ENT_QUOTES, 'UTF-8') . '" />';

        $postPattern = new Form\Element\Radio(
            'postPattern',
            $patterns,
            $postPatternValue,
            _t('文章固定链接'),
            _t('可用参数：<code>{cid}</code> 文章 ID、<code>{slug}</code> 文章别名、<code>{category}</code> 分类、<code>{directory}</code> 多级分类、<code>{year}</code> 年、<code>{month}</code> 月、<code>{day}</code> 日。')
            . '<br />' . _t('请选择适合站点的文章链接结构。链接结构一旦投入使用，不建议频繁修改。')
        );
        if ($customPatternValue) {
            $postPattern->value('custom');
        }
        $form->addInput($postPattern->multiMode());

        $pagePattern = new Form\Element\Text(
            'pagePattern',
            null,
            $this->decodeRule((string) $routingTable['page']['url']),
            _t('独立页面路径'),
            _t('可用参数：<code>{cid}</code> 页面 ID、<code>{slug}</code> 页面别名、<code>{directory}</code> 多级页面路径。')
            . '<br />' . _t('路径中至少需要包含其中一项。')
        );
        $pagePattern->input->setAttribute('class', 'mono w-60');
        $form->addInput($pagePattern->addRule([$this, 'checkPagePattern'], _t('独立页面路径必须至少包含 {cid}、{slug} 或 {directory} 中的一项。')));

        $categoryPattern = new Form\Element\Text(
            'categoryPattern',
            null,
            $this->decodeRule((string) $routingTable['category']['url']),
            _t('分类路径'),
            _t('可用参数：<code>{mid}</code> 分类 ID、<code>{slug}</code> 分类别名、<code>{directory}</code> 多级分类路径。')
            . '<br />' . _t('路径中至少需要包含其中一项。')
        );
        $categoryPattern->input->setAttribute('class', 'mono w-60');
        $form->addInput($categoryPattern->addRule([$this, 'checkCategoryPattern'], _t('分类路径必须至少包含 {mid}、{slug} 或 {directory} 中的一项。')));

        $submit = new Form\Element\Submit('submit', null, _t('保存设置'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        return $form;
    }

    /**
     * 解析自定义的路径
     *
     * @param string $rule 待解码的路径
     * @return string
     */
    protected function decodeRule(string $rule): string
    {
        return preg_replace("/\[([_a-z0-9-]+)[^\]]*\]/i", "{\\1}", $rule);
    }

    private function serializeRoutingTable(array $routingTable): string
    {
        unset($routingTable[0]);
        return Common::jsonEncode($routingTable, 0, '{}');
    }

    private function rewriteStatusLabel(string $status): string
    {
        return match ($status) {
            'verified' => _t('已验证'),
            'disabled' => _t('未启用'),
            default => _t('待验证'),
        };
    }

    private function rewriteStatusDescription(array $state): string
    {
        $rows = [
            '<span class="tr-rewrite-status-line"><span class="tr-rewrite-status-key">' . _t('配置说明：') . '</span><span id="tr-rewrite-status-note">' . htmlspecialchars($state['message'], ENT_QUOTES, 'UTF-8') . '</span></span>',
        ];

        if ($state['basePath'] !== '/') {
            $rows[] = '<span class="tr-rewrite-status-line"><span class="tr-rewrite-status-key">'
                . _t('安装目录：')
                . '</span><code>'
                . htmlspecialchars($state['basePath'], ENT_QUOTES, 'UTF-8')
                . '</code></span>';
        }

        if ($state['verifiedAt'] > 0) {
            $rows[] = '<span class="tr-rewrite-status-line"><span class="tr-rewrite-status-key">'
                . _t('最近验证')
                . '：</span><code>'
                . htmlspecialchars(\Typecho\Timezone::format($state['verifiedAt'], 'Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8')
                . '</code></span>';
        }

        if (!Manager::sameSiteLocation((string) $this->options->rootUrl, (string) $this->options->siteUrl)) {
            $rows[] = '<span class="tr-rewrite-status-line is-warning">'
                . _t(
                    '当前访问地址 <code>%s</code> 与站点地址 <code>%s</code> 不一致，请确认正式访问域名与安装目录。',
                    htmlspecialchars((string) $this->options->rootUrl, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars((string) $this->options->siteUrl, ENT_QUOTES, 'UTF-8')
                )
                . '</span>';
        }

        if (defined('__TYPECHO_REWRITE__')) {
            $rows[] = '<span class="tr-rewrite-status-line is-warning">'
                . _t('检测到配置文件正在接管地址重写开关，后台仅显示当前状态。')
                . '</span>';
        }

        $actions = '';
        if (Manager::enabled($this->options)) {
            $actions = '<span class="tr-rewrite-status-actions">'
                . '<button type="button" class="btn" id="tr-rewrite-probe">' . _t('验证当前配置') . '</button>'
                . '</span>'
                . $this->rewriteProbeScript();
        }

        return '<span class="tr-rewrite-status-meta">'
            . '<span class="tr-rewrite-status-info">' . implode('', $rows) . '</span>'
            . $actions
            . '</span>';
    }

    private function rewriteProbeScript(): string
    {
        $url = Common::jsonEncode(Manager::probePath($this->options));
        $verifiedText = Common::jsonEncode(_t('已校验'));
        $failedText = Common::jsonEncode(_t('校验失败，请检查服务器规则是否已部署。'));
        $checkingText = Common::jsonEncode(_t('验证中...'));
        $checkingNote = Common::jsonEncode(_t('正在验证当前配置...'));

        return <<<HTML
<script>
(function () {
    if (window.__trRewriteProbeBound) {
        return;
    }

    window.__trRewriteProbeBound = true;

    var syncRulePanels = function () {
        var serverSelect = document.querySelector('select[name="rewriteServer"]');
        var nginxField = document.querySelector('textarea[name="rewriteNginxRules"]');
        var apacheField = document.querySelector('textarea[name="rewriteApacheRules"]');
        var managedInput = document.querySelector('input[name="rewriteMode"][value="managed"]');
        var manualInput = document.querySelector('input[name="rewriteMode"][value="manual"]');
        var server = serverSelect ? serverSelect.value : '';
        var nginxWrap = nginxField && nginxField.closest ? nginxField.closest('ul.typecho-option') : null;
        var apacheWrap = apacheField && apacheField.closest ? apacheField.closest('ul.typecho-option') : null;
        var managedWrap = managedInput && managedInput.closest ? managedInput.closest('.multiline') : null;
        if (nginxWrap) {
            nginxWrap.classList.remove('tr-rewrite-primary', 'tr-rewrite-secondary');
        }
        if (apacheWrap) {
            apacheWrap.classList.remove('tr-rewrite-primary', 'tr-rewrite-secondary');
        }
        if (managedInput) {
            managedInput.disabled = server !== 'apache';
            if (managedWrap) {
                managedWrap.classList.toggle('tr-is-disabled', managedInput.disabled);
            }
            if (managedInput.disabled && managedInput.checked && manualInput) {
                manualInput.checked = true;
            }
        }
        if (server === 'nginx') {
            if (nginxWrap) nginxWrap.classList.add('tr-rewrite-primary');
            if (apacheWrap) apacheWrap.classList.add('tr-rewrite-secondary');
        } else if (server === 'apache') {
            if (apacheWrap) apacheWrap.classList.add('tr-rewrite-primary');
            if (nginxWrap) nginxWrap.classList.add('tr-rewrite-secondary');
        }
    };

    var setResult = function (message, state) {
        var statusNote = document.getElementById('tr-rewrite-status-note');
        if (!statusNote) {
            return;
        }

        var defaultStatusNote = statusNote.getAttribute('data-default-text') || statusNote.textContent || '';
        statusNote.textContent = message || defaultStatusNote;
        statusNote.classList.remove('is-success', 'is-error');
        if (state) {
            statusNote.classList.add('is-' + state);
        }
    };

    var init = function () {
        var serverSelect = document.querySelector('select[name="rewriteServer"]');
        var statusNote = document.getElementById('tr-rewrite-status-note');
        if (statusNote && !statusNote.getAttribute('data-default-text')) {
            statusNote.setAttribute('data-default-text', statusNote.textContent || '');
        }

        syncRulePanels();
        if (serverSelect && !serverSelect.getAttribute('data-rewrite-sync-bound')) {
            serverSelect.setAttribute('data-rewrite-sync-bound', '1');
            serverSelect.addEventListener('change', syncRulePanels);
        }
    };

    document.addEventListener('click', function (event) {
        var button = event.target && event.target.closest ? event.target.closest('#tr-rewrite-probe') : null;
        if (!button) {
            return;
        }

        event.preventDefault();
        if (button.disabled) {
            return;
        }

        var statusInput = document.querySelector('input[name="rewriteStatusText"]');
        var defaultButtonText = button.getAttribute('data-default-text') || button.textContent || '';
        var xhr = new XMLHttpRequest();
        button.setAttribute('data-default-text', defaultButtonText);
        button.disabled = true;
        button.textContent = {$checkingText};
        setResult({$checkingNote}, '');
        xhr.timeout = 10000;
        xhr.open('GET', {$url}, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onerror = xhr.ontimeout = function () {
            button.disabled = false;
            button.textContent = defaultButtonText;
            setResult({$failedText}, 'error');
        };
        xhr.onreadystatechange = function () {
            var data = null;
            if (xhr.readyState !== 4) {
                return;
            }
            button.disabled = false;
            button.textContent = defaultButtonText;
            try {
                data = JSON.parse(xhr.responseText);
            } catch (e) {
                setResult({$failedText}, 'error');
                return;
            }
            if (xhr.status < 200 || xhr.status >= 300 || !data || !data.ok) {
                setResult((data && data.message) ? data.message : {$failedText}, 'error');
                return;
            }
            setResult(data.message || {$verifiedText}, 'success');
            if (statusInput) {
                statusInput.value = {$verifiedText};
            }
            var statusNote = document.getElementById('tr-rewrite-status-note');
            if (statusNote) {
                statusNote.setAttribute('data-default-text', data.message || {$verifiedText});
            }
        };
        xhr.send(null);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
        return;
    }

    init();
})();
</script>
HTML;
    }

    private function rememberCustomPattern(string $customPattern): void
    {
        Cookie::set('__typecho_form_item_postPattern', $customPattern);
    }

    private function failCustomPattern(string $customPattern, string $message): void
    {
        $this->rememberCustomPattern($customPattern);
        $this->noticeAndGoBack($message, 'error');
    }

    /**
     * 编码自定义的路径
     *
     * @param string $rule 待编码的路径
     * @return string
     */
    protected function encodeRule(string $rule): string
    {
        return str_replace(
            ['{cid}', '{slug}', '{category}', '{directory}', '{year}', '{month}', '{day}', '{mid}'],
            [
                '[cid:digital]', '[slug]', '[category]', '[directory:split:0]',
                '[year:digital:4]', '[month:digital:2]', '[day:digital:2]', '[mid:digital]'
            ],
            $rule
        );
    }

    protected function hasRouteConflict(array $routingTable, string $routeKey, array $ignoreKeys = []): bool
    {
        if (!isset($routingTable[$routeKey]['url']) || !is_string($routingTable[$routeKey]['url'])) {
            return false;
        }

        $candidateTable = ['candidate' => ['url' => (string) $routingTable[$routeKey]['url']]];
        $parser = new Parser($candidateTable);
        $parsed = $parser->parse();
        $regx = (string) ($parsed['candidate']['regx'] ?? '');
        if ($regx === '') {
            return false;
        }

        foreach ($routingTable as $key => $route) {
            if ($key === $routeKey || in_array($key, $ignoreKeys, true)) {
                continue;
            }

            $url = (string) ($route['url'] ?? '');
            if ($url === '') {
                continue;
            }

            $pathInfo = $this->sampleRoutePath($url);
            if ($pathInfo !== '' && preg_match($regx, $pathInfo)) {
                return true;
            }
        }

        return false;
    }

    protected function sampleRoutePath(string $url): string
    {
        $pathInfo = preg_replace("/\[([_a-z0-9-]+)[^\]]*\]/i", "{\\1}", $url);
        if (!is_string($pathInfo)) {
            return '';
        }

        $pathInfo = strtr($pathInfo, [
            '{cid}' => '123',
            '{slug}' => 'hello',
            '{category}' => 'default',
            '{directory}' => 'section/hello',
            '{year}' => '2008',
            '{month}' => '08',
            '{day}' => '08',
            '{mid}' => '7',
            '{uid}' => '1',
            '{keywords}' => 'keyword',
            '{action}' => 'ajax',
            '{type}' => 'comment',
            '{feed}' => '/atom',
            '{page}' => '2',
            '{commentPage}' => '2',
            '{permalink}' => 'archives/123',
        ]);

        return str_replace(['{', '}'], '', $pathInfo);
    }

    public function action()
    {
        $this->user->pass('administrator');
        $this->security->protect();
        $this->on($this->request->isPost())->updatePermalinkSettings();
        $this->response->redirect($this->options->adminUrl);
    }
}
