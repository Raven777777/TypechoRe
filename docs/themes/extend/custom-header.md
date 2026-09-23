# 自定义头部信息输出

### 默认输出

在默认的模板中，头部信息的输出的结果大概是这样的
```html
<meta name="description" content="Just So So ..." />
<meta name="keywords" content="typecho,php,blog" />
<meta name="generator" content="TypechoRe 1.3.1" />
<meta name="template" content="default" />
<link rel="pingback" href=".../action/xmlrpc" />
<link rel="EditURI" type="application/rsd+xml" title="RSD" href=".../action/xmlrpc?rsd" />
<link rel="wlwmanifest" type="application/wlwmanifest+xml" href=".../action/xmlrpc?wlw" />
<link rel="alternate" type="application/rss+xml" title="... RSS 2.0" href=".../feed/" />
<link rel="alternate" type="application/rdf+xml" title="... RSS 1.0" href=".../feed/rss/" />
<link rel="alternate" type="application/atom+xml" title="... ATOM 1.0" href=".../feed/atom/" />
```
此外在单篇内容页还会输出 `<link rel="canonical" ... />`，并在开启相关选项时输出 Open Graph / Twitter Card 元数据（由 `social` 项控制）以及评论回复、反垃圾脚本。

### 操作函数

打开模板中的 header.php 文件，找到下面这句

```php
<?php $this->header(); ?>
```

加上你要设置的参数即可，比如：
```php
<?php $this->header('keywords=&generator=&template=&pingback=&xmlrpc=&wlw='); ?>
```
以上代码即可过滤关键词、程序、模板名称、文章引用、离线写作等信息的输出，具体效果如下。
```html
<meta name="description" content="..." />
<link rel="alternate" type="application/rss+xml" title="... RSS 2.0" href=".../feed/" />
<link rel="alternate" type="application/rdf+xml" title="... RSS 1.0" href=".../feed/rss/" />
<link rel="alternate" type="application/atom+xml" title="... ATOM 1.0" href=".../feed/atom/" />
```

可用的参数项如下（对应 `Widget\Archive::header()` 中的 `$allows` 数组）：

*   keywords：关键词
*   description：描述、摘要
*   rss1：feed rss1.0
*   rss2：feed rss2.0
*   atom：feed atom
*   generator：程序版本等
*   template：模板名称
*   pingback：文章引用
*   xmlrpc：离线写作（EditURI）
*   wlw：m$ 的离线写作工具
*   commentReply：评论回复
*   antiSpam：反垃圾评论
*   social：Open Graph / Twitter Card 等社交元数据

**说明**
等号（=）为空则不输出该项目，各个参数之间使用 “&” 连接。 如果需要自定义 rss 地址，只填上 rss2=feed订阅地址 即可。
