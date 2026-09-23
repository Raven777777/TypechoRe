<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

function themeConfig($form)
{
    $logoUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'logoUrl',
        null,
        null,
        _t('站点 LOGO 地址'),
        _t('在这里填入一个图片 URL 地址, 以在网站标题前加上一个 LOGO')
    );

    $form->addInput($logoUrl->addRule('url', _t('请填写一个合法的URL地址')));

    $faviconUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'faviconUrl',
        null,
        null,
        _t('站点图标地址'),
        _t('在这里填入一个图片 URL 地址, 以在浏览器标题栏显示 LOGO')
    );

    $form->addInput($faviconUrl->addRule('url', _t('请填写一个合法的URL地址')));

    $sidebarBlock = new \Typecho\Widget\Helper\Form\Element\Checkbox(
        'sidebarBlock',
        [
            'ShowRecentPosts'    => _t('显示最新文章'),
            'ShowRecentComments' => _t('显示最近回复'),
            'ShowCategory'       => _t('显示分类'),
            'ShowArchive'        => _t('显示归档'),
            'ShowOther'          => _t('显示其它杂项')
        ],
        ['ShowRecentPosts', 'ShowRecentComments', 'ShowCategory', 'ShowArchive', 'ShowOther'],
        _t('侧边栏显示')
    );

    $form->addInput($sidebarBlock->multiMode());

    /** 标签云 */
    $showTagCloud = new \Typecho\Widget\Helper\Form\Element\Checkbox(
        'showTagCloud',
        ['1' => _t('在侧边栏显示标签云')],
        [],
        _t('标签云')
    );
    $form->addInput($showTagCloud->multiMode());

    $tagCloudLimit = new \Typecho\Widget\Helper\Form\Element\Number(
        'tagCloudLimit',
        null,
        0,
        _t('显示数量'),
        _t('最多显示多少个标签，0 表示全部')
    );
    $tagCloudLimit->setAttribute('style', 'float:left;width:33.33%;box-sizing:border-box;padding-right:12px;');
    $form->addInput($tagCloudLimit);

    $tagCloudMinSize = new \Typecho\Widget\Helper\Form\Element\Number(
        'tagCloudMinSize',
        null,
        13,
        _t('最小字号'),
        _t('单位 px')
    );
    $tagCloudMinSize->setAttribute('style', 'float:left;width:33.33%;box-sizing:border-box;padding-right:12px;');
    $form->addInput($tagCloudMinSize);

    $tagCloudMaxSize = new \Typecho\Widget\Helper\Form\Element\Number(
        'tagCloudMaxSize',
        null,
        24,
        _t('最大字号'),
        _t('单位 px，文章数越多字号越大')
        . '<style>ul[id^="typecho-option-item-tagCloudLimit-"],'
        . 'ul[id^="typecho-option-item-tagCloudMinSize-"],'
        . 'ul[id^="typecho-option-item-tagCloudMaxSize-"]{margin-top:0;margin-bottom:0;}'
        . '.typecho-option-submit{clear:both;}</style>'
    );
    $tagCloudMaxSize->setAttribute('style', 'float:left;width:33.33%;box-sizing:border-box;padding-right:12px;');
    $form->addInput($tagCloudMaxSize);
}

function postMeta(
    \Widget\Archive $archive,
    string $metaType = 'archive'
)
{
    $titleTag = $metaType == 'archive' ? 'h2' : 'h1';
?>
    <<?php echo $titleTag ?> class="post-title" itemprop="name headline">
        <a itemprop="url"
           href="<?php $archive->permalink() ?>"><?php $archive->title() ?></a>
    </<?php echo $titleTag ?>>
    <?php if ($metaType != 'page'): ?>
        <ul class="post-meta">
            <li itemprop="author" itemscope itemtype="http://schema.org/Person">
                <?php _e('作者'); ?>: <a itemprop="name"
                                       href="<?php $archive->author->permalink(); ?>"
                                       rel="author"><?php $archive->author(); ?></a>
            </li>
            <li><?php _e('时间'); ?>:
                <time datetime="<?php $archive->date('c'); ?>" itemprop="datePublished"><?php $archive->date(); ?></time>
            </li>
            <li><?php _e('分类'); ?>: <?php $archive->category(','); ?></li>
            <?php if ($metaType == 'archive'): ?>
                <li itemprop="interactionCount">
                    <a itemprop="discussionUrl"
                       href="<?php $archive->permalink() ?>#comments"><?php $archive->commentsNum(_t('暂无评论'), _t('1 条评论'), _t('%d 条评论')); ?></a>
                </li>
            <?php endif; ?>
        </ul>
    <?php endif; ?>
<?php
}

/**
 * 输出侧边栏标签云
 *
 * @param \Widget\Options $options
 */
