<?php
/**
 * 文章列表卡片 (首页 / 归档页循环中的单篇)
 *
 * 整张卡片可点击: 标题链接用 .card-link 铺满卡片, 卡片内其它可交互元素
 * (分类、评论数) 需要 relative z-10 才能保持可点击。
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$cardThumb = themeThumbnail($this);
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
    <?php if (null !== $cardThumb): ?>
        <?php /* 缩略图: 桌面端定宽 14rem、最小高度 10.5rem (= 14rem 的 4:3), 再往上跟着卡片
           高度走 —— 卡片比图高时图片被拉伸填满整列, 不会在缩略图下面空出一条白边。
           窄屏是一张通栏横幅, 不跟比例走 (4:3 通栏会高得离谱)。
           图片用 absolute inset-0 铺满: flex 容器里百分比高度靠父元素定高, 而父元素高度
           由内容撑开时, 图片的固有高度会反过来把卡片顶成很高 (竖图尤其明显) ——
           绝对定位把图片摘出文档流, 高度就完全由这一列决定, object-cover 负责缩放填满,
           于是小图被放大填满、大图被裁切, 都不会变形。
           bg-zinc-100 是兜底底色, 图加载失败时不会露出一块死白。 */ ?>
        <div class="relative h-44 w-full shrink-0 overflow-hidden bg-zinc-100 sm:h-auto sm:min-h-[10.5rem] sm:w-56 dark:bg-zinc-800/60">
            <img class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]" src="<?php echo themeEsc($cardThumb['url']); ?>" alt="<?php echo themeEsc($cardThumb['alt']); ?>" loading="lazy" decoding="async">
        </div>
    <?php endif; ?>

    <div class="flex min-w-0 flex-1 flex-col gap-3 p-5">
        <h2 class="text-lg leading-snug font-semibold tracking-tight" itemprop="headline">
            <a class="card-link transition-colors group-hover:text-accent" data-aside-target="<?php echo themeEsc($cardAside); ?>" href="<?php $this->permalink(); ?>" itemprop="url"><?php $this->title(); ?></a>
        </h2>

        <p class="line-clamp-3 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400" itemprop="description">
            <?php $this->excerpt(themeOptionInt('excerptLength', 110)); ?>
        </p>

        <?php /* 元信息行: 分类 + 评论 + 作者 + 发布时间 (与文章页同一顺序) */ ?>
        <div class="mt-auto flex flex-wrap items-center gap-x-4 gap-y-2 pt-1">
            <?php if (null !== $cardPrimary): ?>
                <a class="chip relative z-10" data-skeleton="list" data-aside-target="<?php echo themeEsc($listAside); ?>" href="<?php echo themeEsc((string) $cardPrimary['permalink']); ?>"><?php echo themeEsc((string) $cardPrimary['name']); ?></a>
            <?php endif; ?>
            <a class="meta-text relative z-10 transition-colors hover:text-accent" data-aside-target="<?php echo themeEsc($cardAside); ?>" href="<?php $this->permalink(); ?>#comments">
                <?php echo themeIcon('message-circle', 'size-3.5'); ?>
                <span><?php $this->commentsNum(_t('暂无评论'), _t('1 条评论'), _t('%d 条评论')); ?></span>
            </a>
            <span class="meta-text"><?php echo themeIcon('user', 'size-3.5'); ?><?php $this->author(); ?></span>
            <time class="meta-text" datetime="<?php $this->date('c'); ?>" title="<?php $this->date(); ?>"><?php echo themeIcon('calendar', 'size-3.5'); ?><?php $this->dateWord(); ?></time>
        </div>
    </div>
</article>
