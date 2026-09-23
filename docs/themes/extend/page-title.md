### 说明
官方默认模板的title(html中的`<title>`)如下：
  
```php
<?php $this->archiveTitle([
            'category'  =>  _t('分类 %s 下的文章'),
            'search'    =>  _t('包含关键字 %s 的文章'),
            'tag'       =>  _t('标签 %s 下的文章'),
            'author'    =>  _t('%s 发布的文章')
        ], '', ' - '); ?><?php $this->options->title(); ?>
```
输出结果：...页面标题... - 站点名称

让我们来分解一下其中的语句，后一句大家都很明白，显示站点名称嘛，那前一句呢？其实前一句的标题包含三个参数：
```php
<?php $this->archiveTitle($defines, $before, $end); ?>
```
| 参数名称 | 默认值 | 简介 |
|:--|:--|:--|
| $defines | null | 归档类型到格式字符串的映射数组（如 `category`、`search`、`tag`、`author`、`archive_year` 等），用于为不同归档类型定制输出格式。传入非数组时该参数不生效 |
| $before | ` &raquo; ` | 标题前显示的字符 |
| $end | 空 | 标题后显示的字符 |

> 注意：`archiveTitle()` 的第一个参数是**格式映射数组**，而不是分隔符。若要自定义某类归档的标题文字，应传入形如 `['category' => _t('分类 %s')]` 的数组。

### 建议
其实官方默认这样就已经很好了，但是如果有seo优化需要建议在后面再加上页码信息，如：

```php
<?php $this->archiveTitle([
            'category'  =>  _t('分类 %s 下的文章'),
            'search'    =>  _t('包含关键字 %s 的文章'),
            'tag'       =>  _t('标签 %s 下的文章'),
            'author'    =>  _t('%s 发布的文章')
        ], '', ' - '); ?><?php $this->options->title(); ?><?php if($this->_currentPage>1) echo ' - 第 '.$this->_currentPage.' 页 '; ?>
```
OK，目标达到，剩下就是慢慢等搜索引擎更新标题吧。

> `$this->_currentPage` 由 `Widget\Archive::____currentPage()` 提供，返回当前页码。
