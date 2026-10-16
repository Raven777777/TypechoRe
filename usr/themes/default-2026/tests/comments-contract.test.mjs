/**
 * 评论功能的 PHP 侧契约测试 (纯 Node, 不需要 PHP 运行时)
 *
 * 运行: npm test
 *
 * 这一块有两个曾经真实出问题的约定, 都是「改了源码但看不出坏」的类型:
 *
 *  1. **Markdown 渲染**: TypechoRe 的 `Comments::___content()` 最后会调
 *     `Common::stripTags($html, '<p><br>' . $options->commentsHTMLTagAllowed)`,
 *     而 `commentsHTMLTagAllowed` 默认为空 —— 于是 Markdown 渲染出来的
 *     strong / a / pre / ul 会被整段剥掉, 用户看到的就是「Markdown 没生效」。
 *     这里曾经由主题在 `contentEx` 上重做一次渲染来兜底, 但 `contentEx` 跑在
 *     `stripTags()` **之前**, 补出来的标签会被紧接着剥掉, 那个兜底从来没有生效过
 *     (还让「Markdown 失效」看起来已经修好了)。现在改由内核在 `commentsMarkdown`
 *     打开时放行 Markdown 生成的标签 (含链接协议检查), 主题不再插手。
 *     所以这里钉的是: **主题不许再自建那套注定无效的兜底**, 以及评论框的接线。
 *     内核那侧的行为由仓库根目录的 `tests/smoke.php` 与 `tools/e2e.php` 覆盖。
 *  2. **工具条与设置同步**: 站点关掉 Markdown 后, 评论框不能再显示
 *     「支持 Markdown」和 B / I / 链接等按钮 (comments.php)。
 *
 * 还有两条与骨架屏有关: 第二列骨架必须与 list/single 形态面板**平级**,
 * 不能挂在面板里面 —— 挂在里面就会跟着形态切换, 于是「侧边栏没变」的跳转
 * 也会闪一块侧边栏骨架 (footer.php); 而骨架屏不画第二列时, 遮罩不许把那一列
 * 盖成空白再露回来 (app.js 的 is-aside-kept + main.css)。
 */
import { readFileSync } from 'node:fs';

const root = new URL('../', import.meta.url);
const php = readFileSync(new URL('functions.php', root), 'utf8');
const comments = readFileSync(new URL('comments.php', root), 'utf8');
const footer = readFileSync(new URL('footer.php', root), 'utf8');
const appJs = readFileSync(new URL('src/js/app.js', root), 'utf8');
const css = readFileSync(new URL('src/css/main.css', root), 'utf8');

