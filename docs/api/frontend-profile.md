# Typecho前台修改个人信息

### 代码

typecho 皮肤中的 `author.php` 就是用户的个人中心，那么如何用它实现用户在前台修改个人信息呢？
直接用如下代码即可：
```php
<section>
<h3><?php _e('个人资料'); ?></h3>
<ul><li>
<label class="typecho-label" for="screenName-0-1">
用户名</label><?php $this->user->name() ?></li></ul>
<?php \Widget\Users\Profile::alloc()->profileForm()->render(); ?>
</section>
<section id="change-password">
<h3><?php _e('密码修改'); ?></h3>
<?php \Widget\Users\Profile::alloc()->passwordForm()->render(); ?>
</section>
<?php \Widget\Users\Profile::alloc()->personalFormList(); ?>
```

> 旧写法 `Typecho_Widget::widget('Widget_Users_Profile')` 依然可用（`Widget_Users_Profile` 会被自动映射到 `\Widget\Users\Profile`），新代码推荐使用命名空间 + `::alloc()`。

### 问题
直接用的话，需要考虑一些事情，比如游客访问进来如果也这样显示岂不是很尴尬，A用户访问B用户时显示也会变尴尬，所以需要加入判断。
```php
<?php if($this->user->uid==$this->author->uid && $this->user->hasLogin()): ?>
这里填写上边的代码即可
<?php endif; ?>
```
代码说明：如果处于登录状态，用户的 id 等于当前作者的 id 就显示。

> 说明：`profileForm()`、`passwordForm()` 会生成包含安全令牌的表单，提交后由 `Widget\Users\Profile` 的 `updateProfile()`、`updatePassword()` 处理；`personalFormList()` 用于输出各插件注册的个人配置项。
