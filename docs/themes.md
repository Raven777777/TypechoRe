## index.php

### 模板信息

我们先从主文件说起，打开这个文件，首先看到的是注释：

```php
/**
 * Default theme for Typecho
 *
 * @package Typecho Replica Theme
 * @author Typecho Team
 * @version 1.2
 * @link http://typecho.org
 */
```

这是模板信息存放的地方，它将在后台的模板选择页显示。注释中第一个 `@` 标签之前的文字是模板描述，每个“\*”表示一行。

- **@package** 表示模板名（后台显示的模板名称）
- **@author** 表示作者名
- **@version** 是模板的版本号
- **@link** 是作者的网站链接

> 模板信息的解析由 `Typecho\Plugin::parseInfo()` 完成，它会读取 `index.php` 文件顶部的 doc 注释。除上述字段外还支持 `@since`。

紧挨着注释下方的 `$this->need('header.php')`，在结尾处也会看到 `$this->need('sidebar.php')` 和 `$this->need('footer.php')`。这些语句用来调用模板的其它模块。header 顾名思义是页首，sidebar 是侧栏，footer 是页脚。(与 php 的 include 功能差不多，need 是 typecho 程序内置的方法，内部还会存在一些判断什么的，建议在做主题时使用 need 方法而不是 include)

### 显示文章列表

```php
<?php if ($this->have()): ?>
<?php while ($this->next()): ?>
<a href="<?php $this->permalink() ?>"><?php $this->title() ?></a>
作者:<a href="<?php $this->author->permalink(); ?>"><?php $this->author(); ?></a>
时间: <?php $this->date(); ?>
分类: <?php $this->category(','); ?>
<a href="<?php $this->permalink() ?>#comments"><?php $this->commentsNum('评论', '1 条评论', '%d 条评论'); ?></a>
标签：<?php $this->tags(','); ?>
<?php $this->content('- 阅读剩余部分 -'); ?>
<?php endwhile; ?>
<?php else: ?>暂无文章<?php endif; ?>
```

进入文章循环，输出文章，一句一句介绍

| 代码 | 解释 |
|:--|:--|
|`<?php if ($this->have()): ?>`| 判断是否有文章，没有的话输出提示 |
| `<?php while($this->next()): ?>` | 开始循环输出文章，与`<?php endwhile; ?>`对应 |
| `<?php $this->permalink() ?>` | 文章所在的链接 |
| `<?php $this->title() ?>` | 文章标题 |
| `<?php $this->author(); ?>` | 文章作者 |
| `<?php $this->author->permalink(); ?>` | 文章作者地址 |
| `<?php $this->date(); ?>` | 文章的发布日期，日期格式可在 typecho 后台**设置->阅读**中设置 |
| `<?php $this->category(','); ?>` | 文章所在分类 |
| `<?php $this->tags(','); ?>` | 文章标签 |
| `<?php $this->commentsNum('评论', '1 条评论', '%d 条评论'); ?>` | 文章评论数及链接 |
| `<?php $this->content('- 阅读剩余部分 -'); ?>` | 文章内容，其中的“- 阅读剩余部分 -”是显示摘要时隐藏部分的邀请链接，也可使用 `<?php $this->excerpt(140, '...'); ?>` 来进行自动截取文字内容，“140”是截取字符数量 |

> 注意：在单篇文章页（`$this->is('single')`）中，`$this->content()` 会忽略传入的 `$more` 参数，始终输出完整内容。

### 文章分页

```php
<?php $this->pageNav('«前一页', '后一页»'); ?>
```

也可以这样分开写

```php
<?php $this->pageLink('下一页','next'); ?>
<?php $this->pageLink('上一页'); ?>
```

`pageNav()` 完整签名为 `pageNav($prev, $next, $splitPage = 3, $splitWord = '...', $template = '')`，其中 `$template` 可传入字符串或数组，用于配置外层标签，如 `'wrapTag=ol&wrapClass=page-navigator'`。

### 其他说明

archive.php 代码同 index.php，区别就是 index.php 是显示首页的，而 archive.php 是显示某分类下的文章列表、搜索结果的。如果模板文件中不存在 archive.php，程序就会自动用 index.php 代替 archive.php。

> 模板查找顺序（见 `Widget\Archive::render()`）：先找 `归档类型/缩略名.php`（如 `category/default.php`），再找 `归档类型.php`（如 `category.php`），然后对单篇内容找 `single.php` 或 `archive.php`，最后回退到 `index.php`。单篇内容还会优先使用“自定义模板”（即自定义字段 `template` 指定的文件）。


