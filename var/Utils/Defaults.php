<?php

namespace Utils;

use Typecho\Common;
use Typecho\Cookie;
use Typecho\Request;

class Defaults
{
    public static function language(): string
    {
        $serverLang = Request::getInstance()->getServer('TYPECHO_LANG');

        if (!empty($serverLang)) {
            return $serverLang;
        }

        $lang = 'zh_CN';
        $request = Request::getInstance();

        if ($request->is('lang')) {
            $lang = $request->get('lang');
            Cookie::set('lang', $lang);
        }

        return Cookie::get('lang', $lang);
    }

    public static function siteUrl(): string
    {
        $request = Request::getInstance();
        return $request->isCli() ? $request->getServer('TYPECHO_SITE_URL', 'http://localhost') : $request->getRequestRoot();
    }

    public static function installSeedOptions(array $context = []): array
    {
        return array_merge(
            self::baseOptions(),
            self::routingOptions(),
            self::mailOptions(),
            [
                'lang' => $context['lang'] ?? self::language(),
                'generator' => $context['generator'] ?? Common::generator(),
                'siteUrl' => $context['siteUrl'] ?? self::siteUrl(),
                'mailCronKey' => $context['mailCronKey'] ?? Common::randString(32),
                'secret' => $context['secret'] ?? Common::randString(32, true),
                'installed' => (int) ($context['installed'] ?? 0),
            ]
        );
    }

    public static function bootstrapOptions(array $context = []): array
    {
        return array_merge(
            self::routingOptions(),
            [
                'plugins' => 'a:0:{}',
                'charset' => 'UTF-8',
                'contentType' => 'text/html',
                'timezone' => '28800',
                'installed' => (int) ($context['installed'] ?? 0),
                'generator' => $context['generator'] ?? Common::generator(),
                'siteUrl' => $context['siteUrl'] ?? self::siteUrl(),
                'lang' => $context['lang'] ?? self::language(),
                'secret' => $context['secret'] ?? Common::randString(32, true),
            ]
        );
    }

    public static function repairableOptions(array $context = []): array
    {
        return array_merge(
            self::mailOptions(),
            ['mailCronKey' => $context['mailCronKey'] ?? Common::randString(32)]
        );
    }

