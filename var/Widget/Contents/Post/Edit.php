<?php

namespace Widget\Contents\Post;

use Typecho\Common;
use Typecho\Widget\Exception;
use Widget\Base\Contents;
use Widget\Base\Metas;
use Widget\ActionInterface;
use Typecho\Db\Exception as DbException;
use Typecho\Date as TypechoDate;
use Widget\Contents\EditTrait;
use Widget\Contents\PrepareEditTrait;
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
        $contents = $this->request->from(
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
        
        $attachmentCidsStr = $this->request->get('attachment_cids', '');
        if (!empty($attachmentCidsStr)) {
            $cids = array_filter(array_map('intval', explode(',', $attachmentCidsStr)));
            if (!empty($cids)) {
                $contents['attachment'] = $cids;
            }
        }
        
        if (empty($this->cid)) {
            $contents['attachUnattached'] = true;
        } else {
            $contents['oldCid'] = $this->cid;
        }

        if ($this->request->is('markdown=1')) {
            $contents['text'] = '<!--markdown-->' . $contents['text'];
        }

        $contents = self::pluginHandle()->filter('write', $contents, $this);

        if ($this->request->is('do=publish')) {
            $contents['type'] = 'post';
            $this->publish($contents);

            self::pluginHandle()->call('finishPublish', $contents, $this);

            $trackback = array_filter(
                array_unique(preg_split("/(\r|\n|\r\n)/", trim($this->request->get('trackback', ''))))
            );
            Service::alloc()->sendPing($this, $trackback);

            Notice::alloc()->set('post' == $this->type ?
                _t('文章 "<a href="%s">%s</a>" 已经发布', $this->permalink, $this->title) :
                _t('文章 "%s" 等待审核', $this->title), 'success');

            Notice::alloc()->highlight($this->theId);

            $pageQuery = $this->getPageOffsetQuery($this->cid);

            $this->response->redirect(Common::url('manage-posts.php?' . $pageQuery, $this->options->adminUrl));
        } else {
            $contents['type'] = 'post_draft';
            $draftId = $this->save($contents);

            self::pluginHandle()->call('finishSave', $contents, $this);

            Notice::alloc()->highlight($this->cid);

            if ($this->request->isAjax()) {
                $created = new TypechoDate();
                $this->response->throwJson([
                    'success' => 1,
                    'time'    => $created->format('H:i:s A'),
                    'cid'     => $this->cid,
                    'draftId' => $draftId
                ]);
            } else {
                Notice::alloc()->set(_t('草稿 "%s" 已经被保存', htmlspecialchars((string) $this->title, ENT_QUOTES, 'UTF-8')), 'success');

                $this->response->redirect(Common::url('write-post.php?cid=' . $this->cid, $this->options->adminUrl));
            }
        }
    }

    protected function getPageOffsetQuery(int $cid, ?string $status = null): string
    {
        return 'page=' . $this->getPageOffset(
            'cid',
            $cid,
            'post',
            $status,
            $this->request->is('__typecho_all_posts=on') ? 0 : $this->user->uid
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

            if ($this->isWriteable(clone $condition) && count((array)$postObject)) {
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

                $draft = $this->db->fetchRow($this->db->select('cid')
                    ->from('table.contents')
                    ->where('table.contents.parent = ? AND table.contents.type = ?', $post, 'revision')
                    ->limit(1));

                if (!empty($draft)) {
                    $this->db->query($this->db->update('table.contents')->rows(['status' => $status])
                        ->where('cid = ?', $draft['cid']));
                }

                self::pluginHandle()->call('finishMark', $status, $post, $this);

                $markCount++;
            }

            unset($condition);
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

            if ($this->isWriteable(clone $condition) && count((array)$postObject) && $this->delete($condition)) {
                $this->setCategories($post, [], 'publish' == $postObject->status
                    && 'post' == $postObject->type);

                $this->setTags($post, null, 'publish' == $postObject->status
                    && 'post' == $postObject->type);

                $this->db->query($this->db->delete('table.comments')
                    ->where('cid = ?', $post));

                $this->unAttach($post);

                $draft = $this->db->fetchRow($this->db->select('cid')
                    ->from('table.contents')
                    ->where('table.contents.parent = ? AND table.contents.type = ?', $post, 'revision')
                    ->limit(1));

                $this->deleteFields($post);

                if ($draft) {
                    $this->deleteContent($draft['cid']);
                    $this->deleteFields($draft['cid']);
                }

                self::pluginHandle()->call('finishDelete', $post, $this);

                $deleteCount++;
            }

            unset($condition);
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
            $draft = $this->db->fetchRow($this->db->select('cid')
                ->from('table.contents')
                ->where('table.contents.parent = ? AND table.contents.type = ?', $post, 'revision')
                ->limit(1));

            if ($draft) {
                $this->deleteContent($draft['cid']);
                $this->deleteFields($draft['cid']);
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
}
