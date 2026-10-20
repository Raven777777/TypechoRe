<?php
/**
 * 文章列表卡片 (首页 / 归档页循环中的单篇)
 *
 * 整张卡片可点击: 标题链接用 .card-link 铺满卡片, 卡片内其它可交互元素
 * (分类、评论数) 需要 relative z-10 才能保持可点击。
 *
 * 版面: 左图右文。缩略图是这张卡的锚点, 文字列只靠标题的字重与元信息的克制做对比;
 * 完全没有图 (占位图也关掉) 时不留假图位 (列表里留一块灰比没有图更难看), 文字列直接通栏。
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$cardThumb = themeThumbnail($this);
/* 没有真实图片时退回「浏览器端生成的占位图」: 种子就是文章 id, 图在访客浏览器里算出来,
   服务器上既没有文件也没有数据库字段 (开关见 functions.php 的 themePlaceholderEnabled)。
   开关关闭时留 0, 卡片回到无图形态。 */
$cardThumbPlaceholder = null === $cardThumb && themePlaceholderEnabled() ? (int) $this->cid : 0;
/** @var array<int, array{name: string, permalink: string}> $cardCategories */
$cardCategories = $this->categories;
$cardPrimary = $cardCategories[0] ?? null;
/* 目标页第二列的形态提示 (写成链接上的 data-aside-target): 从列表页点进去时, app.js
   在拿到目标文档之前就靠它判断第二列会不会换 —— 没有它只能保守处理, 而卡片上的分类
   链接去的是列表页, 形态与文章页不同, 所以两种提示分别算。 */
$cardAside = themeContentAsideKind($this);
$listAside = themeListAsideKind();
?>
<article class="card card-hover group relative flex flex-col overflow-hidden sm:flex-row" itemscope itemtype="http://schema.org/BlogPosting">
    <?php if (null !== $cardThumb || 0 < $cardThumbPlaceholder): ?>
        <?php /* 缩略图: 桌面端定宽 14rem、最小高度 11rem; 卡片比图高时图片被拉伸填满整列,
           不会在缩略图下面空出一条白边。窄屏是一张通栏横幅, 不跟比例走 (4:3 通栏会高得离谱)。
           图片用 absolute inset-0 铺满: flex 容器里百分比高度靠父元素定高, 而父元素高度
           由内容撑开时, 图片的固有高度会反过来把卡片顶成很高 (竖图尤其明显) ——
           绝对定位把图片摘出文档流, 高度就完全由这一列决定, object-cover 负责缩放填满。
           浅色缩略图贴在白卡片上会「消失」, 由 .card-media::after 的内描边框住。 */ ?>
        <div class="card-media h-44 w-full shrink-0 bg-surface-2 sm:h-auto sm:min-h-[11rem] sm:w-56"<?php if (0 < $cardThumbPlaceholder): ?> data-thumb-placeholder<?php endif; ?>>
            <?php if (null !== $cardThumb): ?>
                <img class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" src="<?php echo themeEsc($cardThumb['url']); ?>" alt="<?php echo themeEsc($cardThumb['alt']); ?>" width="640" height="440" loading="lazy" decoding="async">
            <?php else: ?>
                <?php /* 占位图: 模板只留一枚空 img + 种子, dist/app.js 在首屏与每次无刷新切页后
                   把它填成 data URI (见 src/js/thumb-placeholder.js)。hidden 由 preflight 的
                   [hidden]{display:none!important} 兜住, JS 没跑到之前不会露出半个图框;
                   alt 留空 —— 这是装饰图, 读屏不必念。宽高按生成器的 400×300 声明。 */ ?>
                <img class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" alt="" width="400" height="300" loading="lazy" decoding="async" data-thumb-seed="<?php echo (int) $cardThumbPlaceholder; ?>" hidden>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="flex min-w-0 flex-1 flex-col gap-2.5 p-5 sm:gap-3 sm:p-6">
        <h2 class="card-title" itemprop="headline">
            <a class="card-link" data-aside-target="<?php echo themeEsc($cardAside); ?>" href="<?php $this->permalink(); ?>" itemprop="url"><?php echo themeText(fn () => $this->title()); ?></a>
        </h2>

        <p class="card-excerpt" itemprop="description"><?php echo themeText(fn () => $this->excerpt(themeExcerptLength())); ?></p>

        <?php /* 元信息行: 左侧是「这是什么」(分类 + 时间), 右侧是「有多少人参与」(评论数)。
           作者与阅读时长留给文章页 —— 列表里每张卡都重复一遍同一个作者, 只是噪声。 */ ?>
        <div class="card-foot">
            <?php if (null !== $cardPrimary): ?>
                <a class="chip relative z-10" data-skeleton="list" data-aside-target="<?php echo themeEsc($listAside); ?>" href="<?php echo themeEsc((string) $cardPrimary['permalink']); ?>"><?php echo themeEsc((string) $cardPrimary['name']); ?></a>
            <?php endif; ?>
            <time class="meta-text" datetime="<?php $this->date('c'); ?>" title="<?php $this->date(); ?>"><?php $this->dateWord(); ?></time>
            <a class="meta-text relative z-10 ml-auto transition-colors hover:text-accent-ink" data-aside-target="<?php echo themeEsc($cardAside); ?>" href="<?php $this->permalink(); ?>#comments" aria-label="<?php $this->commentsNum(_t('暂无评论'), _t('1 条评论'), _t('%d 条评论')); ?>">
                <?php echo themeIcon('message-circle', 'size-3.5'); ?>
                <span><?php $this->commentsNum(_t('暂无评论'), _t('1 条评论'), _t('%d 条评论')); ?></span>
            </a>
        </div>
    </div>
</article>
