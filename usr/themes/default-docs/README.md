# Default Docs

极简单栏文档风主题, 复刻 `tools/docsweb` 的排版: 窄栏、无侧边栏、日期在左标题在右。

- **没有 JavaScript 也能读**: 列表、文章、独立页面、评论和翻页全部由 PHP 输出; 打开 JavaScript 只是额外得到代码复制按钮和语言标签。
- **代码高亮走 CDN**: highlight.js 从 cdnjs 加载 (可在后台「外观设置」里换成自建镜像或站内相对路径), 主题不打包任何高亮脚本。
- **零构建**: 纯 PHP + 一份 `style.css`, 不需要 Node, 改完直接生效。
- **分类 / 归档 / 标签索引页**: 地址 `/categories.html`、`/archives.html`、`/tags.html` 由主题自己认领, 直接用 `page-categories.php` 等模板渲染, **不会往数据库写独立页面**, 换主题不会留下多余的页面。
- **无 JavaScript 也能评论**: 姓名 / 邮箱 / 网址只在提交时由脚本拆回三个隐藏字段; 没有脚本时字段直接可见, 不会把邮箱写进评论正文。

## 目录结构

```text
usr/themes/default-docs/
├── index.php            首页 (独立页链接 + 文章列表)
├── archive.php          通用归档: 分类 / 标签 / 作者 / 搜索 / 日期
├── post.php             文章页 (上下篇、评论)
├── page.php             独立页面
├── page-categories.php  分类索引 (给 /categories.html 用, 也可当独立页模板选)
├── page-archives.php    按年月归档索引 (给 /archives.html 用)
├── page-tags.php        标签索引 (给 /tags.html 用)
├── comments.php         评论列表与表单
├── header.php           文档头、页头导航、highlight.js 加载与代码复制脚本
├── footer.php           页脚
├── 404.php              错误页
├── functions.php        主题设置、置顶标记、链接与分页辅助函数、索引页认领
└── style.css            全部样式
```

## 主题设置

后台「控制台 → 外观 → 设置外观」只有一个选项:

| 选项 | 说明 |
| --- | --- |
| highlight.js 地址 | highlight.js 11 的目录地址, 主题从这里加载 `highlight.min.js` 与 `styles/github-dark.min.css`。默认 `https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.12.0`; 可以填自建镜像 (如 `/assets/highlight`) 实现完全离线; 留空恢复默认。 |

改高亮配色时, 把目录里的 `styles/github-dark.min.css` 换成别的文件名即可 (文件名在 `header.php` 里写死), 或直接覆盖该文件。

## 索引页说明

`/categories.html`、`/archives.html`、`/tags.html` 不是数据库里的独立页面, 而是 `functions.php` 里 `themeInit` 认领的地址: 命中时改成 200 并渲染对应模板, 其它未知地址照常走 404。所以:

- 不需要在后台手动新建页面;
- 换到别的主题后, 这 3 个地址会回到 404, 不会在别的主题里冒出分类/归档/标签页面;
- 旧版本 (2.0 之前) 用过这个主题的站点, 升级后可以把后台里名为 `Categories`、`Archives`、`Tags` 的三个独立页面删掉。

## 兼容性

- Typecho 1.3 / 2.x (测试基于仓库内 `var/` 的当前版本), PHP 8.0+。
- 推荐安装 Sticky 插件: 文章置顶后列表标题后会显示插件里配置的 HTML 标记; 没装插件时什么都不显示。
- 代码块语言按 Typecho 输出的 `class="lang-xxx"` 或 `language-xxx` 识别, `c#` 会自动映射到 highlight.js 的 `csharp`。

## 测试

仓库根目录的两个测试会检查本主题:

```bash
node tests/frontend-regressions.mjs   # 模板与函数形态 (索引页认领、评论身份行)
php tests/integration.php             # 需要完整 Typecho 环境
```
