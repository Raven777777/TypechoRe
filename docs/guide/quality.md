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

针对 PHP 8.5 文档检查了以下兼容性风险：

- `curl_close()` 和 `curl_share_close()`：原先 `var/lbuchs/WebAuthn/WebAuthn.php` 的
  `queryFidoMetaDataService()` 使用 `curl_close()`，该功能在 TypechoRe 中未使用（仅保留
  `none` 自签名），已整体删除
- `imagedestroy()`、`finfo_close()`、`DATE_RFC7231`、`__sleep()` 和 `__wakeup()`、反引号、
  非规范类型转换、`PDO::MYSQL_*` 常量、`ReflectionMethod::setAccessible()`：全部无命中
- PHP 8.5 新增的弃用写法

`var/lbuchs/**` 已不再从 PHPStan 扫描中排除，WebAuthn 相关代码现在同样受静态检查覆盖。

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
- UTF-8 slug 生成（含全角空格 `mb_trim`）
- 私有地址 SSRF 检查
- 未配置可信代理时拒绝伪造的 `X-Forwarded-For`
- HMAC-SHA256 短时 token 生成/校验/拒绝伪造值
- `json_validate()`、`array_first()`/`array_last()` 可用性
- `Typecho\Config` 的 `Iterator`/`ArrayAccess` 原生 `mixed` 返回类型
- `fileinfo` 内容嗅探 MIME
- 备份缓冲 v2（SHA-256）与 v1（MD5）双向兼容
- SQLite 建表、插入、查询和读取
- WebAuthn Passkey 注册参数生成
- 全量类加载（覆盖 167 个类），任一 `#[\Override]` 签名漂移或类链接错误都会在此失败

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

本版本已在生产站点完成部署验证（2026-10-09），暂未发现回归问题。

## PHP 8.5 现代化迁移

本项目已完成一轮底层与库层面的 PHP 8.5 迁移，不改变前台行为：

### 弃用项与死代码清理

- 删除 `var/lbuchs/WebAuthn/WebAuthn.php` 中未使用且命中 PHP 8.5 弃用的
  `queryFidoMetaDataService()`（含 `curl_close()`）
- 删除 SQLite 2 兼容分支（`SQLiteTrait::$isSQLite2`、`filterCountQuery()`），
  SQLite 2 早已不被 PDO/SQLite3 支持
- `Pdo\Mysql` 不再回退到已弃用的 `PDO::MYSQL_*` 常量
- 安装向导移除 MyISAM 选项，统一使用 InnoDB + utf8mb4
- `AttestationObject` 移除已不随包分发的 attestation 格式引用

### PHP 8.5+ 特性采用

- 239 个方法标注 `#[\Override]`（构造函数除外，PHP 对构造器不强制签名兼容，
  加该属性会触发类链接期致命错误），可防住历史上出现过的父类签名漂移
- `#[\SensitiveParameter]` 保护密码参数不进异常堆栈
- `#[\Deprecated]` 标记 `Json`、`isAvailableClass()`、`arrayFlatten()`、`isAppEngine()`
- `#[\NoDiscard]` 标记 `escape()`、`hashPassword()`、`generateAuthCode()`、`hashAuthCode()`、`timeToken()`
- `json_validate()`、`array_first()`、`str_contains()`/`str_starts_with()`、`mb_trim()`
- `Typecho\Config` 的 `Iterator`/`ArrayAccess` 改用原生 `mixed` 返回类型，
  不再依赖 `#[\ReturnTypeWillChange]`

### 安全与性能

- 短时 token 由 `sha1` + `==` 升级为 HMAC-SHA256 + `hash_equals()`
- 登录成功后 `session_regenerate_id(true)`，防止会话固定
- `Common::startSession()` 强制 `session.use_strict_mode=1`
- `Http\Client` 使用 PHP 8.5 `curl_share_init_persistent()` 跨请求复用连接，
  移除 `CURLOPT_FRESH_CONNECT`，优先 HTTP/2，并限制仅允许 http/https 协议
