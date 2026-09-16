<?php

namespace Widget;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 全局统计组件
 *
 * 所有计数按"维度分组"批量加载 (一次查询得到多个统计值),
 * 避免后台每个统计项各发一条 COUNT 查询。
 *
 * @property-read int $publishedPostsNum
 * @property-read int $waitingPostsNum
 * @property-read int $draftPostsNum
 * @property-read int $myPublishedPostsNum
 * @property-read int $myWaitingPostsNum
 * @property-read int $myDraftPostsNum
 * @property-read int $currentPublishedPostsNum
 * @property-read int $currentWaitingPostsNum
 * @property-read int $currentDraftPostsNum
 * @property-read int $publishedPagesNum
 * @property-read int $draftPagesNum
 * @property-read int $publishedCommentsNum
 * @property-read int $waitingCommentsNum
 * @property-read int $spamCommentsNum
 * @property-read int $myPublishedCommentsNum
 * @property-read int $myWaitingCommentsNum
 * @property-read int $mySpamCommentsNum
 * @property-read int $currentCommentsNum
 * @property-read int $currentPublishedCommentsNum
 * @property-read int $currentWaitingCommentsNum
 * @property-read int $currentSpamCommentsNum
 * @property-read int $categoriesNum
 * @property-read int $tagsNum
 */
class Stat extends Base
{
    /**
     * type|status => count
     *
     * @var array|null
     */
    private ?array $contentCounts = null;

    /**
     * @var array|null
     */
    private ?array $myContentCounts = null;

    /**
     * @var array|null
     */
    private ?array $currentContentCounts = null;

    /**
     * status => count
     *
     * @var array|null
     */
    private ?array $commentCounts = null;

    /**
     * @var array|null
     */
    private ?array $myCommentCounts = null;

    /**
     * @var array|null
     */
    private ?array $currentCommentCounts = null;

    /**
     * type => count
     *
     * @var array|null
     */
    private ?array $metaCounts = null;

    /**
     * @param int $components
     */
    protected function initComponents(int &$components)
    {
        $components = self::INIT_USER;
    }

    /**
     * 按 type + status 分组统计内容数
     *
     * @return array
     */
    private function contentCounts(): array
    {
        if (null === $this->contentCounts) {
            $this->contentCounts = $this->fetchContentCounts();
        }

        return $this->contentCounts;
    }

    /**
     * @return array
     */
    private function myContentCounts(): array
    {
        if (null === $this->myContentCounts) {
            $this->myContentCounts = $this->fetchContentCounts(intval($this->user->uid ?? 0));
        }

        return $this->myContentCounts;
    }

    /**
     * @return array
     */
    private function currentContentCounts(): array
    {
        if (null === $this->currentContentCounts) {
            $this->currentContentCounts = $this->fetchContentCounts(
                intval($this->request->filter('int')->get('uid') ?? 0)
            );
        }

        return $this->currentContentCounts;
    }

    /**
     * @return array
     */
    private function commentCounts(): array
    {
        if (null === $this->commentCounts) {
            $this->commentCounts = $this->fetchCommentCounts();
        }

        return $this->commentCounts;
    }

    /**
     * @return array
     */
    private function myCommentCounts(): array
    {
        if (null === $this->myCommentCounts) {
            $this->myCommentCounts = $this->fetchCommentCounts(
                'table.comments.ownerId = ?',
                intval($this->user->uid ?? 0)
            );
        }

        return $this->myCommentCounts;
    }

    /**
     * @return array
     */
    private function currentCommentCounts(): array
    {
        if (null === $this->currentCommentCounts) {
            $this->currentCommentCounts = $this->fetchCommentCounts(
                'table.comments.cid = ?',
                intval($this->request->filter('int')->get('cid') ?? 0)
            );
        }

        return $this->currentCommentCounts;
    }

    /**
     * @return array
     */
    private function metaCounts(): array
    {
        if (null === $this->metaCounts) {
            $this->metaCounts = [];

            $rows = $this->db->fetchAll(
                $this->db->select(['COUNT(mid)' => 'num'], 'table.metas.type')
                    ->from('table.metas')
                    ->group('table.metas.type')
            );

            foreach ($rows as $row) {
                $this->metaCounts[(string)$row['type']] = intval($row['num']);
            }
        }

        return $this->metaCounts;
    }

    /**
     * 一次查询取回 (type, status) 的所有组合计数
     *
     * @param int|null $authorId 传入则只统计该作者
     * @return array
     */
    private function fetchContentCounts(?int $authorId = null): array
    {
        $select = $this->db
            ->select(['COUNT(cid)' => 'num'], 'table.contents.type', 'table.contents.status')
            ->from('table.contents')
            ->group('table.contents.type, table.contents.status');

        if (null !== $authorId) {
            $select->where('table.contents.authorId = ?', $authorId);
        }

        $counts = [];

        foreach ($this->db->fetchAll($select) as $row) {
            $counts[$row['type'] . '|' . $row['status']] = intval($row['num']);
        }

        return $counts;
    }

    /**
     * 一次查询取回各 status 的评论数
     *
     * @param string|null $condition 附加条件
     * @param int|null $value 附加条件参数
     * @return array
     */
    private function fetchCommentCounts(?string $condition = null, ?int $value = null): array
    {
        $select = $this->db
            ->select(['COUNT(coid)' => 'num'], 'table.comments.status')
            ->from('table.comments')
            ->group('table.comments.status');

        if (null !== $condition) {
            $select->where($condition, $value);
        }

        $counts = [];

        foreach ($this->db->fetchAll($select) as $row) {
            $counts[(string)$row['status']] = intval($row['num']);
        }

        return $counts;
    }

