<?php
/**
 * 主题模板作用域变量
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$footerText = themeFooterHtml();
?>
</div><!-- end #page-wrap -->

<?php /* 页脚: 宽屏与页眉等高 (都是 h-14 的横条 + 1px 边框); 窄屏换成上下两行居中 (手机上挤成一团) */ ?>
<?php /* 窄屏的分类 / 归档气泡带已经搬到正文上方 (themeBrowseStrip), 这里不再重复输出:
     放在页脚时手机上要滑到分页之后才出现, 看起来像多出来的一列, 也和列表页对不上。 */ ?>
<footer class="mt-4 border-t border-line text-sm text-ink-3">
    <div class="mx-auto flex min-h-14 w-full <?php echo themeEsc(themePageWidthClass()); ?> flex-col items-center gap-y-1 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-4 sm:px-6 sm:py-2 lg:px-8">
        <p class="flex min-w-0 items-center gap-2">
            <span>&copy; <?php echo date('Y'); ?></span>
            <a class="truncate font-medium text-ink-1 transition-colors hover:text-accent-ink" href="<?php $this->options->siteUrl(); ?>"><?php echo themeEsc((string) $this->options->title); ?></a>
            <?php if ('' !== $footerText): ?>
                <?php /* 同一个值两处输出: title 要去标记, 正文按白名单 HTML 输出 —— 以前这里直接 echo 原文 */ ?>
                <span class="hidden min-w-0 truncate text-xs sm:inline" title="<?php echo themeEsc(strip_tags($footerText)); ?>">· <?php echo $footerText; ?></span>
            <?php endif; ?>
        </p>

        <nav class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-xs sm:ml-auto sm:justify-end sm:gap-x-4" aria-label="<?php echo themeEsc(_t('页脚链接')); ?>">
            <a class="transition-colors hover:text-accent-ink" href="<?php $this->options->feedUrl(); ?>"><?php _e('文章 RSS'); ?></a>
            <a class="transition-colors hover:text-accent-ink" href="<?php $this->options->commentsFeedUrl(); ?>"><?php _e('评论 RSS'); ?></a>
            <?php if ($this->user->hasLogin()): ?>
                <a class="transition-colors hover:text-accent-ink" href="<?php $this->options->adminUrl(); ?>"><?php _e('管理后台'); ?></a>
                <a class="transition-colors hover:text-accent-ink" href="<?php $this->options->logoutUrl(); ?>"><?php _e('退出'); ?></a>
            <?php else: ?>
                <a class="transition-colors hover:text-accent-ink" href="<?php $this->options->adminUrl('login.php'); ?>"><?php _e('登录'); ?></a>
            <?php endif; ?>
            <?php /* 窄屏那一行已经很满 (版权 + 四个链接), 署名只在 sm 以上出现 */ ?>
            <span class="hidden text-ink-3 sm:inline">
                <?php echo sprintf(
                    _t('由 %s 强力驱动'),
                    '<a class="transition-colors hover:text-accent-ink" href="' . themeEsc(\Typecho\Common::PROJECT_URL) . '">'
                        . themeEsc((string) $this->options->software) . '</a>'
                ); ?>
            </span>
        </nav>
    </div>
</footer>

<div class="reading-progress" aria-hidden="true">
    <span></span>
</div>

<?php /* data-visible 由 app.js 按滚动位置切换; 没有 JS 时它始终保持隐藏 (点击也没有意义) */ ?>
<button class="back-to-top no-print" type="button" aria-label="<?php echo themeEsc(_t('返回顶部')); ?>" title="<?php echo themeEsc(_t('返回顶部')); ?>">
    <?php echo themeIcon('chevron-up', 'size-5'); ?>
</button>

<?php
/**
 * 无刷新切页时的骨架屏 (由 dist/app.js 控制显隐与形态, 无 JS 时始终隐藏)
 *
 * 容器类与真实页面一致 (#page-wrap 用的 themeContainerClass), 这样骨架卡片与
 * 真实卡片的左边距/栅格完全对齐, 淡出时不会「跳一下」。
 * 形态 (列表页 / 文章页) 由 app.js 写在 data-kind 上, CSS 负责切换面板。
 * 主题设置里关掉骨架屏时整段标记都不输出 (app.js 读到 data-skeleton="0" 也不会去用它)。
 *
 * 第二列 (侧边栏 / 文章目录) 与形态面板平级, 两种骨架都先渲染好, 由 app.js 按
 * 「这次跳转会不会换掉那一列」切换显隐 (见 syncSkeletonAside)。
 * 本页第二列的形态写在 data-aside-kind 上 (服务端渲染时的记录; 判定看 DOM 里真实
 * 那一列, 切页换掉它之后 app.js 会把这里的值补上)。
 */
