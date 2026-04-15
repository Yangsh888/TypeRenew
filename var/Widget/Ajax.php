<?php

namespace Widget;

use Typecho\Http\Client;
use Typecho\Plugin;
use Typecho\Widget\Exception;
use Widget\Base\Options as BaseOptions;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Ajax extends BaseOptions implements ActionInterface
{
    private const OFFICIAL_FEED_SOURCES = [
        'https://www.typerenew.com/feed/',
    ];

    private const OFFICIAL_PLUGIN_VERSION_SOURCE = 'https://raw.githubusercontent.com/Yangsh888/TypeRenew-plugins/main/README.md';

    private const OFFICIAL_PLUGIN_VERSION_CACHE = 'pluginVersionCache';

    private const OFFICIAL_PLUGIN_VERSION_CACHE_TTL = 7200;

    private const OFFICIAL_PLUGIN_VERSION_FAILURE_TTL = 600;

    private const OFFICIAL_PLUGIN_VERSION_TIMEOUT = 4;

    private function decodeFeedText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = strip_tags($text);
        return trim(preg_replace("/\s+/u", ' ', $text));
    }

    private function extractFeedTag(string $entry, string $tag): ?string
    {
        if (!preg_match('/<' . preg_quote($tag, '/') . '>\s*([\s\S]*?)\s*<\/' . preg_quote($tag, '/') . '>/i', $entry, $match)) {
            return null;
        }

        $value = $this->decodeFeedText((string) ($match[1] ?? ''));
        return $value === '' ? null : $value;
    }

    private function extractFeedLink(string $entry): ?string
    {
        if (
            preg_match('/<link>\s*([\s\S]*?)\s*<\/link>/i', $entry, $match)
        ) {
            $link = trim(html_entity_decode((string) ($match[1] ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8'));
            if ($link !== '' && preg_match('#^https://www\.typerenew\.com/#i', $link)) {
                return $link;
            }
        }

        return null;
    }

    private function parseOfficialFeedEntries(string $response): array
    {
        $items = [];
        if (!preg_match_all('/<item\b[\s\S]*?<\/item>/i', $response, $entries) || empty($entries[0])) {
            return $items;
        }

        foreach ($entries[0] as $entry) {
            $title = $this->extractFeedTag($entry, 'title');
            $link = $this->extractFeedLink($entry);
            $dateText = $this->extractFeedTag($entry, 'pubDate');

            if (empty($title) || empty($link) || empty($dateText)) {
                continue;
            }

            $timestamp = strtotime($dateText);
            if ($timestamp === false) {
                continue;
            }

            $items[] = [
                'title' => $title,
                'link'  => $link,
                'date'  => date('n.j', $timestamp),
                'ts'    => $timestamp,
            ];
        }

        return $items;
    }

    private function collectInstalledPlugins(): array
    {
        $plugins = [];
        $entries = glob(__TYPECHO_ROOT_DIR__ . '/' . __TYPECHO_PLUGIN_DIR__ . '/*');
        $entries = is_array($entries) ? $entries : [];
        natcasesort($entries);

        foreach ($entries as $entry) {
            $pluginName = null;
            $pluginFile = null;

            if (is_dir($entry)) {
                $pluginName = basename($entry);
                $pluginFile = $entry . '/Plugin.php';
            } elseif (is_file($entry) && 'index.php' !== basename($entry)) {
                $part = explode('.', basename($entry));
                if (2 === count($part) && 'php' === $part[1]) {
                    $pluginName = $part[0];
                    $pluginFile = $entry;
                }
            }

            if ($pluginName === null || $pluginFile === null || !is_file($pluginFile)) {
                continue;
            }

            $info = Plugin::parseInfo($pluginFile);
            $info['name'] = $pluginName;
            $plugins[$pluginName] = $info;
        }

        return $plugins;
    }

    private function normalizeComparableVersion(?string $version): ?string
    {
        $version = trim((string) $version);
        if ($version === '') {
            return null;
        }

        $version = ltrim($version, "vV");
        return preg_match('/^\d+(?:\.\d+)*$/', $version) ? $version : null;
    }

    private function cleanMarkdownCell(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/!\[([^\]]*)\]\([^)]+\)/', '$1', $value);
        $value = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $value);
        $value = preg_replace('/`([^`]+)`/', '$1', $value);
        $value = preg_replace('/[*_~]/', '', $value);
        $value = strip_tags((string) $value);

        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    private function parseOfficialPluginVersions(string $markdown): array
    {
        $versions = [];
        $lines = preg_split('/\R/u', $markdown) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] !== '|') {
                continue;
            }

            $cells = array_map('trim', explode('|', trim($line, '|')));
            if (count($cells) < 2) {
                continue;
            }

            $pluginName = $this->cleanMarkdownCell((string) $cells[0]);
            $version = $this->normalizeComparableVersion($this->cleanMarkdownCell((string) $cells[1]));

            if ($pluginName === '' || $version === null) {
                continue;
            }

            try {
                $pluginName = Plugin::normalizeName($pluginName);
            } catch (\Exception $e) {
                continue;
            }

            $versions[$pluginName] = $version;
        }

        return $versions;
    }

    private function isOfficialPlugin(array $info): bool
    {
        $author = strtolower(trim((string) ($info['author'] ?? '')));
        if ($author !== 'typerenew') {
            return false;
        }

        $homepage = trim((string) ($info['homepage'] ?? ''));
        if ($homepage === '') {
            return false;
        }

        $parts = \Typecho\Common::parseUrl($homepage);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return in_array($host, ['www.typerenew.com', 'typerenew.com'], true);
    }

    private function readOfficialPluginVersionCache(): ?array
    {
        $row = $this->db->fetchRow(
            $this->db->select('value')
                ->from('table.options')
                ->where('name = ?', self::OFFICIAL_PLUGIN_VERSION_CACHE)
                ->where('user = ?', 0)
                ->limit(1)
        );

        if (empty($row['value'])) {
            return null;
        }

        $data = json_decode((string) $row['value'], true);
        if (!is_array($data)) {
            return null;
        }

        $checkedAt = max(0, (int) ($data['checkedAt'] ?? 0));
        $ok = !empty($data['ok']);
        $ttl = max(1, (int) ($data['ttl'] ?? ($ok
            ? self::OFFICIAL_PLUGIN_VERSION_CACHE_TTL
            : self::OFFICIAL_PLUGIN_VERSION_FAILURE_TTL)));
        $message = trim((string) ($data['message'] ?? ''));
        $versions = [];
        foreach ((array) ($data['versions'] ?? []) as $pluginName => $version) {
            if (!is_string($pluginName)) {
                continue;
            }

            $normalizedVersion = $this->normalizeComparableVersion((string) $version);
            if ($normalizedVersion === null) {
                continue;
            }

            $versions[$pluginName] = $normalizedVersion;
        }

        if ($checkedAt <= 0) {
            return null;
        }

        if ($ok && empty($versions)) {
            return null;
        }

        return [
            'ok'        => $ok,
            'checkedAt' => $checkedAt,
            'ttl'       => $ttl,
            'message'   => $message,
            'versions'  => $versions,
        ];
    }

    private function writeOfficialPluginVersionCache(bool $ok, array $versions, int $checkedAt, string $message = '', ?int $ttl = null): void
    {
        ksort($versions, SORT_NATURAL | SORT_FLAG_CASE);
        $ttl = max(1, (int) ($ttl ?? ($ok
            ? self::OFFICIAL_PLUGIN_VERSION_CACHE_TTL
            : self::OFFICIAL_PLUGIN_VERSION_FAILURE_TTL)));
        $this->saveOption(self::OFFICIAL_PLUGIN_VERSION_CACHE, [
            'ok'        => $ok ? 1 : 0,
            'checkedAt' => $checkedAt,
            'ttl'       => $ttl,
            'message'   => $message,
            'versions'  => $versions,
        ]);
    }

    private function loadOfficialPluginVersions(bool $forceRefresh = false): array
    {
        $now = time();
        if (!$forceRefresh) {
            $cache = $this->readOfficialPluginVersionCache();
            if (
                $cache !== null
                && ($now - (int) $cache['checkedAt']) < (int) ($cache['ttl'] ?? self::OFFICIAL_PLUGIN_VERSION_CACHE_TTL)
            ) {
                return [
                    'ok'        => (bool) ($cache['ok'] ?? false),
                    'checkedAt' => (int) $cache['checkedAt'],
                    'ttl'       => (int) ($cache['ttl'] ?? self::OFFICIAL_PLUGIN_VERSION_CACHE_TTL),
                    'versions'  => $cache['versions'],
                    'cached'    => true,
                    'message'   => (string) ($cache['message'] ?? ''),
                ];
            }
        }

        $client = Client::get();
        if (!$client) {
            $message = _t('当前环境缺少 curl 扩展，无法访问 GitHub Raw 地址。');
            $this->writeOfficialPluginVersionCache(false, [], $now, $message, self::OFFICIAL_PLUGIN_VERSION_FAILURE_TTL);
            return [
                'ok'        => false,
                'checkedAt' => $now,
                'ttl'       => self::OFFICIAL_PLUGIN_VERSION_FAILURE_TTL,
                'versions'  => [],
                'cached'    => false,
                'message'   => $message,
            ];
        }

        try {
            $client->setHeader('User-Agent', $this->options->generator)
                ->setHeader('Accept', 'text/plain')
                ->setTimeout(self::OFFICIAL_PLUGIN_VERSION_TIMEOUT)
                ->send(self::OFFICIAL_PLUGIN_VERSION_SOURCE);

            if ($client->getResponseStatus() !== 200) {
                $message = _t('官方插件仓库返回异常状态，暂时无法检测版本。');
                $this->writeOfficialPluginVersionCache(false, [], $now, $message, self::OFFICIAL_PLUGIN_VERSION_FAILURE_TTL);
                return [
                    'ok'        => false,
                    'checkedAt' => $now,
                    'ttl'       => self::OFFICIAL_PLUGIN_VERSION_FAILURE_TTL,
                    'versions'  => [],
                    'cached'    => false,
                    'message'   => $message,
                ];
            }

            $responseUrl = $client->getResponseUrl();
            $parts = \Typecho\Common::parseUrl($responseUrl);
            $host = strtolower((string) ($parts['host'] ?? ''));
            if ($host !== 'raw.githubusercontent.com') {
                $message = _t('官方版本源校验失败，暂时无法检测版本。');
                $this->writeOfficialPluginVersionCache(false, [], $now, $message, self::OFFICIAL_PLUGIN_VERSION_FAILURE_TTL);
                return [
                    'ok'        => false,
                    'checkedAt' => $now,
                    'ttl'       => self::OFFICIAL_PLUGIN_VERSION_FAILURE_TTL,
                    'versions'  => [],
                    'cached'    => false,
                    'message'   => $message,
                ];
            }

            $versions = $this->parseOfficialPluginVersions($client->getResponseBody());
            if (empty($versions)) {
                $message = _t('官方插件仓库文档格式已变化，暂时无法解析版本信息。');
                $this->writeOfficialPluginVersionCache(false, [], $now, $message, self::OFFICIAL_PLUGIN_VERSION_FAILURE_TTL);
                return [
                    'ok'        => false,
                    'checkedAt' => $now,
                    'ttl'       => self::OFFICIAL_PLUGIN_VERSION_FAILURE_TTL,
                    'versions'  => [],
                    'cached'    => false,
                    'message'   => $message,
                ];
            }

            $this->writeOfficialPluginVersionCache(true, $versions, $now, '', self::OFFICIAL_PLUGIN_VERSION_CACHE_TTL);

            return [
                'ok'        => true,
                'checkedAt' => $now,
                'ttl'       => self::OFFICIAL_PLUGIN_VERSION_CACHE_TTL,
                'versions'  => $versions,
                'cached'    => false,
                'message'   => '',
            ];
        } catch (\Exception $e) {
            $message = _t('当前服务器可能无法访问 GitHub Raw 地址，或网络 / SSL 临时异常。');
            $this->writeOfficialPluginVersionCache(false, [], $now, $message, self::OFFICIAL_PLUGIN_VERSION_FAILURE_TTL);
            return [
                'ok'        => false,
                'checkedAt' => $now,
                'ttl'       => self::OFFICIAL_PLUGIN_VERSION_FAILURE_TTL,
                'versions'  => [],
                'cached'    => false,
                'message'   => $message,
            ];
        }
    }

    private function buildPluginVersionStatuses(bool $forceRefresh = false): array
    {
        $source = $this->loadOfficialPluginVersions($forceRefresh);
        $statuses = [];

        foreach ($this->collectInstalledPlugins() as $pluginName => $info) {
            $localVersionRaw = trim((string) ($info['version'] ?? ''));
            $localVersion = $this->normalizeComparableVersion($localVersionRaw);

            if (!$this->isOfficialPlugin($info)) {
                $statuses[$pluginName] = [
                    'status'  => 'unofficial',
                    'local'   => $localVersionRaw,
                    'remote'  => '',
                    'message' => _t('这并非 TypeRenew 官方插件，无法在官方插件仓库中检测版本状态。'),
                ];
                continue;
            }

            if (!$source['ok']) {
                $statuses[$pluginName] = [
                    'status'  => 'failed',
                    'local'   => $localVersionRaw,
                    'remote'  => '',
                    'message' => _t('版本检测失败：%s', $source['message']),
                ];
                continue;
            }

            if ($localVersion === null) {
                $statuses[$pluginName] = [
                    'status'  => 'failed',
                    'local'   => $localVersionRaw,
                    'remote'  => '',
                    'message' => _t('本地插件未声明有效版本号，无法完成版本比较。'),
                ];
                continue;
            }

            if (!isset($source['versions'][$pluginName])) {
                $statuses[$pluginName] = [
                    'status'  => 'failed',
                    'local'   => $localVersion,
                    'remote'  => '',
                    'message' => _t('官方插件仓库暂未收录该插件的版本信息。'),
                ];
                continue;
            }

            $remoteVersion = (string) $source['versions'][$pluginName];
            if (version_compare($localVersion, $remoteVersion, '<')) {
                $statuses[$pluginName] = [
                    'status'  => 'update',
                    'local'   => $localVersion,
                    'remote'  => $remoteVersion,
                    'message' => _t('检测到新版本：当前 %s，官方最新 %s。请前往官方插件仓库手动更新。', $localVersion, $remoteVersion),
                ];
                continue;
            }

            $statuses[$pluginName] = [
                'status'  => 'latest',
                'local'   => $localVersion,
                'remote'  => $remoteVersion,
                'message' => _t('当前版本 %s，不低于官方仓库标注的最新版本 %s。', $localVersion, $remoteVersion),
            ];
        }

        return [
            'ok'        => (bool) $source['ok'],
            'checkedAt' => (int) $source['checkedAt'],
            'cached'    => (bool) $source['cached'],
            'ttl'       => self::OFFICIAL_PLUGIN_VERSION_CACHE_TTL,
            'source'    => self::OFFICIAL_PLUGIN_VERSION_SOURCE,
            'message'   => (string) $source['message'],
            'statuses'  => $statuses,
        ];
    }

    public function remoteCallback()
    {
        if ($this->options->generator == $this->request->getAgent()) {
            echo 'OK';
        }
    }

    public function checkVersion()
    {
        $this->user->pass('editor');
        $client = Client::get();
        $result = ['available' => 0];
        if ($client) {
            $client->setHeader('User-Agent', $this->options->generator)
                ->setTimeout(10);

            try {
                $client->send('https://typecho.org/version.json');

                /** 读取响应体并解析 release 信息 */
                $response = $client->getResponseBody();
                $json = json_decode($response, true);

                if (!empty($json)) {
                    $version = $this->options->version;

                    if (
                        isset($json['release'])
                        && preg_match("/^[0-9.]+$/", $json['release'])
                        && version_compare($json['release'], $version, '>')
                    ) {
                        $result = [
                            'available' => 1,
                            'latest'    => $json['release'],
                            'current'   => $version,
                            'link'      => 'https://typecho.org/download'
                        ];
                    }
                }
            } catch (\Exception $e) {
                error_log('[Ajax] checkVersion: ' . $e->getMessage());
            }
        }

        $this->response->throwJson($result);
    }

    public function pluginVersion()
    {
        $this->user->pass('administrator');
        $forceRefresh = 1 === (int) $this->request->filter('int')->get('refresh');
        $this->response->throwJson($this->buildPluginVersionStatuses($forceRefresh));
    }

    public function feed()
    {
        $this->user->pass('subscriber');
        $client = Client::get();
        $result = [
            'ok'      => false,
            'items'   => [],
            'message' => _t('暂时无法获取官方动态，请检查网络后重试。'),
            'partial' => false,
        ];

        if (!$client) {
            $this->response->throwJson($result);
            return;
        }

        $items = [];
        $failed = 0;

        foreach (self::OFFICIAL_FEED_SOURCES as $source) {
            try {
                $client->setHeader('User-Agent', $this->options->generator)
                    ->setTimeout(8)
                    ->send($source);

                if ($client->getResponseStatus() !== 200) {
                    $failed++;
                    continue;
                }

                $responseUrl = $client->getResponseUrl();
                $parts = \Typecho\Common::parseUrl($responseUrl);
                $host = strtolower((string) ($parts['host'] ?? ''));
                if ($host !== 'www.typerenew.com') {
                    $failed++;
                    continue;
                }

                $response = $client->getResponseBody();
                $items = array_merge($items, $this->parseOfficialFeedEntries($response));
            } catch (\Exception $e) {
                $failed++;
            }
        }

        if (!empty($items)) {
            usort($items, static function (array $a, array $b): int {
                return ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0);
            });

            $unique = [];
            $seen = [];
            foreach ($items as $item) {
                $key = $item['link'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                unset($item['ts']);
                $unique[] = $item;
                if (count($unique) >= 9) {
                    break;
                }
            }

            $result['ok'] = true;
            $result['items'] = $unique;
            $result['message'] = $failed > 0 ? _t('部分官方动态读取失败。') : '';
            $result['partial'] = $failed > 0;
        } else {
            $result['message'] = $failed > 0 ? _t('暂时无法获取官方动态，请检查网络后重试。') : _t('暂无动态');
            $result['partial'] = $failed > 0;
        }

        $this->response->throwJson($result);
    }

    public function editorResize()
    {
        $this->user->pass('contributor');
        $size = $this->request->filter('int')->get('size');

        if (
            $this->db->fetchObject($this->db->select(['COUNT(*)' => 'num'])
                ->from('table.options')->where('name = ? AND user = ?', 'editorSize', $this->user->uid))->num > 0
        ) {
            parent::update(
                ['value' => $size],
                $this->db->sql()->where('name = ? AND user = ?', 'editorSize', $this->user->uid)
            );
        } else {
            parent::insert([
                'name'  => 'editorSize',
                'value' => $size,
                'user'  => $this->user->uid
            ]);
        }
    }

    public function action()
    {
        if (!$this->request->isAjax()) {
            $this->response->goBack();
        }

        $this->on($this->request->is('do=remoteCallback'))->remoteCallback();
        $this->on($this->request->is('do=feed'))->feed();
        $this->on($this->request->is('do=checkVersion'))->checkVersion();
        $this->on($this->request->is('do=pluginVersion'))->pluginVersion();
        $this->on($this->request->is('do=editorResize'))->editorResize();
    }
}
