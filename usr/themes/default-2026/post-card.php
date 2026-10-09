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
?>
<article class="card card-hover group relative flex flex-col overflow-hidden sm:flex-row" itemscope itemtype="http://schema.org/BlogPosting">
    <?php if (null !== $cardThumb): ?>
        <div class="shrink-0 overflow-hidden sm:w-56">
            <img class="h-44 w-full object-cover transition duration-500 group-hover:scale-[1.04] sm:h-full sm:min-h-32" src="<?php echo themeEsc($cardThumb['url']); ?>" alt="<?php echo themeEsc($cardThumb['alt']); ?>" loading="lazy" decoding="async">
        </div>
    <?php endif; ?>

    <div class="flex min-w-0 flex-1 flex-col gap-3 p-5">
        <div class="flex flex-wrap items-center gap-2">
            <?php if (null !== $cardPrimary): ?>
                <a class="chip relative z-10" href="<?php echo themeEsc((string) $cardPrimary['permalink']); ?>"><?php echo themeEsc((string) $cardPrimary['name']); ?></a>
            <?php endif; ?>
            <time class="meta-text" datetime="<?php $this->date('c'); ?>" title="<?php $this->date(); ?>"><?php echo themeIcon('calendar', 'size-3.5'); ?><?php $this->dateWord(); ?></time>
        </div>

        <h2 class="text-lg leading-snug font-semibold tracking-tight" itemprop="headline">
            <a class="card-link transition-colors group-hover:text-accent" href="<?php $this->permalink(); ?>" itemprop="url"><?php $this->title(); ?></a>
        </h2>

        <p class="line-clamp-3 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400" itemprop="description">
            <?php $this->excerpt(themeOptionInt('excerptLength', 110)); ?>
        </p>

        <div class="mt-auto flex flex-wrap items-center gap-4 pt-1">
            <a class="meta-text relative z-10 transition-colors hover:text-accent" href="<?php $this->permalink(); ?>#comments">
                <?php echo themeIcon('message-circle', 'size-3.5'); ?>
                <span><?php $this->commentsNum(_t('暂无评论'), _t('1 条评论'), _t('%d 条评论')); ?></span>
            </a>
            <span class="meta-text"><?php echo themeIcon('user', 'size-3.5'); ?><?php $this->author(); ?></span>
        </div>
    </div>
</article>
