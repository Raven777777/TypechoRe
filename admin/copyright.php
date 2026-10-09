<?php if(!defined('__TYPECHO_ADMIN__')) exit; ?>
<?php
/**
 * 后台模板作用域变量: 由 admin/common.php (以及 header.php / menu.php) 通过 include 注入。
 * PHPStan 无法跨 include 传播局部变量, 这里按实际作用域显式声明。
 *
 * @var Widget\Options $options
 */
?>
<footer class="typecho-foot" role="contentinfo">
    <div class="copyright">
        <a href="<?php echo \Typecho\Common::PROJECT_URL; ?>" class="i-logo-s"><?php echo \Typecho\Common::SOFTWARE; ?></a>
        <p><?php echo sprintf(
            _t('由 %s 强力驱动, 版本 %s'),
            '<a href="' . \Typecho\Common::PROJECT_URL . '">' . $options->software . '</a>',
            $options->version
        ); ?></p>
        <p><?php echo sprintf(
            _t('这是基于 <a href="%s">Typecho</a> 的 Fork 版本'),
            \Typecho\Common::PROJECT_ORIGIN_URL
        ); ?></p>
    </div>
    <nav class="resource">
        <a href="<?php echo \Typecho\Common::PROJECT_URL; ?>/issues"><?php _e('报告错误'); ?></a> &bull;
        <a href="<?php echo \Typecho\Common::PROJECT_ORIGIN_URL; ?>"><?php _e('原始项目'); ?></a>
    </nav>
</footer>
