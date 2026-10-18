# 更新日志

本文件记录 TypechoRe 各版本的代码级改动。主题改动见
[`usr/themes/default/CHANGELOG.md`](usr/themes/default/CHANGELOG.md)。

## 未发布

### 后台: 上传文件类型只由站点设置决定

- 移除 `Widget\Upload::checkFileType()` 里写死的危险后缀黑名单 (`php`/`phtml`/`phar`/
  `html`/`js`/`svg`/`xml` 等): 后台「允许上传的文件类型」是唯一判定依据, 站长写进去的
  后缀就能传。原黑名单优先于后台设置, 导致把 `svg` 加进允许列表也传不上去, 而设置页
  对此没有任何说明
- 设置页在该项下面加了一行提示: 「注意: 允许上传 svg/svgz、html/htm、js、xml、php
  等可能存在安全风险。」
- `Widget\Options\General` 不再用 `removeShell()` 过滤自定义后缀 (以前 `php`/`sh`/`asp`
  写了也存不进去, 与「设置说了算」自相矛盾)
- 预设类型清单更新 (只动本仓库的默认值, 不影响已有数据): 图片加 `avif`/`tif`/`heic`/
  `heif`/`jxl`; 多媒体补 `m4a`/`m4v`/`webm`/`mkv`/`opus`/`flac`/`wav`/`aac`, 去掉
  `wmv`/`wma`/`rmvb`/`rm`/`flv`; 常用档案补 `7z`/`md`/`csv`/`odt`/`ods`/`odp`/`epub`/
  `tar`/`gz`/`xz`。设置页标签与实际展开清单之前不一致 (`avif` 只在标签里有), 这次一并对齐
- 附件渲染的 `isImage` 补上 `tif` (与 `tiff` 同义)

### 主题: Default 2026 回复面板浮层 / 折叠动画 / 窄屏导航与分类带

- 回复面板改成**浮层** (`#respond-layer`, `position: fixed` + 半透明遮罩): 窄屏是从底部
  升起的抽屉, 宽屏居中。以前回复某条评论时面板被搬进那条评论里, 会把后面所有评论整体
  推下去, 长列表里还容易被挤到屏幕外; 现在覆盖在页面上, 正文一动不动。关掉后浮层整块
  撤掉, 切页时也清掉残留, 正在回复的那条评论加 2px 描边标记, 标题行显示「回复 @某人」。
- 修复: 回复被当成新的顶层评论。回复链接的 `onclick` 里 `theId` 是 JSON 编码的 (双引号),
  而 `app.js` 用单引号正则去抠 → `parent` 被写成空串。现在模板直接输出
  `data-reply-to` / `data-reply-target`, JS 优先读它, `onclick` 正则两种引号都认 (兜底)。
- 不管面板怎么合上的 (点别处 / Esc / 再点标题行) 都会复位回复状态 (摘掉 `parent`,
  面板搬回原位, 标题还原), 下一个人不会被当成在回复上一条评论。
- 写评论的卡片更紧凑: 「取消回复」挪进标题行 (原来单独占一行白占 28px),
  卡片内边距 `p-5/p-6` → `p-4/p-5`, 输入框最小高度 104px → 84px, 字段间距与表单行距收小。
- 回复框与窄屏导航菜单的展开 / 收起有了动画 (`data-collapse` / `data-collapse-body`
  + Web Animations): 文档流里的面板是高度 + 内边距 + 透明度一起过渡 (内边距放在
  `.respond-body` 的 padding-top 上, 不再用 margin, 否则最后一帧会「啪」地少掉一截),
  绝对定位的窄屏菜单改用从上方浮出的 pop 动画 (不动高度, 内容不会被压扁);
  展开比收起慢一点、用先快后慢的曲线。
- 点别处、按 Esc 会自动收回 (以前只能再点一次触发器), 一次只留一个面板,
  `prefers-reduced-motion` 下直接切换; 只在动画期间 `overflow: hidden`,
  不会把表情面板裁掉。
- 窄屏的分类 / 归档横滑带从页脚搬到正文上方 (`themeBrowseStrip()`): 文章页和页面页现在
  与列表页一致, 手机上打开就在文章上方, 不再在页脚「分页之后」多冒出一列。
