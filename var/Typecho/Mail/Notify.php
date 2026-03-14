<?php

namespace Typecho\Mail;

use Typecho\Common;
use Typecho\Db;
use Typecho\Date;
use Utils\Helper;
use Widget\Options;

class Notify
{
    public static function buildFromComment(object $comment, string $event, Options $options): array
    {
        $settings = self::settings($options);
        if (!$settings['enable']) {
            return [];
        }

        $jobs = [];
        $status = (string) ($comment->status ?? '');
        $isApproved = $status === 'approved';
        $isModerationRequired = (bool) ($options->commentsRequireModeration ?? false);

        if (!$isApproved) {
            if ($event === 'created' && $settings['notifyPending'] && $isModerationRequired) {
                $admin = self::adminRecipient($comment, $options, $settings['adminMail']);
                if ($admin) {
                    if (!Queue::isUnsub($admin['mail'], 'pending', Db::get())) {
                        $jobs[] = self::makeJob('notice', $admin['mail'], $admin['name'], $comment, $options, $settings);
                    }
                }
            }

            return $jobs;
        }

        if (!in_array($event, ['created', 'approved'], true)) {
            return $jobs;
        }

        $authorRecipient = self::authorRecipient($comment, $options, $settings['adminMail']);
        if ($settings['notifyOwner'] && $authorRecipient) {
            $currentMail = strtolower(trim((string) ($comment->mail ?? '')));
            $ownerMail = strtolower(trim((string) $authorRecipient['mail']));
            $isSelfOwner = (int) ($comment->authorId ?? 0) === (int) ($comment->ownerId ?? 0);
            $isSameOwnerMail = $currentMail !== '' && $currentMail === $ownerMail;
            if ((!$isSelfOwner && !$isSameOwnerMail) || $settings['notifyMe']) {
                if (!Queue::isUnsub($authorRecipient['mail'], 'owner', Db::get())) {
                    $jobs[] = self::makeJob('owner', $authorRecipient['mail'], $authorRecipient['name'], $comment, $options, $settings);
                }
            }
        }

        $parentId = (int) ($comment->parent ?? 0);
        if ($settings['notifyGuest'] && $parentId > 0) {
            try {
                $parent = Helper::widgetById('comments', $parentId);
            } catch (\Throwable $e) {
                return $jobs;
            }
            $parentMail = trim((string) ($parent->mail ?? ''));
            $currentMail = strtolower(trim((string) ($comment->mail ?? '')));
            if ($parentMail !== '' && filter_var($parentMail, FILTER_VALIDATE_EMAIL)) {
                if (!$settings['notifyMe'] && strtolower($parentMail) === $currentMail) {
                    return $jobs;
                }

                if ($authorRecipient && strtolower($parentMail) === strtolower(trim((string) $authorRecipient['mail']))) {
                    return $jobs;
                }

                if (Queue::isUnsub($parentMail, 'reply', Db::get())) {
                    return $jobs;
                }

                $jobs[] = self::makeJob('guest', $parentMail, (string) ($parent->author ?? ''), $comment, $options, $settings);
            }
        }

        return $jobs;
    }

