# Default 2026 (Preview)

> **未完成的预览版**: 主题的模板结构、主题设置项与前端实现都还可能调整。
> 不建议直接用于正式站点; 升级方式可能是「删掉旧目录重新覆盖」, 不保证向后兼容。
> 欢迎试用并反馈问题: <https://github.com/Raven777777/TypechoRe/issues>

TypechoRe 的默认主题: 现代、优雅、以性能为先。

- **服务端渲染优先**: 所有内容都是 PHP 渲染的普通 HTML, 关掉 JavaScript 依然可以完整阅读、评论与翻页。
- **渐进增强**: 有 JavaScript 时启用无刷新切页 (Swup)、切页骨架屏与卡片上浮入场、链接预取、目录高亮、代码块高亮 (lite-hl)、代码复制、阅读进度。
- **零外部请求**: 不加载任何第三方 CDN、字体或图标文件; 图标以内联 SVG 输出, 字体使用系统字体栈。
- **宽阅读栏**: 桌面端正文可读宽度约 930px (单栏布局约 830px), 内容图片不做圆角与描边。
- **卡片整块可点**: 列表卡片与上下篇导航都是整张卡片可点击 (标题链接用伪元素铺满卡片)。
- **只把图片当图片**: 卡片缩略图只认自定义字段、正文里的图片和图片附件, 压缩包 / PDF 等非图片附件不会出现在卡片上。
- **深色模式**: 跟随系统 / 始终浅色 / 始终深色三态, 首屏无闪烁, 访客的选择保存在浏览器本地。
- **可配置**: 强调色、字体、布局、目录、预计阅读时间、侧边栏区块、页脚与自定义 CSS 都可以在后台「外观设置」里调整。

## 目录结构

```text
usr/themes/default-2026/
├── index.php          首页 (统一的文章卡片列表, 整张卡片可点击)
├── archive.php        通用归档: 分类 / 标签 / 作者 / 搜索 / 日期
├── post.php           文章页 (目录、上下篇、评论)
├── page.php           独立页面
├── 404.php            错误页 (含搜索框与最新文章)
├── header.php         文档头、页头导航、无闪烁深色模式脚本
├── footer.php         页脚、阅读进度、返回顶部、脚本加载
├── sidebar.php        侧边栏区块
├── toc.php            文章目录 (文章页右栏, 粘性定位)
├── post-card.php      列表卡片 (循环中单篇, 整张卡片可点击)
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
npm test             # 骨架屏状态机 + 动效配置 (纯 Node, 无额外依赖)
npm run test:dom     # 正文脚本重放 + 第二列同步测试 (需要 jsdom, 见下)
npm run test:php     # 主题纯函数: 目录渲染/折叠 + 动效档位表 (需要 php)
```

> 部署用的发布包 (`tools/build_release.py`) **只带运行时需要的东西**: 主题的
> `tests/`、`src/`、`package.json` 等 Node 构建配置与 `.gitignore` 都不进包,
> `dist/` 会照常打进包里。需要重新构建前端时请用完整仓库, 而不是发布包。

`npm test` 会把 `src/js/app.js` 里骨架屏与动效配置那段真实代码抽出来, 配一个虚拟时钟重放
「点文章 → 返回主页」「连点两次」「超快跳转」等时间线, 断言跳转结束后遮罩一定回到
`hidden`, 以及「本次不画第二列」时 `is-aside-kept` 只在遮罩可见期间挂着;
同时验证 `data-anim="off"` 不挂入场类、`data-skeleton="0"` 不显示骨架、
以及 CSS 变量 (`0.83s` / `300ms` / 空值 / 垃圾值) 的解析与兜底。
随后跑 `css-contract.test.mjs`: jsdom 的 `getComputedStyle` 不套用样式表规则 (实测过),
所以动效的 CSS 约定改为直接读 `src/css/main.css` 断言 —— 第二列不得参与退场/插入态、
第二列入场必须同时要求 `is-entering` 与 `is-aside-swapped`、`is-aside-kept` 只做
「提到遮罩之上 + 不吃点击」这两件事、正文进出场仍在、
错峰与位移走变量、CSS 用到的 `--anim-*` 都由 `functions.php` 输出、未显示的骨架层
`pointer-events: none`; 同时检查**设置项接线** (`动效强度` / `切页骨架屏` / `链接预取`
三个开关都要在 `functions.php` 注册、在 `header.php` 输出成 `<html data-*>`,
并被 `app.js` 真正读取 —— 少一环设置项就会静默失效)。

