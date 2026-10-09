# Default 2026 (Preview)

> **未完成的预览版**: 主题的模板结构、主题设置项与前端实现都还可能调整。
> 不建议直接用于正式站点; 升级方式可能是「删掉旧目录重新覆盖」, 不保证向后兼容。
> 欢迎试用并反馈问题: <https://github.com/Raven777777/TypechoRe/issues>

TypechoRe 的默认主题: 现代、优雅、以性能为先。

- **服务端渲染优先**: 所有内容都是 PHP 渲染的普通 HTML, 关掉 JavaScript 依然可以完整阅读、评论与翻页。
- **渐进增强**: 有 JavaScript 时启用无刷新切页 (Swup, 优先走原生 View Transitions API)、链接预取、目录高亮、代码复制、阅读进度。
- **零外部请求**: 不加载任何第三方 CDN、字体或图标文件; 图标以内联 SVG 输出, 字体使用系统字体栈。
- **宽阅读栏**: 桌面端正文可读宽度约 930px (单栏布局约 830px), 内容图片不做圆角与描边。
- **卡片整块可点**: 列表卡片与上下篇导航都是整张卡片可点击 (标题链接用伪元素铺满卡片)。
- **深色模式**: 跟随系统 / 始终浅色 / 始终深色三态, 首屏无闪烁, 访客的选择保存在浏览器本地。
- **可配置**: 强调色、字体、布局、目录、相关文章、侧边栏区块、页脚与自定义 CSS 都可以在后台「外观设置」里调整。

## 目录结构

```text
usr/themes/default-2026/
├── index.php          首页 (统一的文章卡片列表, 整张卡片可点击)
├── archive.php        通用归档: 分类 / 标签 / 作者 / 搜索 / 日期
├── post.php           文章页 (目录、上下篇、相关文章、评论)
├── page.php           独立页面
├── 404.php            错误页 (含搜索框与最新文章)
├── header.php         文档头、页头导航、无闪烁深色模式脚本
├── footer.php         页脚、阅读进度、返回顶部、脚本加载
├── sidebar.php        侧边栏区块
├── toc.php            文章目录 (文章页右栏, 粘性定位)
├── post-card.php      列表卡片 (循环中单篇, 整张卡片可点击)
├── related.php        相关文章
├── comments.php       评论列表与表单
├── functions.php      主题设置、钩子与模板辅助函数
├── src/               前端源码 (Tailwind 入口 / 交互脚本)
├── dist/              构建产物 (style.css + app.js, 已随仓库提交)
├── package.json       Vite + Tailwind 构建配置
└── vite.config.js
```

模板查找顺序由 `Widget\Archive::render()` 决定: 优先 `归档类型/缩略名.php`,
再 `归档类型.php`, 单篇内容回退到 `single.php` / `archive.php`, 最后回退到 `index.php`。

## 开发与构建

主题**开箱即用** (仓库里已包含构建好的 `dist/`), 只有修改前端源码时才需要 Node:

```bash
cd usr/themes/default-2026
npm install          # 首次
npm run build        # 生成 dist/style.css 与 dist/app.js
npm run dev          # 监听源码变化自动构建
```