    public static function explainFromComment(object $comment, string $event, Options $options): string
    {
        $settings = self::settings($options);
        if (!$settings['enable']) {
            return 'mailEnable=0';
        }

        $status = (string) ($comment->status ?? '');
        $isApproved = $status === 'approved';
        $isModerationRequired = (bool) ($options->commentsRequireModeration ?? false);

        if (!$isApproved) {
            if ($event !== 'created') {
                return 'status=' . $status . ', event=' . $event;
            }
            if (!$settings['notifyPending']) {
                return 'mailNotifyPending=0';
            }
            if (!$isModerationRequired) {
                return 'commentsRequireModeration=0';
            }
            $admin = self::adminRecipient($comment, $options, $settings['adminMail']);
            if (!$admin) {
                return 'pending recipient unavailable';
            }
            if (Queue::isUnsub($admin['mail'], 'pending', Db::get())) {
                return 'pending recipient unsubscribed';
            }
            return 'pending job should be created';
        }

        if (!in_array($event, ['created', 'approved'], true)) {
            return 'event not supported: ' . $event;
        }

        $reasons = [];
        $authorRecipient = self::authorRecipient($comment, $options, $settings['adminMail']);
        if (!$settings['notifyOwner']) {
            $reasons[] = 'mailNotifyOwner=0';
        } elseif (!$authorRecipient) {
            $reasons[] = 'owner recipient unavailable';
        } else {
            $currentMail = strtolower(trim((string) ($comment->mail ?? '')));
            $ownerMail = strtolower(trim((string) $authorRecipient['mail']));
            $isSelfOwner = (int) ($comment->authorId ?? 0) === (int) ($comment->ownerId ?? 0);
            $isSameOwnerMail = $currentMail !== '' && $currentMail === $ownerMail;
            if (!$settings['notifyMe'] && $isSelfOwner) {
                $reasons[] = 'self comment to owner';
            } elseif (!$settings['notifyMe'] && $isSameOwnerMail) {
                $reasons[] = 'comment mail equals owner mail';
            } elseif (Queue::isUnsub($authorRecipient['mail'], 'owner', Db::get())) {
                $reasons[] = 'owner unsubscribed';
            }
        }

        $parentId = (int) ($comment->parent ?? 0);
        if (!$settings['notifyGuest']) {
            $reasons[] = 'mailNotifyGuest=0';
        } elseif ($parentId <= 0) {
            $reasons[] = 'no parent comment';
        } else {
            try {
                $parent = Helper::widgetById('comments', $parentId);
                $parentMail = trim((string) ($parent->mail ?? ''));
                $currentMail = strtolower(trim((string) ($comment->mail ?? '')));
                if ($parentMail === '' || !filter_var($parentMail, FILTER_VALIDATE_EMAIL)) {
                    $reasons[] = 'parent mail invalid';
                } elseif (!$settings['notifyMe'] && strtolower($parentMail) === $currentMail) {
                    $reasons[] = 'mailNotifyMe=0 and same mail';
                } elseif ($authorRecipient && strtolower($parentMail) === strtolower(trim((string) $authorRecipient['mail']))) {
                    $reasons[] = 'parent mail equals owner mail';
                } elseif (Queue::isUnsub($parentMail, 'reply', Db::get())) {
                    $reasons[] = 'guest unsubscribed';
                }
            } catch (\Throwable $e) {
                $reasons[] = 'parent load failed';
            }
        }

        if (empty($reasons)) {
            return 'jobs empty by conditions';
        }

        return implode('; ', $reasons);
    }

    public static function subject(string $type, object $comment, array $settings): string
    {
        $title = (string) ($comment->title ?? '');

        $raw = match ($type) {
            'guest' => (string) ($settings['subjectGuest'] ?: '您在「{title}」的评论有了回复'),
            'notice' => (string) ($settings['subjectPending'] ?: '文章「{title}」有一条待审评论'),
            default => (string) ($settings['subjectOwner'] ?: '你的文章「{title}」有了新评论')
        };

        return str_replace('{title}', $title, $raw);
    }

