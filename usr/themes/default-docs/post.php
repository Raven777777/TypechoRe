<?php
/**
 * 文章页模板
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->need('header.php');
?>
<p><a href="<?php $this->options->siteUrl(); ?>">home</a><?php if (!empty($this->categories)): ?>/<?php $this->category('/', true); ?><?php endif; ?> <?php $this->date('Y-m-d'); ?></p>
<h1><?php $this->title(); ?></h1>
<?php $this->content(); ?>
<?php $bits = [];
ob_start();
$this->theNext('%s', '', ['title' => 'Last']);
$bits[] = (string) ob_get_clean();
ob_start();
$this->thePrev('%s', '', ['title' => 'Old']);
$bits[] = (string) ob_get_clean();
$bits = array_values(array_filter($bits));
if ($bits): ?>
<nav><?php echo implode(' · ', $bits); ?></nav>
<?php endif; ?>
<?php $this->need('comments.php'); ?>
<?php $this->need('footer.php'); ?>
