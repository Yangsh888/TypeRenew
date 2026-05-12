<?php

namespace Widget\Contents;

use Typecho\Db\Exception as DbException;
use Typecho\Widget\Exception;
use Widget\Metas\From as MetasFrom;

trait PrepareEditTrait
{

    /**
     * 准备编辑
     * @throws Exception|DbException
     */
    protected function prepareEdit(string $type, bool $hasDraft, string $notFoundMessage): self
    {
        if ($this->request->is('cid')) {
            $contentTypes = [$type];
            if ($hasDraft) {
                $contentTypes[] = $type . '_draft';
            }

            $this->db->fetchRow($this->select()
                ->where('table.contents.type IN ?', $contentTypes)
                ->where('table.contents.cid = ?', $this->request->filter('int')->get('cid'))
                ->limit(1), [$this, 'push']);

            if (!$this->have()) {
                throw new Exception($notFoundMessage, 404);
            }

            if ($hasDraft) {
                $draft = $this->type === $type . '_draft' ? $this->row : $this->db->fetchRow(
                    $this->revisionSelect((int) $this->cid, true),
                    [$this, 'filter']
                );

                if (is_array($draft)) {
                    $draftCid = (int) $draft['cid'];
                    $draft['parent'] = $this->row['parent'];    // keep parent
                    $draft['slug'] = ltrim($draft['slug'], '@');
                    $draft['type'] = $this->type;
                    $draft['draftCid'] = $draftCid;
                    $draft['draft'] = $draft;
                    $draft['cid'] = $this->cid;

                    $this->row = $draft;
                }
            }

            if (!$this->allow('edit')) {
                throw new Exception(_t('没有编辑权限'), 403);
            }
        }

        return $this;
    }

    abstract public function prepare(): self;

    public function getMenuTitle(): string
    {
        return _t('编辑 %s', $this->prepare()->title);
    }

    /**
     * @throws Exception|DbException
     */
    public function allow(...$permissions): bool
    {
        $allow = true;

        foreach ($permissions as $permission) {
            $permission = strtolower($permission);

            if ('edit' == $permission) {
                $allow = $allow && ($this->user->pass('editor', true) || $this->authorId == $this->user->uid);
            } else {
                $permission = 'allow' . ucfirst(strtolower($permission));
                $optionPermission = 'default' . ucfirst($permission);
                $allow = $allow && ($this->{$permission} ?? $this->options->{$optionPermission});
            }
        }

        return $allow;
    }

    protected function ___title(): string
    {
        return $this->have() ? $this->row['title'] : '';
    }

    protected function ___text(): string
    {
        return $this->have() ? ($this->isMarkdown ? substr($this->row['text'], 15) : $this->row['text']) : '';
    }

    protected function ___categories(): array
    {
        return $this->have() ? parent::___categories()
            : MetasFrom::allocWithAlias(
                'category:' . $this->options->defaultCategory,
                ['mid' => $this->options->defaultCategory]
            )->toArray(['mid', 'name', 'slug']);
    }
}