    public static function vars(string $type, object $comment, Options $options, array $settings): array
    {
        $now = new Date((int) ($comment->created ?? time()));
        $time = $now->format('Y-m-d H:i:s');

        $parentText = '';
        $parentTextPlain = '';
        $parentName = '';
        $parentMail = '';
        $parentTime = '';

        $parentId = (int) ($comment->parent ?? 0);
        if ($parentId > 0) {
            try {
                $parent = Helper::widgetById('comments', $parentId);
                $parentName = (string) ($parent->author ?? '');
                $parentMail = (string) ($parent->mail ?? '');
                $parentText = (string) ($parent->content ?? '');
                $parentTextPlain = trim((string) ($parent->text ?? ''));
                if ($parentTextPlain === '') {
                    $parentTextPlain = trim(html_entity_decode(strip_tags($parentText), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }
                $pt = new Date((int) ($parent->created ?? time()));
                $parentTime = $pt->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                $parentName = '';
                $parentMail = '';
                $parentText = '';
                $parentTextPlain = '';
                $parentTime = '';
            }
        }

        $siteUrl = (string) ($options->siteUrl ?? '');
        $adminUrl = (string) ($options->adminUrl ?? '');
        $manageUrl = $adminUrl !== '' ? $adminUrl . 'manage-comments.php' : '';
        $postAuthor = '';
        try {
            $post = Helper::widgetById('Contents', (int) ($comment->cid ?? 0));
            $postAuthor = (string) ($post->author->screenName ?? '');
        } catch (\Throwable $e) {
            $postAuthor = '';
        }

        $commentHtml = (string) ($comment->content ?? '');
        $commentTextPlain = trim((string) ($comment->text ?? ''));
        if ($commentTextPlain === '') {
            $commentTextPlain = trim(html_entity_decode(strip_tags($commentHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        $commentTextPlain = preg_replace("/\r\n?/", "\n", $commentTextPlain) ?? $commentTextPlain;

        $vars = [
            'subject' => self::subject($type, $comment, $settings),
            'title' => (string) ($comment->title ?? ''),
            'PostAuthor' => $postAuthor,
            'time' => $time,
            'commentText' => $commentTextPlain,
            'commentTextPlain' => $commentTextPlain,
            'commentHtml' => $commentHtml,
            'author' => (string) ($comment->author ?? ''),
            'mail' => (string) ($comment->mail ?? ''),
            'ip' => (string) ($comment->ip ?? ''),
            'permalink' => (string) ($comment->permalink ?? ''),
            'siteUrl' => $siteUrl,
            'siteTitle' => (string) ($options->title ?? ''),
            'Pname' => $parentName,
            'Ptext' => $parentTextPlain,
            'Phtml' => $parentText,
            'Pmail' => $parentMail,
            'Ptime' => $parentTime,
            'manageurl' => $manageUrl,
            'status' => self::statusLabel((string) ($comment->status ?? ''))
        ];

        $vars['unsubUrl'] = $type === 'guest'
            ? self::unsubUrl($parentMail, 'reply', $options)
            : '';

        return $vars;
    }

    private static function makeJob(
        string $type,
        string $to,
        string $toName,
        object $comment,
        Options $options,
        array $settings
    ): array {
        $vars = self::vars($type, $comment, $options, $settings);
        if ($type === 'owner') {
            $vars['unsubUrl'] = self::unsubUrl($to, 'owner', $options);
        } elseif ($type === 'notice') {
            $vars['unsubUrl'] = self::unsubUrl($to, 'pending', $options);
        }
        $html = Template::render($type, $vars, $options);

        return [
            'type' => $type,
            'to' => $to,
            'toName' => $toName,
            'subject' => $vars['subject'],
            'html' => $html,
            'text' => strip_tags($html)
        ];
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'approved' => '通过',
            'waiting' => '待审',
            'spam' => '垃圾',
            default => $status
        };
    }

    private static function authorRecipient(object $comment, Options $options, string $fallback): ?array
    {
        $db = Db::get();
        $row = $db->fetchRow(
            $db->select('uid', 'screenName', 'mail')->from('table.users')->where('uid = ?', (int) ($comment->ownerId ?? 0))->limit(1)
        );

        $mail = trim((string) ($row['mail'] ?? ''));
        if ($mail === '') {
            $mail = trim((string) $fallback);
        }

        if ($mail === '' || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return [
            'name' => (string) ($row['screenName'] ?? ''),
            'mail' => $mail
        ];
    }

    private static function adminRecipient(object $comment, Options $options, string $adminMail): ?array
    {
        $mail = trim($adminMail);
        if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            return ['name' => (string) ($options->title ?? ''), 'mail' => $mail];
        }

        return self::authorRecipient($comment, $options, '');
    }

    private static function unsubUrl(string $email, string $scope, Options $options): string
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        $ts = time();
        $payload = 'v2|' . $email . '|' . $scope . '|' . $ts;
        $sig = hash_hmac('sha256', $payload, (string) ($options->secret ?? ''), true);
        $token = rtrim(strtr(base64_encode($payload . '|' . base64_encode($sig)), '+/', '-_'), '=');
        return Common::url('/action/mail?do=unsub&token=' . rawurlencode($token), (string) $options->index);
    }

    private static function settings(Options $options): array
    {
        return [
            'enable' => (bool) ((int) ($options->mailEnable ?? 0) === 1),
            'adminMail' => (string) ($options->mailAdmin ?? ''),
            'notifyOwner' => (bool) ((int) ($options->mailNotifyOwner ?? 1) === 1),
            'notifyGuest' => (bool) ((int) ($options->mailNotifyGuest ?? 1) === 1),
            'notifyPending' => (bool) ((int) ($options->mailNotifyPending ?? 1) === 1),
            'notifyMe' => (bool) ((int) ($options->mailNotifyMe ?? 0) === 1),
            'subjectOwner' => (string) ($options->mailSubjectOwner ?? ''),
            'subjectGuest' => (string) ($options->mailSubjectGuest ?? ''),
            'subjectPending' => (string) ($options->mailSubjectPending ?? '')
        ];
    }
}
