# 代码质量与 PHP 8.5 检查报告

> **数据库兼容性警告：TypechoRe 已不再与原版 Typecho 数据库完全相通。**
> 项目修改了密码/authCode 处理，增加了 `typecho_passkeys` 表，并调整了部分核心行为。迁移到 TypechoRe 后，不应再让原版 Typecho 直接连接同一个生产数据库。

## 检查环境

- PHP 8.5.10
- Windows x64
- SQLite 3（本地）、MySQL 8.4 与 PostgreSQL 16（CI service container）
- Node.js v24.19.0

项目内的 `php-8.5.10/` 仅用于本地检查。生产环境应使用独立配置的 PHP，并根据实际数据库启用相应扩展。

## 当前质量门禁

完整检查使用 `tools/quality.ps1`，GitHub Actions 在 push 和 pull request 时运行
同一工作流（`.github/workflows/quality.yml`）。脚本依次执行：

1. 全部 PHP 文件语法检查（`php -l`）
2. `tests/smoke.php` 基础回归测试
3. `tools/audit-override.php` 反射审计 `#[\Override]` 覆盖率
4. `tests/integration.php` 数据库适配器集成测试（本地默认 SQLite）
5. PHPStan 静态分析（`phpstan.neon`，**level 5**）
6. `tools/e2e.php` 真实站点端到端回归（发布包 + PHP 内置服务器）
7. Semgrep 安全扫描（`p/php`、`p/security-audit`、`p/owasp-top-ten`、`p/secrets`；
   配置 `SEMGREP_APP_TOKEN` 时启用 Pro 规则）

首次运行会把 PHPStan 2.3.0 PHAR 和 Semgrep 1.179.0 安装到被 Git 忽略的
`.tools/` 目录；本地默认使用项目内的 `php-8.5.10/php.exe`，也可通过 `PHP_EXE`
指定 PHP。

### PHPStan level 5

- 扫描范围：`admin`、`install`、`var`、`usr`、`tests`、`install.php`
- 结果：**0 告警**，不再使用 `phpstan-baseline.neon`
- PHP 版本按 `phpVersion: 80500` 校验
- 为表达 Typecho 的运行时动态派发，配置中包含少量显式声明（均有注释）：
  - `universalObjectCratesClasses`：`Typecho\Plugin`（插件钩子名由插件决定）、
    `Typecho\Widget\Request`（请求参数名由 URL 决定）
  - `ignoreErrors`：主题模板访问 `Widget\Archive` 的 protected 成员
    （模板由 `Archive::render()` 以 `require` 载入，运行在组件作用域内）、
    `Plugin::$signal` 的「只写不读」（它通过引用绑定到调用方变量）、
    `IXR\Client::__call()`（XML-RPC 方法名由远端接口决定）

CI 中额外的 `database` job 会用 MySQL 与 PostgreSQL service container 运行
`tests/integration.php`，覆盖 `Mysqli`、`Pdo_Mysql`、`Pgsql`、`Pdo_Pgsql` 与
`SQLite`、`Pdo_SQLite` 六种适配器。

### `#[\Override]` 审计

`tools/audit-override.php` 用反射找出「确实覆盖了父类/接口方法、但没有标注
`#[\Override]`」的类方法。当前 250 处标注，无遗漏。

trait 中声明的覆盖方法不参与审计：属性会由 trait 原样带到每个使用类，只要有
一个使用类在继承链上没有同名方法，就会在类链接期触发致命错误。例如
`TreeTrait::initParameter()` 对 `Page\Admin` 是覆盖，对 `Contents\From` 不是。
脚本会统计并提示被跳过的数量（当前 213 处使用点）。

### 真实站点端到端测试

`tools/e2e.php` 会构建发布包、解压到临时目录、用 PHP 内置服务器起一个真实站点，
然后通过 HTTP 跑完整流程（当前 100 项断言）：

- 安装向导三个步骤（SQLite）
- 前台首页、404 页、Sitemap、Feed
- 后台登录（真实 cookie + 同源 Referer 校验）与 28 个后台页面
- 插件启用/禁用（HelloWorld）与插件配置页
- 发布文章（分类/标签）→ 前台文章页 → 后台列表
- 匿名访客提交评论（CSRF + 反垃圾 + 来源校验）并校验审核状态
- 图片上传（multipart）→ 附件入库 → 附件编辑页 → 文件列表
- Passkey 注册参数（`do=create-options`，覆盖 passkeys 数据表）
- XML-RPC 默认关闭返回 404、开启后 `system.listMethods` 正常
- 1.3.2 升级路径：把版本改回 1.3.1 并删除 passkeys 表，验证后台跳转
  `upgrade.php`、执行升级、版本回到 1.3.2 且 passkey 恢复可用
- 后台登出，并检查服务器日志中没有任何 PHP Warning/Notice/Deprecated/Fatal

运行方式：

```bash
php-8.5.10/php.exe tools/e2e.php              # 自动构建发布包
php-8.5.10/php.exe tools/e2e.php --zip=dist/x.zip --keep --verbose
```

