# 模板常用函数

### 获取模板版本号

```php
function get_theme_version()
{
    $info = \Typecho\Plugin::parseInfo(__DIR__ . '/index.php');
    return $info['version'];
}
```

前台用`<?php echo get_theme_version();?>`来调用输出，用于给模板css和js添加属性。

> 旧写法 `Typecho_Plugin::parseInfo(...)` 依然可用，会自动映射到 `\Typecho\Plugin`。

### themeInit重写函数

```php
function themeInit($archive)
{
    \Utils\Helper::options()->commentsMaxNestingLevels = 999;//评论回复楼层最高999层.这个正常设置最高只有7层
    \Utils\Helper::options()->commentsAntiSpam = false;//强制评论关闭反垃圾保护
    if ($archive->is('author')) {
        $archive->parameter->pageSize = 50; // 作者页面每50篇文章分页一次
    }
    if ($archive->is('category', 'av')) {
        $archive->parameter->pageSize = 9; // 分类缩略名为av的分类列表每9篇文章分页一次
    }
    $archive->content = a_class_replace($archive->content);//文章内容，让a_class_replace函数处理
}
function a_class_replace($content)
{
    $content = preg_replace('#<a(.*?) href="([^"]*/)?(([^"/]*)\.[^"]*)"(.*?)>#',
        '<a$1 href="$2$3"$5 target="_blank">', $content);//给文章每个超链接点击后新窗口打开，原理就是用正则替换文章内容
    return $content;
}
```

> `themeInit($archive)` 会在 `Widget\Archive` 渲染前被调用（若主题 `functions.php` 中定义了该函数）。`Helper::options()` 也可继续使用（`Helper` 是 `\Utils\Helper` 的别名）。

### 文章阅读次数统计(cookie版)

```php
function get_post_view($archive)
{
    $cid    = $archive->cid;
    $db     = \Typecho\Db::get();
    $prefix = $db->getPrefix();
    if (!array_key_exists('views', $db->fetchRow($db->select()->from('table.contents')->page(1, 1)))) {
        $db->query('ALTER TABLE `' . $prefix . 'contents` ADD `views` INT(10) DEFAULT 0;');
        echo 0;
        return;
    }
    $row = $db->fetchRow($db->select('views')->from('table.contents')->where('cid = ?', $cid));
    if ($archive->is('single')) {
        $views = \Typecho\Cookie::get('extend_contents_views');
        if (empty($views)) {
            $views = array();
        } else {
            $views = explode(',', $views);
        }
        if (!in_array($cid, $views)) {
            $db->query($db->update('table.contents')->rows(array('views' => (int) $row['views'] + 1))->where('cid = ?', $cid));
            array_push($views, $cid);
            $views = implode(',', $views);
            \Typecho\Cookie::set('extend_contents_views', $views); //记录查看cookie
        }
    }
    echo $row['views'];
}
```

在需要显示次数的地方 (如 index.php，post.php,page.php) 加下边的代码`<?php get_post_view($this) ?>`，post和page里必须加否则阅读次数不会增加，如果不想显示，可以用css隐藏。

### 判断最新帖子显示图标

例如24小时内发布的贴，需要一个标志来完成。这里是用判断输入特殊字符，再用CSS判断完成的。
```php
/**
* 判断时间区间
*
* 使用方法  if(timeZone($this->date->timeStamp)) echo 'ok';
*/
function timeZone($from)
{
    $now = new \Typecho\Date(\Typecho\Date::gmtTime());
    return $now->timeStamp - $from < 24 * 60 * 60 ? true : false;
}
```
在index.php中调用`<?php if(timeZone($this->date->timeStamp)) echo ' new'; ?>`。

### 页面加载耗时代码

```php
function timer_start()
{
    global $timestart;
    $mtime = explode(' ', microtime());
    $timestart = $mtime[1] + $mtime[0];
    return true;
}
timer_start();
function timer_stop($display = 0, $precision = 3)
{
    global $timestart, $timeend;
    $mtime = explode(' ', microtime());
    $timeend = $mtime[1] + $mtime[0];
    $timetotal = $timeend - $timestart;
    $r = number_format($timetotal, $precision);
    if ($display) {
        echo $r;
    }
    return $r;
}
```

然后，模板部分加入`<?php timer_stop() ?>`。

