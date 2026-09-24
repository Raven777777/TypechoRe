# 代码质量与 PHP 8.5 检查报告

## 检查环境

> **数据库兼容性警告：TypechoRe 已不再与原版 Typecho 数据库完全相通。**
> 项目修改了密码/authCode 处理，增加了 `typecho_passkeys` 表，并调整了部分核心行为。迁移到 TypechoRe 后，不应再让原版 Typecho 直接连接同一个生产数据库。

- PHP 8.5.10
- Windows x64
- SQLite 3
- Node.js v24.19.0

项目内的 `php-8.5.10/` 仅用于本地检查。生产环境应使用独立配置的 PHP，并根据实际数据库启用相应扩展。

## 已完成检查

### PHP 语法和弃用项

项目内约 237 个 PHP 文件全部通过 PHP 8.5 语法检查，没有发现语法错误。

针对 PHP 8.5 文档检查了以下兼容性风险，当前代码没有命中：

- `curl_close()` 和 `curl_share_close()`
- `imagedestroy()`
- `finfo_close()`
- `DATE_RFC7231`
- `__sleep()` 和 `__wakeup()`
- 反引号执行操作符
- 非规范类型转换
- PHP 8.5 新增的弃用写法

### 自动化基础测试

运行：

```bash
php-8.5.10/php.exe tests/smoke.php
```

当前结果：

```text
PASS: PHP 8.5 smoke tests
```

覆盖内容：

- bcrypt 密码生成和验证
- 旧密码兼容验证
- authCode 生成和验证
- 非法 authCode 拒绝
- HTML 转义
- UTF-8 slug 生成
- 私有地址 SSRF 检查
- 未配置可信代理时拒绝伪造的 `X-Forwarded-For`
- SQLite 建表、插入、查询和读取
- WebAuthn Passkey 注册参数生成

### JavaScript

项目内 JavaScript 文件通过 Node.js 语法检查，未发现语法错误。

后台依赖已升级并通过加载测试：

- jQuery 3.7.1
- jQuery UI 1.14.2
- DOMPurify 3.4.15
- jQuery Timepicker 1.6.3

### Passkey/WebAuthn

Passkey 核心代码已移植到 `var/lbuchs/WebAuthn/`，不再依赖体积较大的 `vendor/`。当前仅保留 `none` 自签名认证所需代码，支持：

- Windows Hello
- 手机同步 Passkey
- 浏览器 Discoverable Credential
- FIDO2 安全密钥

注册和登录均要求 User Verification，并校验 Challenge、Origin、RP ID、User Handle、签名计数器和公钥签名。Passkey 私钥不会保存到数据库。

Passkey 需要 `openssl`、`mbstring`、`sodium`、`session` 和数据库扩展，并且正式部署需要 HTTPS。

### 临时站点集成测试

使用 PHP 内置服务器和临时 SQLite 数据库完成了真实请求测试：

- 安装向导
- 首页
- 后台登录
- 旧 MD5 密码登录并自动升级为 bcrypt
- 后台发布文章
- 评论提交
- 评论 CSRF Token
- 文件上传
- 图片附件入库
- Sitemap
- Sitemap 分页
- XML-RPC 基础请求
- 后台登出

Sitemap 大数据测试中，第一子 Sitemap 返回 1000 条 URL，第二子 Sitemap 返回剩余 URL，XML 输出有效。

## 已修复的问题

### 评论表单缺少 CSRF Token

`Security::protect()` 要求请求带有 `_` 参数，但默认主题的评论 action 原先没有注入 Token，导致评论被静默退回。

现在 `var/Widget/Archive.php` 的 `commentUrl()` 会通过 `Security::getTokenUrl()` 生成带 Token 的评论地址，同时兼容父评论参数。

### 未配置可信代理时信任伪造 IP

之前的 IP 处理逻辑在没有配置可信代理时会读取客户端提交的 `X-Forwarded-For`，攻击者可以伪造 IP，影响评论频率限制和 IP 黑名单。

现在只有当 `REMOTE_ADDR` 位于 `__TYPECHO_TRUSTED_PROXIES__` 配置的代理列表中时，才读取转发 IP；否则使用实际连接地址。

## 数据库测试限制

本机没有运行 MySQL 或 PostgreSQL 服务，因此以下适配器没有进行真实连接测试：

- MySQL 原生适配器
- PDO MySQL
- PostgreSQL 原生适配器
- PDO PostgreSQL
- MySQL SSL 连接
- PostgreSQL SSL 连接
- 多数据库并发行为

SQLite 适配器已经完成实际测试，包括安装、文章、评论、上传、Sitemap 和 WAL 初始化。

生产环境使用的 SQLite 数据库应包含 `typecho_passkeys` 表；Passkey 注册记录只会写入实际部署服务器使用的数据库。数据库文件和备份不属于代码仓库，不应提交到 Git。

数据库中的激活插件必须与 `usr/plugins/` 中的代码一致。例如激活 `Sticky` 时必须同时部署 `usr/plugins/Sticky/Plugin.php`，否则会导致回调类不存在并返回 500。

## 代码审查重点

重点检查了以下修改较多的模块：

- `var/Typecho/Common.php`
- `var/Typecho/Request.php`
- `var/Typecho/Response.php`
- `var/Typecho/Cookie.php`
- `var/Typecho/Http/Client.php`
- `var/Typecho/Db/Adapter/`
- `var/Widget/User.php`
- `var/Widget/Security.php`
- `var/Widget/Upload.php`
- `var/Widget/Feedback.php`
- `var/Widget/Action/Sitemap.php`
- `usr/plugins/ItWasBadXD/Plugin.php`

未发现明显的任意命令执行、动态 PHP 执行、无保护的危险反序列化或任意扩展名上传问题。

## 最终部署注意事项

- MySQL 和 PostgreSQL 仍需在对应服务可用后进行连接回归测试。
- 默认主题的 TagCloud 使用了第三方库的 `_next()` 内部方法，后续升级 TagCloud 时需要重新验证。
- 项目当前没有 PHPUnit、PHPStan 或 Psalm 配置，基础回归测试目前使用 `tests/smoke.php`。
- `php-8.5.10/php.ini` 只适合作为本地测试配置，不应直接作为生产配置。
- 生产环境应关闭 `__TYPECHO_DEBUG__` 和 `display_errors`。
- 部署完成后应删除安装压缩包，并确认数据库文件不能通过 Web 直接下载。
- TypechoRe 数据库不再保证与原版 Typecho 兼容，不能让两个程序连接同一个生产数据库。
