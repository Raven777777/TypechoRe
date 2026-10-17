<?php
/**
 * 未完成的预览版 (preview)
 *
 * 主题的模板结构、主题设置项与前端实现都还可能调整, 请勿直接用于正式站点;
 * 升级方式可能是「删掉旧目录重新覆盖」, 不保证向后兼容。
 *
 * TypechoRe · Default 2026: 现代、优雅、以性能为先。
 *
 * @package Default 2026 (Preview)
 * @author TypechoRe
 * @version 0.1.0-preview
 * @since 1.3.2
 * @link https://github.com/Raven777777/TypechoRe
 */

/**
 * 模板查找顺序见 Widget\Archive::render(): 分类/标签等归档若没有专用模板,
 * 会回退到 archive.php, 再回退到本文件。
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->need('header.php');
?>
<main id="main" class="min-w-0 transition-fade">
    <?php /* 首页不摆一条用不上的可见大标题 (站点名已经在导航里), 但屏读仍需要个名字 */ ?>
    <h1 class="sr-only"><?php echo themeEsc((string) $this->options->title); ?><?php _e(' · 最新文章'); ?></h1>

    <?php /* 侧边栏在 < 64rem 时整列隐藏 → 分类在手机上直接消失。
             这里给一条只在窄屏出现的横向胶囊带 (详情页另有归档 / 标签入口)。 */ ?>
    <?php if (themeSkeletonSidebar()): ?>
        <nav class="browse-strip mb-5" aria-label="<?php echo themeEsc(_t('浏览分类')); ?>">
            <?php \Widget\Metas\Category\Rows::alloc()->to($stripCategories); ?>
            <?php while ($stripCategories->next()): ?>
                <a class="chip shrink-0" href="<?php $stripCategories->permalink(); ?>"><?php echo themeText(fn () => $stripCategories->name()); ?><span class="widget-count"><?php echo (int) $stripCategories->count; ?></span></a>
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
            <p class="text-lg font-medium text-ink-1"><?php _e('这里还没有内容'); ?></p>
            <p class="mt-2 text-sm text-ink-2"><?php _e('在后台发布第一篇文章后, 它就会出现在这里。'); ?></p>
        </div>
    <?php endif; ?>
</main>

<?php $this->need('sidebar.php'); ?>
<?php $this->need('footer.php'); ?>
