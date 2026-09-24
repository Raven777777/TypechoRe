# TypechoRe 博客平台 

**设计哲学：安全，简约，必要功能**

**TypechoRe** 是 [Typecho](https://github.com/typecho/typecho) 的积极维护 Fork 版本 —— 一款基于 PHP 的博客程序。

> **重要兼容性说明：TypechoRe 不再与原版 Typecho 数据库完全兼容。**
> TypechoRe 已修改密码/authCode 处理、增加 `typecho_passkeys` 表，并调整了部分核心数据结构和行为。升级或使用 TypechoRe 数据库后，请不要再让原版 Typecho 直接连接同一个数据库。迁移前请务必备份数据库；建议将 TypechoRe 视为独立分支使用。

## 主要特性

* 支持多种数据库（MariaDB、MySQL、SQLite、PostgreSQL）
* Markdown 支持
* 插件支持
* 主题支持
* 自定义字段
* 自定义页面


## 环境要求

* PHP 8.5 或更高
* 必需扩展：`mbstring`、`json`、`Reflection`，以及至少一种数据库扩展
* 可选扩展：`curl`（远程 HTTP 请求）、`gd`（图片处理）、`zip`
* Passkey/WebAuthn：需要 `openssl`、`mbstring`、`sodium`、`session`，以及当前使用的数据库扩展
* 数据库（MariaDB、MySQL、SQLite、PostgreSQL）
  * MariaDB 或 MySQL 5.5.3 或更高
  * SQLite 3.7.11 或更高
  * PostgreSQL 9.1 或更高


## 质量检查

项目使用 PHP 8.5 进行过语法、运行时和基础回归测试：

```bash
php-8.5.10/php.exe tests/smoke.php
```

测试覆盖密码哈希、authCode、CSRF/IP 基础行为、HTML 转义和 SQLite 读写。完整检查结果与已知限制见 [`docs/quality.md`](docs/quality.md)。

## Passkey 登录

TypechoRe 支持现代浏览器的 Passkey/WebAuthn 登录，包括 Windows Hello、手机同步 Passkey 和 FIDO2 安全密钥。首次使用时请使用密码登录后台，在个人资料中注册 Passkey，然后再在登录页使用 Passkey。

Passkey 需要 HTTPS（`localhost` 除外），并且浏览器访问域名必须与站点配置的 RP ID 匹配。

## 文档

文档位于 [`docs/`](docs/)，来源：https://github.com/benzBrake/typecho-docs ，并已根据本仓库代码现状核验更新。

重点文档：

* [部署与恢复](docs/deployment.md)
* [Passkey / WebAuthn](docs/passkey.md)
* [代码质量与 PHP 8.5 检查报告](docs/quality.md)

## 反馈问题

请在 https://github.com/Raven777777/TypechoRe/issues 提交 Issue。
