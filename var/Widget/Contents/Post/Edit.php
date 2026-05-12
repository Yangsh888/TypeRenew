<?php

namespace Widget\Contents\Post;

use Typecho\Common;
use Widget\Base\Contents;
use Widget\Base\Metas;
use Widget\ActionInterface;
use Widget\Contents\EditTrait;
use Widget\Contents\PrepareEditTrait;
use Widget\Contents\Write\Request as WriteRequest;
use Widget\Contents\Write\Response as WriteResponse;
use Widget\Notice;
use Widget\Service;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Edit extends Contents implements ActionInterface
{
    use PrepareEditTrait;
    use EditTrait;

    public function execute()
    {
        $this->user->pass('contributor');
    }

    public function writePost()
    {
        $contents = $this->request->fromInput(
            'password',
            'allowComment',
            'allowPing',
            'allowFeed',
            'slug',
            'tags',
            'text',
            'visibility'
        );

        $contents['category'] = $this->request->getArray('category');
        $contents['title'] = $this->request->get('title', _t('未命名文档'));
        $contents['created'] = $this->getCreated();
        $contents = WriteRequest::normalize($contents, $this->request, (int) ($this->cid ?? 0));

        $contents = self::pluginHandle()->filter('write', $contents, $this);

        if ($this->request->is('do=publish')) {
            $contents['type'] = 'post';
            $this->publish($contents);

            self::pluginHandle()->call('finishPublish', $contents, $this);

            $trackbackInput = $this->request->get('trackback', '');
            $trackbackText = is_scalar($trackbackInput) ? trim((string) $trackbackInput) : '';
            $trackback = array_filter(
                array_unique(preg_split("/(\r|\n|\r\n)/", $trackbackText) ?: [])
            );
            Service::alloc()->sendPing($this, $trackback);

            if ('post' == $this->type) {
                $notice = $this->isFuturePublish()
                    ? _t(
                        '文章 "%s" 已计划于 %s 发布，当前可先<a href="%s">预览</a>',
                        $this->title,
                        $this->date('Y-m-d H:i'),
                        $this->getAdminPreviewUrl()
                    )
                    : _t('文章 "<a href="%s">%s</a>" 已经发布', $this->permalink, $this->title);
            } else {
                $notice = _t('文章 "%s" 等待审核', $this->title);
            }

            Notice::alloc()->set($notice, 'success');

            Notice::alloc()->highlight($this->theId);

            $this->response->redirect(Common::url(
                'manage-posts.php?page=' . $this->getPageOffset(
                    'cid',
                    $this->cid,
                    'post',
                    null,
                    $this->request->is('__typecho_all_posts=on') ? 0 : $this->user->uid
                ),
                $this->options->adminUrl
            ));
            return;
        }

        $contents['type'] = 'post_draft';
        $draftId = $this->save($contents);

        self::pluginHandle()->call('finishSave', $contents, $this);
        WriteResponse::finishSave(
            $this->request,
            $this->response,
            (int) $this->options->time,
            (int) $this->cid,
            (string) $this->title,
            $draftId,
            Common::url('write-post.php?cid=' . $this->cid, $this->options->adminUrl)
        );
    }

    public function markPost()
    {
        $status = $this->request->get('status');
        $statusList = [
            'publish' => _t('公开'),
            'private' => _t('私密'),
            'hidden'  => _t('隐藏'),
            'waiting' => _t('待审核')
        ];

        if (!isset($statusList[$status])) {
            $this->response->goBack();
        }

        $posts = $this->request->filter('int')->getArray('cid');
        $markCount = 0;

        foreach ($posts as $post) {
            self::pluginHandle()->call('mark', $status, $post, $this);

            $condition = $this->db->sql()->where('cid = ?', $post);
            $postObject = $this->db->fetchObject($this->db->select('status', 'type')
                ->from('table.contents')->where('cid = ? AND (type = ? OR type = ?)', $post, 'post', 'post_draft'));

            if ($this->isWriteable(clone $condition) && count((array) $postObject)) {
                $changed = (bool) $this->withWriteTransaction(function () use ($condition, $status, $postObject, $post) {
                    $this->db->query($condition->update('table.contents')->rows(['status' => $status]));

                    if ($postObject->type == 'post') {
                        $op = null;

                        if ($status == 'publish' && $postObject->status != 'publish') {
                            $op = '+';
                        } elseif ($status != 'publish' && $postObject->status == 'publish') {
                            $op = '-';
                        }

                        if (!empty($op)) {
                            $metas = $this->db->fetchAll(
                                $this->db->select()->from('table.relationships')->where('cid = ?', $post)
                            );
                            foreach ($metas as $meta) {
                                $this->db->query($this->db->update('table.metas')
                                    ->expression('count', 'count ' . $op . ' 1')
                                    ->where('mid = ? AND (type = ? OR type = ?)', $meta['mid'], 'category', 'tag'));
                            }
                        }
                    }

                    $draft = $this->db->fetchRow($this->revisionSelect((int) $post));

                    if (!empty($draft)) {
                        $this->db->query($this->db->update('table.contents')->rows(['status' => $status])
                            ->where('cid = ?', $draft['cid']));
                    }

                    return true;
                });

                if ($changed) {
                    self::pluginHandle()->call('finishMark', $status, $post, $this);
                    $markCount++;
                }
            }
        }

        Notice::alloc()
            ->set(
                $markCount > 0 ? _t('文章已经被标记为<strong>%s</strong>', $statusList[$status]) : _t('没有文章被标记'),
                $markCount > 0 ? 'success' : 'notice'
            );

        $this->response->goBack();
    }

    public function deletePost()
    {
        $posts = $this->request->filter('int')->getArray('cid');
        $deleteCount = 0;

        foreach ($posts as $post) {
            self::pluginHandle()->call('delete', $post, $this);

            $condition = $this->db->sql()->where('cid = ?', $post);
            $postObject = $this->db->fetchObject($this->db->select('status', 'type')
                ->from('table.contents')->where('cid = ? AND (type = ? OR type = ?)', $post, 'post', 'post_draft'));

            if ($this->isWriteable(clone $condition) && count((array) $postObject)) {
                $deleted = (bool) $this->withWriteTransaction(function () use ($condition, $postObject, $post) {
                    if (!$this->delete($condition)) {
                        return false;
                    }

                    $this->setCategories($post, [], 'publish' == $postObject->status
                        && 'post' == $postObject->type);

                    $this->setTags($post, null, 'publish' == $postObject->status
                        && 'post' == $postObject->type);

                    $this->db->query($this->db->delete('table.comments')
                        ->where('cid = ?', $post));

                    $this->unAttach($post);

                    $draft = $this->db->fetchRow($this->revisionSelect((int) $post));

                    $this->deleteFields($post);

                    if ($draft) {
                        $this->deleteContent($draft['cid']);
                        $this->deleteFields($draft['cid']);
                    }

                    return true;
                });

                if ($deleted) {
                    self::pluginHandle()->call('finishDelete', $post, $this);
                    $deleteCount++;
                }
            }
        }

        if ($deleteCount > 0) {
            Metas::alloc()->clearTags();
        }

        Notice::alloc()->set(
            $deleteCount > 0 ? _t('文章已经被删除') : _t('没有文章被删除'),
            $deleteCount > 0 ? 'success' : 'notice'
        );

        $this->response->goBack();
    }

    public function deletePostDraft()
    {
        $posts = $this->request->filter('int')->getArray('cid');
        $deleteCount = 0;

        foreach ($posts as $post) {
            $condition = $this->db->sql()
                ->where('cid = ?', $post)
                ->where('type = ? OR type = ?', 'post', 'post_draft');

            if (!$this->isWriteable(clone $condition)) {
                continue;
            }

            $draft = $this->db->fetchRow($this->revisionSelect((int) $post));
            $postObject = $this->db->fetchObject($this->db->select('type', 'parent')
                ->from('table.contents')->where('cid = ?', $post)->limit(1));

            if ($draft) {
                $this->deleteContent($draft['cid']);
                $this->deleteFields($draft['cid']);
                $deleteCount++;
            } elseif ($postObject && $postObject->type === 'post_draft' && (int) $postObject->parent === 0) {
                $this->deleteContent($post);
                $this->deleteFields($post);
                $deleteCount++;
            }
        }

        Notice::alloc()
            ->set(
                $deleteCount > 0 ? _t('草稿已经被删除') : _t('没有草稿被删除'),
                $deleteCount > 0 ? 'success' : 'notice'
            );

        $this->response->goBack();
    }

    public function prepare(): self
    {
        return $this->prepareEdit('post', true, _t('文章不存在'));
    }

    public function action()
    {
        if (!$this->request->isPost()) {
            $this->response->setStatus(405)->throwContent(_t('Method Not Allowed'), 'text/plain');
            return;
        }
        $this->security->protect();
        $this->on($this->request->is('do=publish') || $this->request->is('do=save'))
            ->prepare()->writePost();
        $this->on($this->request->is('do=delete'))->deletePost();
        $this->on($this->request->is('do=mark'))->markPost();
        $this->on($this->request->is('do=deleteDraft'))->deletePostDraft();

        $this->response->redirect($this->options->adminUrl);
    }

    protected function getThemeFieldsHook(): string
    {
        return 'themePostFields';
    }

    protected function hasWriteMetas(): bool
    {
        return true;
    }

    protected function ___categories(): array
    {
        $cid = (int) ($this->draft['draftCid'] ?? 0);
        if ($cid > 0) {
            return \Widget\Metas\Category\Related::allocWithAlias($cid, ['cid' => $cid])
                ->toArray(['mid', 'name', 'slug', 'description', 'order', 'parent', 'count', 'permalink']);
        }

        return parent::___categories();
    }

    protected function ___tags(): array
    {
        $cid = (int) ($this->draft['draftCid'] ?? 0);
        if ($cid > 0) {
            return \Widget\Metas\Tag\Related::allocWithAlias($cid, ['cid' => $cid])
                ->toArray(['mid', 'name', 'slug', 'description', 'count', 'permalink']);
        }

        return parent::___tags();
    }
}