## header.php

### 编码

打开这个文件，见到的第一个 php 代码就是：
```php
<meta charset="<?php $this->options->charset(); ?>">
```
调用默认的编码，现在最经常用都是 utf-8 吧。所以我通常是直接写成 utf-8，省去 php 处理时间。

#### 页面标题

```php
<title><?php $this->archiveTitle([
            'category'  =>  _t('分类 %s 下的文章'),
            'search'    =>  _t('包含关键字 %s 的文章'),
            'tag'       =>  _t('标签 %s 下的文章'),
            'author'    =>  _t('%s 发布的文章')
        ], '', ' - '); ?><?php $this->options->title(); ?></title>
```
`<?php $this->archiveTitle(); ?>`是当前页面的标题，`<?php $this->options->title(); ?>`是网站的标题。`archiveTitle()` 签名为 `archiveTitle($defines = null, $before = ' &raquo; ', $end = '')`。

### 导入样式

```php
<link rel="stylesheet" href="<?php $this->options->themeUrl('style.css'); ?>">
```
其中 style.css 是样式表文件相对模板目录的路径和文件名。

### 其它HTML头部信息
```php
<?php $this->header(); ?>
```
这是 typecho 的自有函数，会输出 HTML 头部信息（包含 description、keywords、canonical、RSS/Atom、Open Graph、评论回复脚本、反垃圾脚本等）；同时这个也是头部插件接口，有了它插件可以向网站头部插入 css 或者 js 代码。

### 网站名称与logo
```php
<?php if ($this->options->logoUrl): ?>
<a id="logo" href="<?php $this->options->siteUrl(); ?>">
<img src="<?php $this->options->logoUrl() ?>" alt="<?php $this->options->title() ?>" />
</a>
<?php else: ?>
<a id="logo" href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title() ?></a>
<p class="description"><?php $this->options->description() ?></p>
<?php endif; ?>
```
第一句的 if 判断是判断模板是否通过模板设置设置了 logo 的地址，如果设置了就显示 logo 图片，否则就显示博客标题。
`<?php $this->options->siteUrl(); ?>`是网站地址
`<?php $this->options->title() ?>`是网站名字
`<?php $this->options->description() ?>`是网站描述。
logo 部分的讲解将会在**functions.php**章节中详细讲解。

### 站内搜索

```php
<form id="search" method="post" action="<?php $this->options->siteUrl(); ?>" role="search">
<input type="text" id="s" name="s" class="text" placeholder="<?php _e('输入关键字搜索'); ?>" />
<button type="submit" class="submit"><?php _e('搜索'); ?></button>
</form>
```
当你的文章很多很多，这个搜索就必不可少。

### 页面导航
```php
<nav id="nav-menu" class="clearfix" role="navigation">
<a<?php if($this->is('index')): ?> class="current"<?php endif; ?> href="<?php $this->options->siteUrl(); ?>"><?php _e('首页'); ?></a>
<?php \Widget\Contents\Page\Rows::alloc()->to($pages); ?>
<?php while($pages->next()): ?>
<a<?php if($this->is('page', $pages->slug)): ?> class="current"<?php endif; ?> href="<?php $pages->permalink(); ?>" title="<?php $pages->title(); ?>"><?php $pages->title(); ?></a>
<?php endwhile; ?>
</nav>
```
其中`<?php $this->options->siteUrl(); ?>`是网站地址，然后下面的 while 循环是循环输出独立页面的，其中`<?php $pages->permalink(); ?>`是独立页面的超链接，`<?php $pages->title(); ?>`是独立页面的标题。

> 旧写法 `<?php $this->widget('Widget_Contents_Page_List')->to($pages); ?>` 依然可用（`Widget_Contents_Page_List` 是 `\Widget\Contents\Page\Rows` 的兼容别名，见 `var/Widget/Init.php` 中的 `__TYPECHO_CLASS_ALIASES__`）。新代码推荐使用命名空间类名 + `::alloc()`。



## sidebar.php

### 最新文章列表

```php
<ul class="widget-list">
<?php \Widget\Contents\Post\Recent::alloc()
            ->parse('<li><a href="{permalink}">{title}</a></li>'); ?>
</ul>
```
获取最新的文章标题，得到的 html 是
```html
<ul class="widget-list">
<li><a href="http://example.com/2008/12/31/sample-post-one">文章1的标题</a></li>
<li><a href="http://example.com/2008/12/31/sample-post-two">文章2的标题</a></li>
    <!-- 省略n个重复 -->
<li><a href="http://example.com/2008/12/31/sample-post-ten">文章10的标题</a></li>
</ul>
```
具体显示数量可在 typecho 后台**设置->阅读**中设置（选项 `postsListSize`）。

