# 标签速选 TagToText

## 一、插件简介

在后台「撰写文章 / 创建页面」的**标签输入框下方**增加一个可搜索的标签面板：自动列出站点已有的标签（可显示关联文章数），点击即选中或取消，支持关键词过滤、回车快速选中、一键清空，并与系统自带的 `tokenInput` 标签控件双向同步。

适用场景：

- 站点标签较多，写文章时记不清以前用过哪些标签；
- 手工输入容易打错字，导致同一个标签被拆成多个（如 `Typecho` 与 `typecho`）；
- 想按热度（文章数）快速挑选常用标签。

排序方式、载入数量、是否显示文章数、是否按热度显示字号、是否默认展开均可在插件设置中调整。

## 二、安装与启用

### 1. 文件结构

```
usr/plugins/tag-to-text
        |
        |—— Plugin.php    插件核心文件（单文件，无额外静态资源）
```

插件目录名、命名空间、类名三者保持一致：

```php
namespace TypechoPlugin\TagToText;

class Plugin implements PluginInterface
```

### 2. 启用步骤

1. 将 `TagToText` 目录上传到 `usr/plugins/` 下；
2. 后台 → 控制台 → 插件，在「禁用的插件」中找到 **TagToText**，点击**启用**；
3. 点击插件右侧的**设置**，按需调整后保存；
4. 进入「撰写文章」页面，标签输入框下方即出现「选择标签」按钮与面板。

> 若修改设置后未生效，可先禁用再重新启用一次（会重建配置数据），或参见
> [插件配置保存的那些坑](/plugins/config-persistence)。

## 三、配置项说明

| 配置项 | 类型 | 默认值 | 说明 |
|:--|:--|:--|:--|
| `sort` | Radio | `count` | 标签排序方式：`count` 按文章数由多到少 / `name` 按名称 / `mid` 按创建顺序由新到旧 |
| `limit` | Number | `100` | 载入的标签数量，填 `0` 表示载入全部 |
| `ignoreZeroCount` | Checkbox | 未勾选 | 隐藏没有被任何文章使用的标签 |
| `showCount` | Checkbox | 勾选 | 在标签后面显示关联的文章数量 |
| `hotStyle` | Checkbox | 未勾选 | 按热度（文章数）显示不同字号 |
| `defaultOpen` | Checkbox | 未勾选 | 进入撰写页面时直接展开标签面板 |

## 四、实现要点

### 1. 挂载点：撰写页面的 bottom 钩子

`admin/write-post.php` 与 `admin/write-page.php` 在输出表单脚本之后、引入页脚之前各留了一个 `bottom` 钩子，适合注入自定义脚本：

```php
// admin/write-post.php
\Typecho\Plugin::factory('admin/write-post.php')->call('bottom', $post);
```

激活时注册（数组形式的可调用对象）：

```php
public static function activate()
{
    \Typecho\Plugin::factory('admin/write-post.php')->bottom = [__CLASS__, 'render'];
    \Typecho\Plugin::factory('admin/write-page.php')->bottom = [__CLASS__, 'render'];
}
```

此时 jQuery 已经由 `common-js.php` 载入，可以直接使用；由于注册顺序在 `write-js.php` 之后，`$(document).ready()` 的回调也会在其之后执行，因此能拿到已初始化好的标签控件。

### 2. 读取已有标签

直接查 `metas` 表，无需依赖 Widget：

```php
$db = \Typecho\Db::get();
$select = $db->select('mid', 'name', 'count')
    ->from('table.metas')
    ->where('type = ?', 'tag')
    ->order('table.metas.count', \Typecho\Db::SORT_DESC)
    ->order('table.metas.mid', \Typecho\Db::SORT_DESC);

if ($limit > 0) {
    $select->limit($limit);
}

$tags = $db->fetchAll($select);
```

数据随脚本一次性输出到页面（无额外请求），用 `JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` 编码，避免标签名中的特殊字符破坏脚本。

### 3. 与后台 tokenInput 控件交互

Typecho 自带 `admin/js/tokeninput.js`，会把 `#tags` 隐藏并生成一个可视化输入控件，因此不能直接改 `#tags` 的值，而要调用它暴露的方法：

```js
var input = jQuery('#tags');
var useToken = !!input.data('tokenInputObject');   // 判断是否已挂载 tokenInput

input.tokenInput('add', {id: name, tags: name});   // 选中
input.tokenInput('remove', {id: name, tags: name}); // 取消
input.tokenInput('clear');                          // 清空
input.trigger('change');                            // 通知表单：内容已变更
```

- `tokenValue` / `propertyToSearch` 均为 `tags`，所以传入的对象要同时带 `id` 与 `tags` 两个字段；
- 每次改动后手动 `trigger('change')`，后台的「尚未保存」提示与自动保存才会被触发；
- 未挂载 tokenInput 时退化为直接读写 `#tags` 的值，并按 `,`、`，` 切分（系统保存标签时也是把中文逗号替换为英文逗号再切分）。

### 4. 面板的交互细节

- 搜索框输入即过滤，回车选中第一个可见标签，`Esc` 收起，点击面板外部自动收起；
- 已选中的标签高亮，并在按钮行显示「已选 N 个标签」；
- 监听 `#tags` 的 `change`（和 `input`）事件反向同步高亮，保证手动输入标签时面板状态也正确；
- 「清空」按钮走 `tokenInput('clear')`，再触发 `change`。

## 五、常见问题

**Q：面板没有出现？**

- 确认插件已启用，且当前是撰写文章/页面；
- 站点还没有任何标签时插件不会输出面板；
- 若开启了「隐藏没有被任何文章使用的标签」，而所有标签的文章数都是 0，同样不会输出。

**Q：设置里勾选了却保存不住？**

这是本分支 `Typecho\Request::get()` 的类型匹配规则导致的（多选框提交的是数组，而默认值由当前保存值推导）。插件已通过 `configHandle()` 自行接管写入来规避，详见
[插件配置保存的那些坑](/plugins/config-persistence)。

**Q：可以改样式吗？**

面板样式写在 `Plugin::getCss()` 中，类名统一以 `.tt-` 开头（`.tt-wrap`、`.tt-actions`、`.tt-panel`、`.tt-list`、`.tt-item`、`.tt-item.tt-active` …），直接修改即可。
