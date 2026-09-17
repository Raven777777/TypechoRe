<?php

namespace TypechoPlugin\TagToText;

use Typecho\Db;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Checkbox;
use Typecho\Widget\Helper\Form\Element\Number;
use Typecho\Widget\Helper\Form\Element\Radio;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 标签速选 TagToText
 *
 * 在后台「撰写文章 / 创建页面」的标签输入框下方增加一个可搜索的标签面板：
 * 自动列出站点已有的标签（可显示关联文章数），点击即选中或取消，支持关键词过滤、
 * 回车快速选中、一键清空，并与系统自带的 tokenInput 标签控件双向同步。
 * 排序方式、载入数量、是否显示文章数、是否默认展开等均可在插件设置中调整。
 * 不用再逐个手打标签，也避免打错字生成重复标签。
 *
 * @package TagToText
 * @author 井水玉藻
 * @version 1.1.0
 * @link https://love4z.cn/
 */
class Plugin implements PluginInterface
{
    /**
     * 插件目录名, 同时用于读取插件配置
     */
    public const NAME = 'TagToText';

    /**
     * 激活插件方法, 如果激活失败, 直接抛出异常
     */
    public static function activate()
    {
        \Typecho\Plugin::factory('admin/write-post.php')->bottom = [__CLASS__, 'render'];
        \Typecho\Plugin::factory('admin/write-page.php')->bottom = [__CLASS__, 'render'];
    }

    /**
     * 禁用插件方法, 如果禁用失败, 直接抛出异常
     */
    public static function deactivate()
    {
    }

    /**
     * 获取插件配置面板
     *
     * @param Form $form 配置面板
     */
    public static function config(Form $form)
    {
        $sort = new Radio('sort', [
            'count' => _t('按文章数量（由多到少）'),
            'name'  => _t('按标签名称'),
            'mid'   => _t('按创建顺序（由新到旧）')
        ], 'count', _t('标签排序方式'));
        $form->addInput($sort);

        $limit = new Number(
            'limit',
            null,
            100,
            _t('载入的标签数量'),
            _t('填写 0 表示载入全部标签, 标签非常多时建议设置一个上限以保证后台加载速度')
        );
        $form->addInput($limit);

        /** 注意: 多选框的默认值必须是数组, 不能是 null */
        $ignoreZeroCount = new Checkbox(
            'ignoreZeroCount',
            ['1' => _t('隐藏没有被任何文章使用的标签')],
            [],
            _t('过滤规则')
        );
        $form->addInput($ignoreZeroCount);

        $showCount = new Checkbox(
            'showCount',
            ['1' => _t('在标签后面显示关联的文章数量')],
            ['1'],
            _t('显示设置')
        );
        $form->addInput($showCount);

        $hotStyle = new Checkbox(
            'hotStyle',
            ['1' => _t('按照热度（文章数量）显示不同的字号')],
            [],
            _t('显示设置')
        );
        $form->addInput($hotStyle);

        $defaultOpen = new Checkbox(
            'defaultOpen',
            ['1' => _t('进入撰写页面时直接展开标签面板')],
            [],
            _t('面板行为')
        );
        $form->addInput($defaultOpen);
    }

    /**
     * 自行接管配置的写入
     *
     * 本分支的 Typecho\Request::get() 要求提交值的类型与默认值一致, 否则会丢弃提交值;
     * 而 Form::getParams() 的默认值又由配置项当前值推导, 历史数据里多选框可能是 null,
     * 于是「勾选 -> 提交数组 -> 被判为类型不符 -> 写回默认值」导致勾选永远保存不了。
     * 这里直接读取原始提交数据自行入库, 避免该问题。
     *
     * @param array $settings 表单提交的默认值集合
     * @param bool $isInit 是否为插件启用时的初始化写入
     */
    public static function configHandle(array $settings, bool $isInit)
    {
        if ($isInit) {
            \Widget\Plugins\Edit::configPlugin(self::NAME, $settings);
            return;
        }

        $request = \Typecho\Request::getInstance();
        $sort = strval($request->get('sort', 'count'));

        if (!in_array($sort, ['count', 'name', 'mid'], true)) {
            $sort = 'count';
        }

        $limit = $request->get('limit', '100');

        \Widget\Plugins\Edit::configPlugin(self::NAME, [
            'sort'            => $sort,
            'limit'           => (string) (is_numeric($limit) ? max(0, intval($limit)) : 100),
            'ignoreZeroCount' => $request->get('ignoreZeroCount', []),
            'showCount'       => $request->get('showCount', []),
            'hotStyle'        => $request->get('hotStyle', []),
            'defaultOpen'     => $request->get('defaultOpen', [])
        ]);
    }

    /**
     * 个人用户的配置面板
     *
     * @param Form $form
     */
    public static function personalConfig(Form $form)
    {
    }

