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
  check('themeInit() 仍然收紧嵌套评论层级', init.includes('commentsMaxNestedLevels') || init.includes('commentsMaxNestingLevels = 4'));
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
  check('本页第二列形态写在 data-aside-kind 上 (供 JS 判定)', footer.includes('data-aside-kind="<?php echo themeEsc($skeletonAsideKind); ?>"'));
  check('形态由 themeAsideKind() 提供', php.includes('function themeAsideKind(') && footer.includes('themeAsideKind($this)'));

  /* 形态判定要覆盖三种情况 */
  const asideKind = functionBody(php, 'themeAsideKind');
  check('形态判定: 单栏布局 → none', asideKind.includes("'full' === themeOption('layout', 'sidebar')") && asideKind.includes("return 'none'"));
  check('形态判定: 有目录的文章页 → toc', asideKind.includes("'toc'"));
  check('形态判定: 其余 → sidebar', asideKind.includes("'sidebar'"));
}

/* ---------- 4. 骨架屏不画第二列时, 遮罩不许把那一列盖成空白 ----------
   遮罩是铺满视口的固定层: 侧边栏在「列表页 → 列表页」时一个字都不会变, 骨架屏里
   不画它 —— 但它照样会被遮罩盖住, 等遮罩淡出再露回来, 看起来就是侧边栏闪了一下。
   app.js 判定不画时给 <html> 挂 is-aside-kept, CSS 把那一列提到遮罩之上。 */
{
  const kept = functionBody(appJs, 'syncAsideKept');

  check('app.js 定义了 syncAsideKept()', kept.length > 0);
  check('★ 只在「本次不画第二列」且当前页确实有那一列时才托起来', kept.includes("'none' === skeletonNextAsideKind") && kept.includes('ASIDE_SELECTOR'));
  check('★ 挂/摘跟着遮罩的显隐走 (淡出期间那一列必须一直浮着)', kept.includes('!overlay.hidden'));
  check('★ 摘标记的时机在遮罩真正隐藏之后', /overlay\.hidden = true;[\s\S]{0,200}syncAsideKept\(\)/.test(appJs));
  check('显示骨架时也算一次', /overlay\.hidden = false;[\s\S]{0,200}syncAsideKept\(\)/.test(appJs));

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
