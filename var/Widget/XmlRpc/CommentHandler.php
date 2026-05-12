<?php

namespace Widget\XmlRpc;

use IXR\Date;
use IXR\Exception;
use Typecho\Common;
use Widget\Archive;
use Widget\Comments\Admin as CommentsAdmin;
use Widget\Comments\Edit as CommentsEdit;
use Widget\Feedback;
use Widget\Stat;

class CommentHandler extends AbstractHandler
{
    public function wpGetCommentCount(int $blogId, string $userName, string $password, int $postId): array
    {
        $stat = Stat::alloc(null, ['cid' => $postId]);

        return [
            'approved' => $stat->currentPublishedCommentsNum,
            'awaiting_moderation' => $stat->currentWaitingCommentsNum,
            'spam' => $stat->currentSpamCommentsNum,
            'total_comments' => $stat->currentCommentsNum
        ];
    }

    public function wpGetCommentStatusList(int $blogId, string $userName, string $password): array
    {
        return [
            'hold' => _t('待审核'),
            'approve' => _t('显示'),
            'spam' => _t('垃圾')
        ];
    }

    public function wpGetComment(int $blogId, string $userName, string $password, int $commentId): array
    {
        $comment = CommentsEdit::alloc(null, ['coid' => $commentId], function (CommentsEdit $comment) {
            $comment->getComment();
        });

        if (!$comment->have()) {
            throw new Exception(_t('评论不存在'), 404);
        }

        if (!$comment->commentIsWriteable()) {
            throw new Exception(_t('没有获取评论的权限'), 403);
        }

        return $this->buildCommentStruct($comment);
    }

    public function wpGetComments(int $blogId, string $userName, string $password, array $struct): array
    {
        $input = [];
        if (!empty($struct['status'])) {
            $input['status'] = $this->xmlRpc->wordpressToTypechoStatus($struct['status'], 'comment');
        } else {
            $input['__typecho_all_comments'] = 'on';
        }

        if (!empty($struct['post_id'])) {
            $input['cid'] = $struct['post_id'];
        }

        $pageSize = 10;
        if (!empty($struct['number'])) {
            $pageSize = max(1, abs(intval($struct['number'])));
        }

        if (!empty($struct['offset'])) {
            $offset = abs(intval($struct['offset']));
            $input['page'] = intdiv($offset, $pageSize) + 1;
        }

        $comments = CommentsAdmin::alloc('pageSize=' . $pageSize, $input, false);
        $commentsStruct = [];

        while ($comments->next()) {
            $commentsStruct[] = $this->buildCommentStruct($comments);
        }

        return $commentsStruct;
    }

    public function wpDeleteComment(int $blogId, string $userName, string $password, int $commentId): bool
    {
        CommentsEdit::alloc(null, ['coid' => $commentId], function (CommentsEdit $comment) {
            $comment->deleteComment();
        });

        return true;
    }

    public function wpEditComment(int $blogId, string $userName, string $password, int $commentId, array $struct): bool
    {
        $input = [];

        if (isset($struct['date_created_gmt']) && $struct['date_created_gmt'] instanceof Date) {
            $created = $this->xmlRpc->fromUtcRpcDate($struct['date_created_gmt']);
            if ($created !== null) {
                $input['created'] = $created;
            }
        }

        if (isset($struct['status'])) {
            $input['status'] = $this->xmlRpc->wordpressToTypechoStatus($struct['status'], 'comment');
        }

        if (isset($struct['content'])) {
            $input['text'] = $struct['content'];
        }

        if (isset($struct['author'])) {
            $input['author'] = $struct['author'];
        }

        if (isset($struct['author_url'])) {
            $input['url'] = $struct['author_url'];
        }

        if (isset($struct['author_email'])) {
            $input['mail'] = $struct['author_email'];
        }

        $comment = CommentsEdit::alloc(null, ['coid' => $commentId] + $input, function (CommentsEdit $comment) {
            $comment->editComment();
        });

        return $comment->have();
    }

    public function wpNewComment(int $blogId, string $userName, string $password, $path, array $struct): int
    {
        if (is_numeric($path)) {
            $post = Archive::alloc('type=single', ['cid' => $path], false);

            if ($post->have()) {
                $path = $post->permalink;
            }
        } else {
            $path = Common::url(substr($path, strlen((string) $this->xmlRpc->optionsWidget()->index)), '/');
        }

        $input = [
            'permalink' => $path,
            'type' => 'comment'
        ];

        if (isset($struct['comment_author'])) {
            $input['author'] = $this->xmlRpc->arrayString($struct, 'comment_author');
        }

        if (isset($struct['comment_author_email'])) {
            $input['mail'] = $this->xmlRpc->arrayString($struct, 'comment_author_email');
        }

        if (isset($struct['comment_author_url'])) {
            $input['url'] = $this->xmlRpc->arrayString($struct, 'comment_author_url');
        }

        if (isset($struct['comment_parent'])) {
            $input['parent'] = $struct['comment_parent'];
        }

        if (isset($struct['content'])) {
            $input['text'] = $struct['content'];
        }

        $comment = Feedback::alloc(['checkReferer' => false], $input, function (Feedback $comment) {
            $comment->action();
        });

        return $comment->have() ? $comment->coid : 0;
    }

    private function buildCommentStruct($comment): array
    {
        return [
            'date_created_gmt' => $this->xmlRpc->toGmtRpcDate((int) $comment->created),
            'user_id' => $comment->authorId,
            'comment_id' => $comment->coid,
            'parent' => $comment->parent,
            'status' => $this->xmlRpc->typechoToWordpressStatus($comment->status, 'comment'),
            'content' => $comment->text,
            'link' => $comment->permalink,
            'post_id' => $comment->cid,
            'post_title' => $comment->title,
            'author' => $comment->author,
            'author_url' => $comment->url,
            'author_email' => $comment->mail,
            'author_ip' => $comment->ip,
            'type' => $comment->type
        ];
    }
}
