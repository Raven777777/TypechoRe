<?php
/**
 * 独立页面模板
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$article = themeArticle($this);
$hasToc = themeOptionBool('showToc') && count($article['toc']) >= 2;

/* 第二列形态 (sidebar|toc|none)。单栏布局没有第二列, 目录改为正文上方的折叠块 */
$pageAside = themeContentAsideKind($this);

$this->need('header.php');
?>
<main id="main" class="min-w-0 transition-fade">
    <?php /* 窄屏没有侧边栏: 分类 / 归档入口放在正文上方, 和列表页位置一致 */ ?>
    <?php themeBrowseStrip(_t('浏览分类'), true); ?>
    <article class="card overflow-hidden" itemscope itemtype="http://schema.org/BlogPosting">
        <header class="border-b border-line p-5 sm:p-7">
            <h1 class="page-title" itemprop="headline"><?php echo themeText(fn () => $this->title()); ?></h1>
        </header>

        <?php if ($hasToc): ?>
            <details class="no-print border-b border-line px-5 py-3 sm:px-7<?php echo 'toc' === $pageAside ? ' lg:hidden' : ''; ?>">
                <summary class="flex cursor-pointer list-none items-center gap-2 text-sm font-medium text-ink-1 [&::-webkit-details-marker]:hidden">
                    <?php echo themeIcon('list', 'size-4'); ?><?php _e('页面目录'); ?>
                    <span class="ml-auto text-[0.8125rem] text-ink-3"><?php echo (int) count($article['toc']), _e(' 节'); ?></span>
                </summary>
                <nav class="js-toc mt-3" aria-label="<?php echo themeEsc(_t('页面目录')); ?>">
                    <?php echo themeTocHtml($article['toc']); ?>
                </nav>
            </details>
        <?php endif; ?>

        <div class="prose prose-zinc prose-theme max-w-none p-5 sm:p-7 dark:prose-invert prose-headings:font-semibold prose-a:font-medium prose-img:my-5 prose-pre:my-5 prose-blockquote:my-5" itemprop="articleBody">
            <?php echo $article['html']; ?>
        </div>
    </article>

    <?php $this->need('comments.php'); ?>
</main>

<?php if ('toc' === $pageAside): ?>
    <?php $this->need('toc.php'); ?>
<?php elseif ('sidebar' === $pageAside): ?>
    <?php $this->need('sidebar.php'); ?>
<?php endif; ?>
<?php $this->need('footer.php'); ?>
