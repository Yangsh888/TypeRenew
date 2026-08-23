<?php

namespace Typecho;

class Feed
{
    public const RSS1 = 'RSS 1.0';

    public const RSS2 = 'RSS 2.0';

    public const ATOM1 = 'ATOM 1.0';

    public const DATE_RFC822 = 'r';

    public const DATE_W3CDTF = 'c';

    public const EOL = "\n";

    private string $feedUrl;

    private string $baseUrl;

    private string $title;

    private ?string $subTitle;

    private array $items = [];

    private ?int $lastUpdate = null;

    private array $links = [];

    public function __construct(
        private $version,
        private string $type = self::RSS2,
        private string $charset = 'UTF-8',
        private string $lang = 'en'
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setTitle(string $title)
    {
        $this->title = $title;
    }

    public function setSubTitle(?string $subTitle)
    {
        $this->subTitle = $subTitle;
    }

    public function setFeedUrl(string $feedUrl)
    {
        $this->feedUrl = $feedUrl;
    }

    public function getFeedUrl(): string
    {
        return $this->feedUrl;
    }

    public function setBaseUrl(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    public function setLastUpdate(int $stamp)
    {
        $this->lastUpdate = $stamp;
    }

    public function setLinks(array $links)
    {
        $this->links = array_values(array_filter($links, 'is_string'));
    }

    public function addItem(array $item)
    {
        $this->items[] = $item;
    }

    public function __toString(): string
    {
        $result = $this->head();

        foreach ($this->items as $item) {
            $result .= $this->item($item);
        }

        return $result . $this->foot();
    }

    public function head(): string
    {
        $result = '<?xml version="1.0" encoding="' . $this->charset . '"?>' . self::EOL;

        if (self::RSS1 == $this->type) {
            $result .= '<rdf:RDF
xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
xmlns="http://purl.org/rss/1.0/"
xmlns:dc="http://purl.org/dc/elements/1.1/">' . self::EOL;

            $result .= '<channel rdf:about="' . $this->xmlText($this->feedUrl) . '">
<title>' . $this->xmlText($this->title) . '</title>
<link>' . $this->xmlText($this->baseUrl) . '</link>
<description>' . $this->xmlText($this->subTitle ?? '') . '</description>
<items>
<rdf:Seq>' . self::EOL;

            foreach ($this->resolveLinks() as $link) {
                $result .= '<rdf:li resource="' . $this->xmlText($link) . '"/>' . self::EOL;
            }

            $result .= '</rdf:Seq>
</items>
</channel>' . self::EOL;
        } elseif (self::RSS2 == $this->type) {
            $result .= '<rss version="2.0"
xmlns:content="http://purl.org/rss/1.0/modules/content/"
xmlns:dc="http://purl.org/dc/elements/1.1/"
xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
xmlns:atom="http://www.w3.org/2005/Atom">
<channel>' . self::EOL;

            $result .= '<title>' . $this->xmlText($this->title) . '</title>
<link>' . $this->xmlText($this->baseUrl) . '</link>
<atom:link href="' . $this->xmlText($this->feedUrl) . '" rel="self" type="application/rss+xml" />
<language>' . $this->xmlText($this->lang) . '</language>
<description>' . $this->xmlText($this->subTitle ?? '') . '</description>
<lastBuildDate>' . $this->dateFormat($this->resolveLastUpdate()) . '</lastBuildDate>
<pubDate>' . $this->dateFormat($this->resolveLastUpdate()) . '</pubDate>' . self::EOL;
        } elseif (self::ATOM1 == $this->type) {
            $result .= '<feed xmlns="http://www.w3.org/2005/Atom"
xmlns:thr="http://purl.org/syndication/thread/1.0"
xml:lang="' . $this->xmlText($this->lang) . '"
xml:base="' . $this->xmlText($this->baseUrl) . '"
>' . self::EOL;

            $result .= '<title type="text">' . $this->xmlText($this->title) . '</title>
<subtitle type="text">' . $this->xmlText($this->subTitle ?? '') . '</subtitle>
<updated>' . $this->dateFormat($this->resolveLastUpdate()) . '</updated>
<generator version="' . $this->xmlText($this->version) . '">TypeRenew</generator>
<link rel="alternate" type="text/html" href="' . $this->xmlText($this->baseUrl) . '" />
<id>' . $this->xmlText($this->feedUrl) . '</id>
<link rel="self" type="application/atom+xml" href="' . $this->xmlText($this->feedUrl) . '" />
';
        }

        return $result;
    }

    public function item(array $item): string
    {
        if (self::RSS1 == $this->type) {
            $title = $this->itemValue($item, 'title');
            $link = $this->itemValue($item, 'link');
            $date = (int) ($item['date'] ?? 0);
            $body = $this->itemValue($item, 'content');

            $content = '<item rdf:about="' . $this->xmlText($link) . '">' . self::EOL;
            $content .= '<title>' . $this->xmlText($title) . '</title>' . self::EOL;
            $content .= '<link>' . $this->xmlText($link) . '</link>' . self::EOL;
            $content .= '<dc:date>' . $this->dateFormat($date) . '</dc:date>' . self::EOL;
            $content .= '<description>' . $this->xmlText(strip_tags($body)) . '</description>' . self::EOL;
            if (!empty($item['suffix'])) {
                $content .= $item['suffix'];
            }
            $content .= '</item>' . self::EOL;

            return $content;
        } elseif (self::RSS2 == $this->type) {
            $title = $this->itemValue($item, 'title');
            $link = $this->itemValue($item, 'link');
            $date = (int) ($item['date'] ?? 0);
            $authorName = $this->itemAuthorValue($item, 'screenName');
            $excerpt = $this->itemValue($item, 'excerpt');
            $body = $this->itemValue($item, 'content');
            $comments = isset($item['comments']) ? (string) $item['comments'] : '';

            $content = '<item>' . self::EOL;
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

            if (!empty($item['suffix'])) {
                $content .= $item['suffix'];
            }

            $content .= '</item>' . self::EOL;

            return $content;
        } elseif (self::ATOM1 == $this->type) {
            $title = $this->itemValue($item, 'title');
            $link = $this->itemValue($item, 'link');
            $date = (int) ($item['date'] ?? 0);
            $authorName = $this->itemAuthorValue($item, 'screenName');
            $authorUrl = $this->itemAuthorValue($item, 'url');
            $excerpt = $this->itemValue($item, 'excerpt');
            $body = $this->itemValue($item, 'content');
            $comments = isset($item['comments']) ? (string) $item['comments'] : '';
            $commentsFeedUrl = $this->itemValue($item, 'commentsFeedUrl');

            $content = '<entry>' . self::EOL;
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

            return $content;
        }

        return '';
    }

    public function foot(): string
    {
        if (self::RSS1 == $this->type) {
            return '</rdf:RDF>';
        } elseif (self::RSS2 == $this->type) {
            return '</channel>
</rss>';
        } elseif (self::ATOM1 == $this->type) {
            return '</feed>';
        }

        return '';
    }

    public function dateFormat(int $stamp): string
    {
        return match ($this->type) {
            self::RSS2 => gmdate(self::DATE_RFC822, $stamp),
            self::RSS1, self::ATOM1 => gmdate(self::DATE_W3CDTF, $stamp),
            default => '',
        };
    }

    private function resolveLastUpdate(): int
    {
        $max = $this->lastUpdate ?? 0;

        foreach ($this->items as $item) {
            $date = (int) ($item['date'] ?? 0);
            if ($date > $max) {
                $max = $date;
            }
        }

        return $max;
    }

    private function resolveLinks(): array
    {
        if (!empty($this->links)) {
            return $this->links;
        }

        $links = [];
        foreach ($this->items as $item) {
            $links[] = $this->itemValue($item, 'link');
        }

        return $links;
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
        return htmlspecialchars(self::stripControls($value), ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, $this->charset);
    }

    private function xmlCdata(mixed $value): string
    {
        return str_replace(']]>', ']]]]><![CDATA[>', self::stripControls($value));
    }

    private static function stripControls(mixed $value): string
    {
        return preg_replace('/[^	
 -\x{10FFFF}]/u', '', (string) $value) ?? (string) $value;
    }
}
