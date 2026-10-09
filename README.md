![TypechoRe](./TypechoRe.svg)

**安全，简约，实用主义**

**TypechoRe** 是 [Typecho](https://github.com/typecho/typecho) 的积极维护 Fork 版本。

> **TypechoRe 不再与原版 Typecho 数据库完全兼容。**
> TypechoRe 修改了密码处理方式并调整了部分核心数据结构和行为。建议将 TypechoRe 视为独立分支使用。

> **数据库支持范围：本项目只维护 SQLite。**
> `Mysqli` / `Pdo_Mysql` / `Pgsql` / `Pdo_Pgsql` 适配器代码虽然保留
> 请使用 SQLite；如确需其他数据库，请自行测试验证并自行承担风险，或改用原版 Typecho。

## 环境要求

* PHP 8.5 或更高
* 必需扩展：`mbstring`、`json`、`Reflection`，以及 SQLite 扩展（`sqlite3` 或 `pdo_sqlite`）
* 推荐扩展：`fileinfo`（基于内容的 MIME 探测）、`curl`（远程 HTTP 请求）、`gd`（图片处理）、`zip`
* Passkey/WebAuthn：需要 `openssl`、`mbstring`、`sodium`、`session`，以及 SQLite 扩展
* 数据库：SQLite 3.7.11 或更高

## 文档

文档位于 [`docs/`](docs/)，来源：https://github.com/benzBrake/typecho-docs ，并已根据本仓库代码现状核验更新。

文档：

* [部署与恢复及生产包构建](docs/guide/deployment.md)
* [Passkey / WebAuthn](docs/guide/passkey.md)
* [代码质量与 PHP 8.5 检查报告](docs/guide/quality.md)
* [更新日志](CHANGELOG.md)

## 许可证

核心代码按 GNU GPL v2 发布，完整许可证文本见 [`LICENSE.txt`](LICENSE.txt)。

## 反馈问题

请在 https://github.com/Raven777777/TypechoRe/issues 提交 Issue。
