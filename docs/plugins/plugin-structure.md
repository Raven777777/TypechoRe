原文转自：http://www.imhan.com/archives/57/ ，并针对当前版本做了核验与更新。

写了有几个 Typecho 插件了吧，准确地说，这并不是什么很标准、规范的教程，只是个人在插件开发过程的一些总结。写在这里，只是为了和大家分享、交流一下心得。如果有什么错误的地方，也欢迎大家来指正。

我们先来了解一下 Typecho 插件的基本结构吧。

首先就是头部的信息注释部份。 最上面的注释是插件的功能描述，将显示在插件列表中。

> @package 后跟的是插件的名称。如本插件名称为 HelloWorld。

> @author 后跟的是插件的作者。如本插件的作者为 qining

> @link 后跟的是插件作者的主页。在插件列表中，点击作者名字，将跳转到该页面。

> @since 后跟的是插件所要求的最低程序版本（语义化版本号），如 `@since 1.2.0` 表示程序版本不低于 1.2.0 时才允许启用。若低于该版本，插件列表中会提示不满足依赖。

> @version 插件的版本号。将作为插件的版本号显示在插件列表。

> 这些信息由 `Typecho\Plugin::parseInfo()` 解析；依赖检测由 `Typecho\Plugin::checkDependence($info['since'])` 完成。旧文档中的 `@dependence` 已不再使用，请改用 `@since`。

接着往下，开始插件的类的定义。 插件的类名和插件的文件名及存放路径有关。 Typecho 的插件一般采用两种方式来存放。

> 简单的单文件插件，可以直接以“插件名.php”的形式存放在 plugins 目录下。此时，类名直接与文件名同名。

> 当然，对于复杂一点的插件，一般建议第二种方法：以“Plugin.php”的文件名，存放在 plugins 的“插件名”子目录下。

**当前版本推荐使用命名空间**，即 `usr/plugins/插件名/Plugin.php`：

```php
<?php

namespace TypechoPlugin\HelloWorld;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;

class Plugin implements PluginInterface
{
    // ...
}
```

> 自动加载器会把 `插件名\Plugin` 映射到 `TypechoPlugin\插件名\Plugin`，路径为 `usr/plugins/插件名/Plugin.php`。旧式的 `插件名_Plugin` 类名也仍然兼容。

定义后类后，接下来就是插件的函数接口了。Typecho 的插件主要有四个函数接口：

`public static function activate()` 这个是插件的激活接口，主要填写一些插件的初始化程序。

`public static function deactivate()` 这个是插件的禁用接口，主要就是插件在禁用时对一些资源的释放。

`public static function config(\Typecho\Widget\Helper\Form $form)` 插件的配置面板，用于制作插件的标准配置菜单。

`public static function personalConfig(\Typecho\Widget\Helper\Form $form)` 插件的个性化配置面板（个人用户的配置面板）。

> 接口定义见 `Typecho\Plugin\PluginInterface`。旧写法 `Typecho_Widget_Helper_Form` 会被自动映射到 `\Typecho\Widget\Helper\Form`，依然可用。
>
> 另外，插件类还可以选择性地实现两个静态方法来自行接管配置写入：
> * `public static function configHandle(array $settings, bool $isInit)`：接管插件配置的保存（见 `Widget\Plugins\Edit::configHandle()`）。
> * `public static function personalConfigHandle(array $settings, bool $isInit)`：接管个人配置的保存。

先说这么多吧，下一篇，将以 HelloWorld 为例子，介绍一下一些基本用法。
