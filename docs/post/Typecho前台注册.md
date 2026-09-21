# Typecho前台注册

> 前提：需要在后台 **设置 → 用户** 中开启“允许注册”（对应选项 `allowRegister`）。

### 代码

```php
<form action="<?php $this->options->registerAction(); ?>" method="post" name="register" role="form">
<input type="hidden" name="_" value="<?php echo $this->security->getToken($this->request->getRequestUrl()); ?>">
用户名<input type="text" name="name">
邮箱:<input type="email" id="mail" name="mail" >
<button type="submit" name="loginsubmit" value="true">注册</button>
</form>
```

> `registerAction()` 返回的地址中已经携带安全令牌，这里的隐藏 `_` 字段属于双保险，可省略。

### 说明

用户进入注册页面，只会要求用户填写用户名和邮箱，点击注册按钮后会跳转到程序后台，此时会提示被分配了个临时密码，同时提示用户修改默认密码，填写个人信息如昵称，个人主页等。

> 实现见 `Widget\Register::action()`：注册成功后会用随机密码登录该用户，并通过 `Notice` 提示“用户 xxx 已经成功注册, 密码为 xxx”。

### 扩展
如果也想像前台登录一样，登录后自定义跳转页面，需要修改 `/var/Widget/Register.php` 这个文件，末尾附近的这行代码：
```php
$this->response->redirect($this->options->adminUrl);
```
换成如下代码：
```php
if (null != $this->request->get('referer')) {
    $this->response->redirect($this->request->get('referer'));
} else {
    $this->response->redirect($this->options->adminUrl);
}
```
这样在 form 里插入
```php
<input type="hidden" name="referer" value="跳转地址">
```
即可。

> 建议改用插件钩子 `finishRegister` 来实现跳转，避免直接修改核心文件，升级时更省心。