- CSS 断点改用经典 `min-width` / `max-width` 写法 (范围内的宽高语法在老 WebView 上不生效)。
- `tests/comments-contract.test.mjs` 增加前端契约: 折叠动画只在动画期间裁切、
  高度与内边距同步过渡、窄屏菜单走 pop 动画、点别处 / Esc 收起、分类带位置与断点写法;
  另外覆盖回复修复 (data-reply-* / 双引号兜底正则 / 浮层与复位) 与卡片紧凑度。
- 回复卡片整体上移 16px (父评论的 padding-bottom 让出 6px, 上方只留 10px),
  卡片底部与下一个对话块的分割线之间留 16px; 上下相抵, 后面的评论位置不变。
- 回复浮层的遮罩改用中性暗纱 (`--veil`, 浅色 30% / 深色 62% 纯黑): 原先从
  `ink-1` 派生, 深色模式下 `ink-1` 是近白色, 叠出来是一片发亮的灰。遮罩淡入,
  只动透明度不碰模糊。
- 浮层动画简化: 卡片淡入 + 上浮 14px (200ms), 收起 150ms 原路退回; 不再走高度动画
  (那样卡片是从中间「长」出来的); 窄屏导航菜单的 pop 也去掉了缩放, 只留位移 + 淡入。
  遮罩跟卡片一起淡出 (以前要等 `collapse:closed` 才整块 remove, 背景模糊是一下子没的)。
- 点「写下你的想法」胶囊也走同一个浮层弹窗, 版面不再跟着跳; 评论页码改到回复框之后,
  胶囊贴着评论列表, 不会落到分页下面 (关闭评论时页码照样输出)。
- 回复中胶囊不再「消失」: 面板搬进浮层时原位留一颗同样的虚线胶囊 (`.respond-placeholder`),
  既看得出「评论框还在这儿」, 也不会让版面塌一下。
- 点「取消回复」不再在页脚原位「缩一下」: 内核的 `cancelReply()` 会先把卡片搬回原位,
  我们的淡出就变成了在文档流里缩一次 —— 现在拆掉它的行内 `onclick`, 改成先在浮层里
  淡出、淡完再归位, 归位那一步不再播动画。
- 提交评论后就地刷新评论列表时, 不再动浮层里的面板 (否则浮层会瞬间空掉、面板被拽回
  文档流); 回执 `#notice-box` 落在页码前面, 不会跑到分页后面。

### 插件: ItWasBadXD 支持多句打字机标题

- 以前离开标签页只是把标题换成一句固定文字; 现在改成打字机效果: 整句逐字打出,
  停顿后逐字删除, 再换下一句并循环 (打字时光标 `▌`, 停顿/删除时 `▁`)。
- 新增配置: 「要播放的句子」(一行一句, 默认就是原来脚本里的那几句台词)、
  打字间隔 / 整句停留 / 句子间隔三个**随机范围** (每次延迟都在 min~max 之间重新随机,
  最小值大于最大值时插件自动对调)、两种光标字符 (可留空)、回到标签页时显示的标题与
  停留时间、播放时机 (离开标签页时 / 始终播放)。
- 句库按 JS 字符串字面量输出 (`JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT`),
  句子里的尖括号、引号、emoji 既不会破坏脚本, 也不会原样落进 HTML。
- 旧配置仍然兼容: 「要播放的句子」留空时退回使用「失去焦点显示的标题」。
- `tools/e2e.php` 新增回归检查: 激活插件并写入配置后, 断言时间范围、光标、播放时机、
  多句话和转义都出现在页面头脚本里, 并且配置面板能正常渲染 Textarea / Radio。

### 插件: Sticky 置顶不再改变每页条数

- 以前置顶文章是「额外」压到列表最前面: 每页设 5 篇、置顶 2 篇, 首页就变成 7 篇;
  而且主查询的 offset 没有跟着挪, 第 2 页会跳过被置顶占掉的位置,
  排在中间的文章要么重复要么被跳过 (极端情况下第 2 页直接 404)。
- 现在置顶文章占掉第一页的名额, 而且**完全没有改动核心文件**
  (`usr/plugins/Sticky/Plugin.php` 重写): 插件挂 `Widget_Archive:query` 钩子接管取数,
  第一页先放置顶文章, 再补足「每页文章数目 - 置顶数」篇普通文章;
  之后各页的 offset 整体前移置顶数, 所以总篇数不变, 文章既不重复也不丢失。
