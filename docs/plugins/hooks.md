### 默认接口
在Typecho中只要这个类是继承自 `Typecho\Widget` 基类，它就默认具备了这个插件接口。接口开发者可以使用这个接口无缝地向当前的Class中注入方法。

比如我要给 `Widget\Archive` 类增加一个方法获取当前文章的字数(charactersNum)，只需要在你的插件`activate`方法中声明
```php
\Typecho\Plugin::factory('Widget_Archive')->___charactersNum = ['MyPlugin_Plugin', 'charactersNum'];
```
注意，我们在方法名前面加三个下划线表示这是一个内部方法。而实现这个方法也很简单，因为系统会将当前的对象作为参数传递给你
```php
public static function charactersNum($archive)
{
    return mb_strlen($archive->text, 'UTF-8');
}
```
那么这个方法就已经植入到 `Widget\Archive` 中去了，你在模版中可以直接调用如下代码输出它
```php
<?php $this->charactersNum(); ?>
```

> 说明：
> * `\Typecho\Plugin::factory()` 的句柄会被规范化为下划线形式，所以 `'Widget_Archive'`、`'Widget\Archive'`、`Widget\Archive::class` 是等价的。
> * 注册组件时可以使用 `组件名_权重` 的形式控制执行顺序，例如 `footer_100`，权重越大越先执行（默认 10）。

### Widget\Archive
|接口|参数|描述|
|:--|:--|:--|
|select|$archive Widget\Archive对象|构建查询前触发，可返回自定义查询以接管|
|handleInit|$archive Widget\Archive对象<br>$select Typecho\Db\Query对象|handle初始化|
|handle|$type<br>$archive Widget\Archive对象<br>$select Typecho\Db\Query对象|未知归档类型时触发|
|indexHandle|$archive Widget\Archive对象<br>$select Typecho\Db\Query对象|当访问最近文章首页以及分页时被触发|
|error404Handle|$archive Widget\Archive对象<br>$select Typecho\Db\Query对象|当访问404页面时被触发|
|singleHandle|$archive Widget\Archive对象<br>$select Typecho\Db\Query对象|当访问单独页面时被触发(文章，页面，附件)|
|categoryHandle|$archive Widget\Archive对象<br>$select Typecho\Db\Query对象|当访问按分类归档页面时被触发|
|tagHandle|$archive Widget\Archive对象<br>$select Typecho\Db\Query对象|当访问按标签归档页面时被触发|
|authorHandle|$archive Widget\Archive对象<br>$select Typecho\Db\Query对象|当访问按作者归档页面时被触发|
|dateHandle|$archive Widget\Archive对象<br>$select Typecho\Db\Query对象|当访问按日期归档页面时被触发|
|search|$keywords 搜索关键词<br>$archive Widget\Archive对象|这是一个独占接口，当访问搜索页面时被触发，当这个接口被实现后，系统自己的搜索动作将不会继续，你需要在这个接口内自己push搜索的数据到Widget\Archive对象，此接口多用于自己实现站内搜索来替换默认的|
|searchHandle|$archive Widget\Archive对象<br>$select Typecho\Db\Query对象|当访问搜索页面时被触发|
|query|$archive Widget\Archive对象<br>$select Typecho\Db\Query对象|Widget\Archive所有的数据库查询动作最终将由一个query方法来执行，此接口在query方法内，多用于hack某些查询语句|
|pageNav|$currentPage<br>$total<br>$pageSize<br>$prev<br>$next<br>$splitPage<br>$splitWord<br>$template<br>$query|分页输出，实现后系统默认分页不再输出|
|headerOptions|$allows 头部选项数组<br>$archive Widget\Archive对象|过滤head部分输出的元数据项（filter 接口）|
|header|$header 已生成的头部HTML<br>$archive Widget\Archive对象|主题head部分内容接口，一般用于引入css|
|footer|$archive Widget\Archive对象|主题页脚部分内容接口，一般用于引入JavaScript|
|beforeRender|$archive Widget\Archive对象|在渲染主题前|
|afterRender|$archive Widget\Archive对象|在渲染主题后|

### Widget\Feed
|接口|参数|描述|
|:--|:--|:--|
|commentFeedItem|$feedType<br>$comments|评论聚合项|
|feedItem|$feedType<br>$archive Widget\Archive对象|内容聚合项|

### Widget\Feedback
|接口|参数|描述|
|:--|:--|:--|
|comment|$comment<br>$content|评论内容过滤（filter 接口）|
|finishComment|$feedback Widget\Feedback对象||
|trackback|$trackback<br>$content|引用通告内容过滤（filter 接口）|
|finishTrackback|$feedback Widget\Feedback对象||

