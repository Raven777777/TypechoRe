<?php

namespace TypechoPlugin\Sticky;

use Typecho\Config;
use Typecho\Db;
use Typecho\Db\Query;
use Typecho\Plugin\Exception as PluginException;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Radio;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Textarea;
use Widget\Archive;
use Widget\Base\Contents;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 文章置顶
 *
 * Mod by <a href="https://love4z.cn/">井水玉藻</a>
 *
 * @package Sticky
 * @author Ryan, Willin Kan
 * @version 1.0.2
 * @update 2026.10.18
 * @link http://kan.willin.org/typecho/
 */
class Plugin implements PluginInterface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * 1.0.2 起置顶不再额外多出几行: 插件接管首页/分类页的取数 (Archive 的 query
     * 钩子), 置顶文章占住第一页的名额; 另外挂 Widget\Base\Contents 的 filter 钩子
     * 给置顶文章加标记。全程不依赖核心改动。
     *
     * @access public
     * @return void
     */
    #[\Override]
    public static function activate()
    {
        \Typecho\Plugin::factory(Archive::class)->query = [self::class, 'query'];
        \Typecho\Plugin::factory(Contents::class)->filter = [self::class, 'decorate'];
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @access public
     * @return void
     */
    #[\Override]
    public static function deactivate()
    {
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Form $form 配置面板
     * @return void
     */
    #[\Override]
    public static function config(Form $form)
    {
        $stickyCids = new Text(
            'sticky_cids',
            null,
            '',
            '置顶文章的 cid',
            '按照排序输入, 请以半角逗号或空格分隔 cid.'
        );
        $form->addInput($stickyCids);

        $stickyHtml = new Textarea(
            'sticky_html',
            null,
            "<span style='color:red'>[置顶]</span>",
            '置顶标题的 html',
            '纯文本部分会追加到置顶文章标题后, 原始 HTML 保存在 sticky_html 字段供主题使用'
        );
        $stickyHtml->input->setAttribute('rows', '7')->setAttribute('cols', '80');
        $form->addInput($stickyHtml);

        $stickyCat = new Radio(
            'sticky_cat',
            ['1' => _t('开启'), '0' => _t('关闭')],
            '1',
            _t('分类置顶'),
            _t('开启分类页面也会把文章置顶')
        );
        $form->addInput($stickyCat);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Form $form 配置面板
     * @return void
     */
    #[\Override]
    public static function personalConfig(Form $form)
    {
    }

    /**
     * 提交查询: 让置顶文章占住第一页的名额, 而不是额外多出来
     *
     * 以前的做法是先把置顶文章 push 到列表最前面, 再让归档另外取满一整页, 于是首页
     * 变成「每页文章数目 + 置顶数」篇。现在插件自己取数 (query 是独占钩子):
     * 第一页先取置顶文章, 再补足「每页文章数目 - 置顶数」篇普通文章; 之后各页把
     * 偏移量整体前移置顶数, 所以总篇数不变, 也不会有文章重复或漏掉。
     *
     * @param Archive $archive 归档组件
     * @param Query $select 查询对象
     * @return void
     */
    public static function query(Archive $archive, Query $select): void
    {
        $db = Db::get();
        $stickyCids = self::appliesTo($archive) ? self::cidsOf() : [];

        if (empty($stickyCids)) {
            $db->fetchAll($select, [$archive, 'push']);
            return;
        }

        /** 归档已经套好分页 (page 方法会写入 limit / offset), 这里照着它的值走 */
        $pageSize = max(1, (int) $select->getAttribute('limit'));
        $offset = max(0, (int) $select->getAttribute('offset'));

        $visible = [];

        /** 按配置顺序取出在当前过滤条件下仍然可见的置顶文章 */
        foreach ($stickyCids as $cid) {
            $row = $db->fetchRow(
                (clone $select)
                    ->cleanAttribute('order')
                    ->cleanAttribute('limit')
                    ->cleanAttribute('offset')
                    ->where('table.contents.cid = ?', $cid)
                    ->limit(1)
            );

            if (!empty($row)) {
                $visible[] = $row;
            }
        }

        $slots = min(count($visible), $pageSize);

        $query = clone $select;

        /** 置顶文章由插件自己排在最前, 普通文章里要把它们排除掉, 免得重复 */
        foreach ($visible as $row) {
            $query->where('table.contents.cid <> ?', (int) $row['cid']);
        }

        if (0 === $offset) {
            /** 第一页: 置顶 + 补足剩余名额 */
            $query->limit(max(0, $pageSize - $slots));

            foreach (array_slice($visible, 0, $slots) as $row) {
                $archive->push($row);
            }
        } else {
            /** 之后的页: 置顶已经占掉前面的名额, 偏移量整体前移 */
            $query->offset(max(0, $offset - $slots));
        }

        $db->fetchAll($query, [$archive, 'push']);
    }

    /**
     * 给置顶文章加上标记 (标题后缀 / sticky 字段), 供主题使用
     *
     * @param array $row 文章行
     * @param mixed $widget 当前内容组件
     * @return array
     */
    public static function decorate(array $row, $widget): array
    {
        if (!$widget instanceof Archive || !self::appliesTo($widget)) {
            return $row;
        }

        $stickyCids = self::cidsOf();

        if (empty($stickyCids) || !in_array((int) ($row['cid'] ?? 0), $stickyCids, true)) {
            return $row;
        }

        $config = self::configOf();

        if (null === $config) {
            return $row;
        }

        $stickyHtml = (string) $config->sticky_html;
        $titleSuffix = self::titleSuffix($stickyHtml);

        if ('' !== $titleSuffix) {
            $row['title'] = ($row['title'] ?? '') . ' ' . $titleSuffix;
        }

        $row['sticky'] = 1;
        $row['sticky_html'] = $stickyHtml;

        return $row;
    }

    /**
     * 当前归档是否需要置顶
     *
     * @param Archive $archive 归档组件
     * @return bool
     */
    private static function appliesTo(Archive $archive): bool
    {
        $config = self::configOf();

        if (null === $config) {
            return false;
        }

        if ($archive->is('index')) {
            return true;
        }

        return $archive->is('category') && !empty($config->sticky_cat);
    }

    /**
     * 标题后缀: 只取纯文本 (标题会被主题转义并写进 RSS, 原始 HTML 放在 sticky_html)
     *
     * @param string $stickyHtml
     * @return string
     */
    private static function titleSuffix(string $stickyHtml): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($stickyHtml)));
    }

    /**
     * 读取插件配置 (配置缺失时不要让整个站点报错, 当作没有置顶)
     */
    private static function configOf(): ?Config
    {
        try {
            return Options::alloc()->plugin('Sticky');
        } catch (PluginException) {
            return null;
        }
    }

    /**
     * 解析配置里的 cid 列表
     *
     * @return int[]
     */
    private static function cidsOf(): array
    {
        static $cids = null;

        if (null !== $cids) {
            return $cids;
        }

        $config = self::configOf();
        $raw = null === $config ? '' : trim((string) $config->sticky_cids);

        if ('' === $raw) {
            return $cids = [];
        }

        /** 逗号/空白/全角逗号都当分隔符, 非数字项直接丢弃 */
        $parts = preg_split('/[\s,，]+/u', $raw) ?: [];
        $cids = [];

        foreach ($parts as $part) {
            $cid = (int) trim($part);

            if ($cid > 0 && !in_array($cid, $cids, true)) {
                $cids[] = $cid;
            }
        }

        return $cids;
    }
}
