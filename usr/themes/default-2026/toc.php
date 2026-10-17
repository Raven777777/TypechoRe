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
<!--
    .page-aside 是切页时同步这一列用的稳定标记 (见 app.js 的 syncPageAside):
    文章页有目录、列表页是侧边栏, 两者互斥, 靠这个类让 swup 之后能整列换掉。
    lg:self-stretch 必须留着: #page-wrap 是 lg:items-start 的栅格, 不撑开这一列,
    里面的 lg:sticky 就没有可滚动的行程, 目录会跟着正文滚走 (被导航栏盖住)。
-->
<aside class="page-aside mt-10 min-w-0 lg:mt-0 lg:self-stretch" data-aside="toc" aria-label="<?php echo themeEsc(_t('文章目录')); ?>">
    <div id="toc" class="card no-print p-5 lg:sticky lg:top-[calc(var(--spacing-nav)+1.25rem)] lg:max-h-[calc(100dvh-var(--spacing-nav)-2.5rem)] lg:overflow-y-auto">
        <h2 class="widget-title"><?php echo themeIcon('list', 'size-4'); ?><?php _e('文章目录'); ?></h2>
        <nav class="js-toc" aria-label="<?php echo themeEsc(_t('文章目录')); ?>">
            <?php echo themeTocHtml($toc, true); ?>
        </nav>
    </div>
</aside>
