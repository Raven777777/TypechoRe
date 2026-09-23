# 调用某一分类下的文章

根据分类 mid 获取某个分类下的文章列表
```php
<?php $this->widget('Widget_Archive@index', 'pageSize=6&type=category', 'mid=1')->to($new); ?>
<?php while ($new->next()): ?>
<a href="<?php $new->permalink(); ?>"><?php $new->title(); ?></a>
<?php endwhile; ?>
```
以上就是获取分类 **mid** 等于1的最新6篇文章，`pageSize=6` 就是指定调用数量，`mid=1` 指定分类 **mid**，也可以用缩略名方式替换如 `slug=name`，其中 **name** 就是 **mid** 等于1的分类的缩略名。

> 新代码推荐使用命名空间 + `allocWithAlias()` 的写法，等价于上面：
> ```php
> <?php \Widget\Archive::allocWithAlias('index', 'pageSize=6&type=category', 'mid=1')->to($new); ?>
> ```

如果同一个页面调用多个分类的文章，那么需要上述代码中 `@index` 的 `@` 后面的参数不同，如下面调用 mid=1 和 mid=2 两个不同分类的文章
```php
分类一
<?php $this->widget('Widget_Archive@indexa', 'pageSize=6&type=category', 'mid=1')->to($new); ?>
<?php while ($new->next()): ?>
<a href="<?php $new->permalink(); ?>"><?php $new->title(); ?></a>
<?php endwhile; ?>
分类二
<?php $this->widget('Widget_Archive@indexb', 'pageSize=6&type=category', 'mid=2')->to($new); ?>
<?php while ($new->next()): ?>
<a href="<?php $new->permalink(); ?>"><?php $new->title(); ?></a>
<?php endwhile; ?>
```

> 说明：`@` 后面的字符串是组件别名（alias），用于在同一请求中缓存不同的 `Widget\Archive` 实例；别名相同会复用同一个实例，所以调用多个分类时必须使用不同的别名。
