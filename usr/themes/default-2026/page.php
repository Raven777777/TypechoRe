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

$this->need('header.php');
?>
<main id="main" class="min-w-0 transition-fade">
    <?php if (themeOptionBool('showBreadcrumbs')): ?>
        <?php echo themeBreadcrumbs($this); ?>
    <?php endif; ?>

    <article class="card overflow-hidden" itemscope itemtype="http://schema.org/BlogPosting">
        <header class="border-b border-zinc-200/80 p-6 sm:p-8 dark:border-zinc-800/80">
            <h1 class="text-2xl leading-tight font-bold tracking-tight sm:text-3xl" itemprop="headline"><?php $this->title(); ?></h1>
        </header>

        <?php if ($hasToc): ?>
            <details class="no-print border-b border-zinc-200/80 px-6 py-4 sm:px-8 lg:hidden dark:border-zinc-800/80">
                <summary class="flex cursor-pointer list-none items-center gap-2 text-sm font-medium [&::-webkit-details-marker]:hidden">
                    <?php echo themeIcon('list', 'size-4'); ?><?php _e('页面目录'); ?>
                    <span class="ml-auto text-xs text-zinc-400"><?php echo count($article['toc']), _e(' 节'); ?></span>
                </summary>
                <nav class="js-toc mt-3" aria-label="<?php echo themeEsc(_t('页面目录')); ?>">
                    <?php echo themeTocHtml($article['toc']); ?>
                </nav>
            </details>
        <?php endif; ?>

        <div class="prose prose-zinc prose-theme max-w-none p-6 sm:p-8 dark:prose-invert prose-headings:font-semibold prose-a:font-medium prose-a:no-underline hover:prose-a:underline prose-img:my-6 prose-pre:my-6" itemprop="articleBody">
            <?php echo $article['html']; ?>
        </div>
    </article>

    <?php $this->need('comments.php'); ?>
</main>

<?php if ($hasToc): ?>
    <?php $this->need('toc.php'); ?>
<?php else: ?>
    <?php $this->need('sidebar.php'); ?>
<?php endif; ?>
<?php $this->need('footer.php'); ?>
