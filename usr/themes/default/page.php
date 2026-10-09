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

<div class="col-mb-12 col-8" id="main" role="main">
    <article class="post" itemscope itemtype="http://schema.org/BlogPosting">
        <?php postMeta($this, 'page'); ?>
        <div class="post-content" itemprop="articleBody">
            <?php $this->content(); ?>
        </div>
    </article>
    <?php $this->need('comments.php'); ?>
</div><!-- end #main-->

<?php $this->need('sidebar.php'); ?>
<?php $this->need('footer.php'); ?>