### Widget\Login
|接口|参数|描述|
|:--|:--|:--|
|loginFail|$user Widget\User对象<br>$name<br>$password<br>$temporarily<br>$expire||
|loginSucceed|$user Widget\User对象<br>$name<br>$password<br>$temporarily<br>$expire||
|simpleLoginFail|$user Widget\User对象||
|simpleLoginSucceed|$user Widget\User对象<br>$authUser 被登录用户对象||

### Widget\Logout
|接口|参数|描述|
|:--|:--|:--|
|logout|无||

### Widget\Register
|接口|参数|描述|
|:--|:--|:--|
|register|$dataStruct|注册数据结构过滤（filter 接口）|
|finishRegister|$register Widget\Register对象||

### Widget\Upload
|接口|参数|描述|
|:--|:--|:--|
|beforeUpload|$result||
|upload|$upload Widget\Upload对象||
|beforeModify|$result||
|modify|$upload Widget\Upload对象||
|uploadHandle|$file 上传文件信息|接管上传，返回附件信息数组则不再执行默认上传|
|modifyHandle|$content<br>$file|接管文件修改|
|deleteHandle|$content|接管附件删除|
|attachmentHandle|$attachment 附件配置对象|生成附件访问地址|
|attachmentDataHandle|$content|读取附件数据|

### Widget\User
|接口|参数|描述|
|:--|:--|:--|
|login|$name<br>$password<br>$temporarily<br>$expire|接管登录验证（trigger 接口，返回真值即视为登录成功）|
|hashValidate|$password<br>$user['password']|密码校验（trigger 接口）|
|loginSucceed|$user Widget\User对象<br>$name<br>$password<br>$temporarily<br>$expire||
|loginFail|$user Widget\User对象<br>$name<br>$password<br>$temporarily<br>$expire||
|logout|无||
|simpleLoginSucceed|$user Widget\User对象<br>$authUser||

### Widget\XmlRpc
|接口|参数|描述|
|:--|:--|:--|
|textFilter|$input['text']<br>$xmlRpc Widget\XmlRpc对象|内容过滤（filter 接口）|
|upload|$xmlRpc Widget\XmlRpc对象||
|pingback|$pingback<br>$post|引用通告过滤（filter 接口）|
|finishPingback|$xmlRpc Widget\XmlRpc对象||

### Widget\Backup
|接口|参数|描述|
|:--|:--|:--|
|export|$fp 文件句柄|备份导出时追加自定义数据|
|import|$type<br>$header<br>$body|备份导入时处理自定义数据|

### Widget\Action\Sitemap
|接口|参数|描述|
|:--|:--|:--|
|sitemapUrls|$urls<br>$sitemap Widget\Action\Sitemap对象|过滤或扩展当前 Sitemap 文件中的 URL 列表（filter 接口）|
|sitemapIndex|$sitemaps<br>$sitemap Widget\Action\Sitemap对象|过滤或扩展 Sitemap Index 中的子 Sitemap 列表（filter 接口）|

> 启用分页后，`sitemapUrls` 会在每个分页子 Sitemap 上分别触发；插件返回的非标准 `changefreq`、`priority`、`lastmod`、跨主机 URL 或超长 URL 会被自动忽略或规范化。

### Widget\Base\Comments
以下接口对 `Widget\Feedback`、`Widget\Comments\Admin`、`Widget\Comments\Archive`、`Widget\Comments\Edit`、`Widget\Comments\Ping`、`Widget\Comments\Recent` 等评论类同样生效。

|接口|参数|描述|
|:--|:--|:--|
|content|$text<br>$comments 评论对象|评论内容解析（trigger filter 接口）|
|contentEx|$text<br>$comments 评论对象|评论内容二次过滤（filter 接口）|
|filter|$row<br>$comments 评论对象|评论行数据过滤（filter 接口）|
|gravatar|$size<br>$rating<br>$default<br>$comments 评论对象|接管头像输出|
|autoP|$text|AutoP 解析（trigger filter 接口）|
|markdown|$text|Markdown 解析（trigger filter 接口）|

### Widget\Base\Contents
以下接口对 `Widget\Archive`、`Widget\Upload`、`Widget\XmlRpc`、`Widget\Contents\Related`、`Widget\Contents\Attachment\*`、`Widget\Contents\Page\Rows`、`Widget\Contents\Post\Admin`、`Widget\Contents\Page\Admin`、`Widget\Contents\Post\Edit`、`Widget\Contents\Page\Edit`、`Widget\Contents\Post\Recent`、`Widget\Contents\Related\Author` 等内容类同样生效。

