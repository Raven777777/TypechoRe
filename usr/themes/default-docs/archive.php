<?php
/**
 * 通用归档模板: 分类 / 标签 / 作者 / 搜索 / 日期
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->need('header.php');

$year = (int) $this->request->get('year');
if ($this->is('date') && $year > 0) {
    $month = (int) $this->request->get('month');
    $day = (int) $this->request->get('day');
    $label = (string) $year
        . ($month > 0 ? '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) : '')
        . ($day > 0 ? '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT) : '');
    $archiveUrl = docswebPageUrl('page-archives.php');
    $archiveLink = $archiveUrl ? docswebLink($archiveUrl, 'archive') : 'archive';
    $heading = docswebLink($this->options->siteUrl, 'home') . '/' . $archiveLink . '/'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
} else {
    $heading = docswebLink($this->options->siteUrl, 'home') . '/'
        . htmlspecialchars((string) $this->getArchiveTitle(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<h1><?php echo $heading; ?></h1>
<?php if ($this->have()): ?>
<p>
<?php while ($this->next()): ?>
<?php $this->date('Y-m-d'); ?> <a href="<?php $this->permalink(); ?>"><?php $this->title(); ?><?php echo docswebStickyBadge($this); ?></a><br>
<?php endwhile; ?>
</p>
<?php endif; ?>
<?php docswebPager($this); ?>
<?php $this->need('footer.php'); ?>
