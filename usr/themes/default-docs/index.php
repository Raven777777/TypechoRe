<?php
/**
 * Default Docs: 复刻 tools/docsweb 的极简单栏样式
 *
 * @package Default Docs
 * @author TypechoRe
 * @version 1.0.0
 * @link https://github.com/Raven777777/TypechoRe
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->need('header.php');
?>
<h1><?php $this->options->title(); ?></h1>
<?php
$pages = \Widget\Contents\Page\Rows::alloc();
$pageLinks = [];
while ($pages->next()) {
    // 分类/归档索引页只作为导航，不重复列在独立页链接里
    if (in_array($pages->template, ['page-categories.php', 'page-archives.php', 'page-tags.php'], true)) {
        continue;
    }
    $pageLinks[] = docswebLink((string) $pages->permalink, (string) $pages->title);
}
?>
<?php if ($pageLinks): ?>
<p><?php echo implode(' · ', $pageLinks); ?></p>
<?php endif; ?>
<?php if ($this->have()): ?>
<p>
<?php while ($this->next()): ?>
<?php $this->date('Y-m-d'); ?> <a href="<?php $this->permalink(); ?>"><?php $this->title(); ?><?php echo docswebStickyBadge($this); ?></a><?php if (!empty($this->categories)): ?> <?php $this->category(', ', false); ?><?php endif; ?><br>
<?php endwhile; ?>
</p>
<?php endif; ?>
<?php docswebPager($this); ?>
<?php $this->need('footer.php'); ?>