$skeletonContainer = themeContainerClass();
$skeletonAsideKind = themeAsideKind($this);
?>
<?php if (themeSkeletonEnabled()): ?>
<div id="page-skeleton" class="page-skeleton no-print" data-kind="list" data-aside-kind="<?php echo themeEsc($skeletonAsideKind); ?>" role="status" aria-live="polite" hidden>
    <div class="<?php echo themeEsc($skeletonContainer); ?>">
        <div class="min-w-0">
            <!-- 列表页形态: 与 post-card.php 同构 (左图 + 标题 / 摘要 / 元信息) -->
            <div class="page-skeleton-panel" data-skeleton-panel="list">
                <div class="space-y-6">
                    <?php for ($skeletonCard = 0; $skeletonCard < 3; $skeletonCard++): ?>
                        <div class="card flex flex-col overflow-hidden sm:flex-row" aria-hidden="true">
                            <div class="card-media h-44 w-full shrink-0 bg-surface-2 sm:h-auto sm:min-h-[11rem] sm:w-56">
                                <div class="skeleton-block h-full min-h-44 w-full rounded-none"></div>
                            </div>
                            <div class="flex min-w-0 flex-1 flex-col gap-2.5 p-5 sm:gap-3 sm:p-6">
                                <div class="skeleton-block h-6 w-3/4"></div>
                                <div class="space-y-2">
                                    <div class="skeleton-block h-3.5 w-full"></div>
                                    <div class="skeleton-block h-3.5 w-11/12"></div>
                                </div>
                                <div class="mt-auto flex flex-wrap items-center gap-x-4 gap-y-2 pt-1">
                                    <div class="skeleton-block h-6 w-16 rounded-full"></div>
                                    <div class="skeleton-block h-3.5 w-20"></div>
                                    <div class="skeleton-block ml-auto h-3.5 w-16"></div>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- 文章 / 独立页面形态: 与 post.php 的卡片头 + 正文段落同构 -->
            <div class="page-skeleton-panel" data-skeleton-panel="single">
                <div class="card overflow-hidden" aria-hidden="true">
                    <div class="space-y-4 border-b border-line p-6 sm:p-8">
                        <div class="skeleton-block h-8 w-3/4"></div>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                            <div class="skeleton-block h-6 w-20 rounded-full"></div>
                            <div class="skeleton-block h-4 w-24"></div>
                            <div class="skeleton-block h-4 w-20"></div>
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

        <?php
        /**
         * 第二列骨架: 侧边栏 / 文章目录两种形态都在这里, 默认全隐藏。
         * app.js 按本次跳转的目标形态切换: 给 <aside> 写 data-aside-kind, 同时只显示
         * 对应的那个 [data-skeleton-aside-variant] (见 syncSkeletonAside)。
         *
         * 两种形态都渲染, 而不是只渲染当前页那一种: 目标是列表页 → 侧边栏, 目标是
         * 文章页 → 目录 (文章页的目录数量要读完全文才知道, 骨架屏等不起)。
         * 目标那篇到底有没有目录, 由卡片链接上的 data-aside-target 提前告诉 app.js;
         * 拿不到提示时它宁可这一列不画 (见 asideKindForSession), 也不赌一把。
         * 这一列在没切换时是空的、默认 hidden, 多出来的标记不影响首屏。
         */
        ?>
        <?php if ('full' !== themeOption('layout', 'sidebar')): ?>
            <aside class="mt-10 min-w-0 space-y-6 lg:mt-0" aria-hidden="true" data-skeleton-aside hidden>
                <div data-skeleton-aside-variant="sidebar" hidden>
                    <div class="space-y-6">
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
                    </div>
                </div>

                <div data-skeleton-aside-variant="toc" hidden>
                    <div class="card space-y-2 p-5">
                        <div class="skeleton-block h-4 w-24"></div>
                        <?php foreach ([86, 72, 92, 64, 80, 58, 76, 68] as $skeletonTocLine): ?>
                            <div class="skeleton-block h-3.5" style="width: <?php echo (int) $skeletonTocLine; ?>%"></div>
                        <?php endforeach; ?>
                    </div>
                </div>
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
