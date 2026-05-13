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
        return $this->containsAnyPlaceholder($value, ['{slug}', '{cid}', '{directory}']);
    }

    /**
     * 检查categoryPattern里是否含有必要参数
     *
     * @param mixed $value
     * @return bool
     */
    public function checkCategoryPattern($value): bool
    {
        return $this->containsAnyPlaceholder($value, ['{slug}', '{mid}', '{directory}']);
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
        $before = [
            'rewrite' => (string) ($this->options->rewrite ?? ''),
            'routingTable' => $this->serializeRoutingTable($this->options->routingTable),
            'rewriteStatus' => (string) ($this->options->rewriteStatus ?? ''),
        ];

        if ($this->form()->validate()) {
            $this->rememberCustomPattern($customPattern);
            $this->response->goBack();
        }

        if ('custom' == $postPattern) {
            $postPattern = '/' . ltrim($this->encodeRule($customPattern), '/');
        }

        $settings = defined('__TYPECHO_REWRITE__') ? [] : $this->request->fromInput('rewrite');
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

        $settings['rewrite'] = $rewrite === '1' ? '1' : '0';
        $settings += Manager::normalizeStoredState([], $settings['rewrite'] === '1');
        if (
            $settings['rewrite'] === $before['rewrite']
            && (string) ($settings['routingTable'] ?? $before['routingTable']) === $before['routingTable']
        ) {
            $settings['rewriteStatus'] = (string) ($this->options->rewriteStatus ?? $settings['rewriteStatus']);
            $settings['rewriteVerifiedAt'] = (string) ($this->options->rewriteVerifiedAt ?? $settings['rewriteVerifiedAt']);
            $settings['rewriteMessage'] = (string) ($this->options->rewriteMessage ?? $settings['rewriteMessage']);
        }
        $this->persistOptions($settings);
        Manager::cleanupLegacyOptions($this->db);
        self::pluginHandle()->call('finishUpdate', $before, [
            'rewrite' => (string) ($settings['rewrite'] ?? $before['rewrite']),
            'routingTable' => (string) ($settings['routingTable'] ?? $before['routingTable']),
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
                _t('启用后，系统将按照当前规则输出不含 <code>index.php</code> 的访问地址。')
            );
            $form->addInput($rewrite);
        }

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
            _t('请将以下规则加入当前站点的 Nginx 配置中。')
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
            _t('请将以下规则写入当前站点的 <code>.htaccess</code> 文件。')
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
            . '<br />' . _t('请选择文章链接结构。')
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
        $decoded = preg_replace("/\[([_a-z0-9-]+)[^\]]*\]/i", "{\\1}", $rule);
        return is_string($decoded) ? $decoded : $rule;
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
            '<span class="tr-rewrite-status-line"><span class="tr-rewrite-status-key">' . _t('说明：') . '</span><span id="tr-rewrite-status-note">' . htmlspecialchars($state['message'], ENT_QUOTES, 'UTF-8') . '</span></span>',
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
                . _t('检测到配置文件接管地址重写开关，后台仅显示状态。')
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
        $publicUrl = Common::jsonEncode(Manager::publicProbePath($this->options));
        $finalizeUrl = Common::jsonEncode(Manager::probePath($this->options));
        $verifiedText = Common::jsonEncode(_t('已校验'));
        $failedText = Common::jsonEncode(_t('校验失败，请检查地址重写规则。'));
        $checkingText = Common::jsonEncode(_t('校验中...'));
        $checkingNote = Common::jsonEncode(_t('正在校验地址重写规则。'));
        $finalizingNote = Common::jsonEncode(_t('正在更新校验状态。'));

        return <<<HTML
<script>
(function () {
    if (window.__trRewriteProbeBound) {
        return;
    }

    window.__trRewriteProbeBound = true;

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
        var statusNote = document.getElementById('tr-rewrite-status-note');
        if (statusNote && !statusNote.getAttribute('data-default-text')) {
            statusNote.setAttribute('data-default-text', statusNote.textContent || '');
        }
    };

    var requestJson = function (url, onSuccess, onFailure) {
        var xhr = new XMLHttpRequest();
        xhr.timeout = 10000;
        xhr.open('GET', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onerror = xhr.ontimeout = function () {
            onFailure(null, xhr);
        };
        xhr.onreadystatechange = function () {
            var data = null;
            if (xhr.readyState !== 4) {
                return;
            }
            try {
                data = JSON.parse(xhr.responseText);
            } catch (e) {
                onFailure(null, xhr);
                return;
            }
            if (xhr.status < 200 || xhr.status >= 300 || !data || !data.ok) {
                onFailure(data, xhr);
                return;
            }
            onSuccess(data, xhr);
        };
        xhr.send(null);
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
        button.setAttribute('data-default-text', defaultButtonText);
        button.disabled = true;
        button.textContent = {$checkingText};
        setResult({$checkingNote}, '');
        requestJson({$publicUrl}, function () {
            setResult({$finalizingNote}, '');
            requestJson({$finalizeUrl}, function (data) {
                button.disabled = false;
                button.textContent = defaultButtonText;
                setResult(data.message || {$verifiedText}, 'success');
                if (statusInput) {
                    statusInput.value = {$verifiedText};
                }
                var statusNote = document.getElementById('tr-rewrite-status-note');
                if (statusNote) {
                    statusNote.setAttribute('data-default-text', data.message || {$verifiedText});
                }
            }, function (data) {
                button.disabled = false;
                button.textContent = defaultButtonText;
                setResult((data && data.message) ? data.message : {$failedText}, 'error');
            });
        }, function (data) {
            button.disabled = false;
            button.textContent = defaultButtonText;
            setResult((data && data.message) ? data.message : {$failedText}, 'error');
        });
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

        $candidateUrl = (string) $routingTable[$routeKey]['url'];
        $candidateRegex = $this->routeRegex($candidateUrl);
        if ($candidateRegex === '') {
            return false;
        }
        $candidatePath = $this->sampleRoutePath($candidateUrl);

        foreach ($routingTable as $key => $route) {
            if ($key === $routeKey || in_array($key, $ignoreKeys, true)) {
                continue;
            }

            $url = (string) ($route['url'] ?? '');
            if ($url === '') {
                continue;
            }

            $pathInfo = $this->sampleRoutePath($url);
            if ($pathInfo !== '' && preg_match($candidateRegex, $pathInfo) === 1) {
                return true;
            }

            $routeRegex = $this->routeRegex($url);
            if (
                $candidatePath !== ''
                && $routeRegex !== ''
                && $this->isStandaloneRoutePattern($url)
                && preg_match($routeRegex, $candidatePath) === 1
            ) {
                return true;
            }
        }

        return false;
    }

    protected function routeRegex(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $parser = new Parser(['candidate' => ['url' => $url]]);
        $parsed = $parser->parse();
        return (string) ($parsed['candidate']['regx'] ?? '');
    }

    private function isStandaloneRoutePattern(string $url): bool
    {
        return $url !== '' && $url[0] === '/';
    }

    private function containsAnyPlaceholder($value, array $placeholders): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        $value = (string) $value;
        foreach ($placeholders as $placeholder) {
            if (strpos($value, $placeholder) !== false) {
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
        if (!$this->request->isPost()) {
            $this->response->setStatus(405)->throwContent(_t('Method Not Allowed'), 'text/plain');
            return;
        }
        $this->security->protect();
        $this->updatePermalinkSettings();
        $this->response->redirect($this->options->adminUrl);
    }
}
