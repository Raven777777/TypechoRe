<?php
/**
 * 主题模板作用域变量
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$footerText = themeOption('footerText');
?>
</div><!-- end #page-wrap -->

<footer class="mt-4 border-t border-zinc-200/80 py-10 text-sm text-zinc-500 dark:border-zinc-800/80 dark:text-zinc-400">
    <div class="mx-auto flex w-full <?php echo themeEsc(themePageWidthClass()); ?> flex-col gap-4 px-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
        <div class="space-y-1">
            <p>
                &copy; <?php echo date('Y'); ?>
                <a class="font-medium text-zinc-700 transition-colors hover:text-accent dark:text-zinc-300" href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title(); ?></a>
            </p>
            <?php if ('' !== $footerText): ?>
                <p class="text-xs leading-relaxed"><?php echo $footerText; ?></p>
            <?php endif; ?>
        </div>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
            <a class="transition-colors hover:text-accent" href="<?php $this->options->feedUrl(); ?>"><?php _e('文章 RSS'); ?></a>
            <a class="transition-colors hover:text-accent" href="<?php $this->options->commentsFeedUrl(); ?>"><?php _e('评论 RSS'); ?></a>
            <?php if ($this->user->hasLogin()): ?>
                <a class="transition-colors hover:text-accent" href="<?php $this->options->adminUrl(); ?>"><?php _e('管理后台'); ?></a>
                <a class="transition-colors hover:text-accent" href="<?php $this->options->logoutUrl(); ?>"><?php _e('退出'); ?></a>
            <?php else: ?>
                <a class="transition-colors hover:text-accent" href="<?php $this->options->adminUrl('login.php'); ?>"><?php _e('登录'); ?></a>
            <?php endif; ?>
            <span class="text-zinc-400 dark:text-zinc-500">
                <?php echo sprintf(
                    _t('由 %s 强力驱动'),
                    '<a class="transition-colors hover:text-accent" href="' . themeEsc(\Typecho\Common::PROJECT_URL) . '">'
                        . themeEsc($this->options->software) . '</a>'
                ); ?>
            </span>
        </div>
    </div>
</footer>

<div class="reading-progress" x-data="readingProgress" aria-hidden="true">
    <span :style="'width:' + (progress * 100).toFixed(2) + '%'"></span>
</div>

<div x-data="backToTop">
    <button class="back-to-top no-print" type="button" x-show="visible" x-cloak x-transition.opacity @click="toTop()" aria-label="<?php echo themeEsc(_t('返回顶部')); ?>" title="<?php echo themeEsc(_t('返回顶部')); ?>">
        <?php echo themeIcon('chevron-up', 'size-5'); ?>
    </button>
</div>

<?php
/**
 * 无刷新切页时的骨架屏 (由 dist/app.js 控制显隐与形态, 无 JS 时始终隐藏)
 *
 * 容器类与真实页面一致 (#page-wrap 用的 themeContainerClass), 这样骨架卡片与
 * 真实卡片的左边距/栅格完全对齐, 淡出时不会「跳一下」。
 * 形态 (列表页 / 文章页) 由 app.js 写在 data-kind 上, CSS 负责切换面板。
 * 主题设置里关掉骨架屏时整段标记都不输出 (app.js 读到 data-skeleton="0" 也不会去用它)。
 */
$skeletonContainer = themeContainerClass();
$skeletonSidebar = false;

if ('full' !== themeOption('layout', 'sidebar')) {
    foreach (['ShowRecentPosts', 'ShowRecentComments', 'ShowCategory', 'ShowArchive', 'ShowTagCloud', 'ShowOther'] as $skeletonBlock) {
        $skeletonSidebar = $skeletonSidebar || themeHasBlock($skeletonBlock);
    }
}
?>
<?php if (themeSkeletonEnabled()): ?>
<div id="page-skeleton" class="page-skeleton no-print" data-kind="list" role="status" aria-live="polite" hidden>
    <div class="<?php echo themeEsc($skeletonContainer); ?>">
        <div class="min-w-0">
            <!-- 列表页形态: 与 post-card.php 同构 (左图 + 标题 / 摘要 / 元信息) -->
            <div class="page-skeleton-panel" data-skeleton-panel="list">
                <div class="space-y-6">
                    <?php for ($skeletonCard = 0; $skeletonCard < 3; $skeletonCard++): ?>
                        <div class="card flex flex-col overflow-hidden sm:flex-row" aria-hidden="true">
                            <div class="shrink-0 sm:w-56">
                                <div class="skeleton-block h-44 w-full rounded-none sm:h-full sm:min-h-32"></div>
                            </div>
                            <div class="flex min-w-0 flex-1 flex-col gap-3 p-5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="skeleton-block h-6 w-16 rounded-full"></div>
                                    <div class="skeleton-block h-3.5 w-24"></div>
                                </div>
                                <div class="skeleton-block h-5 w-3/4"></div>
                                <div class="space-y-2">
                                    <div class="skeleton-block h-3.5 w-full"></div>
                                    <div class="skeleton-block h-3.5 w-11/12"></div>
                                    <div class="skeleton-block h-3.5 w-2/3"></div>
                                </div>
                                <div class="mt-auto flex flex-wrap items-center gap-4 pt-1">
                                    <div class="skeleton-block h-3.5 w-20"></div>
                                    <div class="skeleton-block h-3.5 w-16"></div>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- 文章 / 独立页面形态: 与 post.php 的卡片头 + 正文段落同构 -->
            <div class="page-skeleton-panel" data-skeleton-panel="single">
                <div class="card overflow-hidden" aria-hidden="true">
                    <div class="space-y-4 border-b border-zinc-200/80 p-6 sm:p-8 dark:border-zinc-800/80">
                        <div class="skeleton-block h-6 w-20 rounded-full"></div>
                        <div class="skeleton-block h-8 w-4/5"></div>
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                            <div class="skeleton-block h-4 w-20"></div>
                            <div class="skeleton-block h-4 w-28"></div>
                            <div class="skeleton-block h-4 w-16"></div>
                        </div>
                    </div>
                    <div class="space-y-3 p-6 sm:p-8">
                        <?php foreach ([100, 96, 92, 100, 88, 74, 100, 94, 62] as $skeletonLine): ?>
                            <div class="skeleton-block h-4" style="width: <?php echo (int) $skeletonLine; ?>%"></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($skeletonSidebar): ?>
            <aside class="mt-10 hidden min-w-0 space-y-6 lg:mt-0 lg:block" aria-hidden="true">
                <?php for ($skeletonWidget = 0; $skeletonWidget < 2; $skeletonWidget++): ?>
                    <div class="card space-y-3 p-5">
                        <div class="skeleton-block h-4 w-24"></div>
                        <div class="space-y-2">
                            <div class="skeleton-block h-3.5 w-full"></div>
                            <div class="skeleton-block h-3.5 w-10/12"></div>
                            <div class="skeleton-block h-3.5 w-9/12"></div>
                        </div>
                    </div>
                <?php endfor; ?>
            </aside>
        <?php endif; ?>
    </div>
    <span class="sr-only"><?php _e('正在加载…'); ?></span>
</div>
<?php endif; ?>

<?php $this->footer(); ?>
<script type="module" src="<?php echo themeAsset('dist/app.js'); ?>"></script>
</body>
</html>
