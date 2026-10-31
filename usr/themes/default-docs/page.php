<?php
/**
 * 独立页面模板
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->need('header.php');
?>
<h1><?php $this->title(); ?></h1>
<?php $this->content(); ?>
<?php $this->need('comments.php'); ?>
<?php $this->need('footer.php'); ?>
