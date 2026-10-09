<?php

namespace Widget\Contents;

use Typecho\Db\Exception as DbException;

/**
 * 内容列表分页定位
 *
 * 编辑成功/删除成功之后需要跳回该条目所在的列表页, 这里统一实现「计算某条
 * 内容在第几页」的逻辑。文章 (Post\Edit)、独立页面 (Page\Edit) 与附件
 * (Attachment\Edit) 共用。
 */
trait PageOffsetTrait
{
    /**
     * 获取页面偏移
     *
     * @param string $column 字段名
     * @param integer $offset 偏移值
     * @param string $type 类型
     * @param string|null $status 状态值
     * @param integer $authorId 作者
     * @param integer $pageSize 分页值
     * @return integer
     * @throws DbException
     */
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

        $count = (int) $this->db->fetchObject($select)->num + 1;

        // ceil($count / $pageSize) 的整数实现, 避免 float -> int 的隐式转换
        return intdiv($count + $pageSize - 1, $pageSize);
    }
}