### 最新回复列表
```php
<ul class="widget-list">
<?php \Widget\Comments\Recent::alloc()->to($comments); ?>
<?php while($comments->next()): ?>
<li><a href="<?php $comments->permalink(); ?>"><?php $comments->author(false); ?></a>: <?php $comments->excerpt(35, '...'); ?></li>
<?php endwhile; ?>
</ul>
```
获取最新的回复，得到的 html 是
```html
<ul class="widget-list">
    <li>回复人名字: <a href="http://example.com/2008/12/31/sample-post#comments-12">回复的内容...</a></li>
    <li>回复人名字: <a href="http://example.com/2008/12/31/sample-post#comments-11">回复的内容...</a></li>
    <!-- 省略n个重复 -->
</ul>
```
其中`<?php $comments->excerpt(35, '...'); ?>`，“35”代表要回复内容截取的字的个数，“...”代表省略的意思，你可以自行修改。具体显示数量可在 typecho 后台**设置->评论**中设置（选项 `commentsListSize`）。

### 文章分类列表
```php
<?php \Widget\Metas\Category\Rows::alloc()->listCategories('wrapClass=widget-list'); ?>
```
效果如下
```html
<ul class="widget-list">
<li class="category-level-0 category-parent"><a href="分类1链接">分类1</a></li>
<li class="category-level-0 category-parent"><a href="分类2链接">分类2</a></li>
 <!-- 省略n个重复 -->
</ul>
```
如果有个分类3，分类4是上述分类2的子分类，那么效果如下
```html
<ul class="widget-list">
<li class="category-level-0 category-parent"><a href="分类1链接">分类1</a></li>
<li class="category-level-0 category-parent"><a href="分类2链接">分类2</a>
  <ul class="widget-list">
    <li class="category-level-1 category-child category-level-odd"><a href="分类3链接">分类3</a></li> 
    <li class="category-level-1 category-child category-level-odd"><a href="分类4链接">分类4</a></li>
  </ul>
</li>
 <!-- 省略n个重复 -->
</ul>
```

### 按月归档

```php
<ul class="widget-list">
            <?php \Widget\Contents\Post\Date::alloc('type=month&format=F Y')
            ->parse('<li><a href="{permalink}">{date}</a></li>'); ?>
</ul>
```
输出：
```html
<ul class="widget-list">
<li><a href="http://example.com/2018/11">November 2018</a></li>
<li><a href="http://example.com/2018/10">October 2018</a></li>
</ul>
```

### 登录登出

```php
<?php if($this->user->hasLogin()): ?>
<li class="last"><a href="<?php $this->options->adminUrl(); ?>"><?php _e('进入后台'); ?> (<?php $this->user->screenName(); ?>)</a></li>
<li><a href="<?php $this->options->logoutUrl(); ?>"><?php _e('退出'); ?></a></li>
<?php else: ?>
<li class="last"><a href="<?php $this->options->adminUrl('login.php'); ?>"><?php _e('登录'); ?></a></li>
<?php endif; ?>
```
这些是可有可无的，只是为了方便登录登出。`<?php $this->options->adminUrl(); ?>`是后台地址，`<?php $this->user->screenName(); ?>`用户昵称，`<?php $this->options->logoutUrl(); ?>`登出链接，`<?php $this->options->adminUrl('login.php'); ?>`登录链接。

### RSS地址

```php
<a href="<?php $this->options->feedUrl(); ?>"><?php _e('文章 RSS'); ?></a> <!-- 文章的RSS地址链接 -->
<a href="<?php $this->options->commentsFeedUrl(); ?>"><?php _e('评论 RSS'); ?></a><!-- 评论的RSS地址链接 -->
```

> 另有 `feedRssUrl()`、`feedAtomUrl()`、`commentsFeedRssUrl()`、`commentsFeedAtomUrl()` 可分别输出 RSS 1.0 / Atom 聚合地址。


## footer.php

页脚文件，推荐大家把一些较大的 js 放在这个文件中最后载入，不会影响阅读。看看我们的 footer 要讲解些什么？

