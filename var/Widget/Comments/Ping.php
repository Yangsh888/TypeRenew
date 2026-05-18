<?php

namespace Widget\Comments;

use Typecho\Config;
use Widget\Base\Comments;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Ping extends Comments
{
    private bool $customSinglePingCallback = false;

    protected function initParameter(Config $parameter)
    {
        $parameter->setDefault('parentId=0');

        if (function_exists('singlePing')) {
            $this->customSinglePingCallback = true;
        }
    }

    public function num(...$args)
    {
        if (empty($args)) {
            $args[] = '%d';
        }

        echo sprintf($args[$this->length] ?? array_pop($args), $this->length);
    }

    public function execute()
    {
        if (!$this->parameter->parentId) {
            return;
        }

        $select = $this->select()->where('table.comments.status = ?', 'approved')
            ->where('table.comments.cid = ?', $this->parameter->parentId)
            ->where('table.comments.type <> ?', 'comment')
            ->order('table.comments.coid', 'ASC');

        $this->db->fetchAll($select, [$this, 'push']);
    }

    public function listPings($singlePingOptions = null)
    {
        if ($this->have()) {
            $parsedSinglePingOptions = Config::factory($singlePingOptions);
            $parsedSinglePingOptions->setDefault([
                'before'      => '<ol class="ping-list">',
                'after'       => '</ol>',
                'beforeTitle' => '',
                'afterTitle'  => '',
                'beforeDate'  => '',
                'afterDate'   => '',
                'dateFormat'  => $this->options->commentDateFormat
            ]);

            echo $parsedSinglePingOptions->before;

            while ($this->next()) {
                $this->singlePingCallback($parsedSinglePingOptions);
            }

            echo $parsedSinglePingOptions->after;
        }
    }

    private function singlePingCallback(string $singlePingOptions): void
    {
        if ($this->customSinglePingCallback) {
            singlePing($this, $singlePingOptions);
            return;
        }

        ?>
        <li id="<?php $this->theId(); ?>" class="ping-body">
            <div class="ping-title">
                <cite class="fn"><?php
                    $singlePingOptions->beforeTitle();
                    $this->author(true);
                    $singlePingOptions->afterTitle();
                ?></cite>
            </div>
            <div class="ping-meta">
                <a href="<?php $this->permalink(); ?>"><?php $singlePingOptions->beforeDate();
                    $this->date($singlePingOptions->dateFormat);
                    $singlePingOptions->afterDate(); ?></a>
            </div>
            <?php $this->content(); ?>
        </li>
        <?php
    }

    protected function ___parentContent(): ?array
    {
        return $this->parameter->parentContent;
    }
}
