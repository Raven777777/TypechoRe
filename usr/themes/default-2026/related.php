<?php
/**
 * 相关文章 (按标签关联, 最多 3 篇)
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$related = $this->related(3);

if (!$related->have()) {
    return;
}
?>
<section class="mt-10">
    <h2 class="widget-title"><?php echo themeIcon('list', 'size-4'); ?><?php _e('相关文章'); ?></h2>
    <div class="grid gap-4 sm:grid-cols-3">
        <?php while ($related->next()): ?>
            <a class="card card-hover flex h-full flex-col gap-2 p-4" href="<?php $related->permalink(); ?>">
                <span class="text-sm leading-snug font-medium"><?php $related->title(); ?></span>
                <time class="meta-text mt-auto" datetime="<?php $related->date('c'); ?>">
                    <?php echo themeIcon('calendar', 'size-3.5'); ?><?php $related->date('Y-m-d'); ?>
                </time>
            </a>
        <?php endwhile; ?>
    </div>
</section>