    /**
     * 在撰写页面输出标签选择面板
     *
     * @param mixed $content 当前编辑的文章或页面
     */
    public static function render($content = null)
    {
        $conf = self::getConfig();
        $tags = self::getTags($conf);

        if (empty($tags)) {
            return;
        }

        $settings = [
            'tags'      => $tags,
            'open'      => !empty($conf['defaultOpen']),
            'showCount' => !empty($conf['showCount']),
            'hotStyle'  => !empty($conf['hotStyle'])
        ];

        $i18n = [
            'open'   => _t('选择标签'),
            'close'  => _t('收起面板'),
            'clear'  => _t('清空'),
            'search' => _t('搜索标签'),
            'empty'  => _t('没有找到匹配的标签'),
            'count'  => _t('已选 %s 个标签')
        ];

        $flag = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

        echo '<style type="text/css">' . self::getCss() . '</style>';

        $script = str_replace(
            ['__SETTINGS__', '__I18N__'],
            [json_encode($settings, $flag), json_encode($i18n, $flag)],
            self::getJs()
        );

        echo '<script type="text/javascript">' . $script . '</script>';
    }

    /**
     * 读取插件配置, 未配置的项使用默认值
     *
     * @return array
     */
    private static function getConfig(): array
    {
        $conf = [
            'sort'            => 'count',
            'limit'           => 100,
            'ignoreZeroCount' => 0,
            'showCount'       => 1,
            'hotStyle'        => 0,
            'defaultOpen'     => 0
        ];

        $config = null;

        try {
            $config = Options::alloc()->plugin(self::NAME);
        } catch (\Throwable $e) {
            $config = null;
        }

        foreach (array_keys($conf) as $key) {
            $value = $config ? $config->{$key} : null;

            if (is_array($value)) {
                /** 多选框的值以数组形式保存, 空数组表示未勾选 */
                $value = empty($value) ? 0 : 1;
            }

            if (null !== $value && '' !== $value) {
                $conf[$key] = $value;
            }
        }

        $conf['limit'] = intval($conf['limit']);

        if (!in_array($conf['sort'], ['count', 'name', 'mid'], true)) {
            $conf['sort'] = 'count';
        }

        return $conf;
    }

    /**
     * 取出站点已有的标签
     *
     * @param array $conf 插件配置
     * @return array
     */
    private static function getTags(array $conf): array
    {
        $db = Db::get();
        $select = $db->select('mid', 'name', 'count')
            ->from('table.metas')
            ->where('type = ?', 'tag');

        if (!empty($conf['ignoreZeroCount'])) {
            $select->where('table.metas.count > ?', 0);
        }

        switch ($conf['sort']) {
            case 'name':
                $select->order('table.metas.name', Db::SORT_ASC);
                break;
            case 'mid':
                $select->order('table.metas.mid', Db::SORT_DESC);
                break;
            default:
                $select->order('table.metas.count', Db::SORT_DESC)
                    ->order('table.metas.mid', Db::SORT_DESC);
                break;
        }

        if (intval($conf['limit']) > 0) {
            $select->limit(intval($conf['limit']));
        }

        $tags = [];

        foreach ($db->fetchAll($select) as $row) {
            $name = trim($row['name']);

            if ('' === $name) {
                continue;
            }

            $tags[] = [
                'name'  => $name,
                'count' => intval($row['count'])
            ];
        }

        return $tags;
    }

