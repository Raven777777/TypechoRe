<?php
/**
 * 通用归档模板: 分类 / 标签 / 作者 / 搜索 / 日期归档
 *
 * 首页以外的列表页都会走这里 (index.php 只负责首页)。
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->need('header.php');

$archiveType = (string) $this->getArchiveType();
$archiveName = trim((string) $this->getArchiveTitle());
$archiveDescription = trim((string) $this->getArchiveDescription());

/** 归档类型 -> 标题前缀 (未知类型直接显示归档名)
 *  注意: 内核 getArchiveType() 只返回 index/archive/category/tag/author/search/date,
 *  曾经写成 archive_year/month/day, 于是日期归档永远拿不到徽标。 */
$archiveLabels = [
    'category' => _t('分类'),
    'tag'      => _t('标签'),
    'author'   => _t('作者'),
    'search'   => _t('搜索'),
    'date'     => _t('归档')
];
$archiveLabel = $archiveLabels[$archiveType] ?? '';
?>
<main id="main" class="min-w-0 transition-fade">
    <header class="mb-6">
        <?php if ('' !== $archiveLabel): ?>
            <span class="text-xs font-medium tracking-[0.14em] text-accent-ink uppercase"><?php echo themeEsc($archiveLabel); ?></span>
        <?php endif; ?>
        <h1 class="page-title mt-1">
            <?php echo themeEsc('' !== $archiveName ? $archiveName : (string) \Widget\Options::alloc()->title); ?>
        </h1>
        <?php if ('' !== $archiveDescription): ?>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-ink-2"><?php echo themeEsc($archiveDescription); ?></p>
        <?php endif; ?>
        <p class="mt-3 text-[0.8125rem] text-ink-3 tabular-nums">
            <?php echo themeEsc(sprintf(_t('共 %d 篇内容'), $this->getTotal())); ?>
            <?php if ($this->getCurrentPage() > 1): ?>
                · <?php echo themeEsc(sprintf(_t('第 %d 页'), $this->getCurrentPage())); ?>
            <?php endif; ?>
        </p>
    </header>

    <?php if (themeSkeletonSidebar()): ?>
        <nav class="browse-strip mb-5" aria-label="<?php echo themeEsc(_t('浏览分类')); ?>">
            <?php \Widget\Metas\Category\Rows::alloc()->to($stripCategories); ?>
            <?php while ($stripCategories->next()): ?>
                <a class="chip shrink-0<?php echo (string) $stripCategories->slug === (string) $this->getArchiveSlug() ? ' is-active' : ''; ?>" href="<?php echo themeEsc((string) $stripCategories->permalink); ?>"><?php echo themeEsc((string) $stripCategories->name); ?><span class="widget-count"><?php echo (int) $stripCategories->count; ?></span></a>
            <?php endwhile; ?>
        </nav>
    <?php endif; ?>

    <?php if ($this->have()): ?>
        <div class="space-y-6">
            <?php while ($this->next()): ?>
                <?php $this->need('post-card.php'); ?>
            <?php endwhile; ?>
        </div>

        <?php themePageNav($this); ?>
    <?php else: ?>
        <div class="card p-10 text-center">
            <p class="text-lg font-medium text-ink-1"><?php _e('没有找到内容'); ?></p>
            <p class="mt-2 text-sm text-ink-2"><?php _e('换个关键字或返回首页看看。'); ?></p>
            <a class="btn btn-primary mt-5" href="<?php $this->options->siteUrl(); ?>"><?php _e('返回首页'); ?></a>
        </div>
    <?php endif; ?>
</main>

<?php $this->need('sidebar.php'); ?>
<?php $this->need('footer.php'); ?>
