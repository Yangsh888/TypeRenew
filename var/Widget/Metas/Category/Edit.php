<?php

namespace Widget\Metas\Category;

use Typecho\Common;
use Typecho\Validate;
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

    public function categoryExists(int $mid): bool
    {
        $category = $this->db->fetchRow($this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'category')
            ->where('mid = ?', $mid)->limit(1));

        return isset($category);
    }

    public function nameExists(string $name): bool
    {
        $select = $this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'category')
            ->where('name = ?', $name)
            ->limit(1);

        if ($this->request->is('mid')) {
            $select->where('mid <> ?', $this->request->get('mid'));
        }

        // 只在同一父分类下判断重复性
        $select->where('parent = ?', $this->request->filter('int')->get('parent', 0));

        $category = $this->db->fetchRow($select);
        return !$category;
    }

    public function nameToSlug(string $name): bool
    {
        if (empty($this->request->slug)) {
            $slug = Common::slugName($name);
            if (empty($slug) || !$this->slugExists($name)) {
                return false;
            }
        }

        return true;
    }

    public function slugExists(string $slug): bool
    {
        $select = $this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'category')
            ->where('slug = ?', Common::slugName($slug))
            ->limit(1);

        if ($this->request->is('mid')) {
            $select->where('mid <> ?', $this->request->get('mid'));
        }

        $category = $this->db->fetchRow($select);
        return !$category;
    }

    public function insertCategory()
    {
        if ($this->form('insert')->validate()) {
            $this->response->goBack();
        }

        $category = $this->request->from('name', 'slug', 'description', 'parent');

        $category['slug'] = Common::slugName(Common::strBy($category['slug'] ?? null, $category['name']));
        $category['type'] = 'category';
        $category['order'] = $this->getMaxOrder('category', $category['parent']) + 1;

        $category['mid'] = $this->insert($category);
        $this->push($category);
        self::pluginHandle()->call('finishInsert', $category, $this);

        Notice::alloc()->highlight($this->theId);

        Notice::alloc()->set(
            _t('分类 <a href="%s">%s</a> 已经被增加', $this->permalink, $this->name),
            'success'
        );

        $this->response->redirect(Common::url('manage-categories.php'
            . ($category['parent'] ? '?parent=' . $category['parent'] : ''), $this->options->adminUrl));
    }

    public function form(?string $action = null): Form
    {
        $form = new Form($this->security->getIndex('/action/metas-category-edit'), Form::POST_METHOD);

        $name = new Form\Element\Text('name', null, null, _t('分类名称') . ' *');
        $form->addInput($name);

        $slug = new Form\Element\Text(
            'slug',
            null,
            null,
            _t('分类缩略名'),
            _t('分类缩略名用于创建友好的链接形式, 建议使用字母, 数字, 下划线和横杠.')
        );
        $form->addInput($slug);

        $options = [0 => _t('不选择')];
        $parents = Rows::allocWithAlias(
            'options',
            ($this->request->is('mid') ? 'ignore=' . $this->request->get('mid') : '')
        );

        while ($parents->next()) {
            $options[$parents->mid] = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $parents->levels) . $parents->name;
        }

        $parent = new Form\Element\Select(
            'parent',
            $options,
            $this->request->get('parent'),
            _t('父级分类'),
            _t('此分类将归档在您选择的父级分类下.')
        );
        $form->addInput($parent);

        $description = new Form\Element\Textarea(
            'description',
            null,
            null,
            _t('分类描述'),
            _t('此文字用于描述分类, 在有的主题中它会被显示.')
        );
        $form->addInput($description);

        $do = new Form\Element\Hidden('do');
        $form->addInput($do);

        $mid = new Form\Element\Hidden('mid');
        $form->addInput($mid);

        $submit = new Form\Element\Submit();
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        if (isset($this->request->mid) && 'insert' != $action) {
            $meta = $this->db->fetchRow($this->select()
                ->where('mid = ?', $this->request->mid)
                ->where('type = ?', 'category')->limit(1));

            if (!$meta) {
                $this->response->redirect(Common::url('manage-categories.php', $this->options->adminUrl));
            }

            $name->value($meta['name']);
            $slug->value($meta['slug']);
            $parent->value($meta['parent']);
            $description->value($meta['description']);
            $do->value('update');
            $mid->value($meta['mid']);
            $submit->value(_t('编辑分类'));
            $_action = 'update';
        } else {
            $do->value('insert');
            $submit->value(_t('增加分类'));
            $_action = 'insert';
        }

        if (empty($action)) {
            $action = $_action;
        }

        if ('insert' == $action || 'update' == $action) {
            $name->addRule('required', _t('必须填写分类名称'));
            $name->addRule([$this, 'nameExists'], _t('分类名称已经存在'));
            $name->addRule([$this, 'nameToSlug'], _t('分类名称无法被转换为缩略名'));
            $name->addRule('xssCheck', _t('请不要在分类名称中使用特殊字符'));
            $slug->addRule([$this, 'slugExists'], _t('缩略名已经存在'));
            $slug->addRule('xssCheck', _t('请不要在缩略名中使用特殊字符'));
        }

        if ('update' == $action) {
            $mid->addRule('required', _t('分类主键不存在'));
            $mid->addRule([$this, 'categoryExists'], _t('分类不存在'));
        }

        return $form;
    }

    public function updateCategory()
    {
        if ($this->form('update')->validate()) {
            $this->response->goBack();
        }

        $category = $this->request->from('name', 'slug', 'description', 'parent');
        $category['mid'] = $this->request->get('mid');
        $category['parent'] = (int) ($category['parent'] ?? 0);
        $category['slug'] = Common::slugName(Common::strBy($category['slug'] ?? null, $category['name']));
        $category['type'] = 'category';
        $current = $this->db->fetchRow($this->select()->where('mid = ?', $category['mid']));

        if (!is_array($current)) {
            Notice::alloc()->set(_t('分类不存在'), 'error');
            $this->response->goBack();
        }

        if ((int) ($current['parent'] ?? 0) !== $category['parent']) {
            if ($category['parent'] > 0) {
                $parent = $this->db->fetchRow($this->select()->where('mid = ?', $category['parent']));

                if (!is_array($parent)) {
                    Notice::alloc()->set(_t('父分类不存在'), 'error');
                    $this->response->goBack();
                }

                if ((int) ($parent['mid'] ?? 0) === (int) $category['mid']) {
                    $currentParent = (int) ($current['parent'] ?? 0);
                    $currentOrder = (int) ($current['order'] ?? 0);
                    $parentOrder = (int) ($parent['order'] ?? 0);

                    $category['order'] = $parentOrder;
                    $this->update([
                        'parent' => $currentParent,
                        'order'  => $currentOrder
                    ], $this->db->sql()->where('mid = ?', (int) $parent['mid']));
                } else {
                    $category['order'] = $this->getMaxOrder('category', $category['parent']) + 1;
                }
            } else {
                $category['order'] = $this->getMaxOrder('category', 0) + 1;
            }
        }

        $this->update($category, $this->db->sql()->where('mid = ?', $this->request->filter('int')->get('mid')));
        $this->push($category);
        self::pluginHandle()->call('finishUpdate', $category, $current, $this);

        Notice::alloc()->highlight($this->theId);

        Notice::alloc()
            ->set(_t('分类 <a href="%s">%s</a> 已经被更新', $this->permalink, $this->name), 'success');

        $this->response->redirect(Common::url('manage-categories.php'
            . ($category['parent'] ? '?parent=' . $category['parent'] : ''), $this->options->adminUrl));
    }

    public function deleteCategory()
    {
        $categories = $this->request->filter('int')->getArray('mid');
        $deleteCount = 0;

        foreach ($categories as $category) {
            $row = $this->db->fetchObject($this->select()->where('mid = ?', $category));
            if (!$row) {
                continue;
            }

            $parent = (int) ($row->parent ?? 0);

            if ($this->delete($this->db->sql()->where('mid = ?', $category))) {
                $this->db->query($this->db->delete('table.relationships')->where('mid = ?', $category));
                $this->update(['parent' => $parent], $this->db->sql()->where('parent = ?', $category));
                $deleteCount++;
            }
        }

        if ($deleteCount > 0) {
            self::pluginHandle()->call('finishDelete', $categories, $this);
        }

        Notice::alloc()
            ->set($deleteCount > 0 ? _t('分类已经删除') : _t('没有分类被删除'), $deleteCount > 0 ? 'success' : 'notice');

        $this->response->goBack();
    }

    public function mergeCategory()
    {
        $validator = new Validate();
        $validator->addRule('merge', 'required', _t('分类主键不存在'));
        $validator->addRule('merge', [$this, 'categoryExists'], _t('请选择需要合并的分类'));

        if ($error = $validator->run($this->request->from('merge'))) {
            Notice::alloc()->set($error, 'error');
            $this->response->goBack();
        }

        $merge = $this->request->get('merge');
        $categories = $this->request->filter('int')->getArray('mid');

        if ($categories) {
            $this->merge($merge, 'category', $categories);
            self::pluginHandle()->call('finishMerge', $merge, $categories, $this);

            Notice::alloc()->set(_t('分类已经合并'), 'success');
        } else {
            Notice::alloc()->set(_t('没有选择任何分类'));
        }

        $this->response->goBack();
    }

    public function sortCategory()
    {
        $categories = $this->request->filter('int')->getArray('mid');
        if ($categories) {
            $this->sort($categories, 'category');
        }

        if ($this->request->isAjax()) {
            $this->response->throwJson(['success' => 1, 'message' => _t('分类排序已经完成')]);
        }

        $this->response->redirect(Common::url('manage-categories.php', $this->options->adminUrl));
    }

    public function refreshCategory()
    {
        $categories = $this->request->filter('int')->getArray('mid');
        if ($categories) {
            foreach ($categories as $category) {
                $this->refreshCountByTypeAndStatus($category, 'post');
            }

            self::pluginHandle()->call('finishRefresh', $categories, $this);

            Notice::alloc()->set(_t('分类刷新已经完成'), 'success');
        } else {
            Notice::alloc()->set(_t('没有选择任何分类'));
        }

        $this->response->goBack();
    }

    public function defaultCategory()
    {
        $validator = new Validate();
        $validator->addRule('mid', 'required', _t('分类主键不存在'));
        $validator->addRule('mid', [$this, 'categoryExists'], _t('分类不存在'));

        if ($error = $validator->run($this->request->from('mid'))) {
            Notice::alloc()->set($error, 'error');
        } else {
            $this->db->query($this->db->update('table.options')
                ->rows(['value' => $this->request->get('mid')])
                ->where('name = ?', 'defaultCategory'));

            $this->db->fetchRow($this->select()->where('mid = ?', $this->request->get('mid'))
                ->where('type = ?', 'category')->limit(1), [$this, 'push']);

            Notice::alloc()->highlight($this->theId);

            Notice::alloc()->set(
                _t('<a href="%s">%s</a> 已经被设为默认分类', $this->permalink, $this->name),
                'success'
            );
        }

        $this->response->redirect(Common::url('manage-categories.php', $this->options->adminUrl));
    }

    public function getMenuTitle(): ?string
    {
        if ($this->request->is('mid')) {
            $category = $this->db->fetchRow($this->select()
                ->where('type = ? AND mid = ?', 'category', $this->request->filter('int')->get('mid')));

            if (!empty($category)) {
                return _t('编辑分类 %s', $category['name']);
            }
        }

        if ($this->request->is('parent')) {
            $category = $this->db->fetchRow($this->select()
                ->where('type = ? AND mid = ?', 'category', $this->request->filter('int')->get('parent')));

            if (!empty($category)) {
                return _t('新增 %s 的子分类', $category['name']);
            }
        } else {
            return null;
        }

        throw new \Typecho\Widget\Exception(_t('分类不存在'), 404);
    }

    public function action()
    {
        $this->security->protect();
        $this->on($this->request->is('do=insert'))->insertCategory();
        $this->on($this->request->is('do=update'))->updateCategory();
        $this->on($this->request->is('do=delete'))->deleteCategory();
        $this->on($this->request->is('do=merge'))->mergeCategory();
        $this->on($this->request->is('do=sort'))->sortCategory();
        $this->on($this->request->is('do=refresh'))->refreshCategory();
        $this->on($this->request->is('do=default'))->defaultCategory();
        $this->response->redirect($this->options->adminUrl);
    }
}