- 置顶文章是否属于当前归档, 用归档自己的查询条件判定 (克隆 `$select` 后加
  `cid = ?` 再查一次): 分类页里不属于该分类的置顶文章不会再被硬塞进去。
- 置顶标记通过 `Widget_Base_Contents:filter` 钩子加在行数据上: 标题只追加
  `sticky_html` 的纯文本部分 (标题会被主题转义并写进 RSS), 原始 HTML 仍放在
  `sticky_html` 字段里供主题使用。
- 插件配置缺失时不再抛异常 (当作没有置顶), 不会让整站 500。
- `tools/e2e.php` 新增回归检查: 6 篇新文章 + 每页 5 篇 + 置顶最早的两篇,
  断言首页仍是 5 篇、置顶按配置顺序排在最前、第 2 页条数等于总数减 5 且没有文章丢失。

### 安全修复 (代码审计后的一轮加固)

- CSRF token 现在绑定提交目标的路径 (`var/Widget/Security.php`): `getToken()` 用
  `hash('sha256', token . '&csrf&' . 规范化路径)` 生成, `protect()` 用请求路径比对。
  以前 `$suffix` 形同虚设, 全站共用一个 token, 泄漏后可以拿去重放任意后台操作;
  绑定路径而不绑定 Referer, 是因为 CSP/隐私策略会把 Referer 剥掉
- `install.php`: 三个步骤的 POST 都要校验 CSRF token, 安装成功的「登录地址」不再把
  明文管理员密码塞进 URL 的查询串 (改成自动提交的 POST 表单)
- 主题标题的存储型 XSS: `usr/themes/default-2026/header.php` 用 `themeText()` 输出
  `archiveTitle()`, `usr/themes/default/header.php` 用 `htmlspecialchars()` 包了一层
  (标签名/分类名可以带 HTML)
- 文章/页面的草稿删除与附件绑定加属主校验 (`EditTrait::attach()` 只允许绑定自己的附件,
  `deletePostDraft()`/`deletePageDraft()` 需要 `editor` 权限或是作者本人),
  `markPost()` 不允许投稿者把自己的待审核文章改成公开
- 退出登录会作废 `authCode`(改密码同样), 改密后为用户重发 cookie; 删除用户时连带删除其
  Passkey 凭证
- `Widget\Passkey`: 未认证调用方不再看到 SQL/WebAuthn 库的异常原文, 凭证不存在与签名失败
  统一报「Passkey 验证失败」; 失败 10 次/15 分钟触发 429 并 `sleep(1)`; 注册时排除已注册的
  凭证; 断言里的 `userHandle` 仍与凭证属主 uid 比对 (这一条是真实校验, 不是冗余)
- XML-RPC: 除 `pingback.ping` 与 `system.*` 以外的方法, 只要没解析出 `userName`/`password`
  就直接 403 (以前方法签名里没有这两个参数就完全跳过登录校验), 密码错误计入限流
- `Widget\Options\Permalink` 的自定义 pattern 输入框对回显值做 `htmlspecialchars()`
  (反射型 XSS); `Typecho\Db\Query::order()` 只接受 `ASC`/`DESC`;
  `Typecho\Db\Adapter\SQLite::quoteValue()` 去掉字符串里的 NUL (SQLite 会截断);
  `Widget\Contents\PageOffsetTrait` 的列名走白名单
- `Typecho\Request::isSecure()` 只在直连地址是内网/保留地址时才信任 `X-Forwarded-*`
  (可用 `__TYPECHO_TRUST_PROXY__` 显式放开); `Typecho\Http\Client` 补上协议白名单兜底
  (旧 PHP 没有 `CURLOPT_PROTOCOLS_STR` 时) 并拒绝没有主机名的地址
- `themeFooterHtml()` 改用 `Typecho\Common::stripTags()` 的标签/属性白名单 (原来的正则
  `preg_replace` 能被 `<svg/onload=>`、不带引号的 `href` 绕过), 页脚评论回复链接的
  `onclick` 参数改成 `json_encode(... JSON_HEX_*)` + `themeEsc()`
- `Widget\Ajax::editorResize` 补上 `protect()`; `Widget\Action\Sitemap` 把两次 metas
  COUNT 合并成一条 `GROUP BY`, 并回 `Cache-Control: public, max-age=3600`
- `usr/plugins/Sticky`: 置顶文章一次查询取回 (原来按 cid 逐条查, N+1), 标题里只拼纯文本
  (HTML 单独放在 `sticky_html` 字段), cid 列表解析改成 `preg_split` + 整数过滤
