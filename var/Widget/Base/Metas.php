<?php

namespace Widget\Base;

use Typecho\Common;
use Typecho\Db\Exception;
use Typecho\Db\Query;
use Typecho\Router;
use Typecho\Router\ParamsDelegateInterface;
use Widget\Base;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 描述性数据组件
 *
 * @property int $mid
 * @property string $name
 * @property string $title
 * @property string $slug
 * @property string $type
 * @property string $description
 * @property int $count
 * @property int $order
 * @property int $parent
 * @property-read string $theId
 * @property-read string $url
 * @property-read string $permalink
 * @property-read string[] $directory
 * @property-read string $feedUrl
 * @property-read string $feedRssUrl
 * @property-read string $feedAtomUrl
 *
 * @method void count()
 */
class Metas extends Base implements QueryInterface, RowFilterInterface, PrimaryKeyInterface, ParamsDelegateInterface
{
    /**
     * @return string 获取主键
     */
    #[\Override]
    public function getPrimaryKey(): string
    {
        return 'mid';
    }

    /**
     * @param string $key
     * @return string
     */
    #[\Override]
    public function getRouterParam(string $key): string
    {
        switch ($key) {
            case 'mid':
                return (string)$this->mid;
            case 'slug':
                return urlencode($this->slug);
            case 'directory':
                return implode('/', array_map('urlencode', $this->directory));
            default:
                return '{' . $key . '}';
        }
    }

    /**
     * 获取记录总数
     *
     * @param Query $condition 计算条件
     * @return integer
     * @throws Exception
     */
    #[\Override]
    public function size(Query $condition): int
    {
        return $this->db->fetchObject($condition->select(['COUNT(mid)' => 'num'])->from('table.metas'))->num;
    }

    /**
     * 将每行的值压入堆栈
     *
     * @param array $value 每行的值
     * @return array
     */
    #[\Override]
    public function push(array $value): array
    {
        $value = $this->filter($value);
        return parent::push($value);
    }

    /**
     * 通用过滤器
     *
     * @param array $row 需要过滤的行数据
     * @return array
     */
    #[\Override]
    public function filter(array $row): array
    {
        return Metas::pluginHandle()->filter('filter', $row, $this);
    }

    /**
     * 更新记录
     *
     * @param array $rows 记录更新值
     * @param Query $condition 更新条件
     * @return integer
     * @throws Exception
     */
    #[\Override]
    public function update(array $rows, Query $condition): int
    {
        return $this->db->query($condition->update('table.metas')->rows($rows));
    }

    /**
     * 获取原始查询对象
     *
     * @param mixed $fields
     * @return Query
     * @throws Exception
     */
    #[\Override]
    public function select(...$fields): Query
    {
        return $this->db->select(...$fields)->from('table.metas');
    }

    /**
     * 删除记录
     *
     * @param Query $condition 删除条件
     * @return integer
     * @throws Exception
     */
    #[\Override]
    public function delete(Query $condition): int
    {
        return $this->db->query($condition->delete('table.metas'));
    }

    /**
     * 插入一条记录
     *
     * @param array $rows 记录插入值
     * @return integer
     * @throws Exception
     */
    #[\Override]
    public function insert(array $rows): int
    {
        return $this->db->query($this->db->insert('table.metas')->rows($rows));
    }

    /**
     * 根据tag获取ID
     *
     * @param mixed $inputTags 标签名
     * @return array|int
     * @throws Exception
     */
    public function scanTags($inputTags)
    {
        $tags = is_array($inputTags) ? $inputTags : [$inputTags];
        $result = [];

        foreach ($tags as $tag) {
            if (empty($tag)) {
                continue;
            }

            $row = $this->db->fetchRow($this->select()
                ->where('type = ?', 'tag')
                ->where('name = ?', $tag)->limit(1));

            if ($row) {
                $result[] = $row['mid'];
            } else {
                $slug = Common::slugName($tag);

                if ($slug) {
                    $result[] = $this->insert([
                        'name'  => $tag,
                        'slug'  => $slug,
                        'type'  => 'tag',
                        'count' => 0,
                        'order' => 0,
                    ]);
                }
            }
        }

        return is_array($inputTags) ? $result : current($result);
    }

    /**
     * 锚点id
     *
     * @access protected
     * @return string
     */
    protected function ___theId(): string
    {
        return $this->type . '-' . $this->mid;
    }

    /**
     * @return string
     */
    protected function ___title(): string
    {
        return $this->name;
    }

    /**
     * @return array
     */
    protected function ___directory(): array
    {
        return [];
    }

    /**
     * @return string
     */
    protected function ___permalink(): string
    {
        return Router::url($this->type, $this, $this->options->index);
    }

    /**
     * @return string
     */
    protected function ___url(): string
    {
        return $this->permalink;
    }

    /**
     * @return string
     */
    protected function ___feedUrl(): string
    {
        return Router::url($this->type, $this, $this->options->feedUrl);
    }

    /**
     * @return string
     */
    protected function ___feedRssUrl(): string
    {
        return Router::url($this->type, $this, $this->options->feedRssUrl);
    }

    /**
     * @return string
     */
    protected function ___feedAtomUrl(): string
    {
        return Router::url($this->type, $this, $this->options->feedAtomUrl);
    }

    /**
     * 清理没有任何内容的标签
     *
     * 删除文章/刷新标签后需要调用, 否则 count 为 0 的孤立标签会一直留在表里。
     * 原先只声明在 Widget\Metas\Tag\Edit 上, 导致 Post\Edit 里的调用被
     * __call() 吞掉而静默失效。
     *
     * @throws Exception
     */
    public function clearTags(): void
    {
        // 取出count为0的标签
        $tags = array_column($this->db->fetchAll($this->select('mid')
            ->where('type = ? AND count = ?', 'tag', 0)), 'mid');

        foreach ($tags as $tag) {
            // 确认是否已经没有关联了
            $content = $this->db->fetchRow($this->db->select('cid')
                ->from('table.relationships')->where('mid = ?', $tag)
                ->limit(1));

            if (empty($content)) {
                $this->db->query($this->db->delete('table.metas')
                    ->where('mid = ?', $tag));
            }
        }
    }
}