- 备份文件新增 v2 格式（SHA-256 校验和），导入保持对 v1（MD5）的兼容
- 数据库连接全面使用 PHP 8.4+ 的 `Pdo\Mysql`/`Pdo\Sqlite`/`Pdo\Pgsql` 驱动子类
- 新装站点的 `defaultAllowPing` 与 `allowXmlRpc` 默认关闭（旧站点不受影响，
  历史数据中的选项值保持不变）

### 未采用的项及原因

- 全量 `declare(strict_types=1)`：现有代码有 359 处宽松比较，强制模式可能在
  未覆盖到的主题/插件调用路径上产生 `TypeError`，收益不足以抵消回归风险
- `enum` 替换 `Db::READ/WRITE` 等常量：这些常量是公开插件 API，且 `READ|WRITE`
  是位掩码，枚举无法直接表达
- 重构 Widget 魔术属性为属性钩子、替换 DB/HTTP/Markdown/i18n 为第三方库：
  会引入 vendor 依赖或破坏插件生态，与项目“自包含、安全、简约”的设计目标冲突

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

### PHPStan 与 Semgrep

完整检查使用 `tools/quality.ps1`，GitHub Actions 在 push 和 pull request 时运行同一工作流。首次运行会把 PHPStan 2.3.0 PHAR 和 Semgrep 1.179.0 安装到 Git 忽略的 `.tools/` 目录；Semgrep 使用 `p/php`、`p/security-audit`、`p/owasp-top-ten` 和 `p/secrets` 规则。

本地可用 `SEMGREP_APP_TOKEN` 登录以启用 Semgrep Pro 规则；CI 通过 GitHub Actions secret `SEMGREP_APP_TOKEN` 读取令牌，令牌不要写入仓库。未配置 secret 时仍运行可公开获取的规则。

若令牌已过期或无效，semgrep.dev 会返回 HTTP 401 导致规则无法下载。`tools/quality.ps1` 会打印警告并自动去掉令牌重跑一次（仅公共规则），避免 CI 因 Secret 失效而误报失败；轮换 Secret 后 Pro 规则自动恢复。

当前 Semgrep 检查覆盖所有受 Git 跟踪的 PHP 文件（含 `var/lbuchs/**`），未报告问题。PHPStan 的既有告警保存在 baseline（3 处文件尾空白、`new static()` 风险提示及两个由插件/主题提供的可选函数）；baseline 之外的新问题会让检查失败。Larastan 面向 Laravel，本项目不是 Laravel，因此未安装或启用。

静态分析还发现并修复了三处问题：`Widget\Comments\Ping` 的 `parentContent` 覆盖与父类返回类型不兼容，会在类加载时触发 PHP 致命错误；`editComment()` 实际不返回值，却声明为 `bool`，现改为 `void`；`Widget\Users\EditTrait::getPageOffset()` 声明返回 `int` 但 `ceil()` 返回 `float`，现改为 `intdiv()` 向上取整。

## 最终部署注意事项

- MySQL 和 PostgreSQL 仍需在对应服务可用后进行连接回归测试。
- 默认主题的 TagCloud 使用了第三方库的 `_next()` 内部方法，后续升级 TagCloud 时需要重新验证。
- 项目没有 PHPUnit/Psalm；基础回归测试继续使用 `tests/smoke.php`，PHPStan 与 Semgrep 的运行方式见上文。
- `php-8.5.10/php.ini` 只适合作为本地测试配置，不应直接作为生产配置。
- 生产环境应关闭 `__TYPECHO_DEBUG__` 和 `display_errors`。
- 部署完成后应删除安装压缩包，并确认数据库文件不能通过 Web 直接下载。
- TypechoRe 数据库不再保证与原版 Typecho 兼容，不能让两个程序连接同一个生产数据库。
