<?php

namespace Widget\Metas\Tag;

use Typecho\Common;
use Typecho\Db\Exception;
use Typecho\Widget\Helper\Form;
use Widget\Base\Metas;
use Widget\ActionInterface;
use Widget\Metas\EditTrait;
use Widget\Notice;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Edit extends Metas implements ActionInterface
{
    use EditTrait;

    public function execute()
    {
        $this->user->pass('editor');
    }

    /**
     * 判断标签是否存在
     *
     * @param integer $mid 标签主键
     * @throws Exception
     */
    public function tagExists(int $mid): bool
    {
        $tag = $this->db->fetchRow($this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'tag')
            ->where('mid = ?', $mid)->limit(1));

        return isset($tag);
    }

    /**
     * 判断标签名称是否可用
     *
     * @param string $name 标签名称
     * @throws Exception
     */
    public function nameExists(string $name): bool
    {
        $select = $this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'tag')
            ->where('name = ?', $name)
            ->limit(1);

        if ($this->request->is('mid')) {
            $select->where('mid <> ?', $this->request->filter('int')->get('mid'));
        }

        $tag = $this->db->fetchRow($select);
        return !$tag;
    }

    /**
     * 判断标签名转换到缩略名后是否合法
     *
     * @param string $name 标签名
     * @throws Exception
     */
    public function nameToSlug(string $name): bool
    {
        if (empty($this->request->slug)) {
            if (empty(Common::slugName($name)) || !$this->slugExists($name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 判断标签缩略名是否存在
     *
     * @param string $slug 缩略名
     * @throws Exception
     */
    public function slugExists(string $slug): bool
    {
        $select = $this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'tag')
            ->where('slug = ?', Common::slugName($slug))
            ->limit(1);

        if ($this->request->is('mid')) {
            $select->where('mid <> ?', $this->request->get('mid'));
        }

        $tag = $this->db->fetchRow($select);
        return !$tag;
    }

    public function insertTag()
    {
        if ($this->form('insert')->validate()) {
            $this->response->goBack();
        }

        $tag = $this->request->from('name', 'slug');
        $tag['type'] = 'tag';
        $tag['slug'] = Common::slugName(Common::strBy($tag['slug'] ?? null, $tag['name']));

        $tag['mid'] = $this->insert($tag);
        $this->push($tag);
        self::pluginHandle()->call('finishInsert', $tag, $this);

        Notice::alloc()->highlight($this->theId);

        Notice::alloc()->set(
            _t('标签 <a href="%s">%s</a> 已经被增加', $this->permalink, $this->name),
            'success'
        );

        $this->response->redirect(Common::url('manage-tags.php', $this->options->adminUrl));
    }

    /**
     * 生成表单
     *
     * @param string|null $action 表单动作
     * @return Form
     * @throws Exception
     */
    public function form(?string $action = null): Form
    {
        $form = new Form($this->security->getIndex('/action/metas-tag-edit'), Form::POST_METHOD);

        $name = new Form\Element\Text(
            'name',
            null,
            null,
            _t('标签名称') . ' *',
            _t('该名称为标签在站点中的显示名称，支持中文，例如「地球」')
        );
        $form->addInput($name);

        $slug = new Form\Element\Text(
            'slug',
            null,
            null,
            _t('标签缩略名'),
            _t('缩略名用于生成简洁友好的链接，若留空，将默认使用标签名称')
        );
        $form->addInput($slug);

        $do = new Form\Element\Hidden('do');
        $form->addInput($do);

        $mid = new Form\Element\Hidden('mid');
        $form->addInput($mid);

        $submit = new Form\Element\Submit();
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        if ($this->request->is('mid') && 'insert' != $action) {
            $meta = $this->db->fetchRow($this->select()
                ->where('mid = ?', $this->request->get('mid'))
                ->where('type = ?', 'tag')->limit(1));

            if (!$meta) {
                $this->response->redirect(Common::url('manage-tags.php', $this->options->adminUrl));
            }

            $name->value($meta['name']);
            $slug->value($meta['slug']);
            $do->value('update');
            $mid->value($meta['mid']);
            $submit->value(_t('编辑标签'));
            $_action = 'update';
        } else {
            $do->value('insert');
            $submit->value(_t('增加标签'));
            $_action = 'insert';
        }

        if (empty($action)) {
            $action = $_action;
        }

        if ('insert' == $action || 'update' == $action) {
            $name->addRule('required', _t('必须填写标签名称'));
            $name->addRule([$this, 'nameExists'], _t('标签名称已经存在'));
            $name->addRule([$this, 'nameToSlug'], _t('标签名称无法被转换为缩略名'));
            $name->addRule('xssCheck', _t('请不要标签名称中使用特殊字符'));
            $slug->addRule([$this, 'slugExists'], _t('缩略名已经存在'));
            $slug->addRule('xssCheck', _t('请不要在缩略名中使用特殊字符'));
        }

        if ('update' == $action) {
            $mid->addRule('required', _t('标签主键不存在'));
            $mid->addRule([$this, 'tagExists'], _t('标签不存在'));
        }

        return $form;
    }

    public function updateTag()
    {
        if ($this->form('update')->validate()) {
            $this->response->goBack();
        }

        $tag = $this->request->from('name', 'slug', 'mid');
        $tag['type'] = 'tag';
        $tag['slug'] = Common::slugName(Common::strBy($tag['slug'] ?? null, $tag['name']));

        $this->update($tag, $this->db->sql()->where('mid = ?', $this->request->filter('int')->get('mid')));
        $this->push($tag);
        self::pluginHandle()->call('finishUpdate', $tag, $this);

        Notice::alloc()->highlight($this->theId);

        Notice::alloc()->set(
            _t('标签 <a href="%s">%s</a> 已经被更新', $this->permalink, $this->name),
            'success'
        );

        $this->response->redirect(Common::url('manage-tags.php', $this->options->adminUrl));
    }

    public function deleteTag()
    {
        $tags = $this->request->filter('int')->getArray('mid');
        $deleteCount = 0;

        if ($tags) {
            foreach ($tags as $tag) {
                if ($this->delete($this->db->sql()->where('mid = ?', $tag))) {
                    $this->db->query($this->db->delete('table.relationships')->where('mid = ?', $tag));
                    $deleteCount++;
                }
            }
        }

        if ($deleteCount > 0) {
            self::pluginHandle()->call('finishDelete', $tags, $this);
        }

        Notice::alloc()->set(
            $deleteCount > 0 ? _t('标签已经删除') : _t('没有标签被删除'),
            $deleteCount > 0 ? 'success' : 'notice'
        );

        $this->response->redirect(Common::url('manage-tags.php', $this->options->adminUrl));
    }

    public function mergeTag()
    {
        if (empty($this->request->merge)) {
            Notice::alloc()->set(_t('请填写需要合并到的标签'));
            $this->response->goBack();
        }

        $merge = $this->scanTags($this->request->get('merge'));
        if (empty($merge)) {
            Notice::alloc()->set(_t('合并到的标签名不合法'), 'error');
            $this->response->goBack();
        }

        $tags = $this->request->filter('int')->getArray('mid');

        if ($tags) {
            $this->merge($merge, 'tag', $tags);
            self::pluginHandle()->call('finishMerge', $merge, $tags, $this);

            Notice::alloc()->set(_t('标签已经合并'), 'success');
        } else {
            Notice::alloc()->set(_t('没有选择任何标签'));
        }

        $this->response->redirect(Common::url('manage-tags.php', $this->options->adminUrl));
    }

    public function refreshTag()
    {
        $tags = $this->request->filter('int')->getArray('mid');
        if ($tags) {
            foreach ($tags as $tag) {
                $this->refreshCountByTypeAndStatus($tag, 'post');
            }

            $this->clearTags();
            self::pluginHandle()->call('finishRefresh', $tags, $this);

            Notice::alloc()->set(_t('标签刷新已经完成'), 'success');
        } else {
            Notice::alloc()->set(_t('没有选择任何标签'));
        }

        $this->response->goBack();
    }

    public function clearTags()
    {
        $tags = array_column($this->db->fetchAll($this->select('mid')
            ->where('type = ? AND count = ?', 'tag', 0)), 'mid');

        foreach ($tags as $tag) {
            $content = $this->db->fetchRow($this->db->select('cid')
                ->from('table.relationships')->where('mid = ?', $tag)
                ->limit(1));

            if (empty($content)) {
                $this->db->query($this->db->delete('table.metas')
                    ->where('mid = ?', $tag));
            }
        }
    }

    public function action()
    {
        if (!$this->request->isPost()) {
            $this->response->setStatus(405);
            $this->response->goBack();
            return;
        }

        $this->security->protect();
        $this->on($this->request->is('do=insert'))->insertTag();
        $this->on($this->request->is('do=update'))->updateTag();
        $this->on($this->request->is('do=delete'))->deleteTag();
        $this->on($this->request->is('do=merge'))->mergeTag();
        $this->on($this->request->is('do=refresh'))->refreshTag();
        $this->response->redirect($this->options->adminUrl);
    }
}
