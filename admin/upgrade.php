<?php
/**
 * 后台模板作用域变量: 由 admin/common.php (以及 header.php / menu.php) 通过 include 注入。
 * PHPStan 无法跨 include 传播局部变量, 这里按实际作用域显式声明。
 *
 * @var Widget\Options $options
 * @var Widget\Security $security
 */
include 'common.php';
include 'header.php';
include 'menu.php';
?>

<main class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12">
                <div id="typecho-welcome">
                    <form action="<?php echo $security->getTokenUrl(
                        \Typecho\Router::url('do', ['action' => 'upgrade', 'widget' => 'Upgrade'],
                            \Typecho\Common::url('index.php', $options->rootUrl))); ?>" method="post">
                        <h3><?php _e('检测到新版本!'); ?></h3>
                        <ul>
                            <li><?php _e('您已经更新了系统程序, 我们还需要执行一些后续步骤来完成升级'); ?></li>
                            <li><?php _e('此程序将把您的系统从 <strong>%s</strong> 升级到 <strong>%s</strong>', $options->version, \Typecho\Common::VERSION); ?></li>
                            <li><strong
                                    class="warning"><?php _e('在升级之前强烈建议先<a href="%s">备份您的数据</a>', \Typecho\Common::url('backup.php', $options->adminUrl)); ?></strong>
                            </li>
                        </ul>
                        <p>
                            <button class="btn primary" type="submit"><?php _e('完成升级 &raquo;'); ?></button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
include 'copyright.php';
include 'common-js.php';
?>
<script>
    (function () {
        if (window.sessionStorage) {
            sessionStorage.removeItem('update');
        }
    })();
</script>
<?php include 'footer.php'; ?>
