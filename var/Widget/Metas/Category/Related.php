<?php

namespace Widget\Metas\Category;

use Typecho\Db\Exception;
use Widget\Base\Metas;
use Widget\Base\TreeTrait;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Related extends Metas
{
    use InitTreeRowsTrait;
    use TreeTrait;

    /**
     * @return void
     * @throws Exception
     */
    public function execute()
    {
        $ids = array_column($this->db->fetchAll($this->select('table.metas.mid')
            ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
            ->where('table.relationships.cid = ?', $this->parameter->cid)
            ->where('table.metas.type = ?', 'category')), 'mid');

        /** 预先建立 mid => order 索引, 避免比较器内 O(n) 查找 */
        $orderMap = array_flip(array_values($this->orders));

        usort($ids, function ($a, $b) use ($orderMap) {
            return ($orderMap[$a] ?? PHP_INT_MAX) <=> ($orderMap[$b] ?? PHP_INT_MAX);
        });

        $this->pushAll($this->getRows($ids));
    }
}
