<?php
/**
 * 侧边栏 (可在主题设置里选择内容; 单栏布局下不会输出)
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/** 单栏布局不输出侧边栏 */
if ('full' === themeOption('layout', 'sidebar')) {
    return;
}

/** 侧边栏所有区块都关闭时不必输出空骨架 */
$hasBlock = false;

foreach (['ShowRecentPosts', 'ShowRecentComments', 'ShowCategory', 'ShowArchive', 'ShowTagCloud', 'ShowOther'] as $block) {
    $hasBlock = $hasBlock || themeHasBlock($block);
}

if (!$hasBlock) {
    return;
}
?>
<!-- .page-aside 见 toc.php: 切页时 app.js 会整列同步这一块 (侧边栏 / 文章目录互斥) -->
<aside id="sidebar" class="page-aside mt-10 min-w-0 space-y-6 lg:mt-0" data-aside="sidebar" aria-label="<?php echo themeEsc(_t('侧边栏')); ?>">
    <?php if (themeHasBlock('ShowRecentPosts')): ?>
        <?php /* 条数跟着后台走: 不传 pageSize 时, 内核 Recent 默认就用
                 「设置 → 阅读 → 每页文章数目」(postsListSize)。写死 pageSize 会让后台改了不生效。 */ ?>
        <?php $recent = \Widget\Contents\Post\Recent::alloc(); ?>
        <?php if ($recent->have()): ?>
            <section class="card p-5">
                <h2 class="widget-title"><?php echo themeIcon('clock', 'size-4'); ?><?php _e('最新文章'); ?></h2>
                <ul class="widget-list">
                    <?php while ($recent->next()): ?>
                        <li class="truncate">
                            <a href="<?php $recent->permalink(); ?>" title="<?php echo themeText(fn () => $recent->title()); ?>"><?php echo themeText(fn () => $recent->title()); ?></a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (themeHasBlock('ShowRecentComments')): ?>
        <?php /* 条数跟着后台走: 「设置 → 讨论 → 评论列表数目」(commentsListSize),
                 也是内核 Comments\Recent 不传 pageSize 时的默认值。以前写死 pageSize=5
                 所以后台改了不生效 —— 那是用户报的 bug。 */ ?>
        <?php $recentComments = \Widget\Comments\Recent::alloc(); ?>
        <?php if ($recentComments->have()): ?>
            <section class="card p-5">
                <h2 class="widget-title"><?php echo themeIcon('message-circle', 'size-4'); ?><?php _e('最近回复'); ?></h2>
                <ul class="widget-list">
                    <?php while ($recentComments->next()): ?>
                        <li class="widget-recent-comment truncate">
                            <a href="<?php $recentComments->permalink(); ?>" title="<?php echo themeText(fn () => $recentComments->author(false)); ?>: <?php echo themeText(fn () => $recentComments->excerpt(40, '…')); ?>"><strong><?php echo themeText(fn () => $recentComments->author(false)); ?></strong> · <?php echo themeText(fn () => $recentComments->excerpt(28, '…')); ?></a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (themeHasBlock('ShowCategory')): ?>
        <section class="card p-5">
            <h2 class="widget-title"><?php echo themeIcon('folder', 'size-4'); ?><?php _e('分类'); ?></h2>
            <?php /* 手写循环而不用 listCategories(): 内核把计数模板输出在 <li> 里、
                     <a> 外面, 计数会落到下一行。这里把名字与计数包进同一个链接,
                     顺带把子分类的层级类名 (category-level-N) 也带上。 */ ?>
            <?php $categoryRows = \Widget\Metas\Category\Rows::alloc(); ?>
            <?php if ($categoryRows->have()): ?>
                <ul class="widget-list">
                    <?php while ($categoryRows->next()): ?>
                        <li class="category-level-<?php echo (int) $categoryRows->levels; ?>">
                            <a href="<?php echo themeEsc((string) $categoryRows->permalink); ?>"<?php if ($this->is('category', $categoryRows->slug)): ?> aria-current="page"<?php endif; ?>>
                                <span class="truncate"><?php echo themeEsc((string) $categoryRows->name); ?></span>
                                <span class="widget-count"><?php echo (int) $categoryRows->count; ?></span>
                            </a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (themeHasBlock('ShowTagCloud')): ?>
        <section class="card p-5">
            <h2 class="widget-title"><?php echo themeIcon('tag', 'size-4'); ?><?php _e('标签'); ?></h2>
            <?php themeTagCloud(); ?>
        </section>
    <?php endif; ?>

    <?php if (themeHasBlock('ShowArchive')): ?>
        <section class="card p-5">
            <h2 class="widget-title"><?php echo themeIcon('calendar', 'size-4'); ?><?php _e('归档'); ?></h2>
            <ul class="widget-list">
                <?php \Widget\Contents\Post\Date::alloc('type=month&format=Y-m')
                    ->parse('<li><a href="{permalink}">{date}</a></li>'); ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if (themeHasBlock('ShowOther')): ?>
        <section class="card p-5">
            <h2 class="widget-title"><?php echo themeIcon('rss', 'size-4'); ?><?php _e('其它'); ?></h2>
            <ul class="widget-list">
                <?php if ($this->user->hasLogin()): ?>
                    <li><a href="<?php $this->options->adminUrl(); ?>"><?php _e('进入后台'); ?> (<?php echo themeText(fn () => $this->user->screenName()); ?>)</a></li>
                    <li><a href="<?php $this->options->logoutUrl(); ?>"><?php _e('退出'); ?></a></li>
                <?php else: ?>
                    <li><a href="<?php $this->options->adminUrl('login.php'); ?>"><?php _e('登录'); ?></a></li>
                <?php endif; ?>
                <li><a href="<?php $this->options->feedUrl(); ?>"><?php _e('文章 RSS'); ?></a></li>
                <li><a href="<?php $this->options->commentsFeedUrl(); ?>"><?php _e('评论 RSS'); ?></a></li>
            </ul>
        </section>
    <?php endif; ?>
</aside>
