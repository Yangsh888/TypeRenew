<?php

namespace Widget\Contents\Page;

use Typecho\Common;
use Typecho\Widget\Exception;
use Widget\Base\Contents;
use Widget\Contents\EditTrait;
use Widget\ActionInterface;
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
        $this->user->pass('editor');
    }

    public function writePage()
    {
        $contents = $this->request->from(
            'text',
            'template',
            'allowComment',
            'allowPing',
            'allowFeed',
            'slug',
            'order',
            'visibility'
        );

        $contents['title'] = $this->request->get('title', _t('未命名页面'));
        $contents['created'] = $this->getCreated();
        $contents['visibility'] = ('hidden' == $contents['visibility'] ? 'hidden' : 'publish');
        $contents['parent'] = $this->getParent();
        $contents = $this->normalizeWriteContents($contents);

        $contents = self::pluginHandle()->filter('write', $contents, $this);

        if ($this->request->is('do=publish')) {
            $contents['type'] = 'page';
            $this->publish($contents, false);

            self::pluginHandle()->call('finishPublish', $contents, $this);

            Service::alloc()->sendPing($this);

            $notice = $this->isFuturePublish()
                ? _t(
                    '页面 "%s" 已计划于 %s 发布，当前可先<a href="%s">预览</a>',
                    $this->title,
                    $this->date('Y-m-d H:i'),
                    $this->getAdminPreviewUrl()
                )
                : _t('页面 "<a href="%s">%s</a>" 已经发布', $this->permalink, $this->title);

            Notice::alloc()->set($notice, 'success');

            Notice::alloc()->highlight($this->theId);

            $this->response->redirect(Common::url('manage-pages.php'
                . ($this->parent ? '?parent=' . $this->parent : ''), $this->options->adminUrl));
        } else {
            $contents['type'] = 'page_draft';
            $draftId = $this->save($contents, false);

            self::pluginHandle()->call('finishSave', $contents, $this);
            $this->finishSaveResponse(
                (string) $this->title,
                $draftId,
                Common::url('write-page.php?cid=' . $this->cid, $this->options->adminUrl)
            );
        }
    }

    public function markPage()
    {
        $status = $this->request->get('status');
        $statusList = [
            'publish' => _t('公开'),
            'hidden'  => _t('隐藏')
        ];

        if (!isset($statusList[$status])) {
            $this->response->goBack();
        }

        $pages = $this->request->filter('int')->getArray('cid');
        $markCount = 0;

        foreach ($pages as $page) {
            self::pluginHandle()->call('mark', $status, $page, $this);
            $condition = $this->db->sql()->where('cid = ?', $page);

            if ($this->db->query($condition->update('table.contents')->rows(['status' => $status]))) {
                $draft = $this->db->fetchRow($this->db->select('cid')
                    ->from('table.contents')
                    ->where('table.contents.parent = ? AND table.contents.type = ?', $page, 'revision')
                    ->limit(1));

                if (!empty($draft)) {
                    $this->db->query($this->db->update('table.contents')->rows(['status' => $status])
                        ->where('cid = ?', $draft['cid']));
                }

                self::pluginHandle()->call('finishMark', $status, $page, $this);

                $markCount++;
            }
        }

        Notice::alloc()
            ->set(
                $markCount > 0 ? _t('页面已经被标记为<strong>%s</strong>', $statusList[$status]) : _t('没有页面被标记'),
                $markCount > 0 ? 'success' : 'notice'
            );

        $this->response->goBack();
    }

    public function deletePage()
    {
        $pages = $this->request->filter('int')->getArray('cid');
        $deleteCount = 0;

        foreach ($pages as $page) {
            self::pluginHandle()->call('delete', $page, $this);
            $row = $this->db->fetchObject($this->select()->where('cid = ?', $page));
            if (!$row) {
                continue;
            }

            $parent = (int) ($row->parent ?? 0);

            if ($this->delete($this->db->sql()->where('cid = ?', $page))) {
                $this->db->query($this->db->delete('table.comments')
                    ->where('cid = ?', $page));

                $this->unAttach($page);

                if ($this->options->frontPage == 'page:' . $page) {
                    $this->db->query($this->db->update('table.options')
                        ->rows(['value' => 'recent'])
                        ->where('name = ?', 'frontPage'));
                }

                $draft = $this->db->fetchRow($this->db->select('cid')
                    ->from('table.contents')
                    ->where('table.contents.parent = ? AND table.contents.type = ?', $page, 'revision')
                    ->limit(1));

                $this->deleteFields($page);

                if ($draft) {
                    $this->deleteContent($draft['cid'], false);
                    $this->deleteFields($draft['cid']);
                }

                $this->update(
                    ['parent' => $parent],
                    $this->db->sql()->where('parent = ?', $page)
                        ->where('type = ? OR type = ?', 'page', 'page_draft')
                );

                self::pluginHandle()->call('finishDelete', $page, $this);

                $deleteCount++;
            }
        }

        Notice::alloc()
            ->set(
                $deleteCount > 0 ? _t('页面已经被删除') : _t('没有页面被删除'),
                $deleteCount > 0 ? 'success' : 'notice'
            );

        $this->response->goBack();
    }

    public function deletePageDraft()
    {
        $pages = $this->request->filter('int')->getArray('cid');
        $deleteCount = 0;

        foreach ($pages as $page) {
            $condition = $this->db->sql()
                ->where('cid = ?', $page)
                ->where('type = ? OR type = ?', 'page', 'page_draft');

            if (!$this->isWriteable(clone $condition)) {
                continue;
            }

            $draft = $this->db->fetchRow($this->db->select('cid')
                ->from('table.contents')
                ->where('table.contents.parent = ? AND table.contents.type = ?', $page, 'revision')
                ->limit(1));

            if ($draft) {
                $this->deleteContent($draft['cid'], false);
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

    public function sortPage()
    {
        $pages = $this->request->filter('int')->getArray('cid');

        if ($pages) {
            foreach ($pages as $sort => $cid) {
                $this->db->query($this->db->update('table.contents')->rows(['order' => $sort + 1])
                    ->where('cid = ?', $cid));
            }
        }

        if (!$this->request->isAjax()) {
            $this->response->goBack();
        } else {
            $this->response->throwJson(['success' => 1, 'message' => _t('页面排序已经完成')]);
        }
    }

    public function prepare(): self
    {
        return $this->prepareEdit('page', true, _t('页面不存在'));
    }

    public function action()
    {
        if (!$this->request->isPost()) {
            $this->response->setStatus(405);
            $this->response->goBack();
        }
        $this->security->protect();
        $this->on($this->request->is('do=publish') || $this->request->is('do=save'))
            ->prepare()->writePage();
        $this->on($this->request->is('do=delete'))->deletePage();
        $this->on($this->request->is('do=mark'))->markPage();
        $this->on($this->request->is('do=deleteDraft'))->deletePageDraft();
        $this->on($this->request->is('do=sort'))->sortPage();
        $this->response->redirect($this->options->adminUrl);
    }

    public function getMenuTitle(): string
    {
        $this->prepare();

        if ($this->have()) {
            return _t('编辑 %s', $this->title);
        }

        if ($this->request->is('parent')) {
            $page = $this->db->fetchRow($this->select()
                ->where('table.contents.type = ? OR table.contents.type = ?', 'page', 'page_draft')
                ->where('table.contents.cid = ?', $this->request->filter('int')->get('parent')));

            if (!empty($page)) {
                return _t('新增 %s 的子页面', $page['title']);
            }
        }

        throw new Exception(_t('页面不存在'), 404);
    }

    public function getParent(): int
    {
        if ($this->request->is('parent')) {
            $parent = $this->request->filter('int')->get('parent');

            if (!$this->have() || $this->cid != $parent) {
                $parentPage = $this->db->fetchRow($this->select()
                    ->where('table.contents.type = ? OR table.contents.type = ?', 'page', 'page_draft')
                    ->where('table.contents.cid = ?', $parent));

                if (!empty($parentPage)) {
                    return $parent;
                }
            }
        }

        return 0;
    }

    protected function getThemeFieldsHook(): string
    {
        return 'themePageFields';
    }
}
