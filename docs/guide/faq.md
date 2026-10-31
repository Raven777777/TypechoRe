### 分类/标签计数不准
解决方法：在后台进入分类/标签管理页，选择所有分类/标签，点击【选中项】→【刷新】即可（对应 `Widget\Metas\Category\Edit::refreshCategory` 与 `Widget\Metas\Tag\Edit::refreshTag`）。

### 如何自定义附件上传目录
在 `config.inc.php` 里新增一行下列代码即可，其中 `your_upload_dir` 是你要上传的目录：
```php
define('__TYPECHO_UPLOAD_DIR__', 'your_upload_dir');
```

> 如需修改上传文件的物理根目录，可额外定义 `__TYPECHO_UPLOAD_ROOT_DIR__`。

### 升级完后网站提示 500 错误
原因 1：找不到模板。解决方法：进入后台，重新选择模板。
原因 2：权限问题。解决方法：对 `typecho` 目录重新设定权限，目录 `755`、文件 `644`（例如 `chmod -Rf 755 *` 后再对文件执行 `chmod -Rf 644 *`）。

### 匿名访问一直显示 `BYPASS`，缓存没有命中

先确认后台「设置 → 基本设置」已开启缓存，并且没有启用插件。缓存只处理无 Cookie、无 Authorization、无查询参数的匿名 GET HTML 请求；登录、实际 Cookie、查询参数、后台/动作/feed/Sitemap/XML-RPC 路径都会绕过。查看响应头 `X-Typecho-Page-Cache`：首次 `MISS` 后，TTL 内再次访问应为 `HIT`。

若条件符合但仍是 `BYPASS`，检查 PHP-FPM 错误日志，以及 PHP-FPM 实际生效的 `sys_get_temp_dir()`、目录写权限和 `open_basedir`。`open_basedir` 必须允许访问临时目录（通常为 `/tmp`）；也可在 `config.inc.php` 用 `__TYPECHO_PAGE_CACHE_DIR__` 指定 Web 根目录之外的可写缓存目录。缓存错误会失败开放，页面正常渲染但不缓存。日志若提示 `PageCache::key(): Argument #3 ($language) must be of type string, null given`，请升级到包含该修复的最新版。

> Nginx 的 FastCGI 参数即使把空 Cookie 变量传给 PHP，也应视为没有 Cookie；不要为了命中缓存伪造访客 Cookie。

### https不生效
当你用 `https` 的时候 `typecho` 会自动识别的，但是因为大家的环境千差万别，会有识别错误的情况，比如你 `https` 都布置好了，但是程序依旧只会加载 `http` 的，所以该常量的作用就是强制程序使用 `https`，解决识别错误的情况。 用法 在`config.inc.php`最后面插入

```php
define('__TYPECHO_SECURE__', true);
```

> 注意：旧版本曾使用拼写错误的常量名 `__TYPECHO_SECUER__`，当前版本已修正为 `__TYPECHO_SECURE__`，请使用正确拼写。