需要 `zip` 扩展（解压发布包）、`curl` 扩展与 Python 3（构建发布包）。
`--keep` 会保留临时站点目录便于排查。

### 数据库集成测试

`tests/integration.php` 直接执行 `install/*.sql` 建表，然后验证：

- 连接与 `getAdapterName()` / `getVersion()`
- INSERT 返回自增主键、UPDATE/DELETE 返回影响行数
- `fetchRow()` / `fetchAll()`（含行过滤回调）/ `fetchObject()`
- 三表 JOIN、聚合查询、`truncate()`
- 1.3.2 升级脚本 `Utils\Upgrade::v1_3_2()` 建表且幂等

本地无 MySQL/PostgreSQL 时，未设置 `TYPECHORE_TEST_HOST` 的适配器会被跳过并提示，
退出码仍为 0；CI 中六种适配器全部执行。

## 已完成检查

### PHP 语法和弃用项

项目内约 239 个受 Git 跟踪的 PHP 文件全部通过 PHP 8.5 语法检查，没有发现语法错误。

针对 PHP 8.5 文档检查了以下兼容性风险：

- `curl_close()` 和 `curl_share_close()`：原先 `var/lbuchs/WebAuthn/WebAuthn.php` 的
  `queryFidoMetaDataService()` 使用 `curl_close()`，该功能在 TypechoRe 中未使用（仅保留
  `none` 自签名），已整体删除
- `imagedestroy()`、`finfo_close()`、`DATE_RFC7231`、`__sleep()` 和 `__wakeup()`、反引号、
  非规范类型转换、`PDO::MYSQL_*` 常量、`ReflectionMethod::setAccessible()`：全部无命中
- PHP 8.5 新增的弃用写法

`var/lbuchs/**` 已不再从 PHPStan 扫描中排除，WebAuthn 相关代码同样受 level 5 覆盖。

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

- bcrypt 密码生成和验证、旧密码兼容验证、authCode 生成/验证/拒绝伪造
- HTML 转义、UTF-8 slug 生成（含全角空格 `mb_trim`）、`json_validate()`、
  `array_first()`/`array_last()`
- 私有地址 SSRF 检查、未配置可信代理时拒绝伪造的 `X-Forwarded-For`
- HMAC-SHA256 短时 token 生成/校验/拒绝伪造值
- `Typecho\Config` 的 `Iterator`/`ArrayAccess` 原生 `mixed` 返回类型
- `fileinfo` 内容嗅探 MIME、备份缓冲 v2（SHA-256）与 v1（MD5）双向兼容
- 遗留类名别名表：每个别名目标都能加载，`Typecho_Plugin_Interface`、
  `Widget_Abstract_Contents`、`Typecho_Db_Adapter_Mysql` 等旧类名可被自动加载器解析
- SQLite 建表、插入、查询和读取；WebAuthn Passkey 注册参数生成
- 回归断言（对应本轮修复）：
  - `XmlRpc` 依赖的 `Post\Edit::setCategories()` 保持 public
  - `Attachment\Edit` 具备 `PageOffsetTrait::getPageOffset()`
  - `PageNavigator` 的 `ceil()` 整数等价实现（10/5→2、10/4→3）
  - IXR 联合类型参数不再触发 `ReflectionUnionType::getName()` 致命错误
  - IXR `system.multicall` 能正确识别错误（`instanceof`）
- 全量类加载（覆盖 170+ 类），任一 `#[\Override]` 签名漂移或类链接错误都会在此失败

### JavaScript

项目内 JavaScript 文件通过 Node.js 语法检查，未发现语法错误。后台依赖已升级并通过加载测试：

- jQuery 3.7.1、jQuery UI 1.14.2、DOMPurify 3.4.15、jQuery Timepicker 1.6.3

### Passkey/WebAuthn

Passkey 核心代码位于 `var/lbuchs/WebAuthn/`，不再依赖体积较大的 `vendor/`。当前仅保留
`none` 自签名认证所需代码，支持 Windows Hello、手机同步 Passkey、浏览器
Discoverable Credential 和 FIDO2 安全密钥。

注册和登录均要求 User Verification，并校验 Challenge、Origin、RP ID、User Handle、
签名计数器和公钥签名。Passkey 私钥不会保存到数据库。该目录已纳入 level 5 扫描并完成
类型清理（`randomBuffer()` 的 `string $length` → `int<1, max>`、
`getCertificatePem()` 的返回值可空、`ByteBuffer::equals()` 的冗余判断等）。

Passkey 需要 `openssl`、`mbstring`、`sodium`、`session` 和数据库扩展，并且正式部署需要 HTTPS。

### 临时站点集成测试

使用 PHP 内置服务器和临时 SQLite 数据库完成了真实请求测试：安装向导、首页、
后台登录、旧 MD5 密码登录并自动升级为 bcrypt、后台发布文章、评论提交、
评论 CSRF Token、文件上传、图片附件入库、Sitemap（含分页）、XML-RPC 基础请求、
后台登出。本版本已在生产站点完成部署验证（2026-10-09），暂未发现回归问题。