let failures = 0;
const check = (label, ok, extra = '') => {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${extra ? '  ' + extra : ''}`);
  if (!ok) failures++;
};

/** 取出某个顶层函数的函数体 (从 function 名 到下一个顶格 }) */
function functionBody(source, name) {
  const start = source.indexOf(`function ${name}(`);

  if (start < 0) {
    return '';
  }

  const end = source.indexOf('\n}', start);

  return end < 0 ? source.slice(start) : source.slice(start, end);
}

/* ---------- 1. 评论 Markdown: 主题不再自建兜底 ----------
   历史: 内核会把 Markdown 渲染出来的 strong / a / pre / ul 整段剥掉, 主题因此在
   `contentEx` 上重做了一次渲染并自带白名单 —— 但 `contentEx` 跑在 `stripTags()`
   **之前**, 补出来的标签会被紧接着剥掉, 那个兜底从来没有生效过 (还让「Markdown
   失效」看起来已经修好了)。现在由内核在 `commentsMarkdown` 打开时放行 Markdown
   生成的标签, 主题这边只保留接线; 内核行为由仓库根目录的 smoke / e2e 覆盖。 */
{
  const init = functionBody(php, 'themeInit');

  check('★ 主题不再注册 contentEx 兜底钩子 (它跑在 stripTags 之前, 注定无效)', !init.includes("filter('contentEx'"));
  check('★ 主题里没有重复实现标签白名单', !php.includes('themeCommentAllowedTags') && !php.includes('themeStripCommentTags'));
  check('★ themeInit() 不再覆盖后台的「评论回复层数」(收紧了第 5 层会被内核挂到第 3 层上)', !/commentsMaxNestingLevels\s*=/.test(init));
  check('themeInit() 不再自己渲染评论 (交给内核)', !init.includes('Markdown::convert'));
}

/* ---------- 2. 评论框的 Markdown 工具条跟随设置 ---------- */
{
  check(
    '★ 工具条按钮被 commentsMarkdown 包起来',
    /commentsMarkdown\s*\)\s*:\s*\?>[\s\S]*?data-comment-format="bold"[\s\S]*?endif;\s*\?>/.test(comments),
    '模板里没看到把按钮包在 if 里的结构'
  );
  check('提示语随设置切换', comments.includes('支持 Markdown · 提交后可能需要审核') && comments.includes("_t('提交后可能需要审核')"));
  check('表情面板不受 Markdown 设置影响 (始终可用)', /<details class="emoji-picker">/.test(comments));

  /* 提交走主题自己的 fetch: 表单必须带标记, 且 JS 里有 preventDefault */
  check('★ 表单带 data-comment-submit 标记', comments.includes('data-comment-submit'));
  check('表单带新评论锚点 (提交后就地跳转)', comments.includes('data-comment-anchor='));
  check('表单带失败与审核中的文案', comments.includes('data-submit-failed=') && comments.includes('data-waiting-text='));
}

/* ---------- 3. 第二列骨架不跟着形态面板切换 ---------- */
{
  const asideIndex = footer.indexOf('data-skeleton-aside');
  check('footer.php 输出了第二列骨架', asideIndex > 0);

  /* 形态面板的区间: list 面板开始 → single 面板结束 */
  const panelStart = footer.indexOf('data-skeleton-panel="list"');
  const panelEnd = footer.indexOf('data-skeleton-aside');

  check('★ 第二列骨架写在形态面板之后 (与它们平级, 不挂在面板里面)', asideIndex > panelStart && asideIndex > (footer.lastIndexOf('data-skeleton-panel="single"')), `aside=${asideIndex} panelStart=${panelStart}`);

  /* 两种形态都预渲染好, 由 JS 切换 */
  check('同时输出侧边栏与目录两种骨架', footer.includes('data-skeleton-aside-variant="sidebar"') && footer.includes('data-skeleton-aside-variant="toc"'));
  check('两种骨架默认都是隐藏的', (footer.match(/data-skeleton-aside-variant="(?:sidebar|toc)" hidden/g) || []).length === 2);
  check('本页第二列形态写在 data-aside-kind 上 (服务端渲染时的记录)', footer.includes('data-aside-kind="<?php echo themeEsc($skeletonAsideKind); ?>"'));
  check('形态由 themeAsideKind() 提供', php.includes('function themeAsideKind(') && footer.includes('themeAsideKind($this)'));

  /* 形态判定要覆盖三种情况: 单栏 / 有目录的文章 / 其余列表页 */
  const single = functionBody(php, 'themeContentAsideKind');
  const list = functionBody(php, 'themeListAsideKind');
  check('形态判定: 有目录的文章 → toc', single.includes("'toc'") && single.includes("themeOptionBool('showToc')"));
  check('形态判定: 其余 → sidebar', single.includes("'sidebar'"));
  check('★ 单栏布局下侧边栏消失 (列表页与文章页共用同一个判定)', list.includes("'full' === themeOption('layout', 'sidebar')") && list.includes("return 'none'"));
  check('★ 单栏布局下文章页也没有第二列 (栅格都没画, aside 会掉在评论区下方)', single.includes("'full' === themeOption('layout', 'sidebar')") && single.includes("return 'none'"));

  /* 单栏布局下目录改为正文上方的折叠块 (与窄屏那一版共用) —— 模板只在真的
     有第二列时才载入 toc.php / sidebar.php, 并且单栏下那块不再 lg:hidden */
  const post = readFileSync(new URL('post.php', root), 'utf8');
  const page = readFileSync(new URL('page.php', root), 'utf8');
  check('★ 文章页只在 toc / sidebar 时载入第二列',
    /'toc' === \$postAside[\s\S]{0,80}need\('toc\.php'\)/.test(post) && /'sidebar' === \$postAside[\s\S]{0,80}need\('sidebar\.php'\)/.test(post));
  check('★ 独立页面同样', /'toc' === \$pageAside/.test(page) && /'sidebar' === \$pageAside/.test(page));
  check('★ 单栏布局下目录折叠块不再 lg:hidden (窄屏那一版就地升级)',
    post.includes("'toc' === $postAside ? ' lg:hidden' : ''") && page.includes("'toc' === $pageAside ? ' lg:hidden' : ''"));

  /* 卡片链接上的目标形态提示: app.js 靠它在拿到目标文档之前就定下第二列怎么处理,
     少了它, 「首页 → 没有目录的文章」只能保守处理 (也可能是错的另一种) */
  const card = readFileSync(new URL('post-card.php', root), 'utf8');
  check('★ 卡片链接带 data-aside-target 提示 (文章页形态)', card.includes('data-aside-target="<?php echo themeEsc($cardAside); ?>"'));
  check('★ 分类链接带列表页提示 (形态 + 骨架面板)', card.includes('data-skeleton="list"') && card.includes('data-aside-target="<?php echo themeEsc($listAside); ?>"'));
  check('提示由 themeContentAsideKind() / themeListAsideKind() 提供', card.includes('themeContentAsideKind($this)') && card.includes('themeListAsideKind()'));
}

/* ---------- 4. 骨架屏不画第二列时, 遮罩不许把那一列盖成空白 ----------
   遮罩是铺满视口的固定层: 侧边栏在「列表页 → 列表页」时一个字都不会变, 骨架屏里
   不画它 —— 但它照样会被遮罩盖住, 等遮罩淡出再露回来, 看起来就是侧边栏闪了一下。
   app.js 判定不画时给 <html> 挂 is-aside-kept, CSS 把那一列提到遮罩之上。 */
{
  const kept = functionBody(appJs, 'syncAsideKept');

  check('app.js 定义了 syncAsideKept()', kept.length > 0);
  check('★ 只在「本次判定为 keep (这一列不会变)」且当前页确实有那一列时才托起来', kept.includes('ASIDE_KEEP === skeletonNextAsideKind') && kept.includes('ASIDE_SELECTOR'));
  check('★ 挂/摘跟着遮罩的显隐走 (淡出期间那一列必须一直浮着)', kept.includes('!overlay.hidden'));
  check('★ 摘标记的时机在遮罩真正隐藏之后', /overlay\.hidden = true;[\s\S]{0,200}syncAsideKept\(\)/.test(appJs));
  check('显示骨架时也算一次', /overlay\.hidden = false;[\s\S]{0,200}syncAsideKept\(\)/.test(appJs));

  /* 判定不再读遮罩上的那份首屏记录: 切页会换掉第二列, 而那个属性没人更新 */
  const current = functionBody(appJs, 'currentAsideKind');
  check('★ 当前形态取自 DOM 里真实的那一列', current.includes('ASIDE_SELECTOR') && current.includes('dataset.aside'));
  check('★ 不再读 #page-skeleton 上的 data-aside-kind (首屏之后它就过期了)', !current.includes('page-skeleton'));
  const session = functionBody(appJs, 'asideKindForSession');
  check('★ 拿不准 (目标是文章页、当前是侧边栏) 时按 keep 处理, 不赌一把', session.includes('ASIDE_KEEP'));
  check('★ 链接提示优先 (targetAsideKind)', session.includes('targetAsideKind(visit)'));

  const rule = /html\.is-aside-kept \.page-aside \{([^}]*)\}/.exec(css);
  const navRule = /html\.is-aside-kept #site-nav \{([^}]*)\}/.exec(css);

  check('★ CSS 里有 html.is-aside-kept .page-aside 规则', null !== rule, rule ? rule[1].trim() : '未找到');
  check('★ 那一列被提到遮罩 (z-index 70) 之上', null !== rule && /z-index:\s*7[1-9]/.test(rule[1]), rule ? rule[1].trim() : '未找到');
  check(
    '★ 只是「能看见」, 点击照旧被遮罩拦住',
    null !== rule && /pointer-events:\s*none/.test(rule[1]) && /position:\s*relative/.test(rule[1]),
    rule ? rule[1].trim() : '未找到'
  );
  check(
    '★ 吸顶导航跟着抬高 (否则卡片会压到导航上)',
    null !== navRule && Number(/(?:^|;)\s*z-index:\s*(\d+)/.exec(navRule[1])?.[1] ?? 0) > 71,
    navRule ? navRule[1].trim() : '未找到'
  );
}

console.log(`\n${failures === 0 ? '全部通过' : failures + ' 项失败'}`);
process.exit(failures === 0 ? 0 : 1);
