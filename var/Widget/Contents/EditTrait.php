<?php

namespace Widget\Contents;

use Typecho\Config;
use Typecho\Db\Exception as DbException;
use Typecho\Validate;
use Typecho\Widget\Exception;
use Typecho\Widget\Helper\Form\Element;
use Typecho\Widget\Helper\Layout;
use Widget\Base\Contents;
use Widget\Base\Metas;

trait EditTrait
{
    public function deleteFields(int $cid): int
    {
        return $this->db->query($this->db->delete('table.fields')
            ->where('cid = ?', $cid));
    }

    public function applyFields(array $fields, $cid)
    {
        $exists = array_flip(array_column($this->db->fetchAll($this->db->select('name')
            ->from('table.fields')->where('cid = ?', $cid)), 'name'));

        foreach ($fields as $name => $value) {
            $type = 'str';

            if (is_array($value) && 2 == count($value)) {
                $type = $value[0];
                $value = $value[1];
            } elseif (strpos($name, ':') > 0) {
                [$type, $name] = explode(':', $name, 2);
            }

            if (!$this->checkFieldName($name)) {
                continue;
            }

            $isFieldReadOnly = Contents::pluginHandle()->trigger($plugged)->call('isFieldReadOnly', $name);
            if ($plugged && $isFieldReadOnly) {
                continue;
            }

            if (isset($exists[$name])) {
                unset($exists[$name]);
            }

            $this->setField($name, $type, $value, $cid);
        }

        foreach ($exists as $name => $value) {
            $this->db->query($this->db->delete('table.fields')
                ->where('cid = ? AND name = ?', $cid, $name));
        }
    }

    public function checkFieldName(string $name): bool
    {
        return preg_match("/^[_a-z][_a-z0-9]*$/i", $name);
    }

    public function setField(string $name, string $type, $value, int $cid)
    {
        if (
            empty($name) || !$this->checkFieldName($name)
            || !in_array($type, ['str', 'int', 'float', 'json'])
        ) {
            return false;
        }

        if ($type === 'json') {
            $value = json_encode($value);
        }

        $exist = $this->db->fetchRow($this->db->select('cid')->from('table.fields')
            ->where('cid = ? AND name = ?', $cid, $name));

        $rows = [
            'type'        => $type,
            'str_value'   => 'str' == $type || 'json' == $type ? $value : null,
            'int_value'   => 'int' == $type ? intval($value) : 0,
            'float_value' => 'float' == $type ? floatval($value) : 0
        ];

        if (empty($exist)) {
            $rows['cid'] = $cid;
            $rows['name'] = $name;

            return $this->db->query($this->db->insert('table.fields')->rows($rows));
        } else {
            return $this->db->query($this->db->update('table.fields')
                ->rows($rows)
                ->where('cid = ? AND name = ?', $cid, $name));
        }
    }

    public function incrIntField(string $name, int $value, int $cid)
    {
        if (!$this->checkFieldName($name)) {
            return false;
        }

        $exist = $this->db->fetchRow($this->db->select('type')->from('table.fields')
            ->where('cid = ? AND name = ?', $cid, $name));

        if (empty($exist)) {
            return $this->db->query($this->db->insert('table.fields')
                ->rows([
                    'cid'         => $cid,
                    'name'        => $name,
                    'type'        => 'int',
                    'str_value'   => null,
                    'int_value'   => $value,
                    'float_value' => 0
                ]));
        } else {
            $struct = [
                'str_value'   => null,
                'float_value' => null
            ];

            if ('int' != $exist['type']) {
                $struct['type'] = 'int';
            }

            return $this->db->query($this->db->update('table.fields')
                ->rows($struct)
                ->expression('int_value', 'int_value ' . ($value >= 0 ? '+' : '') . $value)
                ->where('cid = ? AND name = ?', $cid, $name));
        }
    }

