<?php
/**
 * 主题模板作用域变量
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$pageNo = $this->getCurrentPage();
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars(str_replace('_', '-', (string) $this->options->lang), ENT_QUOTES, 'UTF-8'); ?>">
<head>
<meta charset="<?php $this->options->charset(); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php if ($pageNo > 1): ?>Page <?php echo $pageNo; ?> · <?php endif; ?><?php ob_start();
$this->archiveTitle([
    'category' => '%s',
    'search'   => '%s',
    'tag'      => '%s',
    'author'   => '%s'
], '', ' · ');
echo htmlspecialchars((string) ob_get_clean(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><?php $this->options->title(); ?></title>
<link rel="stylesheet" href="<?php $this->options->themeUrl('style.css'); ?>">
<script type="module" src="<?php $this->options->themeUrl('hl.js'); ?>"></script>
<?php $this->header('generator=&template='); ?>
</head>
<body>
