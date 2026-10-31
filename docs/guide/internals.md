# 内核契约

这些规则影响安全或兼容性，改动时应先更新对应测试。

1. **SQL 列名与关键字**：`Query::filterColumn()` 将普通标识符交给适配器加引号；`table.` 只在开头替换成当前表前缀；函数/表达式周围的标点决定引号边界。`KEYWORDS` 是不加引号的 SQL 语法词白名单，不是完整 SQL 解析器。新增关键字必须在 SQLite 上验证。测试：`tests/regressions.php`（由 `tests/integration.php` 对两个 SQLite 适配器执行）。
2. **CSRF token 路径**：`Security::normalizeTokenPath()` 把绝对 URL 规整为站点相对路径，去掉 query、子目录前缀和 `/index.php`。token 绑定路径而不是完整 URL，因此不能用它区分同一路径上的 query 参数（例如具体文章 ID）；不要把它当作资源级授权。测试：`tests/smoke.php` 与 `tools/e2e.php`。
3. **整页缓存**：`PageCache::eligible()` 只允许无 query 的匿名 GET；授权头、后台/动作/feed/sitemap 路径和整页插件钩子均绕过缓存。Typecho 会话/状态 cookie 会绕过缓存，普通统计 cookie 不会。写操作通过 generation token 使旧缓存失效；测试：`tests/smoke.php`、`tools/e2e.php`。
4. **登录限速**：`Throttle` 用临时目录中的 `scope + 小写 subject` 哈希文件计数，文件以锁保护；上限 10 次、窗口 900 秒。目录不可用或锁/文件打开失败时 fail-open（不拒绝登录），因此部署必须保证临时目录可写。测试：`tests/smoke.php` 与 `tools/e2e.php`。
5. **备份格式**：导出文件以 `%TYPECHO_BACKUP_0002%` 开始和结束；中间是有长度信息的数据块。恢复前会校验两端版本头，并在事务中替换数据；包含用户数据时必须至少有一个管理员，否则整体回滚。恢复会清空现有数据，确认备份来源可信且已有可用备份后再操作。测试：`tests/integration.php`、`tools/e2e.php`。