    public function getFieldItems(): array
    {
        $fields = [];

        if ($this->have()) {
            $defaultFields = $this->getDefaultFieldItems();
            $rows = $this->db->fetchAll($this->db->select()->from('table.fields')
                ->where('cid = ?', isset($this->draft) ? $this->draft['cid'] : $this->cid));

            foreach ($rows as $row) {
                $isFieldReadOnly = static::pluginHandle()
                    ->trigger($plugged)->call('isFieldReadOnly', $row['name']);

                if ($plugged && $isFieldReadOnly) {
                    continue;
                }

                if (!isset($defaultFields[$row['name']])) {
                    $fields[] = $row;
                }
            }
        }

        return $fields;
    }

    public function getDefaultFieldItems(): array
    {
        $defaultFields = [];
        $configFile = $this->options->themeFile($this->options->theme, 'functions.php');
        $layout = new Layout();
        $fields = new Config();

        if ($this->have()) {
            $fields = $this->fields;
        }

        Contents::pluginHandle()->call('getDefaultFieldItems', $layout);
        static::pluginHandle()->call('getDefaultFieldItems', $layout);

        if (file_exists($configFile)) {
            require_once $configFile;

            if (function_exists('themeFields')) {
                themeFields($layout);
            }

            $func = $this->getThemeFieldsHook();
            if (function_exists($func)) {
                call_user_func($func, $layout);
            }
        }

        $items = $layout->getItems();
        foreach ($items as $item) {
            if ($item instanceof Element) {
                $name = $item->input->getAttribute('name');

                $isFieldReadOnly = Contents::pluginHandle()
                    ->trigger($plugged)->call('isFieldReadOnly', $name);
                if ($plugged && $isFieldReadOnly) {
                    continue;
                }

                if (preg_match("/^fields\[(.+)\]$/", $name, $matches)) {
                    $name = $matches[1];
                } else {
                    $inputName = 'fields[' . $name . ']';
                    if (preg_match("/^(.+)\[\]$/", $name, $matches)) {
                        $name = $matches[1];
                        $inputName = 'fields[' . $name . '][]';
                    }

                    foreach ($item->inputs as $input) {
                        $input->setAttribute('name', $inputName);
                    }
                }

                if (isset($fields->{$name})) {
                    $item->value($fields->{$name});
                }

                $elements = $item->container->getItems();
                array_shift($elements);
                $div = new Layout('div');

                foreach ($elements as $el) {
                    $div->addItem($el);
                }

                $defaultFields[$name] = [$item->label, $div];
            }
        }

        return $defaultFields;
    }

    abstract protected function getThemeFieldsHook(): string;

    protected function getFields(): array
    {
        $fields = [];
        $fieldNames = $this->request->getArray('fieldNames');

        if (!empty($fieldNames)) {
            $data = [
                'fieldNames'  => $this->request->getArray('fieldNames'),
                'fieldTypes'  => $this->request->getArray('fieldTypes'),
                'fieldValues' => $this->request->getArray('fieldValues')
            ];
            foreach ($data['fieldNames'] as $key => $val) {
                $val = trim($val);

                if (0 == strlen($val)) {
                    continue;
                }

                $fields[$val] = [$data['fieldTypes'][$key], $data['fieldValues'][$key]];
            }
        }

        $customFields = $this->request->getArray('fields');
        foreach ($customFields as $key => $val) {
            $fields[$key] = [is_array($val) ? 'json' : 'str', $val];
        }

        return $fields;
    }

    protected function deleteContent(int $cid, bool $hasMetas = true)
    {
        $this->delete($this->db->sql()->where('cid = ?', $cid));

        if ($hasMetas) {
            $this->setCategories($cid, [], false, false);
            $this->setTags($cid, null, false, false);
        }
    }