### 版权声明等
```php
&copy; <?php echo date('Y'); ?> <a href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title(); ?></a>.
<?php echo sprintf(
    _t('由 <a href="%s">%s</a> 强力驱动'),
    \Typecho\Common::PROJECT_URL,
    $this->options->software
); ?>. <?php echo sprintf(
    _t('(基于 <a href="%s">Typecho</a> 的 Fork 版本)'),
    \Typecho\Common::PROJECT_ORIGIN_URL
); ?>
```
* `<?php echo date('Y'); ?>`是当前年份
* `<?php $this->options->siteUrl(); ?>`是网站地址
* `<?php $this->options->title(); ?>`是网站标题
* `$this->options->software` 输出程序名称（`TypechoRe`）

### 插件接口

```php
<?php $this->footer(); ?>
```
用于插件向页脚插入 css，js 文件等。


## post.php

post 页和 index 是差不多的，下面解释下 post.php 里面存在的 php 代码。

### 代码与说明

|代码|解释|
|:--|:--|
|`<?php $this->permalink() ?>`|文章地址|
|`<?php $this->title() ?>`|文章标题|
|`<?php $this->author->permalink(); ?>`|文章作者主页链接|
|`<?php $this->author(); ?>`|文章作者昵称|
|`<?php $this->date(); ?>`|文章发布时间|
|`<?php $this->category(','); ?>`|文章分类，多个分类中间用逗号隔开|
|`<?php $this->content(); ?>`|文章内容|
|`<?php $this->tags(', ', true, 'none'); ?>`|文章标签，多个标签间用逗号隔开，标签以带超链接的形式显示，如果不存在标签则显示 none|
|`<?php $this->need('comments.php'); ?>`|调用评论页|
|`<?php $this->thePrev('%s','没有了'); ?>`|带有超链接的上一篇文章的标题|
|`<?php $this->theNext('%s','没有了'); ?>`|带有超链接的下一篇文章的标题|

### 其他说明

page.php 代码同 post.php，区别就是 post 是用来显示文章的，而 page.php 是用来显示独立页面的。


## comments.php

### 评论列表
```php
<?php $this->comments()->to($comments); ?>
<?php if ($comments->have()): ?>
<h3><?php $this->commentsNum(_t('暂无评论'), _t('仅有一条评论'), _t('已有 %d 条评论')); ?></h3>
<?php $comments->listComments(); ?>
<?php $comments->pageNav(); ?>
<?php endif; ?>
```

判断文章是否存在评论，如果存在就输出评论；其中`<?php $comments->listComments(); ?>`是评论列表，`<?php $comments->pageNav(); ?>`是评论翻页按钮。

### 评论输入表单

```php
<!-- 判断设置是否允许对当前文章进行评论 -->
<?php if($this->allow('comment')): ?>
    <div id="<?php $this->respondId(); ?>" class="respond">
        <div class="cancel-comment-reply">
        <?php $comments->cancelReply(); ?>
        </div>
    
    	<h3 id="response"><?php _e('添加新评论'); ?></h3>
<!-- 输入表单开始 -->
    	<form method="post" action="<?php $this->commentUrl() ?>" id="comment-form" role="form">
<!-- 如果当前用户已经登录 -->
            <?php if($this->user->hasLogin()): ?>
<!-- 显示当前登录用户的用户名以及登出连接 -->
    		<p><?php _e('登录身份: '); ?><a href="<?php $this->options->profileUrl(); ?>"><?php $this->user->screenName(); ?></a>. <a href="<?php $this->options->logoutUrl(); ?>" title="Logout"><?php _e('退出'); ?> &raquo;</a></p>
<!-- 若当前用户未登录 -->
            <?php else: ?>
<!-- 要求输入名字、邮箱、网址 -->
    		<p>
                <label for="author" class="required"><?php _e('称呼'); ?></label>
    			<input type="text" name="author" id="author" class="text" value="<?php $this->remember('author'); ?>" required />
    		</p>
    		<p>
                <label for="mail"<?php if ($this->options->commentsRequireMail): ?> class="required"<?php endif; ?>><?php _e('Email'); ?></label>
    			<input type="email" name="mail" id="mail" class="text" value="<?php $this->remember('mail'); ?>"<?php if ($this->options->commentsRequireMail): ?> required<?php endif; ?> />
    		</p>
    		<p>
                <label for="url"<?php if ($this->options->commentsRequireUrl): ?> class="required"<?php endif; ?>><?php _e('网站'); ?></label>
    			<input type="url" name="url" id="url" class="text" placeholder="<?php _e('http://'); ?>" value="<?php $this->remember('url'); ?>"<?php if ($this->options->commentsRequireUrl): ?> required<?php endif; ?> />
    		</p>
            <?php endif; ?>
<!-- 输入要回复的内容 -->
    		<p>
                <label for="textarea" class="required"><?php _e('内容'); ?></label>
                <textarea rows="8" cols="50" name="text" id="textarea" class="textarea" required ><?php $this->remember('text'); ?></textarea>
            </p>
    		<p>
                <button type="submit" class="submit"><?php _e('提交评论'); ?></button>
            </p>
    	</form>
    </div>
<!-- 若当前文章不允许进行评论 -->
    <?php else: ?>
    <h3><?php _e('评论已关闭'); ?></h3>
    <?php endif; ?>
```
具体请对应上述代码中注释自行理解。