    /**
     * 对指定前缀的所有键求和, 用于忽略 status 的计数
     *
     * @param array $counts
     * @param string $prefix
     * @return int
     */
    private static function sumByPrefix(array $counts, string $prefix): int
    {
        $total = 0;

        foreach ($counts as $key => $num) {
            if (0 === strpos((string)$key, $prefix)) {
                $total += $num;
            }
        }

        return $total;
    }

    /**
     * 待审核的文章数: post 与 post_draft 两种形态都要计入
     *
     * @param array $counts
     * @return int
     */
    private static function waitingPosts(array $counts): int
    {
        return intval($counts['post|waiting'] ?? 0) + intval($counts['post_draft|waiting'] ?? 0);
    }

    /**
     * 获取已发布的文章数目
     *
     * @return integer
     */
    protected function ___publishedPostsNum(): int
    {
        return intval($this->contentCounts()['post|publish'] ?? 0);
    }

    /**
     * 获取待审核的文章数目
     *
     * @return integer
     */
    protected function ___waitingPostsNum(): int
    {
        return self::waitingPosts($this->contentCounts());
    }

    /**
     * 获取草稿文章数目
     *
     * @return integer
     */
    protected function ___draftPostsNum(): int
    {
        return self::sumByPrefix($this->contentCounts(), 'post_draft|');
    }

    /**
     * 获取当前用户已发布的文章数目
     *
     * @return integer
     */
    protected function ___myPublishedPostsNum(): int
    {
        return intval($this->myContentCounts()['post|publish'] ?? 0);
    }

    /**
     * 获取当前用户待审核文章数目
     *
     * @return integer
     */
    protected function ___myWaitingPostsNum(): int
    {
        return self::waitingPosts($this->myContentCounts());
    }

    /**
     * 获取当前用户草稿数目
     *
     * @return integer
     */
    protected function ___myDraftPostsNum(): int
    {
        return self::sumByPrefix($this->myContentCounts(), 'post_draft|');
    }

    /**
     * 获取当前用户已发布的文章数目
     *
     * @return integer
     */
    protected function ___currentPublishedPostsNum(): int
    {
        return intval($this->currentContentCounts()['post|publish'] ?? 0);
    }

    /**
     * 获取当前用户待审核文章数目
     *
     * @return integer
     */
    protected function ___currentWaitingPostsNum(): int
    {
        return self::waitingPosts($this->currentContentCounts());
    }

    /**
     * 获取当前用户草稿数目
     *
     * @return integer
     */
    protected function ___currentDraftPostsNum(): int
    {
        return self::sumByPrefix($this->currentContentCounts(), 'post_draft|');
    }

    /**
     * 获取已发布页面数目
     *
     * @return integer
     */
    protected function ___publishedPagesNum(): int
    {
        return intval($this->contentCounts()['page|publish'] ?? 0);
    }

    /**
     * 获取草稿页面数目
     *
     * @return integer
     */
    protected function ___draftPagesNum(): int
    {
        return self::sumByPrefix($this->contentCounts(), 'page_draft|');
    }

    /**
     * 获取当前显示的评论数目
     *
     * @return integer
     */
    protected function ___publishedCommentsNum(): int
    {
        return intval($this->commentCounts()['approved'] ?? 0);
    }

    /**
     * 获取当前待审核的评论数目
     *
     * @return integer
     */
    protected function ___waitingCommentsNum(): int
    {
        return intval($this->commentCounts()['waiting'] ?? 0);
    }

    /**
     * 获取当前垃圾评论数目
     *
     * @return integer
     */
    protected function ___spamCommentsNum(): int
    {
        return intval($this->commentCounts()['spam'] ?? 0);
    }

    /**
     * 获取当前用户显示的评论数目
     *
     * @return integer
     */
    protected function ___myPublishedCommentsNum(): int
    {
        return intval($this->myCommentCounts()['approved'] ?? 0);
    }

    /**
     * 获取当前用户待审核的评论数目
     *
     * @return integer
     */
    protected function ___myWaitingCommentsNum(): int
    {
        return intval($this->myCommentCounts()['waiting'] ?? 0);
    }

    /**
     * 获取当前用户垃圾评论数目
     *
     * @return integer
     */
    protected function ___mySpamCommentsNum(): int
    {
        return intval($this->myCommentCounts()['spam'] ?? 0);
    }

    /**
     * 获取当前文章的评论数目
     *
     * @return integer
     */
    protected function ___currentCommentsNum(): int
    {
        return array_sum($this->currentCommentCounts());
    }

    /**
     * 获取当前文章显示的评论数目
     *
     * @return integer
     */
    protected function ___currentPublishedCommentsNum(): int
    {
        return intval($this->currentCommentCounts()['approved'] ?? 0);
    }

    /**
     * 获取当前文章待审核的评论数目
     *
     * @return integer
     */
    protected function ___currentWaitingCommentsNum(): int
    {
        return intval($this->currentCommentCounts()['waiting'] ?? 0);
    }

    /**
     * 获取当前文章垃圾评论数目
     *
     * @return integer
     */
    protected function ___currentSpamCommentsNum(): int
    {
        return intval($this->currentCommentCounts()['spam'] ?? 0);
    }

    /**
     * 获取分类数目
     *
     * @return integer
     */
    protected function ___categoriesNum(): int
    {
        return intval($this->metaCounts()['category'] ?? 0);
    }

    /**
     * 获取标签数目
     *
     * @return integer
     */
    protected function ___tagsNum(): int
    {
        return intval($this->metaCounts()['tag'] ?? 0);
    }
}
