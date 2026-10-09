<?php
/**
 * 主题模板作用域变量
 *
 * 模板由 Widget\Archive::render()/need() 通过 require 载入, 因此 $this 是当前归档组件;
 * 其余局部变量 (如 $comments) 由模板内部赋值。PHPStan 无法推断 include 作用域, 这里显式声明。
 *
 * @var Widget\Archive $this
 */
/**
 * Default theme for Typecho
 *
 * @package Typecho Replica Theme
 * @author Typecho Team
 * @version 1.3.1
 * @link http://typecho.org
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<div class="col-mb-12 col-8" id="main" role="main">
    <?php if (!$this->is('index') && !$this->is('post')): ?>
    <h3 class="archive-title"><?php $this->archiveTitle([
            'category' => _t('分类 %s 下的文章'),
            'search'   => _t('包含关键字 %s 的文章'),
            'tag'      => _t('标签 %s 下的文章'),
            'author'   => _t('%s 发布的文章')
        ], '', ''); ?></h3>
    <?php endif; ?>
    <?php if ($this->have()): ?>
    <?php while ($this->next()): ?>
        <article class="post" itemscope itemtype="http://schema.org/BlogPosting">
            <?php postMeta($this); ?>
            <div class="post-content" itemprop="articleBody">
                <?php $this->content(_t('阅读剩余部分')); ?>
            </div>
        </article>
    <?php endwhile; ?>
    <?php else: ?>
        <article class="post">
            <h2 class="post-title"><?php _e('没有找到内容'); ?></h2>
        </article>
    <?php endif; ?>

    <?php $this->pageNav('&laquo; ' . _t('前一页'), _t('后一页') . ' &raquo;'); ?>
</div><!-- end #main-->

<?php $this->need('sidebar.php'); ?>
<?php $this->need('footer.php'); ?>
