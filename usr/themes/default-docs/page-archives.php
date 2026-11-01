<?php
/**
 * 归档索引
 *
 * @package custom
 * @description 归档索引页
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->need('header.php');

$archives = \Widget\Contents\Post\Date::alloc('format=Y-m&type=month');
$rows = [];
while ($archives->next()) {
    $rows[] = docswebLink((string) $archives->permalink, (string) $archives->date)
        . ' (' . (int) $archives->count . ')';
}
?>
<h1><a href="<?php $this->options->siteUrl(); ?>">home</a>/archive</h1>
<?php if ($rows): ?>
<p><?php echo implode('<br>', $rows); ?></p>
<?php endif; ?>
<?php $this->need('footer.php'); ?>
