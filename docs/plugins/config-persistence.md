# 插件配置保存的那些坑

记录一个很容易踩到、又很难一眼看出原因的问题：**插件设置页能打开，点「保存设置」也提示成功，但某些项就是存不进去**（尤其是多选框，永远勾不上）。

> 本文的原始案例来自已整合进核心的「标签速选」功能（原 TagToText 插件），
> 但 `Typecho\Request::get()` 的类型匹配规则对仍在的插件开发同样适用。

## 一、现象

- 文本框、单选框（Radio）能正常保存；
- 多选框（Checkbox）中，**当前处于未勾选状态的项，勾选后保存无效**；
- 已经勾选的项，取消勾选反而有效。

## 二、保存链路

后台保存插件设置走的是 `/action/plugins-edit?config=插件名`，对应 `Widget\Plugins\Edit::config()`：

```php
$form = Config::alloc()->config();               // 依据插件 config() 重建表单
if ($form->validate()) {                          // 校验，失败则带着错误返回
    $this->response->goBack();
}

$settings = $form->getAllRequest();               // 取表单提交值
if (!$this->configHandle($pluginName, $settings, false)) {
    self::configPlugin($pluginName, $settings);   // 合并写入 options 表
}
```

关键在于 `getAllRequest()` → `Form::getParams()`：

```php
// var/Typecho/Widget/Helper/Form.php
$result[$param] = $request->get($param, is_array($this->getInput($param)->value) ? [] : null);
```

**默认值是「当前配置项的值」推导出来的**：值是数组就传 `[]`，否则传 `null`。

而 `Typecho\Request::get()` 里还有一条类型匹配规则：

```php
// var/Typecho/Request.php
if (isset($value) && $value !== '') {
    if (is_array($default) == is_array($value)) {
        return $value;
    } else {
        return $default;      // 类型不一致时，提交值被丢弃
    }
}
```

于是形成了死锁：

| 当前保存值 | 推导出的默认值 | 提交值（勾选后） | 结果 |
|:--|:--|:--|:--|
| `null`（未勾选时常见的初始值） | `null`（非数组） | `['1']`（数组） | 类型不符 → 返回 `null`，**勾选被丢弃** |
| `['1']`（已勾选） | `[]`（数组） | 无（未勾选） | 返回 `[]`，取消成功 |

**结论：`Checkbox` 的默认值千万不要写 `null`，必须写数组。**

## 三、解决办法

### 方案一：多选框默认值用 `[]`

```php
// 错误：默认值为 null，之后永远勾不上
$element = new Checkbox('hotStyle', ['1' => _t('按热度显示字号')], null, _t('显示设置'));

// 正确：默认值必须是数组
$element = new Checkbox('hotStyle', ['1' => _t('按热度显示字号')], [], _t('显示设置'));
```

读取时用 `empty()` 判断即可：

```php
$conf['hotStyle'] = !empty($config->hotStyle);   // [] / null → false，['1'] → true
```

### 方案二：用 `configHandle()` 自己接管写入

`Widget\Plugins\Edit::configHandle()` 里明确写了：只要插件类定义了 `configHandle`，核心就**不再**自己写库，全部交给插件处理。

```php
public static function configHandle(array $settings, bool $isInit)
{
    if ($isInit) {
        // 插件启用时的初始化写入，此时没有提交数据，直接保存表单默认值
        \Widget\Plugins\Edit::configPlugin('MyPlugin', $settings);
        return;
    }

    $request = \Typecho\Request::getInstance();

    \Widget\Plugins\Edit::configPlugin('MyPlugin', [
        'text'    => strval($request->get('text', '')),
        'switch'  => $request->get('switch', []),      // 默认值给数组，才能收到数组
        'count'   => (string) (is_numeric($request->get('count', '10')) ? intval($request->get('count', '10')) : 10)
    ]);
}
```

要点：

1. 数组类参数（多选框）的默认值一律传 `[]`，否则依然收不到；
2. `$isInit` 为 `true` 时（启用插件）**必须自己调用一次 `configPlugin()`**，否则启用后配置为空；
3. 定义了 `configHandle` 后，核心的 `configPlugin()` 调用会被跳过，别指望它兜底。

## 四、其它注意事项

**1. 不要随意重命名配置项**

`Widget\Plugins\Config::config()` 会用已保存的配置反填表单：

```php
foreach ($options as $key => $val) {
    $form->getInput($key)->value($val);
}
```

旧数据里的键如果在表单中已不存在，`getInput()` 返回 `null`，会直接抛
`Call to a member function value() on null`，整个设置页白屏。真要改名，需要同时清理 `options` 表中 `plugin:插件名` 这一行的旧键（禁用再启用插件即可，禁用时会删除该行）。

**2. `configPlugin()` 是合并写入**

```php
$value = array_merge(is_array($value) ? $value : [], $settings);
```

它只做合并，不会删除键。所以旧键会一直残留，这也放大了上面第 1 点的问题。

**3. 校验规则空值会被跳过**

`Validate::run()` 中，除 `required` / `confirm` 外，值为空时不执行校验。给 `Number` 之类允许留空的字段加 `isInteger` 反而会让用户清空时保存失败，留空场景请自己在 `configHandle()` 里兜底。