    protected function getCreated(): int
    {
        $created = $this->options->time;
        if ($this->request->is('created')) {
            $created = $this->request->get('created');
        } elseif ($this->request->is('date')) {
            $dstOffset = $this->request->get('dst', 0);
            $timezoneSymbol = $this->options->timezone >= 0 ? '+' : '-';
            $timezoneOffset = abs($this->options->timezone);
            $timezone = $timezoneSymbol . str_pad($timezoneOffset / 3600, 2, '0', STR_PAD_LEFT) . ':00';
            [$date, $time] = explode(' ', $this->request->get('date'));

            $created = strtotime("{$date}T{$time}{$timezone}") - $dstOffset;
        } elseif ($this->request->is('year&month&day')) {
            $second = $this->request->filter('int')->get('sec', date('s'));
            $min = $this->request->filter('int')->get('min', date('i'));
            $hour = $this->request->filter('int')->get('hour', date('H'));

            $year = $this->request->filter('int')->get('year');
            $month = $this->request->filter('int')->get('month');
            $day = $this->request->filter('int')->get('day');

            $created = mktime($hour, $min, $second, $month, $day, $year)
                - $this->options->timezone + $this->options->serverTimezone;
        } elseif ($this->have() && $this->created > 0) {
            $created = $this->created;
        } elseif ($this->request->is('do=save')) {
            $created = 0;
        }

        return $created;
    }

    protected function setCategories(int $cid, array $categories, bool $beforeCount = true, bool $afterCount = true)
    {
        $categories = array_unique(array_map('trim', $categories));

        $existCategories = array_column(
            $this->db->fetchAll(
                $this->db->select('table.metas.mid')
                    ->from('table.metas')
                    ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
                    ->where('table.relationships.cid = ?', $cid)
                    ->where('table.metas.type = ?', 'category')
            ),
            'mid'
        );

        if ($existCategories) {
            foreach ($existCategories as $category) {
                $this->db->query($this->db->delete('table.relationships')
                    ->where('cid = ?', $cid)
                    ->where('mid = ?', $category));

                if ($beforeCount) {
                    $this->db->query($this->db->update('table.metas')
                        ->expression('count', 'count - 1')
                        ->where('mid = ?', $category));
                }
            }
        }

        if ($categories) {
            foreach ($categories as $category) {
                if (
                    !$this->db->fetchRow(
                        $this->db->select('mid')
                            ->from('table.metas')
                            ->where('mid = ?', $category)
                            ->limit(1)
                    )
                ) {
                    continue;
                }

                $this->db->query($this->db->insert('table.relationships')
                    ->rows([
                        'mid' => $category,
                        'cid' => $cid
                    ]));

                if ($afterCount) {
                    $this->db->query($this->db->update('table.metas')
                        ->expression('count', 'count + 1')
                        ->where('mid = ?', $category));
                }
            }
        }
    }

    protected function setTags(int $cid, ?string $tags, bool $beforeCount = true, bool $afterCount = true)
    {
        $tags = str_replace('，', ',', $tags ?? '');
        $tags = array_unique(array_map('trim', explode(',', $tags)));
        $tags = array_filter($tags, [Validate::class, 'xssCheck']);

        $existTags = array_column(
            $this->db->fetchAll(
                $this->db->select('table.metas.mid')
                    ->from('table.metas')
                    ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
                    ->where('table.relationships.cid = ?', $cid)
                    ->where('table.metas.type = ?', 'tag')
            ),
            'mid'
        );

        if ($existTags) {
            foreach ($existTags as $tag) {
                if (0 == strlen($tag)) {
                    continue;
                }

                $this->db->query($this->db->delete('table.relationships')
                    ->where('cid = ?', $cid)
                    ->where('mid = ?', $tag));

                if ($beforeCount) {
                    $this->db->query($this->db->update('table.metas')
                        ->expression('count', 'count - 1')
                        ->where('mid = ?', $tag));
                }
            }
        }

        $insertTags = Metas::alloc()->scanTags($tags);

        if ($insertTags) {
            foreach ($insertTags as $tag) {
                if (0 == strlen($tag)) {
                    continue;
                }

                $this->db->query($this->db->insert('table.relationships')
                    ->rows([
                        'mid' => $tag,
                        'cid' => $cid
                    ]));

                if ($afterCount) {
                    $this->db->query($this->db->update('table.metas')
                        ->expression('count', 'count + 1')
                        ->where('mid = ?', $tag));
                }
            }
        }
    }

