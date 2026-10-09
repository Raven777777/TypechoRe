# TypechoRe 部署与恢复

## 发布包内容

构建用于网站根目录的代码 ZIP（需要 Python 3.9+，无额外依赖）：

```bash
python tools/build_release.py
```

输出为 `dist/TypechoRe-<版本>.zip`，解压后文件直接位于网站根目录。发布包包含 `admin/`、`install/`、`usr/`、`var/` 及入口文件；不会包含本地 `config.inc.php`、数据库、日志、备份、缓存或用户上传文件。

生产站点还需要 `config.inc.php` 和数据库，但它们属于站点数据，不放进代码包：首次安装由安装向导生成；升级时保留服务器原有配置、数据库和上传文件，先备份再覆盖代码。

SQLite 数据库建议放在：

```text
usr/2233.db
```

`config.inc.php` 中使用相对于站点根目录的路径：

```php
$db = new \Typecho\Db('SQLite', 'typecho_');
$db->addServer([
    'file' => __TYPECHO_ROOT_DIR__ . '/usr/2233.db'
], \Typecho\Db::READ | \Typecho\Db::WRITE);
\Typecho\Db::set($db);
```

SQLite 所在目录必须允许 PHP-FPM 创建数据库锁文件、临时文件和 WAL 文件。

## PHP 环境

建议 PHP 8.5 或更高版本，并启用：

```text
mbstring
fileinfo
openssl
sodium
session
sqlite3
pdo_sqlite
json
Reflection
```

Passkey 额外依赖 `openssl`、`mbstring`、`sodium` 和 `session`。

建议的 php.ini 生产配置：

```ini
; OPcache: PHP 8.5 已内置, 不要再写 zend_extension=php_opcache.dll
opcache.enable=1
opcache.memory_consumption=192
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0 ; 发布包部署时关闭 stat 检查
opcache.jit=tracing
opcache.jit_buffer_size=64M

session.use_strict_mode=1
session.use_only_cookies=1
session.cookie_httponly=1
session.cookie_samesite=Lax

expose_php=Off
display_errors=Off
zend.assertions=-1
```

`fileinfo` 用于基于文件内容做 MIME 嗅探（`Typecho\Common::mimeContentType()` 会优先使用
`mime_content_type()`/`finfo`），缺失时会退化为扩展名映射。`sodium` 用于 WebAuthn
Ed25519 公钥签名验证；部分 OpenSSL 构建（例如 Windows 发行包）不提供 `ed25519`
曲线，此时没有 `sodium` 将无法验证 EdDSA 类型的 Passkey。

`opcache.validate_timestamps=0` 只适用于通过发布包整包覆盖部署的场景；若直接在服务器上改代码，请保持默认值 `1`。

### Nginx 登录限速

登录失败的延迟只是补充措施，不能替代限速。若使用 Nginx，在 `http {}` 中配置按客户端 IP 计数的 zone：

```nginx
map $request_uri $typechore_login_key {
    default "";
    ~*(?:/index\.php)?/action/login(?:\?|$) $binary_remote_addr;
}
limit_req_zone $typechore_login_key zone=typechore_login:10m rate=10r/m;
```

在现有处理 Typecho PHP/front-controller 请求的 `location` 中添加（不要另建一个会覆盖现有 PHP handler 的 `location`）：

```nginx
limit_req zone=typechore_login burst=5 nodelay;
limit_req_status 429;
```

按访客共享出口 IP 的情况调整阈值。限速配置需部署到 Nginx 并重载后才生效。

## 部署流程

1. 备份服务器当前数据库
2. 备份 `config.inc.php`
3. 上传代码
4. 上传数据库到 `usr/`
5. 检查 `config.inc.php` 的数据库路径
6. 检查 PHP-FPM 对 `usr/2233.db` 和 `usr/uploads/` 的读写权限
7. 访问首页和后台
8. 检查错误日志
9. 测试登录、文章、评论、上传、Sitemap 和 Passkey
10. 确认运行正常后删除部署压缩包

发布包由 `tools/build_release.py` 构建（或由 GitHub Release 自动构建），已排除
`config.inc.php`、`*.db`、`*.log`、`usr/uploads/` 与 `usr/backups/`。部署前仍建议
确认 zip 内没有遗留的数据库或日志文件。

已安装的网站不需要重复访问：

```text
install.php?step=2
```

重复安装可能造成 SQLite 数据库部分初始化，从而出现部分表存在、部分表缺失的情况。

## 插件与数据库一致性

数据库的 `typecho_options` 记录了已激活插件。每个激活的插件都必须存在对应代码目录。

例如数据库中如果激活了 `Sticky`，代码中必须存在：

```text
usr/plugins/Sticky/Plugin.php
```

