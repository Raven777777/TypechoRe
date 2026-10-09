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

<?php $this->footer(); ?>
<script type="module" src="<?php echo themeAsset('dist/app.js'); ?>"></script>
</body>
</html>