function tagCloudRender(\Widget\Options $options)
{
    if (empty($options->showTagCloud)) {
        return;
    }

    $limit = max(0, intval($options->tagCloudLimit));

    $params = 'sort=mid&desc=0&ignoreZeroCount=1';
    if ($limit > 0) {
        $params .= '&limit=' . $limit;
    }

    $tags = \Widget\Metas\Tag\Cloud::alloc($params)->toArray(['name', 'count', 'permalink']);
    if (empty($tags)) {
        return;
    }

    $minSize = max(8, intval($options->tagCloudMinSize) ?: 13);
    $maxSize = max($minSize, intval($options->tagCloudMaxSize) ?: 24);

    /** 用对数压缩文章数差异，避免热门标签字号过大 */
    $weights = array_map(function ($count) {
        return log(1 + max(0, (int) $count));
    }, array_column($tags, 'count'));

    $minWeight = min($weights);
    $maxWeight = max($weights);
    $range = $maxWeight - $minWeight;

    $items = [];
    foreach ($tags as $index => $tag) {
        $ratio = $range > 0 ? pow(($weights[$index] - $minWeight) / $range, 0.85) : 0.5;
        $size = round($minSize + ($maxSize - $minSize) * $ratio, 1);
        $name = htmlspecialchars($tag['name'], ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($tag['permalink'], ENT_QUOTES, 'UTF-8');

        $items[] = '<a href="' . $url . '" draggable="false" style="font-size:' . $size . 'px" title="'
            . $name . '（' . intval($tag['count']) . ' 篇文章）">' . $name . '</a>';
    }

    $itemsJson = json_encode(
        $items,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if (false === $itemsJson) {
        return;
    }

    /** 标签集合指纹，用于跨页面恢复标签云状态时校验是否同一批标签 */
    $signature = md5(implode('|', array_map(function ($tag) {
        return $tag['name'] . ':' . $tag['count'];
    }, $tags)));

    $jsUrl = $options->themeUrl('js/TagCloud.min.js', $options->theme);
    $wrapId = 'theme-tagcloud-wrap';
    ?>
    <section class="widget tagcloud-widget">
        <h3 class="widget-title"><?php _e('标签云'); ?></h3>
        <div class="tagcloud-wrap" id="<?php echo $wrapId; ?>"></div>
    </section>
    <script type="text/javascript" src="<?php echo htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script type="text/javascript">
        (function () {
            var wrap = document.getElementById('<?php echo $wrapId; ?>');
            var items = <?php echo $itemsJson; ?>;
            var signature = <?php echo json_encode($signature); ?>;
            var STORAGE_KEY = 'typecho-tagcloud-state';
            var instance = null;

            if (!wrap || !items.length || !window.TagCloud) {
                return;
            }

            function readState() {
                try {
                    var raw = window.sessionStorage.getItem(STORAGE_KEY);
                    if (!raw) {
                        return null;
                    }
                    var state = JSON.parse(raw);
                    if (!state || state.signature !== signature) {
                        return null;
                    }
                    if (!state.items || state.items.length !== items.length) {
                        return null;
                    }
                    return state;
                } catch (e) {
                    return null;
                }
            }

            function writeState() {
                if (!instance || !instance.items) {
                    return;
                }
                var state = {
                    signature: signature,
                    items: instance.items.map(function (item) {
                        return [item.x, item.y, item.z];
                    }),
                    mouseX: instance.mouseX,
                    mouseY: instance.mouseY
                };
                try {
                    window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
                } catch (e) {
                }
            }

            function restore(state) {
                if (!state) {
                    return;
                }
                instance.items.forEach(function (item, i) {
                    var pos = state.items[i];
                    if (!pos) {
                        return;
                    }
                    item.x = pos[0];
                    item.y = pos[1];
                    item.z = pos[2];
                });
                if (typeof state.mouseX === 'number') {
                    instance.mouseX = state.mouseX;
                }
                if (typeof state.mouseY === 'number') {
                    instance.mouseY = state.mouseY;
                }
            }

            function build() {
                var w = wrap.clientWidth;
                var h = wrap.clientHeight;
                var radius = Math.max(90, Math.min(260, Math.floor(Math.min(w, h) / 1.8)));

                /** 半径未变化时无需重建，避免重复注册动画帧与鼠标事件 */
                if (instance && instance.radius === radius) {
                    return;
                }

                if (instance) {
                    writeState();
                    if (instance.pause) {
                        instance.pause();
                    }
                    if (instance.destroy) {
                        instance.destroy();
                    }
                }

                instance = window.TagCloud('#<?php echo $wrapId; ?>', items, {
                    radius: radius,
                    maxSpeed: 'slow',
                    initSpeed: 'slow',
                    direction: 135,
                    keep: true,
                    useHTML: true,
                    useContainerInlineStyles: false,
                    containerClass: 'tagcloud',
                    itemClass: 'tagcloud--item'
                });

                restore(readState());
                if (instance && instance._next) {
                    instance._next();
                }
            }

            build();

            window.setInterval(writeState, 1000);
            window.addEventListener('pagehide', writeState);
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'hidden') {
                    writeState();
                }
            });

            var timer = null;
            window.addEventListener('resize', function () {
                clearTimeout(timer);
                timer = setTimeout(build, 200);
            });
        })();
    </script>
    <?php
}
