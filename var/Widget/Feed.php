<?php

namespace Widget;

use Typecho\Common;
use Typecho\Config;
use Typecho\Router;
use Typecho\Widget\Exception as WidgetException;
use Widget\Base\Contents;
use Typecho\Feed as FeedGenerator;
use Widget\Comments\Recent;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Feed extends Contents
{
    private FeedGenerator $feed;

    private string $feedContentType = 'application/rss+xml';

    private bool $isComments = false;

    private ?Archive $feedArchive = null;

    protected function initParameter(Config $parameter)
    {
        $parameter->setDefault([
            'pageSize'       => 10,
        ]);
    }

    public function execute()
    {
        $feedPath = $this->request->get('feed', '/');
        $feedType = FeedGenerator::RSS2;
        $feedContentType = 'application/rss+xml';
        $currentFeedUrl = $this->options->feedUrl;
        $isComments = false;

        if (preg_match("/^\/rss(\/|$)/", $feedPath)) {
            $feedPath = substr($feedPath, 4);
            $feedType = FeedGenerator::RSS1;
            $currentFeedUrl = $this->options->feedRssUrl;
            $feedContentType = 'application/rdf+xml';
        } elseif (preg_match("/^\/atom(\/|$)/", $feedPath)) {
            $feedPath = substr($feedPath, 5);
            $feedType = FeedGenerator::ATOM1;
            $currentFeedUrl = $this->options->feedAtomUrl;
            $feedContentType = 'application/atom+xml';
        }

        $feed = new FeedGenerator(
            Common::VERSION,
            $feedType,
            $this->options->charset,
            _t('zh-CN')
        );

        if (preg_match("/^\/comments\/?$/", $feedPath)) {
            $isComments = true;
            $currentFeedUrl = Common::url('/comments/', $currentFeedUrl);
            $feed->setBaseUrl($this->options->siteUrl);
            $feed->setSubTitle($this->options->description);
        } else {
            $archive = Router::match($feedPath, [
                'pageSize' => $this->parameter->pageSize,
                'isFeed'   => true
            ]);

            if (!($archive instanceof Archive)) {
                throw new WidgetException(_t('聚合页不存在'), 404);
            }

            switch ($feedType) {
                case FeedGenerator::RSS1:
                    $currentFeedUrl = $archive->getArchiveFeedRssUrl();
                    break;
                case FeedGenerator::ATOM1:
                    $currentFeedUrl = $archive->getArchiveFeedAtomUrl();
                    break;
                default:
                    $currentFeedUrl = $archive->getArchiveFeedUrl();
                    break;
            }

            $feed->setBaseUrl($archive->getArchiveUrl());
            $feed->setSubTitle($archive->getArchiveDescription());
        }

        if ($currentFeedUrl != $this->request->getRequestUrl()) {
            $this->response->redirect($currentFeedUrl, true);
        }

        $this->feed = $feed;
        $this->feedContentType = $feedContentType;
        $this->isComments = $isComments;
        $this->feedArchive = $archive ?? null;

        if ($isComments || $archive->is('single')) {
            $feed->setTitle(_t(
                '%s 的评论',
                $this->options->title . ($isComments ? '' : ' - ' . $archive->getArchiveTitle())
            ));
        } else {
            $feed->setTitle($this->options->title
                . ($archive->getArchiveTitle() ? ' - ' . $archive->getArchiveTitle() : ''));
        }

        $feed->setFeedUrl($currentFeedUrl);
        $this->prescan($feed);
    }

    private function prescan(FeedGenerator $feed)
    {
        $lastUpdate = 0;
        $links = [];
        $needLinks = FeedGenerator::RSS1 === $feed->getType();
        $archive = $this->feedArchive;

        if ($this->isComments || $archive->is('single')) {
            $comments = $this->isComments
                ? Recent::alloc('pageSize=10')
                : Recent::alloc('pageSize=10&parentId=' . $archive->cid);

            while ($comments->next()) {
                $created = (int) $comments->created;
                if ($created > $lastUpdate) {
                    $lastUpdate = $created;
                }

                if ($needLinks) {
                    $links[] = $comments->permalink;
                }
            }
        } else {
            while ($archive->next()) {
                $created = (int) $archive->created;
                if ($created > $lastUpdate) {
                    $lastUpdate = $created;
                }

                if ($needLinks) {
                    $links[] = $archive->permalink;
                }
            }
        }

        $feed->setLastUpdate($lastUpdate);
        if ($needLinks) {
            $feed->setLinks($links);
        }
    }

    public function feed(
        FeedGenerator $feed,
        string $contentType,
        bool $isComments,
        ?Archive $archive
    ) {
        if ($isComments || $archive->is('single')) {
            if ($isComments) {
                $comments = Recent::alloc('pageSize=10');
            } else {
                $comments = Recent::alloc('pageSize=10&parentId=' . $archive->cid);
            }

            while ($comments->next()) {
                $suffix = self::pluginHandle()->trigger($plugged)->call(
                    'commentFeedItem',
                    $feed->getType(),
                    $comments
                );

                if (!$plugged) {
                    $suffix = null;
                }

                echo $feed->item([
                    'title'   => $comments->author,
                    'content' => $comments->content,
                    'date'    => $comments->created,
                    'link'    => $comments->permalink,
                    'author'  => (object)[
                        'screenName' => $comments->author,
                        'url'        => $comments->url,
                        'mail'       => $comments->mail
                    ],
                    'excerpt' => strip_tags($comments->content),
                    'suffix'  => $suffix
                ]);
                $this->streamFlush();
            }
        } else {
            while ($archive->next()) {
                $suffix = self::pluginHandle()->trigger($plugged)->call('feedItem', $feed->getType(), $archive);

                if (!$plugged) {
                    $suffix = null;
                }

                echo $feed->item([
                    'title'           => $archive->title,
                    'content'         => $this->options->feedFullText ? $archive->content
                        : (false !== strpos((string) $archive->text, '<!--more-->') ? $archive->excerpt .
                            "<p class=\"more\"><a href=\"{$archive->permalink}\" title=\"{$archive->title}\">[...]</a></p>"
                            : $archive->content),
                    'date'            => $archive->created,
                    'link'            => $archive->permalink,
                    'author'          => $archive->author,
                    'excerpt'         => $archive->plainExcerpt,
                    'category'        => $archive->categories,
                    'comments'        => $archive->commentsNum,
                    'commentsFeedUrl' => Common::url($archive->path, $feed->getFeedUrl()),
                    'suffix'          => $suffix
                ]);
                $this->streamFlush();
            }
        }

        $this->response->setContentType($contentType);
    }

    public function render()
    {
        $this->response->setContentType($this->feedContentType);
        \Typecho\Response::getInstance()->sendHeaders();

        echo $this->feed->head();
        $this->streamFlush();

        $this->feed($this->feed, $this->feedContentType, $this->isComments, $this->feedArchive);

        echo $this->feed->foot();
    }

    private function streamFlush()
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
