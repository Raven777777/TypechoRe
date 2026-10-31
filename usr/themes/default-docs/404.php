<?php
/**
 * 404 页面
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->need('header.php');
?>
<p><a href="<?php $this->options->siteUrl(); ?>">Home</a></p>
<h1>404</h1>
<p>Not Found</p>
<?php $this->need('footer.php'); ?>
