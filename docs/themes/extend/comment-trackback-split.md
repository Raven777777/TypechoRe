# 分离文章的评论和引用通告

> 说明：早期版本可以通过 `$this->comments('comment')`、`$this->comments('pingback')` 之类的参数来按类型筛选评论。当前版本 `Widget\Archive::comments()` 已不再接收类型参数，评论与引用通告（trackback / pingback）分别由两个不同的组件输出。

打开模板的 comments.php 文件，找到通篇的核心语句：
```php
<?php $this->comments()->to($comments); ?>
```
它返回 `Widget\Comments\Archive` 对象，用于输出普通评论。

### 只显示评论

在后台 **设置 → 评论 → 评论显示** 中勾选“只显示评论，不显示引用通告”（对应选项 `commentsShowCommentOnly`），
`comments()` 就会在查询时排除 `type <> 'comment'` 的记录，此时只输出普通评论：

```php
<?php $this->comments()->to($comments); ?><!-- 关键 -->
<?php if ($comments->have()) : ?>
	<ol>
	<?php while ($comments->next()) : ?>
	<li id="<?php $comments->theId() ?>">
		<div class="comment_data">
			<?php $comments->gravatar(32); ?>
			<span><?php $comments->author() ?></span> Says:<br />
			<?php $comments->date('F jS, Y'); ?> at <?php $comments->date('h:i a'); ?>
		</div>
		<div class="comment_text"><?php $comments->content() ?></div>
	</li>
	<?php endwhile; ?>
	</ol>
<?php endif; ?>
```

> `gravatar()` 当前签名为 `gravatar(int $size = 32, ?string $default = null, $highRes = false)`，会自动输出带 `avatar` class 的 `<img>`。

### 单独输出引用通告（trackback / pingback）

引用通告使用 `$this->pings()`，它返回 `Widget\Comments\Ping`，内部查询 `type <> 'comment'` 的记录。
最省事的方式是直接调用 `listPings()`：

```php
<?php $this->pings()->to($pings); ?><!-- 关键 -->
<?php if ($pings->have()) : ?>
	<h3>引用通告</h3>
	<?php $pings->listPings(); ?>
<?php endif; ?>
```

如果想完全自定义每条引用通告的展示，可以循环输出，例如只展示作者和日期：

```php
<?php $this->pings()->to($pings); ?><!-- 关键 -->
<?php if ($pings->have()) : ?>
	<h3>Pingbacks</h3>
	<ol>
	<?php while ($pings->next()) : ?>
		<li id="<?php $pings->theId() ?>">
			<?php $pings->author() ?> <?php $pings->date('F jS, Y'); ?>
		</li>
	<?php endwhile; ?>
	</ol>
<?php endif; ?>
```

typecho 模板语法很多是通用的，所以当你遇到不清楚的问题时，可以自己试着拼凑一下，就会有惊喜哦。
