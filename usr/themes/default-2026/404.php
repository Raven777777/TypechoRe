<?php
/**
 * 404 页面
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->need('header.php');
?>
<main id="main" class="mx-auto min-w-0 max-w-2xl transition-fade">
    <div class="card p-8 text-center sm:p-12">
        <p class="text-5xl font-bold tracking-tight text-accent">404</p>
        <h1 class="mt-4 text-2xl font-bold tracking-tight"><?php _e('页面没找到'); ?></h1>
        <p class="mt-3 text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">
            <?php _e('这个地址可能已经被移动或删除了, 试试搜索站内其它内容。'); ?>
        </p>

        <form class="mx-auto mt-6 flex max-w-sm items-center gap-2" method="post" action="<?php $this->options->siteUrl(); ?>" role="search">
            <label class="sr-only" for="search-404"><?php _e('搜索关键字'); ?></label>
            <input class="field" id="search-404" name="s" type="search" placeholder="<?php echo themeEsc(_t('输入关键字…')); ?>" autofocus>
            <button class="btn btn-primary shrink-0" type="submit"><?php echo themeIcon('search', 'size-4'); ?><?php _e('搜索'); ?></button>
        </form>

        <a class="btn btn-ghost mt-6" href="<?php $this->options->siteUrl(); ?>"><?php echo themeIcon('arrow-left', 'size-4'); ?><?php _e('返回首页'); ?></a>
    </div>

    <?php $recent = \Widget\Contents\Post\Recent::alloc('pageSize=5'); ?>
    <?php if ($recent->have()): ?>
        <section class="card mt-6 p-6">
            <h2 class="widget-title"><?php echo themeIcon('clock', 'size-4'); ?><?php _e('最新文章'); ?></h2>
            <ul class="archive-list">
                <?php while ($recent->next()): ?>
                    <li>
                        <a href="<?php $recent->permalink(); ?>"><?php $recent->title(); ?></a>
                        <time datetime="<?php $recent->date('c'); ?>"><?php $recent->date('Y-m-d'); ?></time>
                    </li>
                <?php endwhile; ?>
            </ul>
        </section>
    <?php endif; ?>
</main>

<?php $this->need('footer.php'); ?>
