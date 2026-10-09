<?php if (!defined('__TYPECHO_ADMIN__')) exit; ?>
<?php
/**
 * 后台模板作用域变量: 由 admin/common.php (以及 header.php / menu.php) 通过 include 注入。
 * PHPStan 无法跨 include 传播局部变量, 这里按实际作用域显式声明。
 *
 * @var Widget\Menu $menu
 * @var Widget\Options $options
 * @var Widget\User $user
 */
?>
<header class="typecho-head-nav" role="navigation">
    <nav>
        <details class="menu-bar">
            <summary><?php _e('菜单'); ?></summary>
        </details>
        <menu>
            <?php $menu->output(); ?>
            <li class="operate">
                <?php \Typecho\Plugin::factory('admin/menu.php')->call('navBar'); ?><a title="<?php
                if ($user->logged > 0) {
                    $logged = new \Typecho\Date($user->logged);
                    _e('最后登录: %s', $logged->word());
                }
                ?>" href="<?php $options->adminUrl('profile.php'); ?>" class="author"><?php $user->screenName(); ?></a><a
                    class="exit" href="<?php $options->logoutUrl(); ?>"><?php _e('登出'); ?></a><a
                    href="<?php $options->siteUrl(); ?>"><?php _e('网站'); ?></a>
            </li>
        </menu>
    </nav>
</header>