> 注意：评论网址选项的变量名在 1.2 之后由 `commentsRequireURL` 修正为 `commentsRequireUrl`（升级程序会自动重命名该选项），模板中请使用 `commentsRequireUrl`。


## functions.php

`function themeConfig($form)` 内的代码是模板设置功能

### logo设置

```php
$logoUrl = new \Typecho\Widget\Helper\Form\Element\Text(
    'logoUrl',
    null,
    null,
    _t('站点 LOGO 地址'),
    _t('在这里填入一个图片 URL 地址, 以在网站标题前加上一个 LOGO')
);

$form->addInput($logoUrl->addRule('url', _t('请填写一个合法的URL地址')));
```

这行代码就是在模板设置处添加一个 logo 设置，可以添加一个图片地址作为 logo，添加好了通过如下代码即可输出这个图片

```php
<!--判断logo已被设置-->
<?php if ($this->options->logoUrl): ?>
<!--给logo图片加上本站超链接-->
<a id="logo" href="<?php $this->options->siteUrl(); ?>">
<!--显示logo-->
<img src="<?php $this->options->logoUrl() ?>" alt="<?php $this->options->title() ?>" />
</a>
<?php endif; ?>
```

此处对应 header.php 中的 logo 显示。

> 旧写法 `new Typecho_Widget_Helper_Form_Element_Text(...)` 仍可用，其会被自动映射到命名空间类。新代码推荐使用 `\Typecho\Widget\Helper\Form\Element\Text` 等。

### 显示开关

```php
$sidebarBlock = new \Typecho\Widget\Helper\Form\Element\Checkbox(
    'sidebarBlock',
    [
        'ShowRecentPosts'    => _t('显示最新文章'),
        'ShowRecentComments' => _t('显示最近回复'),
        'ShowCategory'       => _t('显示分类'),
        'ShowArchive'        => _t('显示归档'),
        'ShowOther'          => _t('显示其它杂项')
    ],
    ['ShowRecentPosts', 'ShowRecentComments', 'ShowCategory', 'ShowArchive', 'ShowOther'],
    _t('侧边栏显示')
);

$form->addInput($sidebarBlock->multiMode());
```

是一些开关，这里拿 ShowCategory 举例，如果勾选他
```php
<?php if (!empty($this->options->sidebarBlock) && in_array('ShowCategory', $this->options->sidebarBlock)): ?>
勾选了就会显示这里的文字
<?php endif; ?>
```

这里对应的是 sidebar.php 中的最新文章，最新评论，文章分类，归档等显示开关。

### 其他说明

参考以上代码，照葫芦画瓢，可以增加自己需要的模板设置。

## 文件结构说明


| 文件名 | 作用 | 必须 |
|:--|:--|:--|
| style.css | 主题样式文件 | 否 |
| screenshot.png | 主题缩略图,图片后缀支持 jpg,png,gif,bmp,jpeg,webp,avif | 否 |
| index.php | 首页以及说明文件 | 是 |
| 404.php | 404页面文件 | 否 |
| archive.php | 通用（分类、搜索、标签、作者）页面文件 | 否 |
| category.php | 分类页面文件 | 否 |
| search.php | 搜索页面文件 | 否 |
| tag.php | 标签页面文件 | 否 |
| author.php | 作者页面文件 | 否 |
| comments.php | 评论页面文件 | 否 |
| footer.php | 底部页面文件 | 否 |
| functions.php | 主题函数文件 | 否 |
| header.php | 头部页面文件 | 否 |
| page.php | 独立页面文件 | 否 |
| post.php | 日志页面文件 | 否 |
| sidebar.php | 侧边栏页面文件 | 否 |

**PS：**
如果 archive.php 不存在，index.php 也会作为通用页面，实现 archive.php 的工作。
