<?php
/**
 * 主题模板作用域变量
 *
 * 模板由 Widget\Archive::render()/need() 通过 require 载入, 因此 $this 是当前归档组件。
 * 其余局部变量 (如 $pages) 由模板内部赋值。
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$themeScheme = themeColorScheme();
$themeFont = 'serif' === themeOption('fontStyle', 'sans') ? 'font-serif' : 'font-sans';
$themeContainer = themeContainerClass();
?>
<!DOCTYPE html>
<html lang="<?php echo themeEsc(str_replace('_', '-', (string) $this->options->lang)); ?>" class="<?php echo themeEsc($themeFont); ?><?php echo 'dark' === $themeScheme ? ' dark' : ''; ?>"<?php if ('auto' !== $themeScheme): ?> data-theme-mode="<?php echo themeEsc($themeScheme); ?>"<?php endif; ?> data-default-theme-mode="<?php echo themeEsc($themeScheme); ?>" style="--theme-accent: <?php echo themeEsc(themeAccent()); ?>">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#ffffff" data-light="#ffffff" data-dark="#09090b">
    <title><?php $this->archiveTitle([
            'category' => _t('分类 %s 下的文章'),
            'search'   => _t('包含关键字 %s 的文章'),
            'tag'      => _t('标签 %s 下的文章'),
            'author'   => _t('%s 发布的文章')
        ], '', ' - '); ?><?php $this->options->title(); ?><?php if ($this->getCurrentPage() > 1): ?> - <?php echo _t('第 '), $this->getCurrentPage(), _t(' 页'); ?><?php endif; ?></title>

    <!-- 无闪烁深色模式: 必须在样式表之前执行 -->
    <script>
        (function () {
            var root = document.documentElement;
            var fallback = root.dataset.defaultThemeMode || 'auto';
            var mode = fallback;
            try { mode = localStorage.getItem('typecho-theme-mode') || fallback; } catch (e) { }
            var dark = mode === 'dark' || (mode === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            root.classList.toggle('dark', dark);
            root.dataset.themeMode = mode;
            root.style.colorScheme = dark ? 'dark' : 'light';
            var meta = document.querySelector('meta[name="theme-color"]');
            if (meta) { meta.setAttribute('content', dark ? meta.dataset.dark : meta.dataset.light); }
        })();
    </script>

    <?php if ($this->options->faviconUrl): ?>
        <link rel="icon" href="<?php $this->options->faviconUrl(); ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="<?php echo themeAsset('dist/style.css'); ?>">

    <!-- Typecho 头部信息 (description / canonical / RSS / 评论回复脚本等) -->
    <?php $this->header('generator=&template='); ?>

    <?php if ($this->is('single')): ?>
        <?php echo themePostJsonLd($this, themeThumbnail($this)); ?>
    <?php else: ?>
        <?php echo themeSiteJsonLd(); ?>
    <?php endif; ?>

    <?php $customCss = themeOption('customCss'); ?>
    <?php if ('' !== $customCss): ?>
        <style><?php echo $customCss; ?></style>
    <?php endif; ?>
</head>
<body class="min-h-dvh">
<a class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-[80] focus:rounded-full focus:bg-accent focus:px-4 focus:py-2 focus:text-sm focus:text-white" href="#main"><?php _e('跳到主要内容'); ?></a>

<div id="site-nav" class="sticky top-0 z-50 border-b border-zinc-200/80 bg-white/85 backdrop-blur-md dark:border-zinc-800/80 dark:bg-zinc-950/80">
    <div class="mx-auto flex h-14 w-full <?php echo themeEsc(themePageWidthClass()); ?> items-center gap-3 px-4 sm:px-6 lg:px-8">
        <div class="flex min-w-0 items-center gap-2">
            <?php if ($this->options->logoUrl): ?>
                <a class="flex items-center" href="<?php $this->options->siteUrl(); ?>">
                    <img class="h-8 w-auto" src="<?php $this->options->logoUrl(); ?>" alt="<?php $this->options->title(); ?>">
                </a>
            <?php else: ?>
                <a class="truncate text-base font-semibold tracking-tight transition-colors hover:text-accent" href="<?php $this->options->siteUrl(); ?>">
                    <?php $this->options->title(); ?>
                </a>
            <?php endif; ?>
        </div>

        <nav class="ml-auto hidden items-center gap-1 lg:flex" aria-label="<?php echo themeEsc(_t('站点导航')); ?>">
            <a class="rounded-full px-3 py-1.5 text-sm transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800<?php echo $this->is('index') ? ' font-medium text-accent' : ' text-zinc-600 dark:text-zinc-300'; ?>" href="<?php $this->options->siteUrl(); ?>"><?php _e('首页'); ?></a>
            <?php \Widget\Contents\Page\Rows::alloc()->to($pages); ?>
            <?php while ($pages->next()): ?>
                <a class="rounded-full px-3 py-1.5 text-sm transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800<?php echo $this->is('page', $pages->slug) ? ' font-medium text-accent' : ' text-zinc-600 dark:text-zinc-300'; ?>" href="<?php $pages->permalink(); ?>"><?php $pages->title(); ?></a>
            <?php endwhile; ?>
            <a class="icon-btn" href="<?php $this->options->feedUrl(); ?>" title="<?php echo themeEsc(_t('订阅文章 RSS')); ?>" aria-label="<?php echo themeEsc(_t('订阅文章 RSS')); ?>"><?php echo themeIcon('rss', 'size-4'); ?></a>
        </nav>

        <form class="ml-auto hidden max-w-56 flex-1 items-center lg:ml-2 lg:flex" method="post" action="<?php $this->options->siteUrl(); ?>" role="search">
            <label class="sr-only" for="nav-search"><?php _e('搜索关键字'); ?></label>
            <div class="relative w-full">
                <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-zinc-400"><?php echo themeIcon('search', 'size-4'); ?></span>
                <input class="field py-2 pl-9" id="nav-search" type="search" name="s" value="<?php echo themeEsc($this->request->get('s')); ?>" placeholder="<?php echo themeEsc(_t('搜索…')); ?>" autocomplete="off">
            </div>
        </form>

        <div class="ml-auto flex items-center gap-1 lg:ml-1" x-data="themeToggle">
            <button class="icon-btn" type="button" @click="cycle()" :title="label" :aria-label="label">
                <span class="theme-icon theme-icon-auto"><?php echo themeIcon('monitor', 'size-5'); ?></span>
                <span class="theme-icon theme-icon-light"><?php echo themeIcon('sun', 'size-5'); ?></span>
                <span class="theme-icon theme-icon-dark"><?php echo themeIcon('moon', 'size-5'); ?></span>
            </button>
        </div>

        <details class="relative lg:hidden" data-nav-collapse>
            <summary class="icon-btn cursor-pointer list-none [&::-webkit-details-marker]:hidden" aria-label="<?php echo themeEsc(_t('打开导航菜单')); ?>"><?php echo themeIcon('menu', 'size-5'); ?></summary>
            <div class="card absolute right-0 z-50 mt-2 w-64 p-3">
                <form class="mb-2" method="post" action="<?php $this->options->siteUrl(); ?>" role="search">
                    <label class="sr-only" for="mobile-search"><?php _e('搜索关键字'); ?></label>
                    <div class="relative">
                        <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-zinc-400"><?php echo themeIcon('search', 'size-4'); ?></span>
                        <input class="field py-2 pl-9" id="mobile-search" type="search" name="s" placeholder="<?php echo themeEsc(_t('搜索…')); ?>">
                    </div>
                </form>
                <ul class="m-0 list-none space-y-0.5 p-0">
                    <li>
                        <a class="block rounded-lg px-3 py-2 text-sm transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800<?php echo $this->is('index') ? ' font-medium text-accent' : ' text-zinc-700 dark:text-zinc-200'; ?>" href="<?php $this->options->siteUrl(); ?>"><?php _e('首页'); ?></a>
                    </li>
                    <?php \Widget\Contents\Page\Rows::alloc()->to($mobilePages); ?>
                    <?php while ($mobilePages->next()): ?>
                        <li>
                            <a class="block rounded-lg px-3 py-2 text-sm transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800<?php echo $this->is('page', $mobilePages->slug) ? ' font-medium text-accent' : ' text-zinc-700 dark:text-zinc-200'; ?>" href="<?php $mobilePages->permalink(); ?>"><?php $mobilePages->title(); ?></a>
                        </li>
                    <?php endwhile; ?>
                    <li>
                        <a class="block rounded-lg px-3 py-2 text-sm text-zinc-700 transition-colors hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800" href="<?php $this->options->feedUrl(); ?>"><?php _e('文章 RSS'); ?></a>
                    </li>
                </ul>
            </div>
        </details>
    </div>
</div>

<div class="<?php echo themeEsc($themeContainer); ?>" id="page-wrap">
