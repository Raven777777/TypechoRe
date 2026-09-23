# 常用统计

### 一些常用统计
```php
<?php \Widget\Stat::alloc()->to($stat); ?>
文章总数：<?php $stat->publishedPostsNum() ?>篇
分类总数：<?php $stat->categoriesNum() ?>个
评论总数：<?php $stat->publishedCommentsNum() ?>条
页面总数：<?php $stat->publishedPagesNum() ?>个
当前作者的文章总数：<?php $stat->myPublishedPostsNum() ?>篇
```

> 旧写法 `Typecho_Widget::widget('Widget_Stat')` 依然可用（`Widget_Stat` 会被自动映射到 `\Widget\Stat`）。

### 详细具体参数如下
描述来自源码注释，不是特别清晰，有空我会重新梳理下

|说明|代码|
|:--|:--|
|获取已发布的文章数目|publishedPostsNum|
|获取待审核的文章数目|waitingPostsNum|
|获取草稿文章数目|draftPostsNum|
|获取当前用户已发布的文章数目|myPublishedPostsNum|
|获取当前用户待审核文章数目|myWaitingPostsNum|
|获取当前用户草稿文章数目|myDraftPostsNum|
|获取当前文章已发布的文章数目|currentPublishedPostsNum|
|获取当前文章待审核文章数目|currentWaitingPostsNum|
|获取当前文章草稿文章数目|currentDraftPostsNum|
|获取已发布页面数目|publishedPagesNum|
|获取草稿页面数目|draftPagesNum|
|获取当前显示的评论数目|publishedCommentsNum|
|获取当前待审核的评论数目|waitingCommentsNum|
|获取当前垃圾评论数目|spamCommentsNum|
|获取当前用户显示的评论数目|myPublishedCommentsNum|
|获取当前用户待审核的评论数目|myWaitingCommentsNum|
|获取当前用户垃圾评论数目|mySpamCommentsNum|
|获取当前文章的评论数目|currentCommentsNum|
|获取当前文章显示的评论数目|currentPublishedCommentsNum|
|获取当前文章待审核的评论数目|currentWaitingCommentsNum|
|获取当前文章垃圾评论数目|currentSpamCommentsNum|
|获取分类数目|categoriesNum|
|获取标签数目|tagsNum|

该统计函数来自源码 typecho/var/Widget/Stat.php 中

> 上述 `xxxNum` 均为计算属性（通过魔术方法 `___xxxNum()` 提供），在模板中也可以直接当属性读取，如 `$stat->publishedPostsNum`。
