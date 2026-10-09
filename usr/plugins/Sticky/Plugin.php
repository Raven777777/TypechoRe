<?php

namespace TypechoPlugin\Sticky;

use Typecho\Db;
use Typecho\Db\Query;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Radio;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Textarea;
use Widget\Archive;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 文章置顶
 *
 * Mod by <a href="http://doufu.ru">Ryan</a>
 *
 * @package Sticky
 * @author Ryan, Willin Kan
 * @version 1.0.1
 * @update 2017.07.32
 * @link http://kan.willin.org/typecho/
 */
class Plugin implements PluginInterface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     */
    #[\Override]
    public static function activate()
    {
        \Typecho\Plugin::factory(Archive::class)->indexHandle = [self::class, 'sticky'];
        \Typecho\Plugin::factory(Archive::class)->categoryHandle = [self::class, 'stickyC'];
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
            '这里的代码会自动插入置顶文章的标题后'
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
     * @param Form $form
     * @return void
     */
    #[\Override]
    public static function personalConfig(Form $form)
    {
    }

    /**
     * 选取置顶文章 (首页)
     *
     * @access public
     * @param Archive $archive 归档组件
     * @param Query $select 查询对象
     * @return void
     */
    public static function sticky($archive, $select)
    {
        $config = self::configOf();
        $stickyCids = self::cids($config);

        if (empty($stickyCids)) {
            return;
        }

        $db = Db::get();
        $page = (int) $archive->request->get('page', 1);

        foreach ($stickyCids as $cid) {
            $stickyPost = $cid ? $db->fetchRow($archive->select()->where('cid = ?', $cid)) : null;

            if ($stickyPost) {
                if (1 == $page) {
                    // 首页 page.1 才会有置顶文章
                    $stickyPost['title'] .= (string) $config->sticky_html;
                    $archive->push($stickyPost);
                }

                // 使文章不重复
                $select->where('table.contents.cid != ?', $cid);
            }
        }
    }

    /**
     * 选取置顶文章 (分类页)
     *
     * @access public
     * @param Archive $archive 归档组件
     * @param Query $select 查询对象
     * @return void
     */
    public static function stickyC($archive, $select)
    {
        $config = self::configOf();

        if (!$config->sticky_cat) {
            return;
        }

        self::sticky($archive, $select);
    }

    /**
     * 读取插件配置
     */
    private static function configOf(): \Typecho\Config
    {
        return Options::alloc()->plugin('Sticky');
    }

    /**
     * 解析配置里的 cid 列表
     *
     * @param \Typecho\Config $config
     * @return array<int, string>
     */
    private static function cids(\Typecho\Config $config): array
    {
        $raw = trim((string) $config->sticky_cids);

        if ('' === $raw) {
            return [];
        }

        return array_values(array_filter(explode(',', strtr($raw, ' ', ',')), static fn($cid) => '' !== trim($cid)));
    }
}
