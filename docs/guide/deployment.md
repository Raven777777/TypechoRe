# TypechoRe 部署与恢复

## 发布包内容

生产环境至少需要：

```text
admin/
install/
usr/
var/
index.php
install.php
config.inc.php
```

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
openssl
sodium
session
sqlite3
pdo_sqlite
json
Reflection
```

Passkey 额外依赖 `openssl`、`mbstring`、`sodium` 和 `session`。

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
