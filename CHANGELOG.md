# 更新日志

本文件记录 TypechoRe 各版本的代码级改动。主题改动见
[`usr/themes/default/CHANGELOG.md`](usr/themes/default/CHANGELOG.md)。

## 1.3.2

本版本不改变前台行为，重点是「把静态分析提升到 PHPStan level 5 并清零」，
顺带修掉了 level 5 暴露出来的真实缺陷。**数据库结构新增 `typecho_passkeys`
自动升级步骤；升级后请勿再用原版 Typecho 连接同一数据库。**

### 质量门禁

- PHPStan 从 level 0 提升到 **level 5**，`admin`、`install`、`var`、`usr`、
  `tests`、`install.php` 全部纳入扫描，**0 告警、不再使用 baseline**
  （此前只扫 `admin/install/var`，且用 baseline 压制既有告警）
- 新增 3 条有注释的 `ignoreErrors`，以及 2 个 `universalObjectCratesClasses`
  （`Typecho\Plugin`、`Typecho\Widget\Request`），用于表达 Typecho 的运行时动态派发
- 新增 `tools/audit-override.php`：反射审计 `#[\Override]` 覆盖率，已接入
  `tools/quality.ps1`；`#[\Override]` 从 239 处增至 **250 处**
- 新增 `tools/e2e.php`：用发布包在临时目录起真实站点，跑安装向导、28 个后台页面、
  发布文章、匿名评论、图片上传、插件启停、Passkey 参数、XML-RPC 与 1.3.2 升级路径，
  共 100 项断言，并检查服务器日志无任何 PHP 警告
- 新增 `tests/integration.php`：真实数据库适配器集成测试（建表/CRUD/JOIN/
  truncate/lastInsertId/affectedRows/升级脚本），本地默认跑 SQLite，
  CI 通过 MySQL 与 PostgreSQL service container 跑 `Mysqli`/`Pdo_Mysql`/
  `Pgsql`/`Pdo_Pgsql`
- 新增 `.github/workflows/release.yml`：推送 `v*` tag 时自动构建发布包并附到
  GitHub Release

### 修复的真实缺陷

- `var/Widget/XmlRpc.php`：调用 `Widget\Contents\Post\Edit::setCategories()`
  时遇到 protected 方法，`mt.setPostCategories` 会触发致命错误；该方法改为 public
- `var/Widget/Contents/Attachment/Edit.php`：`getPageOffsetQuery()` 调用了附件
  组件没有的 `getPageOffset()`，请求被 `__call()` 静默吞掉；抽出
  `Widget\Contents\PageOffsetTrait` 供文章/页面/附件共用
- `var/IXR/Server.php`：`$parameter->getType()->getName()` 在参数声明为联合类型时
  会触发 `ReflectionUnionType::getName()` 致命错误；改为只对
  `ReflectionNamedType` 的内置类型做 `settype()`，并补上参数个数检查
- `var/IXR/Server.php`：`is_a($result, 'Error')` 在命名空间下会匹配 PHP 内置
  `\Error`，导致 `system.multicall` 永远无法识别错误；改为 `instanceof Error`
- `var/Widget/Contents/AdminTrait.php`：恢复上游 v1.2.1 中被 v1.3.0 重构丢失的
  `___hasSaved()`，XML-RPC 的 `wp.getPosts` / `metaWeblog.getPost` 重新能报告草稿状态
- `var/Widget/Contents/Page/Rows.php`：`listRows()` 参数错位（把回调名当成类型、
  把当前页当成回调名），页面树输出使用了错误的 CSS 类且主题回调失效
- `var/Widget/Base/Metas.php`：`clearTags()` 只在 `Tag\Edit` 上声明，删除文章时
  `Metas::alloc()->clearTags()` 被 `__call()` 吞掉，孤立标签不会被清理；方法上移到
  `Base\Metas`
- `var/Widget/Archive.php`：日期归档参数非法（如 `/archive/abc/`）时会使用未定义
  变量构造查询；现在返回 404
- `var/Widget/Contents/EditTrait.php`：时区偏移用 `str_pad($offset / 3600)` 输出，
  UTC+5:30 这类半小时时区会生成非法时间串；改为分别计算时与分
- `var/Widget/Feedback.php`：`$this->content` 声明为 `Widget\Archive`，但
  `Router::match()` 可能返回 `false`/其它组件；改为局部变量收窄后再赋值
- `var/Widget/Service.php`、`var/Widget/Action/Sitemap.php`、`var/Widget/Base/Comments.php`：
  修正恒真/恒假的冗余判断与 `ceil()` 到整数的隐式转换
- `install.php`：重复建表检测的 `'42S01' == $code` 在 PHPStan 看来恒假（PDO 返回
  SQLSTATE 字符串、mysqli 返回数字码），统一按字符串比较，安装向导在
  PDO 驱动下重新能进入「保留原有数据」分支

### PHP 8.5 现代化

- 新增 `#[\Deprecated]` 标注 16 处（`Json::encode/decode`、
  `Common::isAvailableClass/arrayFlatten/isAppEngine`、`Date::gmtTime()`、
  `Plugin::__call()`、`Widget::destory()`、`Archive` 的 7 个旧 getter/setter、
  `IXR\Client::setDebug()`）
- `Typecho\Common::CLASS_ALIASES` 成为遗留类名别名表的唯一来源，自动加载器与
  `Widget\Init` 共用它；站点仍可在 `config.inc.php` 中覆盖
  `__TYPECHO_CLASS_ALIASES__`
- `Typecho\Db\Adapter` 接口与全部实现改用真实对象类型
  （`PDOStatement` / `mysqli_result` / `SQLite3Result` / `PgSql\Result`），
  不再声明早已不存在的 `resource`
- `PgsqlTrait` 使用 `@template` 描述两种适配器的句柄/连接类型
- 补齐 `Widget\Request::get()` 等一批 PHPDoc 类型（`@param null` → `mixed`、
  `array?` → `?array`、`@var array()` → `array` 等），修正 12 处 PHPDoc 语法错误
- `var/lbuchs/WebAuthn/**`（内置 Passkey 实现）同步清理类型与冗余判断
- 内置 `Sticky` 插件改写为命名空间插件（`TypechoPlugin\Sticky\Plugin`），
  保留旧类名兼容

### 升级说明

- 版本号更新到 1.3.2；旧站点首次访问后台会提示升级，升级脚本会为缺少
  `typecho_passkeys` 表的站点自动建表（MySQL/PostgreSQL/SQLite 三种 DDL，幂等）
- 数据库不再保证与原版 Typecho 兼容，请勿让两个程序连接同一数据库

## 1.3.1（2026-09-22）

- Passkey / WebAuthn 登录
- 短时 token 改为 HMAC-SHA256 + `hash_equals()`
- 依赖库升级：jQuery 3.7.1、jQuery UI 1.14.2、DOMPurify 3.4.15、
  jQuery Timepicker 1.6.3