- 后台 JS 里的界面文案改用新的 `_je()`/`_js()` (`var/Typecho/Common.php`), 不再把翻译
  文本塞进单引号 JS 字符串里 (文案带 `'` 会截断脚本)
- 回归测试: `tools/e2e.php` 按新约定从页面里取各端点专属 token, 并新增三条断言 ——
  含下划线的 slug 去重、跨端点重放 token 被拒、XML-RPC 错误密码被拒

### 仓库与文档整理

- 新增 [`tools/README.md`](tools/README.md): 逐个说明构建/打包/质量检查脚本的用途、常用命令与调用关系,
  并注明 `tools/` 与 `tests/` 均不会进发布包; `.tools/`(PHPStan / Semgrep) 可随时删除, 下次运行自动重建
- 文档侧边栏把 `代码质量与 PHP 8.5` 与新增的 `TypePHP 原生二进制实验` 拆到单独的「开发与构建」分组,
  `docs/README.md`、`README.md` 补上指向这两篇与 `tools/README.md` 的链接
- 修正两个失效的文档相对链接: `docs/guide/deployment.md` 的 `tools/assets/README.md`(少一层 `../`)、
  `docs/guide/installation.md` 的截图路径(`../../img/` 应为 `../img/`)
- 清理仓库内的临时产物: `.tmp-test/`(E2E 与 PHPStan 缓存)、`dist/` 旧包、`php-errors.log`、
  测试留下的 `usr/*.db`、空的 `tools/phpstan/` 与 `__pycache__/`(均在 `.gitignore` 里)

### 实验: TypePHP 原生二进制