|接口|参数|描述|
|:--|:--|:--|
|excerpt|$content<br>$contents 内容对象|摘要生成（filter 接口）|
|excerptEx|$excerpt<br>$contents 内容对象|摘要二次过滤（filter 接口）|
|content|$text<br>$contents 内容对象|正文解析（trigger filter 接口）|
|contentEx|$content<br>$contents 内容对象|正文二次过滤（filter 接口）|
|isFieldReadOnly|$name|字段是否只读|
|filter|$row<br>$contents 内容对象|内容行数据过滤（filter 接口）|
|title|$title<br>$contents 内容对象|标题过滤（trigger filter 接口）|
|autoP|$text|AutoP 解析|
|markdown|$text|Markdown 解析|
|getDefaultFieldItems|$layout|默认自定义字段项（对 Post/Page/Attachment 编辑类生效）|

### Widget\Base\Metas
|接口|参数|描述|
|:--|:--|:--|
|filter|$row<br>$metas 元数据对象|元数据行过滤（filter 接口）。对 `Widget\Metas\Category\Edit`、`Widget\Metas\Category\Rows`、`Widget\Metas\Category\Admin`、`Widget\Metas\Tag\Cloud`、`Widget\Metas\Tag\Admin`、`Widget\Metas\Tag\Edit` 生效|

### Widget\Base\Users
|接口|参数|描述|
|:--|:--|:--|
|filter|$row<br>$users 用户对象|用户行过滤（filter 接口）。对 `Widget\Login`、`Widget\Logout`、`Widget\Register`、`Widget\Users\Admin`、`Widget\Users\Author`、`Widget\Users\Edit`、`Widget\Users\Profile` 生效|

### Widget\Comments\Archive
|接口|参数|描述|
|:--|:--|:--|
|listComments|$singleCommentOptions<br>$archive Widget\Comments\Archive对象|接管评论列表输出|
|reply|$word<br>$archive Widget\Comments\Archive对象|接管回复按钮|
|cancelReply|$word<br>$archive Widget\Comments\Archive对象|接管取消回复按钮|

### Widget\Comments\Edit
|接口|参数|描述|
|:--|:--|:--|
|mark|$comment<br>$edit Widget\Comments\Edit对象<br>$status||
|delete|$comment<br>$edit Widget\Comments\Edit对象||
|finishDelete|$comment<br>$edit Widget\Comments\Edit对象||
|edit|$comment<br>$edit Widget\Comments\Edit对象|评论编辑数据过滤（filter 接口）|
|finishEdit|$edit Widget\Comments\Edit对象||
|comment|$comment<br>$edit Widget\Comments\Edit对象||
|finishComment|$edit Widget\Comments\Edit对象||

### Widget\Contents\Post\Edit / Widget\Contents\Page\Edit / Widget\Contents\Attachment\Edit
|接口|参数|描述|
|:--|:--|:--|
|getDefaultFieldItems|$layout|默认自定义字段项|
|write|$contents<br>$edit 编辑对象|写入数据过滤（filter 接口）|
|finishPublish|$contents<br>$edit 编辑对象||
|finishSave|$contents<br>$edit 编辑对象||
|delete|$post<br>$edit 编辑对象||
|finishDelete|$post<br>$edit 编辑对象||
|mark|$status<br>$post<br>$edit 编辑对象|（文章/页面编辑类）|
|finishMark|$status<br>$post<br>$edit 编辑对象|（文章/页面编辑类）|

### Widget\Metas\Category\Rows
|接口|参数|描述|
|:--|:--|:--|
|listCategories|$categoryOptions<br>$list Widget\Metas\Category\Rows对象|接管分类列表输出|

### Widget\Contents\Page\Rows
|接口|参数|描述|
|:--|:--|:--|
|listPages|$pageOptions<br>$list Widget\Contents\Page\Rows对象|接管独立页面列表输出|

### 后台页面接口

|句柄|组件|参数|描述|
|:--|:--|:--|:--|
|index.php|begin / end|无|程序入口开始/结束|
|admin/common.php|begin|无|后台公共初始化|
|admin/header.php|header|$header 头部HTML|后台头部过滤（filter 接口）|
|admin/footer.php|begin / end|无|后台页脚开始/结束|
|admin/menu.php|navBar|无|后台导航栏|
|admin/profile.php|bottom|无|个人设置页底部|
|admin/theme-editor.php|bottom|$files|主题编辑器底部|
|admin/write-post.php|content / option / advanceOption / richEditor / bottom|$post|撰写文章页各部分|
|admin/write-page.php|content / option / advanceOption / richEditor / bottom|$page|撰写页面页各部分|
|admin/write-js.php|write|无|撰写页脚本|
|admin/editor-js.php|markdownEditor|$content|Markdown 编辑器|

> `richEditor` 为 trigger 接口：实现后系统不再输出默认富文本编辑器。
