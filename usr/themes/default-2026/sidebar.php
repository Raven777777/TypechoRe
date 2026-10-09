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
<aside id="sidebar" class="mt-10 min-w-0 space-y-6 lg:mt-0">
    <?php if (themeHasBlock('ShowRecentPosts')): ?>
        <?php $recent = \Widget\Contents\Post\Recent::alloc(); ?>
        <?php if ($recent->have()): ?>
            <section class="card p-5">
                <h2 class="widget-title"><?php echo themeIcon('clock', 'size-4'); ?><?php _e('最新文章'); ?></h2>
                <ul class="widget-list">
                    <?php while ($recent->next()): ?>
                        <li>
                            <a href="<?php $recent->permalink(); ?>"><?php $recent->title(); ?></a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (themeHasBlock('ShowRecentComments')): ?>
        <?php $recentComments = \Widget\Comments\Recent::alloc(); ?>
        <?php if ($recentComments->have()): ?>
            <section class="card p-5">
                <h2 class="widget-title"><?php echo themeIcon('message-circle', 'size-4'); ?><?php _e('最近回复'); ?></h2>
                <ul class="widget-list">
                    <?php while ($recentComments->next()): ?>
                        <li>
                            <a href="<?php $recentComments->permalink(); ?>"><strong><?php $recentComments->author(false); ?></strong>: <?php $recentComments->excerpt(24, '…'); ?></a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (themeHasBlock('ShowCategory')): ?>
        <section class="card p-5">
            <h2 class="widget-title"><?php echo themeIcon('folder', 'size-4'); ?><?php _e('分类'); ?></h2>
            <?php \Widget\Metas\Category\Rows::alloc()->listCategories('wrapTag=ul&wrapClass=widget-list'); ?>
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
                    <li><a href="<?php $this->options->adminUrl(); ?>"><?php _e('进入后台'); ?> (<?php $this->user->screenName(); ?>)</a></li>
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
