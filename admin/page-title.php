<?php if (!defined('__TYPECHO_ADMIN__')) exit; ?>
<?php
/**
 * 后台模板作用域变量: 由 admin/common.php (以及 header.php / menu.php) 通过 include 注入。
 * PHPStan 无法跨 include 传播局部变量, 这里按实际作用域显式声明。
 *
 * @var Widget\Menu $menu
 */
?>
<div class="typecho-page-title">
    <h2><?php echo $menu->title; ?></h2>
    <?php
    if (!empty($menu->addLink)) {
        echo "<a href=\"{$menu->addLink}\">" . _t("新增") . "</a>";
    }
    ?>
</div>
