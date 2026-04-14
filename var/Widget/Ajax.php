<?php

namespace Widget;

use Typecho\Http\Client;
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
        $this->on($this->request->is('do=editorResize'))->editorResize();
    }
}
