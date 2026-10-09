<?php
/**
 * 文章目录 (右侧栏, 仅在标题数量足够时由 post.php / page.php 载入)
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$toc = themeArticle($this)['toc'];

if (count($toc) < 2) {
    return;
}
?>
<aside class="mt-10 min-w-0 lg:mt-0" aria-label="<?php echo themeEsc(_t('文章目录')); ?>">
    <div id="toc" class="card no-print p-5 lg:sticky lg:top-20 lg:max-h-[calc(100dvh-6rem)] lg:overflow-y-auto">
        <h2 class="widget-title"><?php echo themeIcon('list', 'size-4'); ?><?php _e('文章目录'); ?></h2>
        <nav class="js-toc" aria-label="<?php echo themeEsc(_t('文章目录')); ?>">
            <?php echo themeTocHtml($toc); ?>
        </nav>
    </div>
</aside>
