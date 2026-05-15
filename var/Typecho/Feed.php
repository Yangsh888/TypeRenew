<?php

namespace Typecho;

/**
 * Feed
 *
 * @package Feed
 */
class Feed
{
    public const RSS1 = 'RSS 1.0';

    public const RSS2 = 'RSS 2.0';

    public const ATOM1 = 'ATOM 1.0';

    public const DATE_RFC822 = 'r';

    public const DATE_W3CDTF = 'c';

    public const EOL = "\n";

    /**
     * feed状态
     *
     * @var string
     */
    private string $type;

    /**
     * 字符集编码
     *
     * @var string
     */
    private string $charset;

    /**
     * 语言状态
     *
     * @var string
     */
    private string $lang;

    /**
     * 聚合地址
     *
     * @var string
     */
    private string $feedUrl;

    /**
     * 基本地址
     *
     * @var string
     */
    private string $baseUrl;

    /**
     * 聚合标题
     *
     * @var string
     */
    private string $title;

    /**
     * 聚合副标题
     *
     * @var string|null
     */
    private ?string $subTitle;

    /**
     * 版本信息
     *
     * @var string
     */
    private string $version;

    /**
     * 所有的items
     *
     * @var array
     */
    private array $items = [];

    /**
     * 创建Feed对象
     *
     * @param $version
     * @param string $type
     * @param string $charset
     * @param string $lang
     */
    public function __construct($version, string $type = self::RSS2, string $charset = 'UTF-8', string $lang = 'en')
    {
        $this->version = $version;
        $this->type = $type;
        $this->charset = $charset;
        $this->lang = $lang;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * 设置标题
     *
     * @param string $title 标题
     */
    public function setTitle(string $title)
    {
        $this->title = $title;
    }

    /**
     * 设置副标题
     *
     * @param string|null $subTitle 副标题
     */
    public function setSubTitle(?string $subTitle)
    {
        $this->subTitle = $subTitle;
    }

    /**
     * 设置聚合地址
     *
     * @param string $feedUrl 聚合地址
     */
    public function setFeedUrl(string $feedUrl)
    {
        $this->feedUrl = $feedUrl;
    }

    /**
     * @return string
     */
    public function getFeedUrl(): string
    {
        return $this->feedUrl;
    }

    /**
     * 设置主页
     *
     * @param string $baseUrl 主页地址
     */
    public function setBaseUrl(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * $item的格式为
     * <code>
     * array (
     *     'title'      =>  'xxx',
     *     'content'    =>  'xxx',
     *     'excerpt'    =>  'xxx',
     *     'date'       =>  'xxx',
     *     'link'       =>  'xxx',
     *     'author'     =>  'xxx',
     *     'comments'   =>  'xxx',
     *     'commentsUrl'=>  'xxx',
     *     'commentsFeedUrl' => 'xxx',
     * )
     * </code>
     *
     * @param array $item
     */
    public function addItem(array $item)
    {
        $this->items[] = $item;
    }

    /**
     * 输出字符串
     *
     * @return string
     */
    public function __toString(): string
    {
        $result = '<?xml version="1.0" encoding="' . $this->charset . '"?>' . self::EOL;

        if (self::RSS1 == $this->type) {
            $result .= '<rdf:RDF
xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
xmlns="http://purl.org/rss/1.0/"
xmlns:dc="http://purl.org/dc/elements/1.1/">' . self::EOL;

            $content = '';
            $links = [];
            $lastUpdate = 0;

            foreach ($this->items as $item) {
                $title = $this->itemValue($item, 'title');
                $link = $this->itemValue($item, 'link');
                $date = (int) ($item['date'] ?? 0);
                $body = $this->itemValue($item, 'content');

                $content .= '<item rdf:about="' . $this->xmlText($link) . '">' . self::EOL;
                $content .= '<title>' . $this->xmlText($title) . '</title>' . self::EOL;
                $content .= '<link>' . $this->xmlText($link) . '</link>' . self::EOL;
                $content .= '<dc:date>' . $this->dateFormat($date) . '</dc:date>' . self::EOL;
                $content .= '<description>' . $this->xmlText(strip_tags($body)) . '</description>' . self::EOL;
                if (!empty($item['suffix'])) {
                    $content .= $item['suffix'];
                }
                $content .= '</item>' . self::EOL;

                $links[] = $link;

                if ($date > $lastUpdate) {
                    $lastUpdate = $date;
                }
            }

            $result .= '<channel rdf:about="' . $this->xmlText($this->feedUrl) . '">
<title>' . $this->xmlText($this->title) . '</title>
<link>' . $this->xmlText($this->baseUrl) . '</link>
<description>' . $this->xmlText($this->subTitle ?? '') . '</description>
<items>
<rdf:Seq>' . self::EOL;

            foreach ($links as $link) {
                $result .= '<rdf:li resource="' . $this->xmlText($link) . '"/>' . self::EOL;
            }

            $result .= '</rdf:Seq>
</items>
</channel>' . self::EOL;

            $result .= $content . '</rdf:RDF>';
        } elseif (self::RSS2 == $this->type) {
            $result .= '<rss version="2.0"
xmlns:content="http://purl.org/rss/1.0/modules/content/"
xmlns:dc="http://purl.org/dc/elements/1.1/"
xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
xmlns:atom="http://www.w3.org/2005/Atom"
xmlns:wfw="http://wellformedweb.org/CommentAPI/">
<channel>' . self::EOL;

            $content = '';
            $lastUpdate = 0;

            foreach ($this->items as $item) {
                $title = $this->itemValue($item, 'title');
                $link = $this->itemValue($item, 'link');
                $date = (int) ($item['date'] ?? 0);
                $authorName = $this->itemAuthorValue($item, 'screenName');
                $excerpt = $this->itemValue($item, 'excerpt');
                $body = $this->itemValue($item, 'content');
                $comments = isset($item['comments']) ? (string) $item['comments'] : '';
                $commentsFeedUrl = $this->itemValue($item, 'commentsFeedUrl');

                $content .= '<item>' . self::EOL;
                $content .= '<title>' . $this->xmlText($title) . '</title>' . self::EOL;
                $content .= '<link>' . $this->xmlText($link) . '</link>' . self::EOL;
                $content .= '<guid>' . $this->xmlText($link) . '</guid>' . self::EOL;
                $content .= '<pubDate>' . $this->dateFormat($date) . '</pubDate>' . self::EOL;
                $content .= '<dc:creator>' . $this->xmlText($authorName)
                    . '</dc:creator>' . self::EOL;

                if (!empty($item['category']) && is_array($item['category'])) {
                    foreach ($item['category'] as $category) {
                        $content .= '<category><![CDATA['
                            . $this->xmlCdata((string) ($category['name'] ?? ''))
                            . ']]></category>' . self::EOL;
                    }
                }

                if ($excerpt !== '') {
                    $content .= '<description><![CDATA[' . $this->xmlCdata(strip_tags($excerpt))
                        . ']]></description>' . self::EOL;
                }

                if ($body !== '') {
                    $content .= '<content:encoded xml:lang="' . $this->xmlText($this->lang) . '"><![CDATA['
                        . self::EOL .
                        $this->xmlCdata($body) . self::EOL .
                        ']]></content:encoded>' . self::EOL;
                }

                if ($comments !== '') {
                    $content .= '<slash:comments>' . $this->xmlText($comments) . '</slash:comments>' . self::EOL;
                }

                $content .= '<comments>' . $this->xmlText($link . '#comments') . '</comments>' . self::EOL;
                if ($commentsFeedUrl !== '') {
                    $content .= '<wfw:commentRss>' . $this->xmlText($commentsFeedUrl) . '</wfw:commentRss>' . self::EOL;
                }

                if (!empty($item['suffix'])) {
                    $content .= $item['suffix'];
                }

                $content .= '</item>' . self::EOL;

                if ($date > $lastUpdate) {
                    $lastUpdate = $date;
                }
            }

            $result .= '<title>' . $this->xmlText($this->title) . '</title>
<link>' . $this->xmlText($this->baseUrl) . '</link>
<atom:link href="' . $this->xmlText($this->feedUrl) . '" rel="self" type="application/rss+xml" />
<language>' . $this->xmlText($this->lang) . '</language>
<description>' . $this->xmlText($this->subTitle ?? '') . '</description>
<lastBuildDate>' . $this->dateFormat($lastUpdate) . '</lastBuildDate>
<pubDate>' . $this->dateFormat($lastUpdate) . '</pubDate>' . self::EOL;

            $result .= $content . '</channel>
</rss>';
        } elseif (self::ATOM1 == $this->type) {
            $result .= '<feed xmlns="http://www.w3.org/2005/Atom"
xmlns:thr="http://purl.org/syndication/thread/1.0"
xml:lang="' . $this->xmlText($this->lang) . '"
xml:base="' . $this->xmlText($this->baseUrl) . '"
>' . self::EOL;

            $content = '';
            $lastUpdate = 0;

            foreach ($this->items as $item) {
                $title = $this->itemValue($item, 'title');
                $link = $this->itemValue($item, 'link');
                $date = (int) ($item['date'] ?? 0);
                $authorName = $this->itemAuthorValue($item, 'screenName');
                $authorUrl = $this->itemAuthorValue($item, 'url');
                $excerpt = $this->itemValue($item, 'excerpt');
                $body = $this->itemValue($item, 'content');
                $comments = isset($item['comments']) ? (string) $item['comments'] : '';
                $commentsFeedUrl = $this->itemValue($item, 'commentsFeedUrl');

                $content .= '<entry>' . self::EOL;
                $content .= '<title type="html"><![CDATA[' . $this->xmlCdata($title) . ']]></title>' . self::EOL;
                $content .= '<link rel="alternate" type="text/html" href="' . $this->xmlText($link) . '" />' . self::EOL;
                $content .= '<id>' . $this->xmlText($link) . '</id>' . self::EOL;
                $content .= '<updated>' . $this->dateFormat($date) . '</updated>' . self::EOL;
                $content .= '<published>' . $this->dateFormat($date) . '</published>' . self::EOL;
                $content .= '<author>
    <name>' . $this->xmlText($authorName) . '</name>
    <uri>' . $this->xmlText($authorUrl) . '</uri>
</author>' . self::EOL;

                if (!empty($item['category']) && is_array($item['category'])) {
                    foreach ($item['category'] as $category) {
                        $content .= '<category scheme="' . $this->xmlText((string) ($category['permalink'] ?? '')) . '" term="'
                            . $this->xmlText((string) ($category['name'] ?? '')) . '" />' . self::EOL;
                    }
                }

                if ($excerpt !== '') {
                    $content .= '<summary type="html"><![CDATA[' . $this->xmlCdata($excerpt)
                        . ']]></summary>' . self::EOL;
                }

                if ($body !== '') {
                    $content .= '<content type="html" xml:base="' . $this->xmlText($link)
                        . '" xml:lang="' . $this->xmlText($this->lang) . '"><![CDATA['
                        . self::EOL .
                        $this->xmlCdata($body) . self::EOL .
                        ']]></content>' . self::EOL;
                }

                if ($comments !== '') {
                    $content .= '<link rel="replies" type="text/html" href="' . $this->xmlText($link . '#comments')
                        . '" thr:count="' . $this->xmlText($comments) . '" />' . self::EOL;

                    if ($commentsFeedUrl !== '') {
                        $content .= '<link rel="replies" type="application/atom+xml" href="'
                            . $this->xmlText($commentsFeedUrl) . '" thr:count="' . $this->xmlText($comments) . '"/>' . self::EOL;
                    }
                }

                if (!empty($item['suffix'])) {
                    $content .= $item['suffix'];
                }

                $content .= '</entry>' . self::EOL;

                if ($date > $lastUpdate) {
                    $lastUpdate = $date;
                }
            }

            $result .= '<title type="text">' . $this->xmlText($this->title) . '</title>
<subtitle type="text">' . $this->xmlText($this->subTitle ?? '') . '</subtitle>
<updated>' . $this->dateFormat($lastUpdate) . '</updated>
<generator uri="https://github.com/Yangsh888/TypeRenew/" version="' . $this->xmlText($this->version) . '">TypeRenew</generator>
<link rel="alternate" type="text/html" href="' . $this->xmlText($this->baseUrl) . '" />
<id>' . $this->xmlText($this->feedUrl) . '</id>
<link rel="self" type="application/atom+xml" href="' . $this->xmlText($this->feedUrl) . '" />
';
            $result .= $content . '</feed>';
        }

        return $result;
    }

    /**
     * 获取Feed时间格式
     *
     * @param integer $stamp 时间戳
     * @return string
     */
    public function dateFormat(int $stamp): string
    {
        if (self::RSS2 == $this->type) {
            return date(self::DATE_RFC822, $stamp);
        } elseif (self::RSS1 == $this->type || self::ATOM1 == $this->type) {
            return date(self::DATE_W3CDTF, $stamp);
        }

        return '';
    }

    private function itemValue(array $item, string $key): string
    {
        $value = $item[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    private function itemAuthorValue(array $item, string $field): string
    {
        $author = $item['author'] ?? null;
        if (is_object($author) && isset($author->{$field}) && is_scalar($author->{$field})) {
            return (string) $author->{$field};
        }

        if (is_array($author) && isset($author[$field]) && is_scalar($author[$field])) {
            return (string) $author[$field];
        }

        return '';
    }

    private function xmlText(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, $this->charset);
    }

    private function xmlCdata(mixed $value): string
    {
        return str_replace(']]>', ']]]]><![CDATA[>', (string) $value);
    }
}
