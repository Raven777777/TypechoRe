# 内置 Sitemap 功能

TypechoRe 内置了 Sitemap 生成功能，无需插件即可输出符合 sitemaps.org 协议的 XML Sitemap。

### 路由

- `/sitemap.xml`
- `/sitemap-[page:digital].xml`

实现类为 `Widget\Action\Sitemap`。

### 分页能力

- 当全站可收录 URL 总数不超过 `1000` 时，`/sitemap.xml` 直接输出单个 `<urlset>`。
- 当超过 `1000` 个 URL 时，`/sitemap.xml` 自动输出标准 `<sitemapindex>`。
- 分页子 Sitemap 地址格式为 `/sitemap-1.xml`、`/sitemap-2.xml`。
- `<sitemapindex>` 符合 sitemaps.org XML Sitemap 协议，每个子 Sitemap 使用 `<sitemap><loc>...</loc></sitemap>`。

### 收录范围

- 已发布文章：`type = post` 且 `status = publish`。
- 已发布独立页面：`type = page` 且 `status = publish`。
- 排除未来定时内容：`created < 当前时间`。
- 排除密码保护内容：`password` 为空或 `NULL`。
- 收录有内容的分类：`metas.count > 0`。
- 收录有内容的标签：`metas.count > 0`。

### 路由注册与兼容

- 默认 Sitemap 路由会自动注册。
- 旧版本路由表兼容：旧安装无需手动修改数据库；如果旧路由表中缺少 `sitemap` 或 `sitemapPage`，系统会在运行时自动注入；全新安装会直接写入默认路由。

### 性能

- 为 1000+ 文章规模的站点加入分页与内存优化。
- 每个子 Sitemap 最多输出 `1000` 个 URL，避免单次生成超大 XML。
- 查询内容时不再读取 `contents.text` 大字段，仅读取 Sitemap 所需字段。
- 内容按 `cid` 升序；分类和标签按 `mid` 升序。
- 每 `100` 个 URL 触发一次输出 flush，降低大文件输出时的内存峰值。
- 子 Sitemap 输出完成后释放临时 Widget 栈。
- 协议硬上限保护：单个 `<urlset>` 最多 `50,000` 个 URL；单个 `<sitemapindex>` 最多 `50,000` 个子 Sitemap。

### 标准

- 按 sitemaps.org XML Sitemap 标准输出，支持 `<urlset>`、`<sitemapindex>`、`<url>`、`<sitemap>`、`<loc>`、`<lastmod>`、`<changefreq>`、`<priority>`。
- XML 文件统一输出 UTF-8，输出标准命名空间：

  ```xml
  xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
  ```

- 同时输出 XML Schema 位置信息：

  ```
  xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 ..."
  ```

- `<loc>` 执行 URL escaping 和 XML entity escaping。
- `<loc>` 长度超过或等于 2048 字符时自动跳过。
- Sitemap 中所有 URL 强制校验为同一 host。
- `<lastmod>` 使用 W3C Datetime 格式。
- `<changefreq>` 仅允许标准值：`always`、`hourly`、`daily`、`weekly`、`monthly`、`yearly`、`never`。
- `<priority>` 限制在 0.0 到 1.0。

### 默认优先级与更新频率

| URL 类型 | changefreq | priority |
|:--|:--|:--|
| 首页 | daily | 1.0 |
| 文章 | weekly | 0.8 |
| 独立页面 | weekly | 0.6 |
| 分类 | weekly | 0.6 |
| 标签 | weekly | 0.4 |

- 首页 `<lastmod>` 根据已发布内容的最新 modified 或 created 时间生成。
- 文章和页面 `<lastmod>` 使用 max(created, modified)。
- 分类和标签默认不输出 `<lastmod>`。

### 插件 API

- `sitemapUrls`：用于过滤或扩展当前 Sitemap 文件中的 URL 列表。
- `sitemapIndex`：用于过滤或扩展 Sitemap Index 中的子 Sitemap 列表。
- 注意：启用分页后，`sitemapUrls` 会在每个分页子 Sitemap 上分别触发，而不是只在整个站点 Sitemap 上触发一次。
- 插件返回的非标准 `changefreq`、`priority`、`lastmod`、跨主机 URL 或超长 URL 会被自动忽略或规范化。

### 兼容性与测试

- 无数据库结构变更，无强制升级步骤。
- 旧站点无需重建路由表即可访问 `/sitemap.xml`；全新安装会自动包含 Sitemap 路由。
- 已在 PHP 8.5 + SQLite 环境下测试：
  - 小数据量：`/sitemap.xml` 返回合法 `<urlset>`，HTTP 状态码 200。
  - 1000+ 内容：`/sitemap.xml` 返回合法 `<sitemapindex>`，`/sitemap-1.xml` 输出 1000 个 URL，`/sitemap-2.xml` 输出剩余 URL，超出分页范围返回 404。
  - 草稿、私密内容、密码保护内容、未来定时发布内容不会出现在 Sitemap。
  - 已验证特殊字符 URL 的转义（`&`、`<`、`>`、`"`、`'`、非 ASCII 字符）。
  - 前台首页、文章页、分类页路由不受影响。