技术栈: [Tailwind CSS v4](https://tailwindcss.com) + [@tailwindcss/typography](https://github.com/tailwindlabs/tailwindcss-typography)
+ [Alpine.js](https://alpinejs.dev) + [Swup](https://swup.js.org) (head / preload / progress / scroll 插件)。

构建产物文件名固定, PHP 侧通过 `themeAsset()` 追加 `?v=<filemtime>` 做缓存失效,
因此部署时不需要读取 manifest, 也不会有额外文件 IO。

> Tailwind 的内容探测以本目录 (`package.json`) 为项目根自动扫描, `node_modules/` 与
> `dist/` 都在 `.gitignore` 中, 不会被当成内容源。Tailwind 4.3.x 的 `@source` 指令
> 在 Vite 插件与 CLI 下的路径解析不一致, 主题里刻意没有使用。

## 主题设置

| 设置 | 说明 |
| --- | --- |
| 站点 LOGO / favicon | 图片地址, 留空则显示站点标题文字 |
| 强调色 | 十六进制颜色, 通过 `--theme-accent` 注入, 同时用于链接、按钮、目录高亮 |
| 默认外观 | 跟随系统 / 始终浅色 / 始终深色 |
| 正文字体 | 无衬线 (系统字体) / 衬线 |
| 页面布局 | 内容 + 侧边栏 / 单栏 |
| 文章目录 | 自动为二至四级标题生成锚点与目录, 标题少于两个时不显示 |
| 预计阅读时间 | 中英文混排估算 |
| 相关文章 | 按标签关联, 最多 3 篇 |
| 面包屑导航 | 同时输出结构化数据 |
| 列表摘要字数 | 列表页卡片的摘要长度 |
| 侧边栏内容 | 最新文章 / 最近回复 / 分类 / 归档 / 标签 / 其它 |
| 页脚附加内容 | 支持 HTML, 适合备案号 |
| 自定义 CSS | 在主题样式之后输出 |

设置保存在 `theme:default-2026` 选项里, 首次启用主题时会写入默认值。

## 自定义

**新增一个设置项**: 在 `functions.php` 的 `themeConfig()` 里添加表单项,
模板中用 `themeOption('键名', '默认值')` / `themeOptionBool()` / `themeOptionInt()` 读取。

**改配色**: 绝大多数颜色来自 Tailwind 调色板 (`zinc`) 与 `--color-accent`,
在 `src/css/main.css` 的 `@theme` 中调整后重新构建即可。

**代码高亮**: 主题刻意不内置高亮库 (避免为博客正文增加几十 KB 依赖),
代码块已经提供了圆角、复制按钮与语言徽标。若需要高亮, 推荐:
使用 `typecho-plugin-shiki` 之类的插件在服务端输出高亮 HTML, 或自行引入 Prism/Shiki
并在 `app.js` 的 `enhancePage()` 中调用 (每次页面切换后都会重新执行)。

**正文 HTML 处理**: 图片懒加载在 `themeArticle()` 中显式处理, 而不是通过
`Plugin::factory('Widget\Base\Contents')->contentEx` 钩子 —— 因为主题的 `functions.php`
由 `Widget\Archive::execute()` 在 `singleHandle()` 之后才载入, 而 `singleHandle()`
会通过 `archiveDescription = plainExcerpt` 提前渲染并缓存正文, 主题注册的钩子会错过正文。
插件不受此限制 (插件在引导阶段注册钩子), 其过滤结果依然会体现在 `$archive->content` 中。

## 性能与可访问性

- 单个 CSS (约 12 KB gzip) + 单个 JS (约 35 KB gzip), 均为长期缓存 (filemtime 版本号)。
- 省流模式 (`navigator.connection.saveData` 或 2G 网络) 下自动关闭无刷新切页与预取。
- 尊重 `prefers-reduced-motion` / `prefers-reduced-data`。
- 首图不加 `loading` (通常是 LCP 元素), 其余正文图片 `loading="lazy" decoding="async"`。
- 语义化标签、跳转到主内容链接、可见焦点环、`aria-*` 标注、原生 `<details>` 导航 (无 JS 可用)。
- 打印样式会隐藏导航、侧边栏、目录与评论区。

## 兼容性

- 需要 PHP 8.0+ (与 TypechoRe 的运行要求一致), 无额外扩展依赖。
- 移动端导航使用原生 `<details>`, 评论表单是普通 POST, 因此无 JavaScript 时功能完整。
- Swup 会跳过后台 (`/admin/`)、动作路由 (`/action/`)、`install.php`、跨域链接、
  带 `onclick` 的链接 (评论回复 / 取消回复) 与带 `download` / `target` 的链接。
- 正文中的锚点标题使用可读 slug (保留中文), 同名标题自动追加序号, 与页面已有锚点
  (`comments`、`respond` 等) 冲突时会加 `toc-` 前缀。
