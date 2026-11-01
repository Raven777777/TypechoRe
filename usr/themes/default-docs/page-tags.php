<?php
/**
 * 标签索引
 *
 * @package custom
 * @description 标签索引页
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->need('header.php');

$tags = \Widget\Metas\Tag\Cloud::alloc('sort=count&desc=1&ignoreZeroCount=1');
$items = [];
$max = 0;
$min = null;
while ($tags->next()) {
    $count = (int) $tags->count;
    $max = max($max, $count);
    $min = $min === null ? $count : min($min, $count);
    $items[] = [
        'name'      => (string) $tags->name,
        'permalink' => (string) $tags->permalink,
        'count'     => $count,
    ];
}
?>
<h1><a href="<?php $this->options->siteUrl(); ?>">home</a>/tag</h1>
<?php if ($items): ?>
<p class="tag-cloud">
<?php foreach ($items as $item): ?>
<?php $size = $max > $min ? 0.85 + 0.75 * ($item['count'] - $min) / ($max - $min) : 1; ?>
<a href="<?php echo htmlspecialchars($item['permalink'], ENT_QUOTES, 'UTF-8'); ?>" style="font-size:<?php echo round($size, 2); ?>em"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?><small><?php echo $item['count']; ?></small></a> 
<?php endforeach; ?>
</p>
<?php else: ?>
<p>暂无标签</p>
<?php endif; ?>
<?php $this->need('footer.php'); ?>