`npm run test:dom` 用 jsdom 起一个真实 DOM, 跑两个文件: `inline-scripts.test.mjs`
复现 Swup 的 `cloneNode(true)` 路径不执行正文脚本的行为, 再验证重放恰好执行一次;
`aside-sync.test.mjs` 验证第二列在「侧边栏 ↔ 文章目录 ↔ 不存在」之间切换时能正确同步,
并且「侧边栏 → 侧边栏」不会重建那一列 (重建会让侧边栏闪), 同时钉住骨架屏那四档判定
(`keep` / `none` / `sidebar` / `toc`) 与链接提示 (`data-aside-target`) 的优先级。
需要 `npm i -D jsdom` (主题刻意没把它写进依赖, 免得为一个可选测试引入一堆包),
未安装时会打印 SKIP 并以 0 退出。

`npm run test:php` 只加载 `functions.php` 定义, 不碰 Typecho 运行时与数据库, 校验目录
渲染/折叠与动效档位表 (四档齐全、未知值回落、档位递增、入场窗口足够长)。需要 `php`
在 PATH 上, 仓库根目录自带的那份可以这样用:
`..\..\..\php-8.5.10\php.exe tests/theme-functions.php`。

技术栈: [Tailwind CSS v4](https://tailwindcss.com) + [@tailwindcss/typography](https://github.com/tailwindlabs/tailwindcss-typography)
+ [Swup](https://swup.js.org) (head / preload / progress / scroll 插件)
+ [lite-hl](https://github.com/docmd-io/lite-hl) (代码高亮, 见「开源组件与来源」)。
轻交互 (主题切换 / 阅读进度 / 返回顶部 / 折叠) 一律用原生 DOM 实现, 不引声明式框架。

构建产物文件名固定, PHP 侧通过 `themeAsset()` 追加 `?v=<filemtime>` 做缓存失效,
因此部署时不需要读取 manifest, 也不会有额外文件 IO。

**首屏只下载 `dist/style.css` 与 `dist/app.js`**: swup 及它的四个插件被拆进
`dist/chunks/`, 在页面就绪后按需加载 (文件名带内容 hash)。首屏渲染用不到切页逻辑,
不需要为它付费; 模块到位之前的点击走原生跳转, 功能不丢。

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
| 动效强度 | 关闭 / 轻盈 / 标准 / 明显, 控制切页上浮的距离、时长与卡片错峰 |
| 切页骨架屏 | 等待新内容时是否显示占位骨架 (与动效强度相互独立) |
| 链接预取 | 鼠标悬停在链接上时是否在后台提前取回目标页面; 关掉可省流量与服务器请求, 代价是首次点击变慢 |
| 文章目录 | 自动为二至三级标题生成目录 (四级标题仍生成锚点), 可见条目少于两个时不显示; 单栏布局下改为正文上方的折叠块 |
| 预计阅读时间 | 中英文混排估算 |
| 列表摘要字数 | 列表页卡片的摘要长度 |
| 无图文章的占位缩略图 | 没有自定义字段 `thumbnail`、正文也没有图片时, 用文章 ID 在浏览器端生成几何占位图 (默认开启) |
| 侧边栏内容 | 最新文章 / 最近回复 / 分类 / 归档 / 标签 / 其它 |
| 页脚附加内容 | 支持 HTML, 适合备案号 |
| 自定义 CSS | 在主题样式之后输出 |

设置保存在 `theme:default-2026` 选项里, 首次启用主题时会写入默认值。

## 自定义

**新增一个设置项**: 在 `functions.php` 的 `themeConfig()` 里添加表单项,
模板中用 `themeOption('键名', '默认值')` / `themeOptionBool()` / `themeOptionInt()` 读取。

**改正文链接样式**: 正文链接 (`.prose-theme :where(a)`) 平时是一条 38% 主色的浅下划线,
悬停时下划线变实并浮出一层极浅主色底 (`.prose-theme :where(a:hover)`)。这两条规则写在
`src/css/main.css` 的层外 (unlayered), 所以盖得住 typography 插件的 `prose-a:*` 工具类 ——
`post.php` / `page.php` 上不再需要 `prose-a:no-underline hover:prose-a:underline`。
整张包在链接里的图片 (`a:has(img)`) 会被排除, 不会出现下划线和高亮块。

**改配色**: 绝大多数颜色来自 Tailwind 调色板 (`zinc`) 与 `--color-accent`,
在 `src/css/main.css` 的 `@theme` 中调整后重新构建即可。代码令牌的颜色是
`--lite-hl-*` 一组变量 (见上文的「代码高亮的配色」)。

**改代码块圆角**: `src/css/main.css` 的 `@theme` 里的 `--radius-code` (默认 `10px`),
只作用于代码块, 不影响引用块、图片与卡片。

**代码高亮**: 主题内置 [lite-hl](https://github.com/docmd-io/lite-hl) 做客户端高亮 ——
它是启发式的通用分词器, 不猜语言、也不需要按语言注册语法, 整包不到 10KB (gzip 后约 4KB,
已随 `dist/app.js` 打包, 不额外请求任何 CDN)。**服务端不需要装任何插件**: 只要 Typecho 照常
输出 `<pre><code>`, 浏览器里就会自动着色; 源码里的 `<` `&` 由 lite-hl 负责转义, 分词只包
`<span>`, 所以 `textContent` 逐字符不变, 复制按钮拿到的仍是原始代码, 关掉 JavaScript 也只是
回到没有颜色的纯文本。实现与约定:

- 高亮范围是正文 (`.prose-theme`) 与评论 (`.comment-content`) 里的 `pre > code`, 行内 `code` 不动。
- 每次切页 (`enhancePage()`) 与评论就地刷新后都会重新执行, 用 `data-highlighted` 标记保证幂等,
  不会把已经分好词的代码再分一次。
- 想让某个代码块保持原样, 在 `<pre>` 或 `<code>` 上写 `data-no-highlight` 即可。
- 分词失败时静默退回纯文本 (不影响阅读与复制), 不做任何破坏性替换。
- 输出的类名沿用 highlight.js 的 `hljs-*` 约定 (`mimicHljs`), 因此也能直接套用现成的
  highlight.js 主题 CSS。

**代码高亮的配色**: 令牌颜色定义在 `src/css/main.css` 的 `--lite-hl-*` 变量里 (与 lite-hl
自带主题同名, 改单个令牌只需覆盖一个变量)。这里**没有**直接引 lite-hl 的 `theme.css`:
那套主题用 `prefers-color-scheme` 切换明暗两套配色, 而本主题的深色模式是 `html.dark` 类驱动的,
两者会错位; 加上代码块在浅色与深色模式下都是深色底, 它那份「白底」配色放上去根本看不清。
所以主题只取了它的暗色一套值, 并按变量名重新写了一遍。

**代码块圆角与滚动**: 代码块的圆角由 `@theme` 里的 `--radius-code` (默认 `10px`) 统一控制,
正文与评论共用这一个值。开启增强后, 横向滚动交给 `<code class="code-scroll">`,
复制按钮定位在不滚动的 `<pre class="code-block">` 上, 因此滚动代码时按钮不会跟着跑;
按钮下方的代码文字用一层与代码块同色的渐隐 (`pre.code-block::before`) 盖住。
`pre.code-block` 上的 `overflow: hidden` 只为把内容按圆角裁掉 —— 横向滚动条两端与右上角
那层渐隐都不会顶到圆角外面。没有 JavaScript 时, `<pre>` 自身带 `overflow-x: auto`,
长行在圆角盒子内部滚动, 而不是溢出到盒子外面 (那里的文字是浅色的, 溢到白底上等于看不见)。

**切页骨架屏与入场动画**: `footer.php` 里的 `#page-skeleton` 由 `app.js` 控制显隐与形态 ——
跳转等待超过 130ms 才上浮淡入, 容器替换后立刻淡出; 同一时间 `app.js` 给 `<html>` 挂上
`is-entering`, 让 `#main` 里的卡片错峰上浮淡入, 与骨架淡出交叠成一次连续的「浮起」。
形态只有两种: `data-kind="list"` (列表卡片) 与 `data-kind="single"` (文章 / 独立页面),
默认按「点击的是不是文章卡片」推断, 也可以在链接或卡片上写 `data-skeleton="list|single"` 指定。
骨架标记与样式分别是 `#page-skeleton` / `.page-skeleton`、`.skeleton-block`,
不需要可以直接删掉这段标记与对应的 CSS (删除后只是回到「内容直接出现」)。

**动效参数怎么流动**: 所有数值只有一份来源 —— `functions.php` 的 `themeAnimPreset()`
(四档预览: 关闭 / 轻盈 / 标准 / 明显), 由 `header.php` 写成 `<html>` 上的 CSS 变量
(`--anim-enter-duration`、`--anim-enter-distance`、`--anim-exit-distance`、`--anim-stagger`、
`--anim-fade-duration`、`--anim-skeleton-fade`、`--anim-window`), 同时把档位写成 `data-anim`、
把骨架屏开关写成 `data-skeleton`。`main.css` 直接 `var()` 取用, `app.js` 读同一批变量换算
自己的定时器 (所以改了 CSS 时长, JS 里那个「入场窗口」也会跟着变, 不会出现动画没播完就
被收掉)。「关闭」档 = 位移与入场时长归零 + JS 不挂 `is-entering`; 骨架屏淡出仍保留 0.16s,
避免硬切。`prefers-reduced-motion` 依旧全局断电 (见 `main.css` 的 base 层)。

**第二列也走同一条时间轴**: 侧边栏 / 文章目录的**入场**用的就是上面那批变量
(`aside-rise`, 位移 `--anim-enter-distance`、时长 `--anim-enter-duration`), 所以切换
「侧边栏 ↔ 文章目录」时它和正文是一套动作, 不会一边动一边硬切。但**只有它真的换了内容
才动**: `app.js` 换掉那一列时给 `<html>` 加 `is-aside-swapped`, CSS 里第二列的入场规则
一律要求 `is-entering` 与它同时存在 —— 列表页之间来回切 (侧边栏内容没变) 时那一列
一动不动 (也不会重建, 见下)。入场比卡片晚一档 (`--anim-stagger`), 像是跟在正文后面。

> 第二列**刻意不做退场**: 退场阶段还没拿到目标文档, 无从判断这一列会不会被换掉。
> 一旦给它加淡出, 「侧边栏没变」的跳转 (例如两篇都没有目录的文章之间切换) 就会淡出后
> 被 `is-rendering` 瞬间弹回 —— 骨架屏开着时那层不透明遮罩正好盖住这个闪烁, 关掉骨架屏
> 就露出来了。这条约定由 `tests/css-contract.test.mjs` 钉住。

**「侧边栏没变」时骨架遮罩不许盖住它**: 遮罩 (`.page-skeleton`) 是一层不透明的固定层,
铺满导航之下的整个视口 —— 判定「本次不画第二列骨架」时它仍然会把那一列盖成一块空白,
等遮罩淡出再露回来, 看起来就是侧边栏闪了一下。所以 `app.js` 在这种情况下给 `<html>` 挂
`is-aside-kept`, `main.css` 据此把那一列提到遮罩之上 (`z-index: 71`, 遮罩是 70, 吸顶导航
一起抬到 72 免得被卡片压住); 它只是「留在画面上」, 点击照旧被遮罩拦住
(`pointer-events: none`), 否则用户在等待期间还能点到侧边栏里的链接、排上第二次跳转。
标记从遮罩显示一直挂到它真正 `hidden`, 覆盖整个淡出过程 —— 中途摘掉就等于又被盖回去。

> 这两条规则**必须写在所有 `@layer` 之外**: 吸顶导航身上有 Tailwind 的 `.z-50`
> (utilities 层), 而**层级的优先级高于选择器优先级** —— 写在 `components` 层里的
> `z-index: 72` 永远赢不了它, 于是第二列 (71) 会压到导航 (50) 上, 页面滚动时侧边栏
> 卡片会盖住导航。(同样的问题也适用于文档里「正文排版覆盖」那一节。)
> `tests/css-contract.test.mjs` 会断言这两条规则的 `@layer` 归属。

**等待期间第二列怎么处理**: `visit:start` 时还**拿不到目标文档** (Swup 要等 fetch +
`DOMParser`), 所以 `app.js` 的 `asideKindForSession()` 只能在「链接给的提示」与「当前页
DOM 里那一列」之间做判断, 结果有四档:

| 判定 | 含义 | 用在哪 |
| --- | --- | --- |
| `keep` | 不画骨架, 把真实那一列托到遮罩之上 (见上) | 这一列不会变 |
| `none` | 不画, 也不托 | 当前页或目标页根本没有这一列 |
| `sidebar` / `toc` | 画对应骨架 (要先遮住旧的, 再换成新的) | 这一列真的会换 |

- **精确来源**: 卡片链接上的 `data-aside-target` 由 `post-card.php` 写成
  `themeContentAsideKind()` 的结果, 也就是「点进去那一页的第二列是什么」; 分类链接
  写的是 `themeListAsideKind()` (列表页) 并顺带用 `data-skeleton="list"` 修掉骨架面板形态。
- **侧边栏是全站数据**: 两边都是侧边栏时一个字都不会变 → `keep`; **目录每篇都不同**
  (同页锚点跳转不会走到这里) → 每次都重画。
- **拿不准就保守**: 目标是文章 / 独立页面、又没有提示时, 无法提前知道那篇有没有目录 ——
  若当前页正是侧边栏就按 `keep` 处理, **不画**。早期版本是「一律按目录画」, 于是
  「首页 → 没有目录的文章」会用目录骨架盖住侧边栏、遮罩淡出再露回来 —— 侧边栏没变却
  闪了一下 (线上报的就是这个)。判断目标是列表页时不必保守: 列表页的第二列必定是侧边栏。
- 当前页的形态读的是 **DOM 里真实那一列** (`currentAsideKind()`), 不是 `#page-skeleton`
  上的 `data-aside-kind` —— 后者是服务端首屏渲染时写下的, 切页换掉第二列之后由
  `recordAsideKind()` 补写, 判定不依赖它 (早期版本读它, 于是首屏是列表页时, 从「有目录的
  文章」切回列表页也会以为当前是侧边栏)。

**评论里的 Markdown**: 由内核负责, 主题不插手。`Widget\Base\Comments::___content()`
在 `commentsMarkdown` 打开时会放行 Markdown 生成的标签 (strong / em / code / pre /
blockquote / ul / ol / li / h2–h6 / hr / a), 并对 `href` / `src` 的值做协议检查
(只留 http / https / mailto 与相对地址)。主题此前在 `contentEx` 上注册过一个兜底过滤器,
但那个钩子跑在 `stripTags()` **之前**, 补出来的标签会被紧接着剥掉 —— 它从来没有生效过,
只会让「Markdown 失效」看起来已经被修好了, 现已删除。

**文章目录的层级与折叠**: `themeArticle()` 只为 h2 / h3 生成目录条目, **h4 仍会注入锚点 id**
(已有深链不受影响) 但不进目录, 所以目录只有两层、缩进只有一档。可见条目少于两个时
整个目录不显示。桌面版 (`toc.php` 传 `collapsible: true`) 在条目超过 12 条时把尾部折进
原生 `<details>`「展开全部 (N 条)」, 无 JS 也能展开; 移动端本身在 `<details>` 里, 不再折叠。

**为什么不走原生 View Transitions**: Swup 的 `native` 模式只能把 `#main` 当作一整块快照交叉淡入,
既无法逐张卡片错峰, 又会在 `is-rendering` 期间把新内容藏在 `opacity: 0` 上,
与骨架屏淡出叠在一起会露出空白。因此 `app.js` 里固定 `native: false`,
过渡时间轴由 `html.is-animating` (旧内容淡出上移) 与 `html.is-entering` (新内容上浮) 两个类控制。

**正文里的内联脚本**: Swup 用 `incomingEl.cloneNode(true)` 插入新容器, 而从 `DOMParser` 文档
克隆出来的 `<script>` 带着 "already started" 标记 (HTML 规范: script 的克隆步骤会置位它),
浏览器永远不会执行 —— 也就是说写在文章 / 独立页面正文里的 `<script>` 只有按 F5 才生效。
主题因此在替换前把新文档里 (`#main` / `#site-nav` 范围内) 的脚本**缴械** (type 改成
`text/swup-deferred`), 替换后再按原属性、原顺序重新造一个 `<script>` 插回去, 每个脚本恰好
执行一次。所以下面这种「正文里塞自定义标签 + 脚本」的写法切页后也会正常渲染:

```html
<p-c>会用脚本逐字上色的一句话</p-c>
<script>/* 操作上面这些 <p-c> 的脚本 */</script>
```

- 需要保持「只在 F5 执行」的脚本, 在标签上写 `data-swup-skip`。
- 只覆盖 Swup 容器 (`#main`、`#site-nav`) 与第二列 (`.page-aside`) 内的脚本;
  `<head>` 里的脚本由 `@swup/head-plugin` 负责, 主题自己的增强逻辑统一走
  `enhancePage()`, 不去重放它。

**第二列 (侧边栏 / 文章目录)**: 文章页与独立页面在「有目录」时是**用目录整列替换掉侧边栏**
(`post.php` / `page.php` 按 `themeContentAsideKind()` 的结果载入 `toc.php` 或 `sidebar.php`)。
**单栏布局 (`layout = full`) 没有第二列**: 栅格都没画, 硬塞一份 `<aside>` 只会掉在评论区
下方、成为页面底部的一坨 —— 所以 `themeContentAsideKind()` 在单栏下一律返回 `none`, 目录
改由模板在正文上方输出折叠块 (与窄屏那一版共用同一段标记, 见 `post.php` 的 `$postAside`;
有第二列时那块只在 `lg` 以下显示)。这一列是 `#main`
的兄弟节点, 不在 Swup 容器里 —— 如果不管它, 从列表页进文章就看不到目录 (必须 F5),
从文章回列表页又会留着上一页的目录。因此两个模板的 `<aside>` 都带同一个稳定类
`page-aside`, `app.js` 的 `syncPageAside()` 在容器替换后整列同步 (目标页有就替换、
没有就移除、当前没有就插到 `#main` 之后)。

> 这里刻意**不**把 `.page-aside` 注册成 Swup 容器: 容器在目标文档里缺失会让 Swup
> 判定 `Container mismatch` 并放弃接管导航, 而这一列在「单栏布局 / 侧边栏区块全关 /
> 404」等情况下本来就可能不存在 (`sidebar.php` 会提前 return)。

目录那版 `<aside>` 还带着 `lg:self-stretch`, 这是**目录能吸顶的前提**:
`#page-wrap` 是 `lg:items-start` 的栅格, 不把这一列撑到行高, 里面的
`lg:sticky` 就没有滚动行程, 目录会跟着正文滚走并被导航栏盖住。改样式时不要删掉它。

**正文 HTML 处理**: 图片懒加载在 `themeArticle()` 中显式处理, 而不是通过
`Plugin::factory('Widget\Base\Contents')->contentEx` 钩子 —— 因为主题的 `functions.php`
由 `Widget\Archive::execute()` 在 `singleHandle()` 之后才载入, 而 `singleHandle()`
会通过 `archiveDescription = plainExcerpt` 提前渲染并缓存正文, 主题注册的钩子会错过正文。
插件不受此限制 (插件在引导阶段注册钩子), 其过滤结果依然会体现在 `$archive->content` 中。

## 窄屏 (手机)

- **第二列 (侧边栏 / 文章目录) 在 `lg` 以下整体隐藏** (`.page-aside` + 骨架屏的
  `[data-skeleton-aside]`, 断点与栅格生效的 `lg` = 64rem 同一挡)。窄屏时那一列是「正文和
  分页看完之后又冒出来的一整列」, 只是噪音; 窄屏本来就有替代品 —— 文章目录在 `post.php`
  里另有一份 `<details>`。隐藏写在大屏栅格所在的层里, 而不是给模板加 `hidden lg:block`:
  `lg:block` 属于 utilities 层, 会反压 `[data-skeleton-aside][hidden]` 的 `display: none`。
- **页脚窄屏两行居中**: `© 年份 + 站点标题` 一行, 链接一行 (`sm` 以下隐藏「由 … 强力驱动」,
  否则那一行在 375px 上要挤五个链接), `sm` 以上恢复成「左版权 / 右链接」的单行横条 ——
  这时它与页眉等高 (都是 `h-14` + 1px 边框)。

## 性能与可访问性

- 单个 CSS (约 14 KB gzip) + 单个 JS (约 40 KB gzip), 均为长期缓存 (filemtime 版本号)。
- 省流模式 (`navigator.connection.saveData` 或 2G 网络) 下自动关闭无刷新切页与预取。
- 尊重 `prefers-reduced-motion` / `prefers-reduced-data`。
- 首图不加 `loading` (通常是 LCP 元素), 其余正文图片 `loading="lazy" decoding="async"`。
- 语义化标签、跳转到主内容链接、可见焦点环、`aria-*` 标注、原生 `<details>` 导航 (无 JS 可用)。
- 打印样式会隐藏导航、侧边栏、目录与评论区。

## 卡片缩略图的取值规则

`themeThumbnail()` 依次尝试: 自定义字段 `thumbnail` → 正文首图 → 附件, 并且**只接受图片**:

- 正文按 Markdown / HTML 原文解析, 逐个跳过 `![附件](file.zip)`、`<img src="a.pdf">`
  这类非图片后缀 (没有后缀的地址 (图床 / 动态缩略图接口) 视为图片)。
- 附件按 Typecho 的附件类型判断 (`attachment->isImage`), 压缩包、PDF、音视频不会进卡片。
- 卡片缩略图**不低于 4:3**: 桌面端定宽 `14rem`、最小高度 `10.5rem` (正好是 `14rem` 的
  4:3), 再往上跟着卡片高度走 —— 卡片比图高时图片把整列填满, 不会在缩略图下面空出一条
  白边。图片用 `absolute inset-0` + `object-cover` 铺满: flex 容器里百分比高度靠父元素定高,
  而父元素高度由内容撑开时, 图片的固有高度会反过来把卡片顶得很高 (竖图尤其明显) ——
  绝对定位把图片摘出文档流就没这个问题, 小图会被放大填满、大图被裁切, 都不会变形。
  外层 `bg-zinc-100` 是兜底底色, 图加载失败时不会露出一块死白。窄屏是一张通栏横幅 (`h-44`),
  不跟比例走 (4:3 通栏会高得离谱)。
- 所以「上传了别的文件」不会再把卡片图片搞坏, 自定义字段里的地址仍然无条件信任 (由站长自己填写)。

### 都没有时: 浏览器端生成的占位图

三步都取不到图 (`themeThumbnail()` 返回 `null`) 且「无图文章的占位缩略图」开着 (默认开) 时,
卡片改为输出一枚只有 `data-thumb-seed="<文章 id>"` 与 `hidden` 的空 `<img>`, 由 `dist/app.js`
在访客浏览器里生成一张 400×300 的几何 SVG (data URI) 填进去:

- **不落盘、不入库**: 服务器上没有新增任何文件, 数据库里只有那个布尔开关, 文章的图片信息一个字节也没写。
  代价是这张图**不会出现在 JSON-LD 的 `image` 里** —— data URI 不是可索引的绝对地址。
  需要社交分享大图的站点仍应填写 `thumbnail` 字段。
- **种子只有文章 id**, 由 `src/js/thumb-placeholder.js` 里的 FNV-1a hash 决定色相、5 个径向光斑与
  1–3 个几何图案, 所以同一篇文章在首页、分类页、搜索结果里永远是同一张图, 改标题或时间不会换图。
- **走 `<img>` 而不是内联 `<svg>`**: 复用卡片现有的 `absolute inset-0` / `object-cover` /
  `group-hover:scale` 与 `loading="lazy"`, 视口外的卡片不触发滤镜光栅化; SVG 在 `<img>` 里是独立
  文档, 主题的 `svg` 选择器与 `currentColor` 不会渗进去。
- 首屏与 swup 无刷新切页共用同一条路径: `enhancePage()` 每次都调用 `hydrateThumbs()`,
  新换进来的卡片照样会被补图。
- 完全禁用 JS 时整列由 `<noscript>` 里的 `.card-media[data-thumb-placeholder]{display:none}` 藏掉,
  回到上面的无图形态。

## 评论区

- **行为完全跟随后台「设置 → 讨论」**: 是否分页 / 每页多少条 / 默认显示第一页还是最后一页 /
  新的还是旧的在前 / 是否开启嵌套回复 / 最多几层 —— 这些都由内核
  `Widget\Comments\Archive::execute()` 读选项实现, 主题**不覆盖任何一项**。
  一个坑: 主题曾经在 `themeInit()` 里把 `commentsMaxNestingLevels` 收紧成 4 (担心深层
  嵌套挤窄正文), 后果是后台设的「5 层」不生效 —— 越过上限的那层会被内核挂到上上层,
  到第 4 层连「回复」链接也不再输出; 现在评论区是扁平排版, 层级只决定「回复挂在哪」, 没有
  必要再收紧。
- **扁平排版**: 一条评论 = 元信息行 (`[头像] 井水玉藻 10-15 23:39 回复`) + 正文, 全部
  左对齐 —— 不缩进、不设底色、不带左侧竖线, 嵌套回复也不单独分级。同层之间只压一条极细分
  隔线 (`.comment-list > .comment-body + .comment-body` 对任意深度都生效, 因为每一层都被包在
  自己的 `ol.comment-list` 里; 另外一条 `.comment-children > .comment-list > .comment-body:first-child`
  免得嵌套回复粘在父评论正文后面)。视觉间隔 `33px → 17px`。
  内核输出的节点顺序是 `author / meta / content / reply / children` —— 「回复」在正文之后,
  直接排下去会单独占一行 (看上去就像孤零零飘在最右边), 所以主题**不改内核标记**, 只用
  `.comment-body` 上的 `flex-wrap` + `order` 把视觉顺序扳成
  `author(1) / meta(2) / reply(3) / content(4) / children(5)`, 回复框搬进评论里是 `order: 6`
  (否则那个 `order: 0` 的回复框会被顶到最上面)。
  不用 grid 是因为正文要跨列, 而跨列项的 `max-content` 会把网格列撑开。
- **关了 Gravatar 就不再请求头像**: 评论列表是内核输出的, `Comments::gravatar()` 自己会看
  `commentsAvatar` 选项; 但登录身份的 `Users::gravatar()` 不看, 所以 `comments.php` 里那次
  调用额外判了一次。头像关闭时元信息行也不留空位 —— 头像间距挂在头像自己的 `margin-right` 上,
  而不是 `.comment-author` 的 `gap` (内核在关闭时仍会输出一个空的 `<span itemprop="image">`)。
- **「回复」平时隐藏**: 只在 `@media (hover: hover)` 里给 `opacity: 0`, 悬停这条评论或键盘聚焦
  到它时淡入 —— 触屏没有 hover, 照常可见, 否则点不到。用 `opacity` 而不是 `display`,
  悬停时不会因为按钮突然出现而挤动排版。
- **回复框默认收起**: `#respond-xxx` 本身就是 `<details>`, 收起时它就是一颗虚线胶囊
  (只包住文字, 不铺满整行); 点它, 或点某条评论的「回复」
  (`app.js` 与内核的内联脚本会把评论框搬进那条评论并展开), 才展开成卡片。
  提交成功后 `resetRespond()` 收起评论框并清掉隐藏的 `parent` 字段。
- **提交回执在回复框外面**: `#notice-box` 是 `#comments` 的直接子节点 (不在 `<details>` 里),
  否则收起评论框会把「评论已提交, 通过审核后显示」一起藏起来; `refreshComments()` 重铺评论区时
  会把它放回原位。
- 回复框搬进评论后由 `.comment-body > .respond` 占满一整行, `order: 6` 决定它排在子评论后面;
  该规则写在 `.respond[open]` 之后, 顺便盖掉 `.respond` 的 `mt-6`。
- 占位节点优先复用内核内联脚本建的 `#respond-xxx-holder` (它只有 id, 没有主题的 `data-*` 标记),
  否则会在评论里再建一个同 id 的节点, 「取消回复」会把评论框搬回那条评论里面。
- 头像是 36px、日期用 `m-d H:i` (同一年的评论不再重复年份)。

## 开源组件与来源

主题把运行时要用的第三方库都在构建期打进 `dist/`, 页面**不请求任何 CDN / 外部文件**:

| 组件 | 版本 | 许可 | 来源 | 用在哪 |
| --- | --- | --- | --- | --- |
| lite-hl | `^0.2.1` | MIT | <https://github.com/docmd-io/lite-hl> (docmd 生态, 作者 Ghazi / mgks.dev) | 代码块语法高亮, `src/js/app.js` 的 `highlightCodeBlocks()` |
| Swup (+ head / preload / progress / scroll 插件) | `^4.10.0` | MIT | <https://github.com/swup/swup> | 无刷新切页 (按需加载, 见 `loadPageTransitions()`) |
| Tailwind CSS + @tailwindcss/typography | `^4.3.3` / `^0.5.20` | MIT | <https://tailwindcss.com> | 只在构建期生成 `dist/style.css` |

### lite-hl

- 安装与引入: `npm install lite-hl` (见 `package.json` 的 `dependencies`),
  代码里 `import { highlight } from 'lite-hl'`, 由 Vite 打包进 `dist/app.js`。
- 只调用它的公共 API (`highlight(code, { language, mimicHljs: true })`),
  **没有修改、也没有单独重新分发**它的源码。
- 主题只自行编写了令牌配色 (变量名 `--lite-hl-*` 与上游保持一致, 见「代码高亮的配色」):
  这部分是主题原创, 不是从上游复制的样式表。
- 上游许可是 MIT, 主题整体是 GPL-2.0-or-later: 打包后的 `dist/app.js` 里同时包含两种许可的
  代码, 二次分发时请一并保留上游的许可声明, 并在你 fork 的 README 里保留本节的来源说明。

## 兼容性

- 主题不单独提高 PHP 版本要求；运行 TypechoRe 整站仍需要 PHP 8.5+。主题本身无额外 PHP 扩展依赖。
- 移动端导航与评论回复框都用原生 `<details>`, 评论表单是普通 POST, 因此无 JavaScript 时功能完整
  (无 JS 时点「回复」不会把评论框搬进那条评论, 但可以手动展开「添加新回复」照常提交)。
- Swup 会跳过后台 (`/admin/`)、动作路由 (`/action/`)、`install.php`、跨域链接、
  带 `onclick` 的链接 (评论回复 / 取消回复) 与带 `download` / `target` 的链接。
- 正文中的锚点标题使用可读 slug (保留中文), 同名标题自动追加序号, 与页面已有锚点
  (`comments`、`respond` 等) 冲突时会加 `toc-` 前缀。
