<?php

namespace Widget;

use Typecho\Common;
use Typecho\Cookie;
use Typecho\Db;
use Typecho\Router;
use Typecho\Validate;
use Typecho\Widget\Exception;
use Utils\Comment;
use Widget\Base\Comments;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Feedback extends Comments implements ActionInterface
{
    private $content;

    /**
     * 对已注册用户的保护性检测
     *
     * @param string $userName 用户名
     * @return bool
     * @throws Db\Exception
     */
    public function requireUserLogin(string $userName): bool
    {
        if ($this->user->hasLogin() && $this->user->screenName != $userName) {
            return false;
        } elseif (
            !$this->user->hasLogin() && $this->db->fetchRow($this->db->select('uid')
                ->from('table.users')->where('screenName = ? OR name = ?', $userName, $userName)->limit(1))
        ) {
            return false;
        }

        return true;
    }

    /**
     * 处理反馈请求
     *
     * @throws \Exception
     */
    public function action()
    {
        $callback = $this->request->get('type');
        $this->content = Router::match($this->request->get('permalink'));

        if (
            $this->content instanceof Archive &&
            $this->content->have() && $this->content->is('single') &&
            in_array($callback, ['comment', 'trackback'])
        ) {

            if ('comment' == $callback) {
                if (!$this->content->allow('comment')) {
                    throw new Exception(_t('对不起,此内容的反馈被禁止.'), 403);
                }

                if ($this->options->commentsCheckReferer && 'false' != $this->parameter->checkReferer) {
                    $referer = $this->request->getReferer();

                    if (empty($referer)) {
                        throw new Exception(_t('评论来源页错误.'), 403);
                    }

                    $refererPart = Common::parseUrl($referer);
                    $currentPart = Common::parseUrl((string) $this->content->permalink);

                    if (!$this->sameRefererTarget($refererPart, $currentPart)) {
                        if ('page:' . $this->content->cid == $this->options->frontPage) {
                            $currentPart = Common::parseUrl(rtrim($this->options->siteUrl, '/') . '/');

                            if (!$this->sameRefererTarget($refererPart, $currentPart)) {
                                throw new Exception(_t('评论来源页错误.'), 403);
                            }
                        } else {
                            throw new Exception(_t('评论来源页错误.'), 403);
                        }
                    }
                }

                if (
                    !$this->user->pass('editor', true) && $this->content->authorId != $this->user->uid &&
                    $this->options->commentsPostIntervalEnable
                ) {
                    $latestComment = $this->db->fetchRow($this->db->select('created')->from('table.comments')
                        ->where('cid = ? AND ip = ?', $this->content->cid, $this->request->getIp())
                        ->order('created', Db::SORT_DESC)
                        ->limit(1));

                    if (
                        $latestComment && ($this->options->time - $latestComment['created'] > 0 &&
                            $this->options->time - $latestComment['created'] < $this->options->commentsPostInterval)
                    ) {
                        throw new Exception(_t('对不起, 您的发言过于频繁, 请稍候再次发布.'), 403);
                    }
                }
            }

            if ('trackback' == $callback && !$this->content->allow('ping')) {
                throw new Exception(_t('对不起,此内容的引用被禁止.'), 403);
            }

            $this->$callback();
        } else {
            throw new Exception(_t('找不到内容'), 404);
        }
    }

    /** @throws \Exception */
    private function comment()
    {
        // 使用安全模块保护
        $this->security->enable($this->options->commentsAntiSpam);
        $this->security->protect();

        $comment = [
            'cid' => $this->content->cid,
            'created' => $this->options->time,
            'agent' => $this->request->getAgent(),
            'ip' => $this->request->getIp(),
            'ownerId' => $this->content->author->uid,
            'type' => 'comment',
            'status' => !$this->content->allow('edit')
                && $this->options->commentsRequireModeration ? 'waiting' : 'approved'
        ];

        if ($parentId = $this->request->filter('int')->get('parent')) {
            if (
                $this->options->commentsThreaded
                && ($parent = $this->db->fetchRow($this->db->select('coid', 'cid')->from('table.comments')
                    ->where('coid = ?', $parentId))) && $this->content->cid == $parent['cid']
            ) {
                $comment['parent'] = $parentId;
            } else {
                throw new Exception(_t('父级评论不存在'));
            }
        }

        $validator = new Validate();
        $validator->addRule('author', 'required', _t('必须填写用户名'));
        $validator->addRule('author', 'xssCheck', _t('请不要在用户名中使用特殊字符'));
        $validator->addRule('author', [$this, 'requireUserLogin'], _t('您所使用的用户名已经被注册,请登录后再次提交'));
        $validator->addRule('author', 'maxLength', _t('用户名最多包含150个字符'), 150);

        if ($this->options->commentsRequireMail && !$this->user->hasLogin()) {
            $validator->addRule('mail', 'required', _t('必须填写电子邮箱地址'));
        }

        $validator->addRule('mail', 'email', _t('邮箱地址不合法'));
        $validator->addRule('mail', 'maxLength', _t('电子邮箱最多包含150个字符'), 150);

        if ($this->options->commentsRequireUrl && !$this->user->hasLogin()) {
            $validator->addRule('url', 'required', _t('必须填写个人主页'));
        }
        $validator->addRule('url', 'url', _t('个人主页地址格式错误'));
        $validator->addRule('url', 'maxLength', _t('个人主页地址最多包含255个字符'), 255);

        $validator->addRule('text', 'required', _t('必须填写评论内容'));

        $comment['text'] = $this->request->get('text');

        if (!$this->user->hasLogin()) {
            $comment['author'] = $this->request->filter('trim')->get('author');
            $comment['mail'] = $this->request->filter('trim')->get('mail');
            $comment['url'] = $this->request->filter('trim', 'url')->get('url');

            if (!empty($comment['url'])) {
                $urlParams = Common::parseUrl((string) $comment['url']);
                if (!isset($urlParams['scheme'])) {
                    $comment['url'] = 'https://' . $comment['url'];
                }
            }

            $expire = 30 * 24 * 3600;
            Cookie::set('__typecho_remember_author', $comment['author'], $expire);
            Cookie::set('__typecho_remember_mail', $comment['mail'], $expire);
            Cookie::set('__typecho_remember_url', $comment['url'], $expire);
        } else {
            $comment['author'] = $this->user->screenName;
            $comment['mail'] = $this->user->mail;
            $comment['url'] = $this->user->url;

            $comment['authorId'] = $this->user->uid;
        }

        if (!$this->options->commentsRequireModeration && $this->options->commentsWhitelist) {
            if (
                $this->size(
                    $this->select()->where(
                        'author = ? AND mail = ? AND status = ?',
                        $comment['author'],
                        $comment['mail'],
                        'approved'
                    )
                )
            ) {
                $comment['status'] = 'approved';
            } else {
                $comment['status'] = 'waiting';
            }
        }

        if ($error = $validator->run($comment)) {
            $safeText = htmlspecialchars($comment['text'], ENT_QUOTES, 'UTF-8');
            Cookie::set('__typecho_remember_text', $safeText);
            throw new Exception(implode("\n", $error));
        }

        try {
            $comment = self::pluginHandle()->filter('comment', $comment, $this->content);
        } catch (\Typecho\Exception $e) {
            Cookie::set('__typecho_remember_text', $comment['text']);
            throw $e;
        }

        $commentId = $this->insert($comment);
        Cookie::delete('__typecho_remember_text');
        $this->db->fetchRow($this->select()->where('coid = ?', $commentId)
            ->limit(1), [$this, 'push']);

        self::pluginHandle()->call('finishComment', $this);

        \Typecho\Mail\Queue::enqueueComment($this, 'created', $this->options);
        $this->purgeCommentCache();

        if ($this->status !== 'approved') {
            Cookie::set('__typecho_unapproved_comment', $commentId);
        }

        $this->response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $this->response->setHeader('Pragma', 'no-cache');
        $this->response->setHeader('Expires', '0');
        $this->response->redirect($this->permalink);
    }

    /** @throws Exception|Db\Exception */
    private function trackback()
    {
        if (!$this->request->isPost() || $this->request->getReferer()) {
            $this->response->redirect($this->content->permalink);
        }

        if (
            $this->size($this->select()
                ->where('status = ? AND ip = ?', 'spam', $this->request->getIp())) > 0
        ) {
            throw new Exception(_t('找不到内容'), 404);
        }

        $trackback = [
            'cid' => $this->content->cid,
            'created' => $this->options->time,
            'agent' => $this->request->getAgent(),
            'ip' => $this->request->getIp(),
            'ownerId' => $this->content->author->uid,
            'type' => 'trackback',
            'status' => $this->options->commentsRequireModeration ? 'waiting' : 'approved'
        ];

        $trackback['author'] = $this->request->filter('trim')->get('blog_name');
        $trackback['url'] = $this->request->filter('trim', 'url')->get('url');
        $trackback['text'] = $this->request->get('excerpt');

        $validator = new Validate();
        $validator->addRule('url', 'required', 'We require all Trackbacks to provide an url.')
            ->addRule('url', 'url', 'Your url is not valid.')
            ->addRule('url', 'maxLength', 'Your url is not valid.', 255)
            ->addRule('text', 'required', 'We require all Trackbacks to provide an excerption.')
            ->addRule('author', 'required', 'We require all Trackbacks to provide an blog name.')
            ->addRule('author', 'xssCheck', 'Your blog name is not valid.')
            ->addRule('author', 'maxLength', 'Your blog name is not valid.', 150);

        $validator->setBreak();
        if ($error = $validator->run($trackback)) {
            $message = ['success' => 1, 'message' => current($error)];
            $this->response->throwXml($message);
        }

        $trackback['text'] = Common::subStr($trackback['text'], 0, 100, '[...]');

        if (
            $this->size($this->select()
                ->where('cid = ? AND url = ? AND type <> ?', $this->content->cid, $trackback['url'], 'comment')) > 0
        ) {
            throw new Exception(_t('禁止重复提交'), 403);
        }

        $trackback = self::pluginHandle()->filter('trackback', $trackback, $this->content);

        $this->insert($trackback);

        self::pluginHandle()->call('finishTrackback', $this);

        $this->response->throwXml(['success' => 0, 'message' => 'Trackback has registered.']);
    }

    private function sameRefererTarget(array $refererPart, array $currentPart): bool
    {
        $refererHost = strtolower((string) ($refererPart['host'] ?? ''));
        $currentHost = strtolower((string) ($currentPart['host'] ?? ''));
        if ($refererHost === '' || $currentHost === '' || $refererHost !== $currentHost) {
            return false;
        }

        $refererPort = (int) ($refererPart['port'] ?? 0);
        $currentPort = (int) ($currentPart['port'] ?? 0);
        if (($refererPort !== 0 || $currentPort !== 0) && $refererPort !== $currentPort) {
            return false;
        }

        return $this->normalizeRefererPath($refererPart) === $this->normalizeRefererPath($currentPart);
    }

    private function normalizeRefererPath(array $parts): string
    {
        $path = (string) ($parts['path'] ?? '/');
        if ($path === '') {
            return '/';
        }

        $path = '/' . ltrim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function purgeCommentCache(): void
    {
        if ((string) ($this->status ?? '') !== 'approved') {
            return;
        }
        Comment::purgeCache((int) ($this->options->cacheCommentFlush ?? 1), function (): void {
            try {
                self::pluginHandle()->call('commentCachePurge', $this);
            } catch (\Throwable $e) {
                error_log('Widget.Feedback.purgeCommentCache.plugin: ' . $e->getMessage());
            }
        });
    }
}
