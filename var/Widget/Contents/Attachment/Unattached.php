<?php

namespace Widget\Contents\Attachment;

use Typecho\Db;
use Widget\Base\Contents;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
/**
 * 没有关联的文件
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 * @version $Id$
 */

/**
 * 没有关联的文件组件
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Unattached extends Contents
{
    /**
     * @return void
     * @throws Db\Exception
     */
    public function execute()
    {
        $select = $this->select()->where('table.contents.type = ? AND
        (table.contents.parent = 0 OR table.contents.parent IS NULL)', 'attachment');

        $select->where('table.contents.authorId = ?', $this->user->uid);

        $select->order('table.contents.created', Db::SORT_DESC);

        $this->db->fetchAll($select, [$this, 'push']);
    }
}