    /**
     * 面板样式
     *
     * @return string
     */
    private static function getCss(): string
    {
        return <<<'CSS'
.tt-wrap { margin-top: 3px; }
.tt-actions { margin-bottom: 5px; }
.tt-actions .btn { margin-right: 5px; }
.tt-selected { color: #999; font-size: 12px; }
.tt-panel { display: none; padding: 8px; border: 1px solid #D9D9D6; border-radius: 3px; background: #FFF; }
.tt-panel.tt-open { display: block; }
.tt-search { display: block; width: 100%; margin-bottom: 8px; -webkit-box-sizing: border-box; -moz-box-sizing: border-box; box-sizing: border-box; }
.tt-list { max-height: 180px; overflow-x: hidden; overflow-y: auto; }
.tt-item { display: inline-block; margin: 0 5px 5px 0; padding: 2px 8px; border: 1px solid #D9D9D6; border-radius: 10px; background: #F7F7F5; color: #666; font-size: 12px; line-height: 18px; cursor: pointer; }
.tt-item:hover { border-color: #467B96; color: #467B96; }
.tt-item.tt-active { border-color: #467B96; background: #467B96; color: #FFF; }
.tt-item .tt-num { margin-left: 4px; font-size: 11px; opacity: .7; }
.tt-empty { display: none; margin: 0; padding: 2px 0; color: #999; font-size: 12px; }
.tt-empty.tt-show { display: block; }
CSS;
    }

    /**
     * 面板脚本
     *
     * @return string
     */
    private static function getJs(): string
    {
        return <<<'JS'
(function () {
    jQuery(function () {
        var cfg = __SETTINGS__, lang = __I18N__,
            input = jQuery('#tags'), tags = cfg.tags || [], i, tag;

        if (input.length < 1 || tags.length < 1) {
            return;
        }

        /** 后台自带的 tokenInput 会把 #tags 隐藏起来, 两种模式下的数据读写方式不一样 */
        var useToken = !!input.data('tokenInputObject'), maxCount = 1,
            host = input.closest('section');

        if (host.length < 1) {
            host = input.parent();
        }

        for (i = 0; i < tags.length; i ++) {
            if (tags[i].count > maxCount) {
                maxCount = tags[i].count;
            }
        }

        var wrap = jQuery('<div class="tt-wrap"></div>').appendTo(host),
            actions = jQuery('<div class="tt-actions"></div>').appendTo(wrap),
            toggle = jQuery('<button type="button" class="btn btn-xs"></button>').appendTo(actions),
            clear = jQuery('<button type="button" class="btn btn-xs"></button>').text(lang.clear).appendTo(actions),
            state = jQuery('<span class="tt-selected"></span>').appendTo(actions),
            panel = jQuery('<div class="tt-panel"></div>').appendTo(wrap),
            search = jQuery('<input type="text" class="text-s tt-search" autocomplete="off" />')
                .attr('placeholder', lang.search).appendTo(panel),
            list = jQuery('<div class="tt-list"></div>').appendTo(panel),
            empty = jQuery('<p class="tt-empty"></p>').text(lang.empty).appendTo(panel);

        for (i = 0; i < tags.length; i ++) {
            tag = tags[i];
            var item = jQuery('<button type="button" class="tt-item"></button>')
                .attr('data-name', tag.name)
                .append(jQuery('<span class="tt-name"></span>').text(tag.name))
                .appendTo(list);

            if (cfg.showCount) {
                item.append(jQuery('<span class="tt-num"></span>').text(tag.count));
            }

            if (cfg.hotStyle && maxCount > 0) {
                item.css('font-size', (12 + Math.round(6 * tag.count / maxCount)) + 'px');
            }
        }

        /** 当前已填写的标签 */
        function current() {
            var items = (input.val() || '').split(/[,，]/), result = [], j, name;

            for (j = 0; j < items.length; j ++) {
                name = items[j].replace(/^\s+|\s+$/g, '');

                if (name) {
                    result.push(name);
                }
            }

            return result;
        }

        function has(name) {
            return jQuery.inArray(name, current()) > -1;
        }

        function setOpen(open) {
            panel.toggleClass('tt-open', !!open);
            toggle.text(open ? lang.close : lang.open);

            if (open) {
                search.focus();
            }
        }

        /** 高亮面板中已经选中的标签 */
        function sync() {
            var items = current();

            list.children('.tt-item').each(function () {
                var el = jQuery(this);
                el.toggleClass('tt-active', jQuery.inArray(el.attr('data-name'), items) > -1);
            });

            state.text(lang.count.replace('%s', items.length));
        }

        function filter() {
            var key = (search.val() || '').toLowerCase(), shown = 0;

            list.children('.tt-item').each(function () {
                var el = jQuery(this),
                    match = !key || el.attr('data-name').toLowerCase().indexOf(key) > -1;

                el.toggle(match);

                if (match) {
                    shown ++;
                }
            });

            empty.toggleClass('tt-show', shown < 1);
        }

        function toggleTag(name) {
            if (useToken) {
                if (has(name)) {
                    input.tokenInput('remove', {id: name, tags: name});
                } else {
                    input.tokenInput('add', {id: name, tags: name});
                }
            } else {
                var items = current(), next = [], j, found = false;

                for (j = 0; j < items.length; j ++) {
                    if (items[j] === name) {
                        found = true;
                        continue;
                    }

                    next.push(items[j]);
                }

                if (!found) {
                    next.push(name);
                }

                input.val(next.join(','));
            }

            input.trigger('change');
            sync();
        }

        toggle.click(function () {
            setOpen(!panel.hasClass('tt-open'));
        });

        clear.click(function () {
            if (useToken) {
                input.tokenInput('clear');
            } else {
                input.val('');
            }

            input.trigger('change');
            sync();
        });

        list.on('click', '.tt-item', function (e) {
            e.preventDefault();
            toggleTag(jQuery(this).attr('data-name'));
        });

        search.on('input propertychange', filter).on('keydown', function (e) {
            if (13 === e.keyCode) {
                e.preventDefault();

                var first = list.children('.tt-item:visible').first();

                if (first.length > 0) {
                    toggleTag(first.attr('data-name'));
                }
            } else if (27 === e.keyCode) {
                setOpen(false);
            }
        });

        jQuery(document).on('click', function (e) {
            if (panel.hasClass('tt-open') && jQuery(e.target).closest('.tt-wrap').length < 1) {
                setOpen(false);
            }
        });

        input.on('change input', sync);

        sync();
        filter();
        setOpen(!!cfg.open);
    });
})();
JS;
    }
}