- 新增 `docs/guide/typephp.md` 与 `tools/typephp/runner/`(`project.yml` + `main.php`),
  记录用 [TypePHP](https://github.com/swoole/typephp) 编译原生可执行文件的完整配方与结论:
  Windows x64 上能构建成功(`tpc.exe` 29.5 MiB, 全套工具链 32 分钟), 原生入口已能把 `GET /`
  交给站点并渲染出 37,493 字节的首页 HTML, `pdo_sqlite`/`mbstring`/`openssl` 等扩展在原生进程里可用
- 结论: TypePHP 的 AOT 只能编译 `main.php` 这类带显式 `main()` 的入口, 框架代码仍由链接进来的
  Zend 运行时 `include` 解释执行; `--nano`(不依赖任何 DLL)模式拒绝 `include`/`require`,
  与 TypechoRe 全站模板引导方式不兼容。产物需随包携带 `php8ts.dll`、`phpx.dll`、
  mpdecimal/gmp/mpfr 与 `php.ini`, 不是单文件二进制。**未接入 CI 与发布流程**;
  另外 TypePHP/PHPX 是 GPL-3.0, 分发前需先确认与本项目 GPL-2.0 的兼容性

### 发布包: 打包时用 Parcel 压缩前端资源

- 新增 `tools/assets/`(Parcel 2.16.4, 仅构建时使用) 与 `tools/minify_assets.py`:
  `tools/build_release.py` 现在会把发布集里的 CSS 与自包含 HTML 交给 Parcel 压缩后再进包,
  仓库里的文件不会被改写。当前 8 个样式表 167.5 KiB → 150.5 KiB (-10.2%),
  `usr/themes/default/normalize.css` 与 `admin/css/normalize.css` 各缩到约三分之一。
  发布包里目前没有静态 `.html` 文件, HTML 这一路是给主题/插件以后放的静态页留的;
  `install.php` 与主题模板是 PHP 文件(HTML 里嵌 `<?php ?>`), 不交给 Parcel ——
  它的 HTML 流水线会把 PHP 标签当文本处理并改写外部引用
- Parcel 是打包器: CSS 的相对 `url(...)` 会被当资源依赖改名并平坦化, 所以压缩前先换成
  `url(#__typechore_asset_N__)` 片段占位、压缩后换回原文; 含相对 `@import` 的样式表跳过。
  JS 不压缩 —— 包里全是已压缩的构建产物 (Vite 的 `dist/app.js`、`admin/js/` 里的 jQuery 等
  vendor 包), 重新打包会把它们包进 IIFE 并丢掉 `window.jQuery` 这类全局入口
- `tools/build_release.py` 新增 `--skip-minify`(不压缩) 与 `--require-minify`(压缩失败即报错);
  没有 Node 或未装 `tools/assets/` 依赖时默认只警告并跳过压缩。`SHA256SUMS.txt` 的摘要是
  按包内字节(即压缩之后)算的
- 发布包不再包含 `admin/src/`(1.5 MB 的 JS/SCSS 源码, 压缩前源文件, 全仓库无运行时引用):
  共减少 89 个文件, ZIP 从 1.3 MiB 降到 1.0 MiB
- `tests/build_release.py` 新增断言: `admin/src/` 不在包内、样式表确实被压缩、
  每个 `url(...)` 引用与源文件逐字一致、没有遗留占位符; 另外用临时样本实际跑一次 HTML 压缩
  (自包含页面变小且注释被去掉, 引用同目录 `theme.css` 的页面保持原样);
  `tools/quality.ps1`、`.github/workflows/release.yml` 与 `quality.yml` 会先装好 Parcel 工具链
- `docs/guide/deployment.md`、`docs/guide/quality.md`、`tools/assets/README.md` 记录了
  构建命令与「为什么 JS 不压缩」的实测原因

### 新主题 Default 2026 (预览版)

- 新增 `usr/themes/default-2026`: 现代、优雅、以性能为先的默认主题,
  技术栈为 Tailwind CSS v4 + @tailwindcss/typography + Alpine.js + Swup,
  用法与自定义说明见 [`usr/themes/default-2026/README.md`](usr/themes/default-2026/README.md)
- **定位为未完成的预览版** (`0.1.0-preview`): 模板结构、主题设置项与前端实现
  仍可能调整, 不建议用于正式站点, 升级也不保证向后兼容
- 首页为统一的卡片列表 (整张卡片可点击), 桌面端正文可读宽度约 930px,
  内容图片与代码块不做圆角与描边
- 无 JavaScript 时功能完整: 移动端导航使用原生 `<details>`, 评论表单为普通 POST,
  正文与目录均为服务端渲染; 省流模式 (`saveData` / 2G) 下自动关闭无刷新切页与预取
- 构建产物 (单 CSS + 单 JS) 随仓库分发, 部署无需 Node; 仅在修改前端源码时需要
  `npm install && npm run build`
- `tools/build_release.py` 新增排除 `node_modules`, 避免主题前端依赖进入发布包
- `.gitignore` 放行 `usr/themes/*/dist/`, 让主题构建产物可以随仓库提交

### 修复: 评论 Markdown 不解析 (内核)

- `var/Widget/Base/Comments.php`: 打开后台的「在评论中使用 Markdown 语法」后,
  渲染出来的 strong / em / code / pre / blockquote / ul / ol / li / h2–h6 / hr / a
  会被 `___content()` 最后的 `Common::stripTags()` 整段剥掉 —— 它只放行 `<p><br>`
  加上站长填写的 `commentsHTMLTagAllowed` (默认为空), 于是用户看到的就是
  「Markdown 没生效」。现在开关打开时内核一并放行 Markdown 会生成的标签,
  站长的白名单仍然有效 (同名标签以站长的声明为准)
- `var/Typecho/Common.php`: `stripTags()` 补上链接类属性 (href / src / srcset /
  action / formaction / poster / cite 等) 的协议检查 —— 白名单只管标签与属性名,
  属性值是不查的, 所以 `<a href="javascript:...">` 能原样留在输出里。检查前先做
  与浏览器一致的归一化 (实体解码 + 去掉 ASCII 控制字符与空白), 否则
  `java&#115;cript:` / `java\tscript:` / 大小写混写都能绕过去; 值安全时原样保留,
  不安全时换成 `#`。这条同时对「站长放行了 `<a href>`」的老站点生效
- `var/Utils/HyperDown.php`: 链接协议不在白名单里时 `cleanUrl()` 只返回了字符串 `#`,
  而调用方会按 `[$url, $title]` 解构 —— 触发 PHP 警告、href 变成空字符串;
  改为保持返回结构 (与正常分支一致)
- `usr/themes/default-2026`: 删掉主题里那个在 `contentEx` 上重做 Markdown 渲染的兜底
  (它跑在 `stripTags()` **之前**, 补出来的标签会被紧接着剥掉, 从来没有生效过)
- 测试: `tests/smoke.php` 覆盖危险协议 (8 种写法) 与 Markdown 标签白名单;
  `tools/e2e.php` 新增「评论 Markdown」端到端回归 (真实站点发一条 Markdown 评论,
  断言前台与侧边栏都渲染出标签且 `javascript:` 链接被换成 `#`)

### 修复: Default 2026 切页时侧边栏仍会闪一下

- 骨架遮罩 (`.page-skeleton`) 是不透明的固定层, 铺满导航之下的整个视口: 本次跳转
  「不换第二列」时骨架里不画它, 但遮罩照样把那一列盖成一块空白, 等淡出再露回来 ——
  看起来就是侧边栏闪了一下 (「不闪骨架」但「还是闪一下」)。现在判定不画第二列骨架时
  给 `<html>` 挂 `is-aside-kept`, `main.css` 把那一列提到遮罩之上 (`z-index: 71`),
  并让它只「看得见」不吃点击 (`pointer-events: none`, 点击照旧归遮罩);
  标记一直挂到遮罩真正 `hidden`, 覆盖整个淡出过程
- 测试: `tests/skeleton-state.test.mjs` 新增「挂/摘时机」时间线断言,
  `tests/css-contract.test.mjs` 与 `tests/comments-contract.test.mjs` 钉住那条 CSS 约定

### 修复: Default 2026 侧边栏会压到吸顶导航上

- 上一条修复把「本次不换的第二列」提到了遮罩之上 (`z-index: 71`), 同时把吸顶导航抬到 72。
  但那条导航规则写在了 `@layer components` 里, 而导航身上的 Tailwind `.z-50` 在
  utilities 层 —— **层级的优先级高于选择器优先级**, 所以 `z-index: 72` 从来没生效,
  导航停在 50: 页面往下滚过一段时, 侧边栏卡片正好压在导航那一条上, 切换页面时会
  短暂盖住导航 (关掉骨架屏就没有这个标记, 所以看起来正常)
- 两条 `is-aside-kept` 规则改为写在所有 `@layer` 之外 (与 `main.css` 里
  「必须写在层外」的正文排版覆盖同一处理方式)
- 测试: `tests/css-contract.test.mjs` 新增 `@layer` 归属断言 —— 这类 bug
  只看选择器和属性是看不出来的, 必须断言「这条规则不在任何层里」

### 主题 Default 2026: 移除相关文章与面包屑导航

- 删除主题设置里的 `相关文章` / `面包屑导航` 两个开关, 以及模板调用
  (`post.php` / `page.php` / `archive.php`)、`themeBreadcrumbs()` 与 `related.php`
- 这两个功能的内核 API (`Widget\Contents\Related`、`Archive::related()`) 不受影响,
  需要时仍可在自定义模板里直接调用

### 修复: Default 2026 切到文章页时侧边栏仍会闪一下 (第二列判定靠猜)

- 现象: 首页 ↔ 文章页来回切时侧边栏会「刷新」一下, 而首页 ↔ 独立页面不会 ——
  前者侧边栏其实一个字都没变。根因是 `app.js` 的骨架屏判定:
  `visit:start` 时拿不到目标文档, 早期版本按「目标是文章页 → 一定换成目录」处理,
  于是从首页点进一篇**没有目录**的文章 (`/archives/76/`: 正文里没有二级标题, 那一页
  的第二列还是侧边栏) 也会画一块目录骨架, 不透明遮罩把侧边栏盖住, 等遮罩淡出再露回来
- 现在判定有四档 (`keep` / `none` / `sidebar` / `toc`): 两边形态一样 (侧边栏是全站数据)
  就判 `keep` —— 不画骨架, 并把真实那一列托到遮罩之上; 真的会换才画对应骨架
- **精确来源**: `post-card.php` 在卡片链接 (标题 / 评论数) 上写 `data-aside-target`,
  值是 `themeContentAsideKind()` 算出来的「点进去那一页的第二列是什么」, app.js 直接用
  它; 分类链接写 `themeListAsideKind()` (列表页) 并顺带用 `data-skeleton="list"` 修掉
  骨架面板形态 (此前它按文章卡片推测, 明明去的是列表页却画文章骨架)
- **拿不准就保守**: 目标页没有提示、又无法提前知道那篇有没有目录时, 若当前页正是侧边栏
  就判 `keep` (不画), 不再赌一把; 目标是列表页时不必保守 —— 列表页第二列必定是侧边栏
- 当前页的形态改读 **DOM 里真实那一列** (`currentAsideKind()`), 不再读 `#page-skeleton`
  上的 `data-aside-kind`: 那个属性是服务端首屏渲染时写下的, 切页换掉第二列之后无人更新,
  于是从第二次跳转起判定一直用着首屏的形态 (首屏是列表页时, 从「有目录的文章」往回切
  也会以为当前是侧边栏); 该属性现在由 `recordAsideKind()` 跟着补写, 保持一致
- `themeAsideKind()` 拆成 `themeContentAsideKind()` / `themeListAsideKind()`, 文章页那档
  不再按 `layout` 一刀切 —— `toc.php` 不区分布局, 单栏布局下有目录的文章照样会输出那一版
  `<aside>`, 提示必须与页面真实渲染出来的东西一致
- 测试: 主题的 `skeleton-state.test.mjs` / `aside-sync.test.mjs` 覆盖四档判定、链接提示
  (含提示写在祖先节点上的情况) 与保守档; `comments-contract.test.mjs` 钉住「判定读 DOM,
  不读那个会过期的属性」; `tools/e2e.php` 新增一段端到端契约 (临时切到 Default 2026):
  卡片提示必须与目标页真实渲染的第二列一致 (无标题 → 两边都 sidebar; 加两个 `<h2>` 后
  → 两边都 toc), 分类链接必须要求列表骨架

### 发布包: 排除开发文件, 附带逐文件 SHA-256 清单

- `tools/build_release.py` 新增排除只服务于开发/构建的东西: 主题的 `tests/` 与 `src/`
  (纯 Node 测试与前端源码, `dist/` 才是随包分发的构建产物)、`package.json` /
  `package-lock.json` / `pnpm-lock.yaml` / `yarn.lock` / `vite.config.*` 等 Node 构建配置,
  以及任何位置的 `.gitignore` / `.gitattributes` / `.editorconfig`
- 发布包内新增 `SHA256SUMS.txt`: 逐文件 (清单自己除外) 的 SHA-256, 每行
  `<摘要>  <相对路径>`, 与 GNU coreutils 的 `sha256sum` 格式一致 ——
  解压后 `sha256sum -c SHA256SUMS.txt` 即可整包校验 (单文件被改动会报 `FAILED` 并以 1 退出)
- 构建时的自检 `verify()` 一并升级: 除了原有的必需文件与「危险文件」检查, 还会打开生成的
  ZIP, 确认清单恰好覆盖包内每个文件 (清单自己除外)、且逐条与包内字节重新计算的摘要一致
- `tests/build_release.py` 增加两项断言: 主题开发文件确实没进包 (同时确认 `dist/` 在包里,
  防止排除过宽)、`SHA256SUMS.txt` 与包内字节逐条一致
- 文档: `docs/guide/deployment.md` 补充发布包内容与校验方式, `docs/guide/quality.md`
  同步测试说明

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
  发布文章、匿名评论、图片上传、插件启停、Passkey 注册与签名登录、XML-RPC 与 1.3.2 升级路径，
  当前共 110 项断言，并检查服务器日志无任何 PHP 警告
- 新增 `tests/integration.php`：真实数据库适配器集成测试（建表/CRUD/JOIN/
  truncate/lastInsertId/affectedRows/升级脚本），本地与 CI 都只跑 SQLite（`SQLite`、
  `Pdo_SQLite`）；MySQL / PostgreSQL 适配器不再维护、不再测试
- 新增 `tests/webauthn.php`：用临时 P-256 密钥覆盖 WebAuthn `none` 注册、有效断言签名、Challenge/Origin/RP ID/UP/UV 拒绝、伪造签名及计数器回放检查；纳入质量门禁
- Apache/IIS 规则统一拒绝 `.db`、`.sqlite`、`.sqlite3` 主文件及 `-wal` / `-shm` / `-journal` 辅助文件；发布包与 Git 忽略规则也排除这些 SQLite 运行时文件，`tests/build_release.py` 会检查实际 ZIP。发行包同时包含 GPL v2 许可证文本。修正文档中遗留密码“兼容验证”的错误描述，并明确迁移时需重设旧格式密码
- 数据库支持范围收窄：**本项目只维护 SQLite**，`Mysqli`/`Pdo_Mysql`/`Pgsql`/
  `Pdo_Pgsql` 适配器保留（沿用上游 Typecho）但不在 CI 中运行，也不再修复问题
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
