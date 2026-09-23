# 自定义字段

### 字段使用与调用
在新建/编辑文章界面时，下方点击**添加字段**，填写字段名与字段值，如图
![字段](../../img/%E5%AD%97%E6%AE%B5.png)

模板对应位置加上如下代码
```php
<!--判断say字段的字段值是否存在-->
<?php if ($this->fields->say): ?> 
他说了：<?php $this->fields->say(); ?> 
<?php endif;?>
```

输出结果为
```php
他说了：你好世界
```

> `$this->fields` 返回一个 `Typecho\Config` 对象，字段名作为它的属性/键，读取方式与普通配置一致。

### 设置个自定义字段

打开主题的 functions.php ，填入如下函数，就可以为你的主题增加一个自动绑定的输入框

```php
function themeFields($layout) {
    $say = new \Typecho\Widget\Helper\Form\Element\Text('say', null, null, _t('留言'), _t('输入想说的话'));
    $layout->addItem($say);
}
```

模板作者这样设置后，文章字段就不用用户手动增加了，而是默认就加好了，用户只需要提交 **字段值(自己想说的话)** 就可以了。

> 旧写法 `new Typecho_Widget_Helper_Form_Element_Text(...)` 依然可用，会被自动映射到命名空间类。

还可以只针对文章（post）编辑页面插入字段。推荐使用主题钩子函数 `themePostFields`（独立页面用 `themePageFields`）：

```php
function themePostFields($layout) {
    $say = new \Typecho\Widget\Helper\Form\Element\Text('say', null, null, _t('留言'), _t('输入想说的话'));
    $layout->addItem($say);
}
```

> 旧文档中通过 `if($_SERVER['SCRIPT_NAME']=="/admin/write-post.php")` 判断页面类型的方式虽然仍能工作（后台文件仍为 `write-post.php`），但推荐改用上面的专用钩子函数，更清晰也更可靠。
