# 创建自定义模板

Typecho 允许为文章或独立页面指定“自定义模板”，从而让同一篇内容使用不同的布局。创建方法如下。

### 一、在主题目录新建模板文件

在你的主题目录（如 `usr/themes/default/`）下新建一个 `.php` 文件，例如 `links.php`，并在文件顶部的注释中声明 `@package custom`：

```php
<?php
/**
 * 友情链接
 *
 * @package custom
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<div class="col-mb-12 col-8" id="main" role="main">
    <article class="post">
        <h1 class="post-title"><?php $this->title() ?></h1>
        <div class="post-content">
            <?php $this->content(); ?>
        </div>
    </article>
    <?php $this->need('comments.php'); ?>
</div>

<?php $this->need('sidebar.php'); ?>
<?php $this->need('footer.php'); ?>
```

要点：
- 注释中 `@package custom` 是必须的，程序通过它识别出“自定义模板”（见 `Widget\Base\Contents::getTemplates()`）。
- 注释中第一个 `@` 标签之前的文字（如“友情链接”）会作为模板名称显示。
- `index.php` 不会出现在可选模板列表中。

### 二、在后台选择模板

进入后台 **管理 → 文章/独立页面 → 编辑**，在右侧“高级选项”中的**自定义模板**下拉框里选择刚才创建的模板，保存即可。

### 三、工作原理

- 编辑内容时选择的模板会保存到内容的 `template` 字段。
- 渲染时，`Widget\Archive::singleHandle()` 检测到 `$this->template` 后，将其作为要加载的主题文件（`themeFile`），于是这篇内容就会使用该模板输出。

> 提示：自定义模板同样可以用 `$this->need()` 复用 header/sidebar/footer，保持整体风格一致。