## level 5 发现并修复的缺陷

这些是 level 0 完全看不到、但在真实请求中可能触发的问题：

| 位置 | 问题 | 结果 |
| --- | --- | --- |
| `var/Widget/XmlRpc.php` | `mt.setPostCategories` 调用 protected 的 `setCategories()` | 调用即致命错误；方法已改为 public |
| `var/IXR/Server.php` | 联合类型参数调用 `ReflectionType::getName()` | 致命错误；只对 `ReflectionNamedType` 处理 |
| `var/IXR/Server.php` | `is_a($result, 'Error')` 匹配到 PHP 内置 `\Error` | multicall 永不返回错误；改为 `instanceof` |
| `var/Widget/Contents/Attachment/Edit.php` | 调用不存在的 `getPageOffset()` | 分页参数被 `__call()` 吞掉；抽出 `PageOffsetTrait` |
| `var/Widget/Contents/AdminTrait.php` | v1.3.0 重构丢失 `___hasSaved()` | XML-RPC 无法报告草稿状态；已恢复 |
| `var/Widget/Base/Metas.php` | `clearTags()` 未上移到基类 | 删除文章后孤立标签不清理；已上移 |
| `var/Widget/Contents/Page/Rows.php` | `listRows()` 参数错位 | 页面树 CSS 类错误、主题回调失效 |
| `var/Widget/Archive.php` | 日期归档非法参数使用未定义变量 | 现在返回 404 |
| `var/Widget/Contents/EditTrait.php` | 半小时时区生成非法时间串 | 改为分别计算时与分 |
| `var/Widget/Feedback.php` | `Router::match()` 返回值与属性类型不符 | 局部变量收窄后再赋值 |
| `install.php` | 重复建表检测恒假 | PDO 驱动下重新能保留原有数据 |
| `Typecho\Db\Adapter` | 接口仍声明 `resource` | 改为真实对象类型（含 `@template` trait） |

## 动态派发与静态分析的边界

Typecho 大量使用运行时动态派发，以下几处在 PHPStan 中无法用类型系统完整表达，
已在 `phpstan.neon` 中显式声明（而不是用 baseline 压制）：

- `Typecho\Widget::__get()/__call()`：`$widget->field` 与 `$widget->field()` 的名称
  来自数据表列或 `___xxx()` 方法。基础类已用 `@property` / `@method` 标注常用成员；
  请求参数与插件钩子名无法穷举，故采用对象箱语义
- `Typecho\Config::__call()`：主题自定义的评论选项名
- `IXR\Client::__call()`：XML-RPC 方法名由远端接口决定
- 主题模板：`$this->options` 等 protected 成员访问在组件内部作用域内合法

## 未完成与已知限制

- **MySQL / PostgreSQL 只在本机以外的 CI 中测试**：本机没有运行数据库服务，
  `Mysqli`/`Pdo_Mysql`/`Pgsql`/`Pdo_Pgsql` 的 SSL 连接与多库并发行为仍未覆盖
- 项目没有 PHPUnit/Psalm：基础回归测试使用 `tests/smoke.php`，数据库测试使用
  `tests/integration.php`，真实请求测试使用 `tools/e2e.php`
- `tools/e2e.php` 只覆盖 SQLite 站点；MySQL/PostgreSQL 的真实请求路径仍需在对应
  服务可用时手工验证（CI 只跑适配器层集成测试）
- 无 `cid` 直接访问 `admin/media.php` 会返回 500（上游同样如此，后台界面不会生成该
  链接）；如需对外开放该地址应补 404 处理
- `#[\Override]` 无法标注 trait 中声明的覆盖方法（见上文说明）
- 默认主题的 TagCloud 使用了第三方库的 `_next()` 内部方法，后续升级 TagCloud 时
  需要重新验证
- `var/Utils/HyperDown.php`、`var/Utils/AutoP.php`、`var/IXR/**` 为内嵌的上游代码，
  已纳入 level 5 扫描并修复类型问题，但保持与上游一致的实现风格
- `Utils\Upgrade` 的升级脚本按版本号顺序执行；新增数据库结构时应在其中补充对应
  `vX_Y_Z()` 方法，而不是只改 `install/*.sql`

## 最终部署注意事项

- `php-8.5.10/php.ini` 只适合作为本地测试配置，不应直接作为生产配置
- 生产环境应关闭 `__TYPECHO_DEBUG__` 和 `display_errors`
- 部署完成后应删除安装压缩包，并确认数据库文件不能通过 Web 直接下载
- 升级到 1.3.2 后请访问一次后台以执行升级脚本（会为旧站点补建
  `typecho_passkeys` 表，幂等）
- TypechoRe 数据库不再保证与原版 Typecho 兼容，不能让两个程序连接同一个生产数据库
