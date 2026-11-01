<?php
/**
 * 分类索引
 *
 * @package custom
 * @description 分类索引页
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->need('header.php');

$categories = \Widget\Metas\Category\Rows::alloc();
$rows = [];
while ($categories->next()) {
    $rows[] = docswebLink((string) $categories->permalink, (string) $categories->name)
        . ' (' . (int) $categories->count . ')';
}
?>
<h1><a href="<?php $this->options->siteUrl(); ?>">home</a>/category</h1>
<?php if ($rows): ?>
<p><?php echo implode('<br>', $rows); ?></p>
<?php else: ?>
<p>暂无分类</p>
<?php endif; ?>
<?php $this->need('footer.php'); ?>