### 文章缩略图
```php
function showThumbnail($widget)
{
    $mr = '默认图片地址';
    $attach = $widget->attachments(1)->attachment;
    $pattern = '/\<img.*?src\=\"(.*?)\"[^>]*>/i';
    if (preg_match_all($pattern, $widget->content, $thumbUrl)) {
        echo $thumbUrl[1][0];
    } elseif ($attach && $attach->isImage) {
        echo $attach->url;
    } else {
        echo $mr;
    }
}
```

使用`<?php showThumbnail($this); ?>`调用。

> 注意：当文章没有附件时 `$widget->attachments(1)->attachment` 为 `null`，因此需要加 `$attach &&` 判断。

### 随机文章

```php
function theme_random_posts()
{
    $db = \Typecho\Db::get();
    $sql = $db->select()->from('table.contents')
        ->where('status = ?', 'publish')
        ->where('type = ?', 'post')
        ->where('created < ?', \Widget\Options::alloc()->time)
        ->limit(6)
        ->order('RAND()');
    \Widget\Contents\From::allocWithAlias('random', ['query' => $sql])
        ->parse('<li class="archive-post"><a class="archive-post-title" href="{permalink}">{title}</a></li>');
}
```

前台调用`<?php theme_random_posts();?>`。

> 旧写法使用 `Typecho_Widget::widget('Widget_Abstract_Contents')->filter($val)`，但当前 `filter()` 不再生成 `permalink`，因此改为使用 `Widget\Contents\From` 传入自定义查询，既能得到完整字段也能直接使用 `{permalink}` 模板。

### 自定义上下篇链接

内置的 `thePrev()` / `theNext()` 已支持自定义链接文字与样式：

```php
<?php $this->thePrev('%s', '没有了', ['title' => '上一篇']); ?>
<?php $this->theNext('%s', '没有了', ['title' => '下一篇']); ?>
```

如果确实需要自己写函数，可以借助 `Widget\Contents\From` 查询相邻内容：

```php
/**
* 显示下一篇
*
* @param Widget\Archive $widget 当前归档对象
* @param string $default 如果没有下一篇,显示的默认文字
*/
function theNext($widget, $default = null)
{
    $db = \Typecho\Db::get();
    $sql = $db->select()->from('table.contents')
        ->where('table.contents.created > ?', $widget->created)
        ->where('table.contents.status = ?', 'publish')
        ->where('table.contents.type = ?', $widget->type)
        ->where("table.contents.password IS NULL OR table.contents.password = ''")
        ->order('table.contents.created', \Typecho\Db::SORT_ASC)
        ->limit(1);
    $content = \Widget\Contents\From::allocWithAlias('next', ['query' => $sql]);
    if ($content->have()) {
        echo '<a href="' . $content->permalink . '" title="' . $content->title . '">下一篇</a>';
    } else {
        echo $default;
    }
}
```

（`thePrev` 同理，只需把比较符和排序方向反过来。）

### N天内评论最多的文章
代码作用是N天内评论最多的N篇文章，这个N你可以自己定义，自己修改。

```php
<?php
function rmcp($days = 30, $num = 5)
{
    $time = time() - (24 * 60 * 60 * $days);
    $db = \Typecho\Db::get();
    $sql = $db->select()->from('table.contents')
        ->where('created >= ?', $time)
        ->where('type = ?', 'post')
        ->limit($num)
        ->order('commentsNum', \Typecho\Db::SORT_DESC);
    \Widget\Contents\From::allocWithAlias('rmcp', ['query' => $sql])
        ->parse('<li><a href="{permalink}">{title}</a><span class="subcolor">已有评论数：{commentsNum}</span></li>');
}
?>
```
模板调用`<?php rmcp(60,5);?>`这个调用的意思是2个月内评论最多的前5篇文章。

### 统计当前分类和子分类文章总数
typecho发布一篇文章，然后只勾选子分类，然后发布。
父分类输出分类的文章数量，并没有包含这个新发布的文章。所以有了以下函数

```php
function fenleinum($id)
{
    $db = \Typecho\Db::get();
    $po = $db->select('table.metas.count')->from('table.metas')->where('parent = ?', $id)->orWhere('mid = ?', $id);
    $pom = $db->fetchAll($po);
    $num = count($pom);
    $shu = 0;
    for ($x = 0; $x < $num; $x++) {
        $shu = $pom[$x]['count'] + $shu;
    }
    echo $shu;
}
```

前台调用，可以在分类列表循环中输入下面代码，分类mid需要根据自己的代码自填

```php
<?php fenleinum(分类mid); ?>
```
