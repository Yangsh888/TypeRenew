<?php

namespace Widget\Contents\Post;

use Typecho\Config;
use Typecho\Db;
use Typecho\Router;
use Typecho\Timezone;
use Widget\Base;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Date extends Base
{
    protected function initParameter(Config $parameter)
    {
        $parameter->setDefault('format=Y-m&type=month&limit=0');
    }

    public function execute()
    {
        $this->parameter->setDefault('format=Y-m&type=month&limit=0');

        $resource = $this->db->query($this->db->select('created')->from('table.contents')
            ->where('type = ?', 'post')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.created < ?', $this->options->time)
            ->order('table.contents.created', Db::SORT_DESC));

        $result = [];
        while ($post = $this->db->fetchRow($resource)) {
            $timeStamp = (int) $post['created'];
            $date = Timezone::format($timeStamp, $this->parameter->format);
            $parts = Timezone::formatDateParts($timeStamp);

            if (isset($result[$date])) {
                $result[$date]['count'] ++;
            } else {
                $result[$date]['year'] = $parts['year'];
                $result[$date]['month'] = $parts['month'];
                $result[$date]['day'] = $parts['day'];
                $result[$date]['date'] = $date;
                $result[$date]['count'] = 1;
            }
        }

        if ($this->parameter->limit > 0) {
            $result = array_slice($result, 0, $this->parameter->limit);
        }

        foreach ($result as $row) {
            $row['permalink'] = Router::url(
                'archive_' . $this->parameter->type,
                $row,
                $this->options->index
            );
            $this->push($row);
        }
    }
}
