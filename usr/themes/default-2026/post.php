<?php
/**
 * 文章页模板
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$article = themeArticle($this);
$hasToc = themeOptionBool('showToc') && count($article['toc']) >= 2;
$showReadingTime = themeOptionBool('showReadingTime');

/* 第二列形态 (sidebar|toc|none)。单栏布局没有第二列, 目录改为正文上方的折叠块 */
$postAside = themeContentAsideKind($this);

/** @var array<int, array{name: string, permalink: string}> $postCategories */
$postCategories = $this->categories;
$postPrimary = $postCategories[0] ?? null;

/** @var array<int, array{name: string, permalink: string}> $postTags */
$postTags = $this->tags;

$this->need('header.php');
?>
<main id="main" class="min-w-0 transition-fade">
    <article class="card overflow-hidden" itemscope itemtype="http://schema.org/BlogPosting">
        <header class="border-b border-zinc-200/80 p-6 sm:p-8 dark:border-zinc-800/80">
            <h1 class="text-2xl leading-tight font-bold tracking-tight sm:text-3xl" itemprop="headline"><?php $this->title(); ?></h1>

            <?php /* 元信息行: 分类 + 评论 + 作者 + 发布时间 + 预计阅读时间
                     (分类跟在标题下面, 不再压在标题上方) */ ?>
            <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                <?php if (null !== $postPrimary): ?>
                    <a class="chip" href="<?php echo themeEsc((string) $postPrimary['permalink']); ?>"><?php echo themeEsc((string) $postPrimary['name']); ?></a>
                <?php endif; ?>
                <?php if ($this->allow('comment')): ?>
                    <a class="meta-text transition-colors hover:text-accent" href="#comments">
                        <?php echo themeIcon('message-circle', 'size-4'); ?><?php $this->commentsNum(_t('暂无评论'), _t('1 条评论'), _t('%d 条评论')); ?>
                    </a>
                <?php endif; ?>
                <a class="meta-text transition-colors hover:text-accent" href="<?php $this->author->permalink(); ?>" rel="author" itemprop="author">
                    <?php echo themeIcon('user', 'size-4'); ?><?php $this->author(); ?>
                </a>
                <time class="meta-text" datetime="<?php $this->date('c'); ?>" itemprop="datePublished"><?php echo themeIcon('calendar', 'size-4'); ?><?php $this->date(); ?></time>
                <?php if ($showReadingTime): ?>
                    <span class="meta-text"><?php echo themeIcon('clock', 'size-4'); ?><?php echo sprintf(themeEsc(_t('约 %d 分钟')), $article['readingTime']); ?></span>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($hasToc): ?>
            <details class="no-print border-b border-zinc-200/80 px-6 py-4 sm:px-8 dark:border-zinc-800/80<?php echo 'toc' === $postAside ? ' lg:hidden' : ''; ?>">
                <summary class="flex cursor-pointer list-none items-center gap-2 text-sm font-medium [&::-webkit-details-marker]:hidden">
                    <?php echo themeIcon('list', 'size-4'); ?><?php _e('文章目录'); ?>
                    <span class="ml-auto text-xs text-zinc-400"><?php echo count($article['toc']), _e(' 节'); ?></span>
                </summary>
                <nav class="js-toc mt-3" aria-label="<?php echo themeEsc(_t('文章目录')); ?>">
                    <?php echo themeTocHtml($article['toc']); ?>
                </nav>
            </details>
        <?php endif; ?>

        <div class="prose prose-zinc prose-theme max-w-none p-6 sm:p-8 dark:prose-invert prose-headings:font-semibold prose-a:font-medium prose-blockquote:font-normal prose-img:my-6 prose-pre:my-6" itemprop="articleBody">
            <?php echo $article['html']; ?>
        </div>

        <?php if ([] !== $postTags): ?>
            <footer class="flex flex-wrap items-center gap-2 border-t border-zinc-200/80 p-6 sm:px-8 dark:border-zinc-800/80">
                <?php foreach ($postTags as $tag): ?>
                    <a class="chip" href="<?php echo themeEsc((string) $tag['permalink']); ?>" rel="tag"><?php echo themeIcon('tag', 'size-3.5'); ?><?php echo themeEsc((string) $tag['name']); ?></a>
                <?php endforeach; ?>
            </footer>
        <?php endif; ?>
    </article>

    <nav class="post-nav mt-8 grid gap-4 sm:grid-cols-2 sm:gap-8" aria-label="<?php echo themeEsc(_t('上下篇导航')); ?>">
        <?php $prevPost = themeAdjacentPost($this, 'prev'); ?>
        <?php $nextPost = themeAdjacentPost($this, 'next'); ?>
        <?php if (null !== $prevPost): ?>
            <a class="post-nav-link group" href="<?php echo themeEsc($prevPost['permalink']); ?>">
                <span class="post-nav-label"><?php echo themeIcon('arrow-left', 'size-3.5'); ?><?php _e('上一篇'); ?></span>
                <span class="post-nav-title"><?php echo themeEsc($prevPost['title']); ?></span>
            </a>
        <?php endif; ?>
        <?php if (null !== $nextPost): ?>
            <a class="post-nav-link post-nav-next group" href="<?php echo themeEsc($nextPost['permalink']); ?>">
                <span class="post-nav-label"><?php _e('下一篇'); ?><?php echo themeIcon('arrow-right', 'size-3.5'); ?></span>
                <span class="post-nav-title"><?php echo themeEsc($nextPost['title']); ?></span>
            </a>
        <?php endif; ?>
    </nav>

    <?php $this->need('comments.php'); ?>
</main>

<?php if ('toc' === $postAside): ?>
    <?php $this->need('toc.php'); ?>
<?php elseif ('sidebar' === $postAside): ?>
    <?php $this->need('sidebar.php'); ?>
<?php endif; ?>
<?php $this->need('footer.php'); ?>
