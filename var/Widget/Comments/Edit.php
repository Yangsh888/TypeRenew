<?php

namespace Widget\Comments;

use Typecho\Db\Query;
use Utils\Comment;
use Widget\Base\Comments;
use Widget\ActionInterface;
use Widget\Notice;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Edit extends Comments implements ActionInterface
{
    private const EDITABLE_STATUSES = ['approved', 'waiting', 'spam'];

    public function waitingComment()
    {
        $this->markComments(
            $this->request->filter('int')->getArray('coid'),
            'waiting',
            _t('评论已经被标记为待审核'),
            _t('没有评论被标记为待审核')
        );
    }

    public function commentIsWriteable(?Query $condition = null): bool
    {
        if (empty($condition)) {
            return $this->have() && ($this->user->pass('editor', true) || $this->ownerId == $this->user->uid);
        }

        $post = $this->db->fetchRow($condition->select('ownerId')->from('table.comments')->limit(1));

        return $post && ($this->user->pass('editor', true) || $post['ownerId'] == $this->user->uid);
    }

    private function mark(int $coid, string $status): bool
    {
        $comment = $this->db->fetchRow($this->select()
            ->where('coid = ?', $coid)->limit(1), [$this, 'push']);

        if ($comment && $this->commentIsWriteable()) {
            self::pluginHandle()->call('mark', $comment, $this, $status);

            if ($status == $comment['status']) {
                return false;
            }

            $this->db->query($this->db->update('table.comments')
                ->rows(['status' => $status])->where('coid = ?', $coid));
            $this->refreshCommentsNum((int) $comment['cid']);

            if ('approved' != $comment['status'] && 'approved' == $status) {
                \Typecho\Mail\Queue::enqueueComment($this, 'approved', $this->options);
            }

            $this->purgeCommentCacheForTransition((string) $comment['status'], $status);

            return true;
        }

        return false;
    }

    public function spamComment()
    {
        $this->markComments(
            $this->request->filter('int')->getArray('coid'),
            'spam',
            _t('评论已经被标记为垃圾'),
            _t('没有评论被标记为垃圾')
        );
    }

    public function approvedComment()
    {
        $this->markComments(
            $this->request->filter('int')->getArray('coid'),
            'approved',
            _t('评论已经被通过'),
            _t('没有评论被通过')
        );
    }

    public function deleteComment()
    {
        $comments = $this->request->filter('int')->getArray('coid');
        $deleteRows = 0;

        foreach ($comments as $coid) {
            $comment = $this->db->fetchRow($this->select()
                ->where('coid = ?', $coid)->limit(1), [$this, 'push']);

            if ($comment && $this->commentIsWriteable()) {
                self::pluginHandle()->call('delete', $comment, $this);
                $this->reparentChildren((int) $coid, (int) ($comment['parent'] ?? 0));
                $this->delete($this->db->sql()->where('coid = ?', $coid));

                self::pluginHandle()->call('finishDelete', $comment, $this);

                $this->purgeCommentCacheForTransition((string) $comment['status'], null);

                $deleteRows++;
            }
        }

        $this->respondDeleteResult($deleteRows);
    }

    public function deleteSpamComment()
    {
        $select = $this->select()->where('status = ?', 'spam');

        $deleteQuery = $this->db->delete('table.comments')->where('status = ?', 'spam');
        if (!$this->request->is('__typecho_all_comments=on') || !$this->user->pass('editor', true)) {
            $select->where('ownerId = ?', $this->user->uid);
            $deleteQuery->where('ownerId = ?', $this->user->uid);
        }

        if ($this->request->is('cid')) {
            $select->where('cid = ?', $this->request->get('cid'));
            $deleteQuery->where('cid = ?', $this->request->get('cid'));
        }

        $comments = $this->db->fetchAll($select);
        $deletedParents = [];
        foreach ($comments as $comment) {
            $deletedParents[(int) ($comment['coid'] ?? 0)] = (int) ($comment['parent'] ?? 0);
        }

        foreach ($comments as $comment) {
            $coid = (int) ($comment['coid'] ?? 0);
            if ($coid <= 0) {
                continue;
            }

            self::pluginHandle()->call('delete', $comment, $this);
            $this->reparentChildren($coid, $this->resolveReparentTarget((int) ($comment['parent'] ?? 0), $deletedParents));
        }

        $deleteRows = $this->db->query($deleteQuery);

        if ($deleteRows > 0) {
            foreach ($comments as $comment) {
                self::pluginHandle()->call('finishDelete', $comment, $this);
            }

            $this->purgeCommentCache();
        }

        Notice::alloc()->set(
            $deleteRows > 0 ? _t('所有垃圾评论已经被删除') : _t('没有垃圾评论被删除'),
            $deleteRows > 0 ? 'success' : 'notice'
        );

        $this->response->goBack();
    }

    public function getComment()
    {
        $coid = $this->request->filter('int')->get('coid');
        $comment = $this->db->fetchRow($this->select()
            ->where('coid = ?', $coid)->limit(1), [$this, 'push']);

        if ($comment && $this->commentIsWriteable()) {
            $this->response->throwJson([
                'success' => 1,
                'comment' => $comment
            ]);
        } else {
            $this->response->throwJson([
                'success' => 0,
                'message' => _t('获取评论失败')
            ]);
        }
    }

    public function editComment(): bool
    {
        $coid = $this->request->filter('int')->get('coid');
        $comment = [
            'text' => $this->request->get('text'),
            'author' => $this->request->filter('strip_tags', 'trim', 'xss')->get('author'),
            'mail' => $this->request->filter('strip_tags', 'trim', 'xss')->get('mail'),
            'url' => $this->request->filter('url')->get('url'),
        ];

        if ($this->request->is('created')) {
            $comment['created'] = $this->request->filter('int')->get('created');
        }

        if ($this->request->is('status')) {
            $comment['status'] = $this->request->get('status');
        }

        $updatedComment = $this->editCommentData($coid, $comment);

        if ($updatedComment !== null) {
            $this->response->throwJson([
                'success' => 1,
                'comment' => $updatedComment
            ]);
            return true;
        }

        $this->response->throwJson([
            'success' => 0,
            'message' => _t('修评论失败')
        ]);
        return false;
    }

    public function editCommentData(int $coid, array $comment): ?array
    {
        $commentSelect = $this->db->fetchRow($this->select()
            ->where('coid = ?', $coid)->limit(1), [$this, 'push']);

        if (!$commentSelect || !$this->commentIsWriteable()) {
            return null;
        }

        $comment = self::pluginHandle()->filter('edit', $comment, $this);

        if (isset($comment['status'])) {
            $status = $this->normalizeEditableStatus($comment['status']);
            if ($status === null) {
                unset($comment['status']);
            } else {
                $comment['status'] = $status;
            }
        }

        $this->update($comment, $this->db->sql()->where('coid = ?', $coid));

        $updatedComment = $this->db->fetchRow($this->select()
            ->where('coid = ?', $coid)->limit(1), [$this, 'push']);
        $updatedComment['content'] = $this->content;

        self::pluginHandle()->call('finishEdit', $this);
        $beforeStatus = (string) ($commentSelect['status'] ?? '');
        $afterStatus = (string) ($updatedComment['status'] ?? $beforeStatus);

        if ($beforeStatus !== 'approved' && $afterStatus === 'approved') {
            \Typecho\Mail\Queue::enqueueComment($this, 'approved', $this->options);
        }

        $this->purgeCommentCacheForTransition($beforeStatus, $afterStatus);

        return $updatedComment;
    }

    public function replyComment()
    {
        $coid = $this->request->filter('int')->get('coid');
        $commentSelect = $this->db->fetchRow($this->select()
            ->where('coid = ?', $coid)->limit(1), [$this, 'push']);

        if ($commentSelect && $this->commentIsWriteable()) {
            $comment = [
                'cid'      => $commentSelect['cid'],
                'created'  => $this->options->time,
                'agent'    => $this->request->getAgent(),
                'ip'       => $this->request->getIp(),
                'ownerId'  => $commentSelect['ownerId'],
                'authorId' => $this->user->uid,
                'type'     => 'comment',
                'author'   => $this->user->screenName,
                'mail'     => $this->user->mail,
                'url'      => $this->user->url,
                'parent'   => $coid,
                'text'     => $this->request->get('text'),
                'status'   => 'approved'
            ];

            self::pluginHandle()->call('comment', $comment, $this);

            $commentId = $this->insert($comment);

            $insertComment = $this->db->fetchRow($this->select()
                ->where('coid = ?', $commentId)->limit(1), [$this, 'push']);
            $insertComment['content'] = $this->content;

            self::pluginHandle()->call('finishComment', $this);
            $this->status = 'approved';
            \Typecho\Mail\Queue::enqueueComment($this, 'created', $this->options);
            $this->purgeCommentCache();

            $this->response->throwJson([
                'success' => 1,
                'comment' => $insertComment
            ]);
        }

        $this->response->throwJson([
            'success' => 0,
            'message' => _t('回复评论失败')
        ]);
    }

    public function action()
    {
        $this->user->pass('contributor');
        $do = (string) $this->request->get('do');
        if ($do !== 'get' && !$this->request->isPost()) {
            $this->response->setStatus(405);
            $this->response->goBack();
        }
        $this->security->protect();
        $this->on($this->request->is('do=waiting'))->waitingComment();
        $this->on($this->request->is('do=spam'))->spamComment();
        $this->on($this->request->is('do=approved'))->approvedComment();
        $this->on($this->request->is('do=delete'))->deleteComment();
        $this->on($this->request->is('do=delete-spam'))->deleteSpamComment();
        $this->on($this->request->is('do=get&coid'))->getComment();
        $this->on($this->request->is('do=edit&coid'))->editComment();
        $this->on($this->request->is('do=reply&coid'))->replyComment();

        $this->response->redirect($this->options->adminUrl);
    }

    private function purgeCommentCache(): void
    {
        Comment::purgeCache((int) ($this->options->cacheCommentFlush ?? 1), function (): void {
            try {
                self::pluginHandle()->call('commentCachePurge', $this);
            } catch (\Throwable $e) {
                error_log('Widget.Comments.Edit.purgeCommentCache.plugin: ' . $e->getMessage());
            }
        });
    }

    private function markComments(array $comments, string $status, string $successMessage, string $emptyMessage): void
    {
        $updateRows = 0;

        foreach ($comments as $comment) {
            if ($this->mark((int) $comment, $status)) {
                $updateRows++;
            }
        }

        Notice::alloc()->set($updateRows > 0 ? $successMessage : $emptyMessage, $updateRows > 0 ? 'success' : 'notice');
        $this->response->goBack();
    }

    private function respondDeleteResult(int $deleteRows): void
    {
        if ($this->request->isAjax()) {
            $this->response->throwJson([
                'success' => $deleteRows > 0 ? 1 : 0,
                'message' => $deleteRows > 0 ? _t('删除评论成功') : _t('删除评论失败')
            ]);
        }

        Notice::alloc()->set(
            $deleteRows > 0 ? _t('评论已经被删除') : _t('没有评论被删除'),
            $deleteRows > 0 ? 'success' : 'notice'
        );
        $this->response->goBack();
    }

    private function purgeCommentCacheForTransition(?string $beforeStatus, ?string $afterStatus): void
    {
        $before = (string) ($beforeStatus ?? '');
        $after = (string) ($afterStatus ?? '');
        if ($before !== 'approved' && $after !== 'approved') {
            return;
        }

        $this->status = $after !== '' ? $after : $before;
        $this->purgeCommentCache();
    }

    private function reparentChildren(int $coid, int $parent): void
    {
        if ($coid <= 0) {
            return;
        }

        $this->db->query(
            $this->db->update('table.comments')
                ->rows(['parent' => max(0, $parent)])
                ->where('parent = ?', $coid)
        );
    }

    private function resolveReparentTarget(int $parent, array $deletedParents): int
    {
        while ($parent > 0 && isset($deletedParents[$parent])) {
            $parent = (int) $deletedParents[$parent];
        }

        return max(0, $parent);
    }

    private function normalizeEditableStatus($status): ?string
    {
        if (!is_string($status) && !is_scalar($status)) {
            return null;
        }

        $status = strtolower(trim((string) $status));

        return in_array($status, self::EDITABLE_STATUSES, true) ? $status : null;
    }
}