否则首页可能出现：

```text
class "Sticky_Plugin" not found
```

注意：`TagToText` 插件自 TypechoRe 起已整合进核心
（撰写页标签速选面板，设置在「管理 → 标签」右侧栏）。
升级前请先在后台停用并删除该插件，否则会出现插件记录
指向不存在目录的错误。

恢复数据库或更换代码包后，应检查激活插件和实际插件目录是否一致。

## 数据库兼容性警告

> **数据库支持范围：本项目只维护 SQLite。** MySQL / MariaDB / PostgreSQL 适配器代码虽然保留，但不维护、不在 CI 中测试，相关 Issue / PR 可能不会被处理。下面涉及 MySQL / PostgreSQL 的 SQL 仅供存量安装参考，请自行验证。

TypechoRe 已经不是与原版 Typecho 完全相通的数据库分支。

TypechoRe 修改了：

- 密码哈希与 authCode 存储方式 (仅支持 bcrypt cost 12 / SHA-512, 不兼容旧格式)
- Passkey 数据表
- CSRF 和 Session 行为
- 部分数据库和请求处理逻辑

因此：

- 不要让原版 Typecho 和 TypechoRe 同时连接同一个生产数据库
- 不要用原版 Typecho 直接回滚运行 TypechoRe 数据库
- 数据库迁移前必须备份
- 数据库备份和网站代码应保持版本对应

### 从 1.3.1 升级到 1.3.2

1.3.2 没有破坏性数据库变更：

- 首次进入后台时版本号比对会引导执行升级脚本；
  `Utils\Upgrade::v1_3_2()` 会为缺少 `typecho_passkeys` 的旧站点建表
  （MySQL / PostgreSQL / SQLite 三种 DDL，重复执行安全）
- 升级后可在数据库中确认该表已存在；不需要手动执行 SQL
- `# [\Override]`、PHPStan level 5 等改动只涉及代码，不影响数据

### 从旧版本升级 authCode 列宽

authCode 摘要为 128 位十六进制（SHA-512），旧安装的 `users.authCode` 列宽
为 varchar(64)。SQLite 不受影响（TEXT 亲和性不强制列宽）；MySQL / PgSQL
存量安装需手动执行：

```sql
-- MySQL
ALTER TABLE `typecho_users` MODIFY `authCode` varchar(128) default NULL;

-- PostgreSQL
ALTER TABLE "typecho_users" ALTER COLUMN "authCode" TYPE varchar(128);
```

执行后重新登录一次，所有会话凭证即切换为新格式。

## 敏感文件保护

### 为什么需要

`config.inc.php` 含数据库路径与站点密钥, SQLite 数据库文件含全部用户
数据。两者的正常防线不同:

- `config.inc.php` 依赖 PHP 正确执行且无输出; 一旦 PHP handler 配置
  事故 (如 FPM 未启动、扩展名误映射), 会被当作静态文件原样返回
- SQLite 数据库文件名虽为 128 位随机串, 但文件名保密不作为安全边界

两者都应在 Web 服务器层硬拒绝, 不依赖 PHP 行为或文件名保密。

### Nginx

```nginx
# server {} 块内
location = /config.inc.php { deny all; }
location ~* \.(db|sql)$    { deny all; }
```

### Apache

```apache
# .htaccess 或 vhost 配置
<FilesMatch "(config\.inc\.php|\.(db|sql))$">
    Require all denied
</FilesMatch>
```

### IIS

发布包自带的 `web.config` 已包含 `.db` / `.sql` 扩展名拒绝规则与
`config.inc.php` URL 序列拒绝, 无需额外配置。

### 根治方案

将数据库文件移出 web 根目录 (修改 `config.inc.php` 中的 `file` 路径)
是根治方案, 但部分主机环境对上层目录没有写权限, 因此默认保持
`usr/` 内随机名 + 服务器层拒绝的组合方案。

## SQLite 数据库检查

可以用 PHP 检查关键表：

```bash
php -r '
$db = new SQLite3("usr/2233.db");
foreach (["typecho_options", "typecho_users", "typecho_contents", "typecho_comments", "typecho_passkeys"] as $table) {
    $ok = $db->querySingle("SELECT 1 FROM sqlite_master WHERE type=\"table\" AND name=\"$table\"");
    echo $table . ": " . ($ok ? "OK" : "MISSING") . PHP_EOL;
}
'
```

## 安全收尾

生产环境确认完成后：

- 关闭 `__TYPECHO_DEBUG__`
- 关闭 `display_errors`
- 删除服务器上的 ZIP 压缩包
- 不公开 `config.inc.php`
- 不公开 SQLite 数据库文件
- 不公开数据库备份
- 保持 HTTPS
- 保留至少两种登录恢复方式
