<?php

namespace Utils;

class Defaults
{
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
}