    protected function attach(int $cid, array $contents = [])
    {
        $attachments = !empty($contents['attachment']) ? $contents['attachment'] : $this->request->getArray('attachment');
        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $key => $attachment) {
                $attachmentCid = intval($attachment);
                if ($attachmentCid <= 0) {
                    continue;
                }
                $this->db->query($this->db->update('table.contents')->rows([
                    'parent' => $cid,
                    'status' => 'publish',
                    'order'  => $key + 1
                ])->where('cid = ? AND type = ?', $attachmentCid, 'attachment'));
            }
        }

        $attachUnattached = !empty($contents['attachUnattached']) || $this->request->get('attachUnattached');
        if ($attachUnattached) {
            $authorId = $this->user->uid;
            $this->db->query($this->db->update('table.contents')->rows([
                'parent' => $cid,
                'status' => 'publish'
            ])->where('parent = 0 AND type = ? AND authorId = ?', 'attachment', $authorId)
                ->limit(100));
        }
        
        if (!empty($contents['oldCid']) && $contents['oldCid'] != $cid) {
            $oldCid = intval($contents['oldCid']);
            $authorId = $this->user->uid;
            $this->db->query($this->db->update('table.contents')->rows([
                'parent' => $cid,
                'status' => 'publish'
            ])->where('parent = ? AND type = ? AND authorId = ?', $oldCid, 'attachment', $authorId));
        }
    }

    protected function unAttach(int $cid)
    {
        $this->db->query($this->db->update('table.contents')->rows(['parent' => 0, 'status' => 'publish'])
            ->where('parent = ? AND type = ?', $cid, 'attachment'));
    }

    protected function publish(array $contents, bool $hasMetas = true)
    {
        $this->checkStatus($contents);

        $realId = 0;
        $draftCid = 0;
        $orphanDraftCid = 0;

        $isDraftToPublish = false;
        $isBeforePublish = false;
        $isAfterPublish = 'publish' === $contents['status'];

        if ($this->have()) {
            $isDraftToPublish = preg_match("/_draft$/", $this->type);
            $isBeforePublish = 'publish' === $this->status;

            if (!$isDraftToPublish && $this->draft) {
                $draftCid = $this->draft['cid'];
                $this->deleteContent($draftCid);
                $this->deleteFields($draftCid);
            }

            if ($this->update($contents, $this->db->sql()->where('cid = ?', $this->cid))) {
                $realId = $this->cid;
            }
        } else {
            $requestCid = $this->request->filter('int')->get('cid');
            if ($requestCid > 0) {
                $existingDraft = $this->db->fetchRow($this->select()
                    ->where('table.contents.cid = ? AND table.contents.parent = ?', $requestCid, 0)
                    ->where("table.contents.type LIKE '%_draft'")
                    ->limit(1));
                
                if ($existingDraft) {
                    $orphanDraftCid = $requestCid;
                }
            }
            
            $realId = $this->insert($contents);
        }

        if ($realId > 0) {
            if ($isDraftToPublish && $this->draft && $this->draft['cid'] > 0) {
                $draftCid = $this->draft['cid'];
            }
            
            if ($draftCid > 0 && $draftCid != $realId) {
                $authorId = $this->user->uid;
                $this->db->query($this->db->update('table.contents')->rows([
                    'parent' => $realId
                ])->where('parent = ? AND type = ? AND authorId = ?', $draftCid, 'attachment', $authorId));
            }
            
            if ($orphanDraftCid > 0 && $orphanDraftCid != $realId) {
                $authorId = $this->user->uid;
                $this->db->query($this->db->update('table.contents')->rows([
                    'parent' => $realId
                ])->where('parent = ? AND type = ? AND authorId = ?', $orphanDraftCid, 'attachment', $authorId));
                
                $this->deleteContent($orphanDraftCid);
                $this->deleteFields($orphanDraftCid);
            }

            if ($hasMetas) {
                if (array_key_exists('category', $contents)) {
                    $this->setCategories(
                        $realId,
                        !empty($contents['category']) && is_array($contents['category'])
                            ? $contents['category'] : [$this->options->defaultCategory],
                        !$isDraftToPublish && $isBeforePublish,
                        $isAfterPublish
                    );
                }

                if (array_key_exists('tags', $contents)) {
                    $this->setTags($realId, $contents['tags'], !$isDraftToPublish && $isBeforePublish, $isAfterPublish);
                }
            }

            $this->attach($realId, $contents);

            $this->applyFields($this->getFields(), $realId);

            $this->db->fetchRow($this->select()
                ->where('table.contents.cid = ?', $realId)->limit(1), [$this, 'push']);
        }
    }

    protected function save(array $contents, bool $hasMetas = true): int
    {
        $this->checkStatus($contents);

        $realId = 0;

        if ($this->draft) {
            $isRevision = !preg_match("/_draft$/", $this->type);
            if ($isRevision) {
                $contents['parent'] = $this->cid;
                $contents['type'] = 'revision';
            }

            if ($this->update($contents, $this->db->sql()->where('cid = ?', $this->draft['cid']))) {
                $realId = $this->draft['cid'];
            }
        } else {
            if ($this->have()) {
                $contents['parent'] = $this->cid;
                $contents['type'] = 'revision';
            }

            $realId = $this->insert($contents);

            if (!$this->have()) {
                $this->db->fetchRow(
                    $this->select()->where('table.contents.cid = ?', $realId)->limit(1),
                    [$this, 'push']
                );
            }
        }

        if ($realId > 0) {
            if ($hasMetas) {
                if (array_key_exists('category', $contents)) {
                    $this->setCategories($realId, !empty($contents['category']) && is_array($contents['category']) ?
                        $contents['category'] : [$this->options->defaultCategory], false, false);
                }

                if (array_key_exists('tags', $contents)) {
                    $this->setTags($realId, $contents['tags'], false, false);
                }
            }

            $this->attach($realId, $contents);

            $this->applyFields($this->getFields(), $realId);

            return $realId;
        }

        return $this->draft['cid'] ?? 0;
    }

    protected function getPageOffset(
        string $column,
        int $offset,
        string $type,
        ?string $status = null,
        int $authorId = 0,
        int $pageSize = 20
    ): int {
        $select = $this->db->select(['COUNT(table.contents.cid)' => 'num'])->from('table.contents')
            ->where("table.contents.{$column} > {$offset}")
            ->where(
                "table.contents.type = ? OR (table.contents.type = ? AND table.contents.parent = ?)",
                $type,
                $type . '_draft',
                0
            );

        if (!empty($status)) {
            $select->where("table.contents.status = ?", $status);
        }

        if ($authorId > 0) {
            $select->where('table.contents.authorId = ?', $authorId);
        }

        $count = $this->db->fetchObject($select)->num + 1;
        return ceil($count / $pageSize);
    }

    private function checkStatus(array &$contents)
    {
        if ($this->user->pass('editor', true)) {
            if (empty($contents['visibility'])) {
                $contents['status'] = 'publish';
            } elseif (
                !in_array($contents['visibility'], ['private', 'waiting', 'publish', 'hidden'])
            ) {
                if (empty($contents['password']) || 'password' != $contents['visibility']) {
                    $contents['password'] = '';
                }
                $contents['status'] = 'publish';
            } else {
                $contents['status'] = $contents['visibility'];
                $contents['password'] = '';
            }
        } else {
            $contents['status'] = 'waiting';
            $contents['password'] = '';
        }
    }
}
