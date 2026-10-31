<?php

namespace Widget\Comments;

use Typecho\Config;
use Typecho\Db;
use Typecho\Db\Exception;
use Widget\Base\Comments;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 最近评论组件
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Recent extends Comments
{
    /**
     * @param Config $parameter
     */
    #[\Override]
    protected function initParameter(Config $parameter)
    {
        $parameter->setDefault(
            ['pageSize' => $this->options->commentsListSize, 'parentId' => 0, 'ignoreAuthor' => false]
        );
    }

    /**
     * 执行函数
     *
     * @throws Exception
     */
    #[\Override]
    public function execute()
    {
        $select = $this->select('table.comments.*')->limit($this->parameter->pageSize)
            ->join('table.contents', 'table.contents.cid = table.comments.cid')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ? OR table.contents.type = ?', 'post', 'page')
            ->where('table.contents.created <= ?', $this->options->time)
            ->where('table.comments.status = ?', 'approved')
            ->order('table.comments.coid', Db::SORT_DESC);

        if ($this->parameter->parentId) {
            $select->where('table.comments.cid = ?', $this->parameter->parentId);
        }

        if ($this->options->commentsShowCommentOnly) {
            $select->where('table.comments.type = ?', 'comment');
        }

        /** 忽略作者评论 */
        if ($this->parameter->ignoreAuthor) {
            $select->where('table.comments.ownerId <> table.comments.authorId');
        }

        $this->db->fetchAll($select, [$this, 'push']);
    }
}
