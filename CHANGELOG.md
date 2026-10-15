# 更新日志

本文件记录 TypechoRe 各版本的代码级改动。主题改动见
[`usr/themes/default/CHANGELOG.md`](usr/themes/default/CHANGELOG.md)。

## 未发布

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
