<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
/**
 * 主题模板作用域变量
 *
 * 模板由 Widget\Archive::render()/need() 通过 require 载入, 因此 $this 是当前归档组件;
 * 其余局部变量 (如 $comments) 由模板内部赋值。PHPStan 无法推断 include 作用域, 这里显式声明。
 *
 * @var Widget\Archive $this
 */
?>
<?php $this->need('header.php'); ?>

<div class="col-mb-12 col-tb-8 col-tb-offset-2" id="main">

    <div class="error-page">
        <h2 class="post-title">404 - <?php _e('页面没找到'); ?></h2>
        <p><?php _e('你想查看的页面已被转移或删除了, 要不要搜索看看: '); ?></p>
        <form method="post">
            <p><input type="text" name="s" class="text" autofocus/></p>
            <p>
                <button type="submit" class="submit"><?php _e('搜索'); ?></button>
            </p>
        </form>
    </div>

</div><!-- end #content-->
<?php $this->need('footer.php'); ?>
