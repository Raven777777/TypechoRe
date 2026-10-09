<?php

namespace Widget\Contents;

use Typecho\Config;
use Typecho\Db\Exception as DbException;
use Typecho\Db\Query;
use Typecho\Widget\Exception;
use Typecho\Widget\Helper\PageNavigator\Box;
use Widget\Users\Author;

/**
 * 文章管理列表组件
 *
 * @property-read ?array $revision 该条目当前保存的草稿 (cid/modified/status)
 * @property-read bool $hasSaved 是否存在未发布的草稿
 */
trait AdminTrait
{
    /**
     * 所有文章个数
     *
     * @var integer|null
     */
    private ?int $total;

    /**
     * 当前页
     *
     * @var integer
     */
    private int $currentPage;

    /**
     * @return void
     */
    protected function initPage()
    {
        $this->parameter->setDefault('pageSize=20');
        $this->currentPage = $this->request->filter('int')->get('page', 1);
    }

    /**
     * @param Query $select
     * @return void
     */
    protected function searchQuery(Query $select)
    {
        if ($this->request->is('keywords')) {
            $keywords = $this->request->filter('search')->get('keywords');
            $args = [];
            $keywordsList = explode(' ', $keywords);
            $args[] = implode(' OR ', array_fill(0, count($keywordsList), 'table.contents.title LIKE ?'));

            foreach ($keywordsList as $keyword) {
                $args[] = '%' . $keyword . '%';
            }

            $select->where(...$args);
        }
    }

    /**
     * @param Query $select
     * @return void
     */
    protected function countTotal(Query $select)
    {
        $countSql = clone $select;
        $this->total = $this->size($countSql);
    }

    /**
     * 输出分页
     *
     * @throws Exception
     * @throws DbException
     */
    public function pageNav()
    {
        $query = $this->request->makeUriByRequest('page={page}');

        /** 使用盒状分页 */
        $nav = new Box(
            $this->total,
            $this->currentPage,
            $this->parameter->pageSize,
            $query
        );

        $nav->render('&laquo;', '&raquo;');
    }

    /**
     * 批量预取列表页需要的关联数据, 消除逐行 N+1 查询
     *
     * 仅在管理列表 (Post/Page/Attachment\Admin) 的 execute 尾部调用,
     * 将结果写入每行的 #author / #categories / #revision 魔术字段,
     * 模板中的 $posts->author / categories / revision 会命中缓存,
     * 不再触发逐行的 ___xxx() 单次查询
     *
     * @return void
     * @throws DbException
     */
    public function initListData(): void
    {
        if (empty($this->stack)) {
            return;
        }

        $cids = [];
        foreach ($this->stack as $row) {
            $cids[] = (int)$row['cid'];
        }
        $cids = array_values(array_unique($cids));

        /** 预取修订版 */
        $revisionMap = [];
        $rows = $this->db->fetchAll($this->db->select('cid', 'parent', 'modified')
            ->from('table.contents')
            ->where('table.contents.type = ? AND table.contents.parent IN ?', 'revision', $cids));

        foreach ($rows as $row) {
            $revisionMap[(int)$row['parent']] = ['cid' => $row['cid'], 'modified' => $row['modified']];
        }

        /** 预取分类, 字段与 Base\Contents::___categories 保持一致 (permalink 除外) */
        $categoryMap = [];
        $rows = $this->db->fetchAll($this->db->select(
            'table.relationships.cid',
            'table.metas.mid',
            'table.metas.name',
            'table.metas.slug',
            'table.metas.description',
            'table.metas.count',
            'table.metas.parent'
        )->from('table.relationships')
            ->join('table.metas', 'table.relationships.mid = table.metas.mid')
            ->where('table.metas.type = ? AND table.relationships.cid IN ?', 'category', $cids)
            ->order('table.metas.order', 'ASC'));

        foreach ($rows as $row) {
            $cid = $row['cid'];
            unset($row['cid']);
            $categoryMap[$cid][] = $row;
        }

        /** 每 uid 复用同一个作者 widget, 利用 widgetPool 按别名缓存 */
        $authorWidgets = [];
        $authorWidget = function (int $uid) use (&$authorWidgets): ?Author {
            if (array_key_exists($uid, $authorWidgets)) {
                return $authorWidgets[$uid];
            }
            try {
                return $authorWidgets[$uid] = Author::allocWithAlias('avatar-' . $uid, ['uid' => $uid]);
            } catch (Exception $e) {
                return $authorWidgets[$uid] = null;
            }
        };

        /** 注入堆栈 */
        foreach ($this->stack as $key => $row) {
            $cid = (int)$row['cid'];
            $authorId = (int)($row['authorId'] ?? 0);

            $row['#revision'] = $revisionMap[$cid] ?? null;
            $row['#categories'] = $categoryMap[$cid] ?? [];

            if ($authorId > 0) {
                $row['#author'] = $authorWidget($authorId);
            }

            $this->stack[$key] = $row;
        }
    }

    /**
     * @return array|null
     * @throws DbException
     */
    protected function ___revision(): ?array
    {
        return $this->db->fetchRow(
            $this->select('cid', 'modified')
                ->where(
                    'table.contents.parent = ? AND table.contents.type = ?',
                    $this->cid,
                    'revision'
                )
                ->limit(1)
        );
    }

    /**
     * 当前条目是否存在未发布的草稿
     *
     * 上游 v1.2.1 在 Widget\Contents\Post\Admin 中实现过同样逻辑, v1.3.0 重构时
     * 丢失, 导致 XmlRpc 的 wp.getPosts / metaWeblog.getPost 无法报告 draft 状态。
     * 这里放回共用 trait, 供文章/页面/附件管理列表使用。
     *
     * @return bool
     * @throws DbException
     */
    protected function ___hasSaved(): bool
    {
        if (in_array($this->type, ['post_draft', 'page_draft'], true)) {
            return true;
        }

        $savedPost = $this->db->fetchRow(
            $this->db->select('cid', 'modified', 'status')
                ->from('table.contents')
                ->where(
                    'table.contents.parent = ? AND (table.contents.type = ? OR table.contents.type = ?)',
                    $this->cid,
                    'post_draft',
                    'page_draft'
                )
                ->limit(1)
        );

        if ($savedPost) {
            $this->modified = $savedPost['modified'];
            return true;
        }

        return false;
    }
}
