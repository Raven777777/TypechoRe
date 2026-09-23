### 神奇的is语法
typecho可以使用is语法判断很多东西，比如
```php
$this->is('index');  //判断首页
$this->is('archive'); //判断archive
$this->is('single'); //判断为阅读页面page+post
$this->is('page'); //判断独立页面page
$this->is('post'); //判断文章页面post
$this->is('category'); //判断分类页面
$this->is('tag'); //判断标签页面
$this->is('front'); //判断归档首页（首页为独立页面/文件且开启归档模式时）
$this->is('attachment'); //判断附件页面
```
`is()` 的完整签名为 `is(string $archiveType, ?string $archiveSlug = null): bool`，定义在 `Widget\Archive` 中。

关于首页的判断需要说明一下：当你把某个独立页面或文件设为首页后，`$this->is('index')` 依然返回 `true`（程序内部通过 `makeSinglePageAsFrontPage` 标记处理）。而 `$this->is('front')` 针对的是“站点首页”选择独立页面/文件并同时勾选“将文章列表页路径更改为…”（即 `frontArchive` 选项）时的归档列表页。

#### 分类，页面，文章还可以这样判断
```php
$this->is('category', 'default'); //判断分类缩略名等于default
$this->is('page', 'start'); //判断独立页面缩略名等于start
$this->is('post', 1); //判断文章cid等于1
```
需要注意的是，后面的参数是分类、页面的缩略名

#### 完整使用实例
```php
<?php if ($this->is('post')) : ?>
如果是文章页面就会显示这里的文字
<?php endif; ?>
```

### 判断为当前文章列表页的第几篇文章，并单独输出内容

```php
<?php if ($this->sequence == 1): ?>
如果是当前文章列表页的第1篇文章，就会输出该内容
<?php endif; ?>
```

### 判断登录
```php
<?php if ($this->user->hasLogin()): ?>
此处内容登录可见
<?php endif; ?>
```

### 判断程序版本号
前台输出版本号
```php
<?php $this->options->version; ?>
```

就能输出typecho的版本号，当前为语义化版本号，形如：

```
1.3.1
```

> 旧版本曾使用形如 `1.1/17.11.15` 的“版本/日期”格式，现在 `version` 已统一为语义化版本号（`Typecho\Common::VERSION`）。程序名称可通过 `$this->options->software` 获取（当前为 `TypechoRe`）。

那么假设你的模板不兼容某个版本的typecho时，为何不做个温馨提示呢？

```php
<?php
$tver = $this->options->version;
if (version_compare($tver, '1.0', '>')) {
    echo '该模板可能不兼容大于1.0版本的typecho';
}
?>
```

在`functions.php`中如果不方便使用`$this`，可以这样获取：

```php
$options = \Utils\Helper::options();
echo $options->version;
```

> 旧写法 `Typecho_Widget::widget('Widget_Options')->Version` 仍可用，但不推荐。

### 判断当前用户是否是文章作者

```php
<?php if($this->user->uid==$this->authorId):?>
当前用户是文章作者
<?php endif;?>
```
