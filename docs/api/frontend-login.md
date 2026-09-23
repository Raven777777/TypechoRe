# Typecho前台登录

### 前言
前台登录是个很方便的功能，无论是作为个人博客还是多人博客，前台登录都会节省用户时间。

### 代码

```php
<form action="<?php $this->options->loginAction(); ?>" method="post" name="login" role="form">
<input type="hidden" name="referer" value="<?php $this->options->siteUrl(); ?>">
<input type="text" name="name" autocomplete="username" placeholder="请输入用户名" required/>
<input type="password" name="password" autocomplete="current-password" placeholder="请输入密码" required/>
<button type="submit">登录</button>
</form>
```

其中 `referer` 这个 **input** 就指明了登录成功后的跳转位置，现在默认的首页，可以修改 **value** 的值来自行定义登录成功跳转的地址。

> `loginAction()` 返回的提交地址中已经携带了安全令牌（`_` 参数），因此无需再手动添加 token 字段。登录成功后程序会校验 `referer`，只允许跳转到本站地址或站内相对路径，避免开放重定向。
