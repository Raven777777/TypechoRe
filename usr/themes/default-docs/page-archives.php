<?php
/**
 * 归档索引
 *
 * @package custom
 * @description 归档索引页
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->need('header.php');

$archives = $this->widget('Widget\Contents\Post\Date', 'format=Y-m&type=month');
$rows = [];
while ($archives->next()) {
    $rows[] = '<a href="' . htmlspecialchars((string) $archives->permalink, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars((string) $archives->date, ENT_QUOTES, 'UTF-8') . '</a> ('
        . (int) $archives->count . ')';
}
?>
<h1><a href="<?php $this->options->siteUrl(); ?>">home</a>/archive</h1>
<?php if ($rows): ?>
<p><?php echo implode('<br>', $rows); ?></p>
<?php endif; ?>
<?php $this->need('footer.php'); ?>
