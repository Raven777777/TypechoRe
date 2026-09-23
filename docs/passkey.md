# Passkey / WebAuthn

TypechoRe 支持现代浏览器的 Passkey 登录。Passkey 使用 WebAuthn 标准，私钥保存在浏览器、操作系统或硬件安全密钥中，服务器只保存 credential ID、公钥和签名计数器。

## 支持的平台

- Windows Hello
- Android Passkey
- iPhone / iPad / macOS Passkey
- Chrome、Edge、Firefox、Safari 等现代浏览器
- FIDO2 安全密钥

## 必需环境

PHP 扩展：

```text
openssl
mbstring
sodium
session
```

同时需要 TypechoRe 正在使用的数据库扩展。例如 SQLite 使用：

```text
sqlite3
```

## HTTPS 要求

正式网站必须使用 HTTPS。`localhost` 可以作为本地开发例外。

访问域名必须与站点设置一致。例如站点为：

```text
https://love4z.cn
```

则 WebAuthn RP ID 为：

```text
love4z.cn
```

不要混用服务器 IP、其他域名或未经 HTTPS 的地址。

## 注册 Passkey

1. 使用原密码登录后台
2. 打开个人资料页面
3. 找到「Passkey 登录」区域
4. 点击「注册 Passkey」
5. 输入设备名称
6. 按浏览器提示完成 Windows Hello、手机确认或安全密钥确认
7. 退出后台
8. 在登录页使用「使用 Passkey 登录」测试

首次使用时必须先注册 Passkey，直接在没有凭据的登录页点击 Passkey 登录可能会被浏览器报告为超时或未允许操作。

## 数据库表

Passkey 使用独立表：

```text
typecho_passkeys
```

主要字段：

- `uid`：Typecho 用户 ID
- `credential_id`：Passkey 凭据 ID
- `public_key`：用于验证签名的公钥
- `sign_count`：认证器签名计数器
- `name`：设备名称
- `created`：注册时间
- `last_used`：最后使用时间

数据库不会保存：

- Passkey 私钥
- Windows Hello PIN
- 指纹或面部数据
- 手机锁屏密码

## 安全校验

TypechoRe 会校验：

- Challenge 及其有效期
- WebAuthn Origin
- RP ID Hash
- Client Data 类型
- User Present 标志
- User Verified 标志
- User Handle
- 公钥签名
- 签名计数器

注册和删除 Passkey 需要管理员权限以及 CSRF Token。

当前实现使用隐私友好的 `none` Attestation，不保存厂商认证证书，也不尝试识别具体硬件品牌。

## 备份与恢复

数据库备份必须包含 `typecho_passkeys` 表。恢复数据库后，原有 Passkey 是否可用取决于数据库中的 credential ID、公钥和当前网站 RP ID 是否保持一致。

如果数据库恢复到注册 Passkey 之前的版本，用户需要重新注册 Passkey。

## 常见问题

### 浏览器显示操作超时或未允许

通常有以下原因：

- 尚未注册 Passkey
- 当前访问的域名与注册时不同
- 使用了 HTTP 而不是 HTTPS
- 浏览器取消了系统认证弹窗
- 当前设备没有可用的 Passkey
- Passkey 已从系统密钥管理器删除

### 注册弹窗成功但服务器失败

检查 PHP 错误日志，并确认服务器代码包含：

```text
var/Widget/Passkey.php
var/lbuchs/WebAuthn/
```

## 删除 Passkey

在后台个人资料的 Passkey 列表中删除对应设备即可。建议至少保留一种备用登录方式，或者注册两台设备，避免唯一认证设备丢失后无法登录。