    public static function routingTable(): array
    {
        return [
            'index' => ['url' => '/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'archive' => ['url' => '/blog/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'do' => ['url' => '/action/[action:alpha]', 'widget' => '\Widget\Action', 'action' => 'action'],
            'post' => ['url' => '/archives/[cid:digital]/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'attachment' => ['url' => '/attachment/[cid:digital]/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'category' => ['url' => '/category/[slug]/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'tag' => ['url' => '/tag/[slug]/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'author' => ['url' => '/author/[uid:digital]/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'search' => ['url' => '/search/[keywords]/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'index_page' => ['url' => '/page/[page:digital]/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'archive_page' => ['url' => '/blog/page/[page:digital]/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'category_page' => ['url' => '/category/[slug]/[page:digital]/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'tag_page' => ['url' => '/tag/[slug]/[page:digital]/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'author_page' => ['url' => '/author/[uid:digital]/[page:digital]/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'search_page' => ['url' => '/search/[keywords]/[page:digital]/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'archive_year' => ['url' => '/[year:digital:4]/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'archive_month' => ['url' => '/[year:digital:4]/[month:digital:2]/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'archive_day' => ['url' => '/[year:digital:4]/[month:digital:2]/[day:digital:2]/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'archive_year_page' => ['url' => '/[year:digital:4]/page/[page:digital]/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'archive_month_page' => ['url' => '/[year:digital:4]/[month:digital:2]/page/[page:digital]/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'archive_day_page' => ['url' => '/[year:digital:4]/[month:digital:2]/[day:digital:2]/page/[page:digital]/', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'comment_page' => ['url' => '[permalink:string]/comment-page-[commentPage:digital]', 'widget' => '\Widget\CommentPage', 'action' => 'action'],
            'feed' => ['url' => '/feed[feed:string:0]', 'widget' => '\Widget\Feed', 'action' => 'render'],
            'page' => ['url' => '/[slug].html', 'widget' => '\Widget\Archive', 'action' => 'render'],
            'feedback' => ['url' => '[permalink:string]/[type:alpha]', 'widget' => '\Widget\Feedback', 'action' => 'action'],
        ];
    }

    public static function mailOptions(): array
    {
        return [
            'mailEnable' => 0,
            'mailTransport' => 'smtp',
            'mailAdmin' => '',
            'mailFrom' => '',
            'mailFromName' => '',
            'mailSmtpHost' => '',
            'mailSmtpPort' => 25,
            'mailSmtpUser' => '',
            'mailSmtpPass' => '',
            'mailSmtpSecure' => '',
            'mailQueueMode' => 'async',
            'mailAsyncIps' => '',
            'mailBatchSize' => 50,
            'mailMaxAttempts' => 3,
            'mailKeepDays' => 30,
            'mailNotifyOwner' => 1,
            'mailNotifyGuest' => 1,
            'mailNotifyPending' => 1,
            'mailNotifyMe' => 0,
            'mailSubjectOwner' => '',
            'mailSubjectGuest' => '',
            'mailSubjectPending' => '',
        ];
    }

    private static function baseOptions(): array
    {
        return [
            'theme' => 'default',
            'theme:default' => json_encode([
                'logoUrl' => '',
                'sidebarBlock' => [
                    'ShowRecentPosts',
                    'ShowRecentComments',
                    'ShowCategory',
                    'ShowArchive',
                    'ShowOther',
                ],
            ]),
            'timezone' => '28800',
            'charset' => 'UTF-8',
            'contentType' => 'text/html',
            'gzip' => 0,
            'title' => 'Hello World',
            'description' => 'Your description here.',
            'keywords' => 'typerenew,php,blog',
            'rewrite' => 0,
            'frontPage' => 'recent',
            'frontArchive' => 0,
            'commentsRequireMail' => 1,
            'commentsWhitelist' => 0,
            'commentsRequireUrl' => 0,
            'commentsRequireModeration' => 0,
            'plugins' => 'a:0:{}',
            'commentDateFormat' => 'F jS, Y \a\t h:i a',
            'defaultCategory' => 1,
            'allowRegister' => 0,
            'defaultAllowComment' => 1,
            'defaultAllowPing' => 1,
            'defaultAllowFeed' => 1,
            'pageSize' => 5,
            'postsListSize' => 10,
            'commentsListSize' => 10,
            'commentsHTMLTagAllowed' => null,
            'postDateFormat' => 'Y-m-d',
            'feedFullText' => 1,
            'editorSize' => 350,
            'autoSave' => 0,
            'markdown' => 1,
            'xmlrpcMarkdown' => 0,
            'commentsMaxNestingLevels' => 5,
            'commentsPostTimeout' => 24 * 3600 * 30,
            'commentsUrlNofollow' => 1,
            'commentsShowUrl' => 1,
            'commentsMarkdown' => 0,
            'commentsPageBreak' => 0,
            'commentsThreaded' => 1,
            'commentsPageSize' => 20,
            'commentsPageDisplay' => 'last',
            'commentsOrder' => 'ASC',
            'commentsCheckReferer' => 1,
            'commentsAutoClose' => 0,
            'commentsPostIntervalEnable' => 1,
            'commentsPostInterval' => 60,
            'commentsShowCommentOnly' => 0,
            'commentsAvatar' => 1,
            'commentsAvatarRating' => 'G',
            'commentsAntiSpam' => 1,
            'attachmentTypes' => '@image@',
            'cacheStatus' => 0,
            'cacheDriver' => 'redis',
            'cacheTtl' => 300,
            'cachePrefix' => 'typerenew:cache:',
            'cacheCommentFlush' => 1,
            'cacheRedisHost' => '127.0.0.1',
            'cacheRedisPort' => 6379,
            'cacheRedisPassword' => '',
            'cacheRedisDatabase' => 0,
            'allowXmlRpc' => 1,
        ];
    }

    private static function routingOptions(): array
    {
        return [
            'routingTable' => \Typecho\Common::jsonEncode(self::routingTable(), 0, '{}'),
            'actionTable' => \Typecho\Common::jsonEncode([], 0, '[]'),
            'panelTable' => \Typecho\Common::jsonEncode([], 0, '[]'),
        ];
    }

}
