# 分类页面拓展内容

### 输出分类名字和描述

**通用写法（推荐）**
```php
<!--当前分类的名字-->
<?php echo $this->getArchiveTitle(); ?>
<!--当前分类的描述-->
<?php echo $this->getArchiveDescription(); ?>
```

> `getArchiveDescription()` 从 1.3.0 起为推荐方法，旧的 `getDescription()` 仍可用但已标记为 `@deprecated`。
> 分类归档页在 `Widget\Archive::categoryHandle()` 中会把分类名写入归档标题、把分类描述写入归档描述，因此上面的写法对分类、标签、作者、日期等归档页都通用。

### 输出文章多级分类

假设有一篇文章的分类为【php】，而【php】有个父级分类【code】，那么输出文章分类使用代码`<?php $this->category(',', true, ''); ?>`输出时只会显示分类【php】。

那么如何同时显示父级分类和子级分类呢？

```php
<?php $this->directory(' > ', true, ''); ?>
```

输出效果如下
```
【code】>【php】
```

> `directory()` 签名为 `directory(string $split = '/', bool $link = true, ?string $default = null)`。

### 自定义分类列表

在 sidebar 中的分类列表就一句代码写死了，那么如何自定义呢？
```php
<?php \Widget\Metas\Category\Rows::alloc()->to($categories); ?>
<?php while($categories->next()): ?>
<li class="menu-item menu-item-home">
    <a href="<?php $categories->permalink(); ?>" rel="section"><?php $categories->name(); ?>【<?php $categories->count(); ?>】</a>
</li>
<?php endwhile; ?>
```

> 旧写法 `<?php $this->widget('Widget_Metas_Category_List')->to($categories); ?>` 依然可用（`Widget_Metas_Category_List` 是 `\Widget\Metas\Category\Rows` 的兼容别名）。

**可用字段**
*   **mid**：分类id
*   **name**：分类名称
*   **slug**：分类缩写名
*   **type**：分类类型，譬如 category
*   description：分类的描述
*   **count**：该分类下的文章数目
*   order：
*   parent：父分类的mid
*   levels：所在的层级
*   directory：Array类型，数组元素是每层分类的slug
*   permalink：该分类的url
*   feedUrl：该分类的feed地址
*   feedRssUrl：该分类的feedRss地址
*   feedAtomUrl：该分类的feedAtom地址

**可用参数**

*   ignore 不显示的分类mid（该分类及其子分类都不显示）
*   current 当前分类mid，如果设置了，则会在输出时增加 `class="category-active"` 样式；若当前分类是其子分类，则增加 `category-parent-active`
```php
<?php \Widget\Metas\Category\Rows::alloc('ignore=1&current=2')->to($categories); ?>
```
则不显示 mid 为 1 的分类，并在 mid 为 2 的 li 上增加 `category-active` 样式。

> 使用 `listCategories()` 时也可以直接传参：`\Widget\Metas\Category\Rows::alloc()->listCategories('wrapClass=widget-list&showCount=1');`。
