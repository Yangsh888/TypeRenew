<?php

namespace Utils\Migration\Steps;

use Typecho\Db;
use Utils\Migration\StepInterface;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class NormalizeLegacyStorageStep implements StepInterface
{
    public function version(): string
    {
        return '1.3.0';
    }

    public function up(Db $db, Options $options)
    {
        $routingTable = $options->routingTable;

        $routingTable['comment_page'] = [
            'url' => '[permalink:string]/comment-page-[commentPage:digital]',
            'widget' => '\Widget\CommentPage',
            'action' => 'action'
        ];

        $routingTable['feed'] = [
            'url' => '/feed[feed:string:0]',
            'widget' => '\Widget\Feed',
            'action' => 'render'
        ];

        unset($routingTable[0]);

        $db->query($db->update('table.options')
            ->rows(['value' => json_encode($routingTable)])
            ->where('name = ?', 'routingTable'));

        $db->query($db->update('table.options')
            ->rows(['name' => 'commentsRequireUrl'])
            ->where('name = ?', 'commentsRequireURL'));

        $db->query($db->update('table.contents')
            ->rows(['type' => 'revision'])
            ->where('parent <> 0 AND (type = ? OR type = ?)', 'post_draft', 'page_draft'));

        $lastId = 0;
        do {
            $rows = $db->fetchAll(
                $db->select('cid', 'text')->from('table.contents')
                    ->where('cid > ?', $lastId)
                    ->where('type = ?', 'attachment')
                    ->order('cid', Db::SORT_ASC)
                    ->limit(100)
            );

            foreach ($rows as $row) {
                if (strpos($row['text'], 'a:') !== 0) {
                    continue;
                }

                $value = $this->tryUnserialize((string) $row['text']);
                if ($value !== null) {
                    $db->query($db->update('table.contents')
                        ->rows(['text' => json_encode($value)])
                        ->where('cid = ?', $row['cid']));
                }

                $lastId = $row['cid'];
            }
        } while (count($rows) === 100);

        $rows = $db->fetchAll($db->select()->from('table.options'));

        foreach ($rows as $row) {
            if (
                in_array($row['name'], ['plugins', 'actionTable', 'panelTable'])
                || strpos($row['name'], 'plugin:') === 0
                || strpos($row['name'], 'theme:') === 0
            ) {
                $value = $this->tryUnserialize((string) $row['value']);
                if ($value !== null) {
                    $db->query($db->update('table.options')
                        ->rows(['value' => json_encode($value)])
                        ->where('name = ?', $row['name']));
                }
            }
        }
    }

    private function tryUnserialize(string $value)
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $result = @unserialize($value, ['allowed_classes' => false]);
        if ($result === false && $value !== 'b:0;' && $value !== 'N;') {
            return null;
        }

        return $result;
    }
}
