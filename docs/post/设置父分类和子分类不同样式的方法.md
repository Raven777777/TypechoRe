# Typecho设置父分类和子分类不同样式的方法

### 描述
普通的分类，正常输出；有子分类的父级分类，输出后点击它会展开二级菜单里面有这个父级旗下的子分类。

### 使用 listCategories 输出的 class（推荐）

`Widget\Metas\Category\Rows::listCategories()` 在输出分类树时会自动带上层级相关的 class，直接写 CSS 即可区分父/子分类：

```php
<?php \Widget\Metas\Category\Rows::alloc()->listCategories('wrapClass=widget-list&current=' . ($this->is('category') ? $this->archiveSlug : '')); ?>
```

生成的 HTML 形如：

```html
<ul class="widget-list">
  <li class="category-level-0 category-parent"><a href="...">分类A</a></li>
  <li class="category-level-0 category-parent category-active"><a href="...">分类B</a>
    <ul class="widget-list">
      <li class="category-level-1 category-child category-level-odd"><a href="...">子分类B1</a></li>
    </ul>
  </li>
</ul>
```

可用的 class：

* `category-parent` / `category-child`：父级 / 子级
* `category-level-N`：所在层级
* `category-level-odd` / `category-level-even`：同层的奇偶交替
* `category-active`：当前分类；`category-parent-active`：当前分类的父级

因此只要写 `.category-parent > a { ... }`、`.category-child > a { ... }` 就能分别设置样式；再把子级 `ul` 用 CSS 隐藏、在父级悬停时展开，即可实现下拉菜单：

```css
.widget-list .category-child > ul { display: none; }
.widget-list .category-parent:hover > ul { display: block; }
```

### 关于旧文档中的写法

旧文档使用了 `$categorys->getAllChildren()` 和 `$categorys->getCategory()` 两个方法，它们在当前版本中都已不存在，需要替换为：

* `getAllChildIds($mid)`：返回某个分类下**所有子孙**分类的 id 数组（对应旧 `getAllChildren`）。
* `getChildIds($mid)`：只返回**直接子级**分类的 id 数组。
* `getRow($id)`：返回某个分类的原始数据数组（对应旧 `getCategory`）。
* `getAllParents($id)` / `getAllParentsSlug($id)`：返回某个分类的所有父级。

> 注意：`getRow()` 返回的是原始行数据（mid、name、slug、count、parent 等），**不包含**计算出来的 `permalink`。如果确实需要在自定义循环里输出链接，请使用上面的 `listCategories()` 方式，或自行通过 `Typecho\Router::url()` 生成。
