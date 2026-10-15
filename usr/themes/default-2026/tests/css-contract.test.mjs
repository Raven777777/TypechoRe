/**
 * CSS 契约测试 (纯 Node, 不需要 jsdom / PHP / 浏览器)
 *
 * 运行: npm test
 *
 * 有些动效 bug 出在 CSS 而不是 JS —— jsdom 的 getComputedStyle 不套用样式表规则
 * (实测新旧 CSS 都只读到默认值), 所以这里改为直接读 src/css/main.css, 把几条
 * 「改坏了就会重现老 bug」的约定钉住:
 *
 *  1. 第二列 (.page-aside) 不得出现在退场/插入态的无条件规则里。
 *     退场阶段还没拿到目标文档, 无从判断这一列会不会被换掉; 一旦给它淡出,
 *     「侧边栏没变」的跳转 (两篇都没有目录的文章之间切换) 就会淡出后被
 *     is-rendering 弹回, 闪一下 —— 骨架屏开着时被遮罩盖住, 关掉就露出来。
 *  2. 第二列的入场必须同时要求 is-entering 与 is-aside-swapped。
 *  3. #main 的退场/入场规则仍在 (别为了修第二列把正文动画弄丢)。
 *  4. 卡片错峰与上浮位移必须走 CSS 变量 (否则主题设置里的动效强度接不上)。
 *  5. main.css 里用到的每个 --anim-* 都必须由 functions.php 输出 (否则那个
 *     设置项永远不会生效, 只会静静用兜底值)。
 *  6. 未显示的骨架层必须 pointer-events: none (那层是覆盖内容区的固定层,
 *     停在透明状态会让整片内容点不动)。
 *  7. 设置项接线: 每个开关都要在 functions.php 注册、在 header.php 输出成 <html>
 *     上的 data-*, 并被 app.js 真正读取 —— 少任何一环设置项就会静默失效。
 *  8. 「本次不画第二列骨架」时, 遮罩不许把那一列盖成空白 (盖住再露出来就是闪一下):
 *     is-aside-kept 只把那一列提到遮罩之上, 并且不吃点击。
 */
import { readFileSync } from 'node:fs';

const cssSource = readFileSync(process.env.CSS_MAIN || new URL('../src/css/main.css', import.meta.url), 'utf8');
const php = readFileSync(new URL('../functions.php', import.meta.url), 'utf8');
const header = readFileSync(new URL('../header.php', import.meta.url), 'utf8');
const appJs = readFileSync(new URL('../src/js/app.js', import.meta.url), 'utf8');

let failures = 0;
const check = (label, ok, extra = '') => {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${extra ? '  ' + extra : ''}`);
  if (!ok) failures++;
};

/* ---------- 极简规则抽取: 先剥注释, 再按花括号配平收集 ---------- */
const strippedCss = cssSource.replace(/\/\*[\s\S]*?\*\//g, '');
const rules = [];
{
  const css = strippedCss;
  const re = /([^{}]+)\{/g;
  let match;

  while ((match = re.exec(css)) !== null) {
    const selector = match[1].trim().replace(/\s+/g, ' ');

    /* 跳过 @media/@layer/@keyframes 等前缀与 keyframes 的 0%/from/to */
    if (!selector || selector.startsWith('@') || selector === 'from' || selector === 'to' || /^\d+%$/.test(selector)) {
      continue;
    }

    let depth = 1;
    let i = re.lastIndex;
    while (i < css.length && depth > 0) {
      if (css[i] === '{') depth++;
      else if (css[i] === '}') depth--;
      i++;
    }

    rules.push({ selector, body: css.slice(re.lastIndex, i - 1).trim() });
  }
}
console.log(`已解析 main.css: ${rules.length} 条规则\n`);

const withSelector = (needle) => rules.filter((rule) => rule.selector.includes(needle));
const showRules = (list) => list.map((rule) => rule.selector).join(' | ');

/**
 * 收集 @layer 块的范围 (起止偏移)
 *
 * 层级 (layer) 的优先级高于选择器优先级: 写在 @layer components 里的 `z-index: 72`
 * 永远赢不了 utilities 层里的 Tailwind `.z-50`。这正是「侧边栏压到吸顶导航上」
 * 那个 bug 的成因 —— 规则看着完全正确, 却被工具类顶掉了, 所以必须单独断言。
 */
function layerBlocks(css) {
  const blocks = [];
  const re = /@layer\s+([a-zA-Z0-9_-]+)\s*\{/g;
  let match;

  while ((match = re.exec(css)) !== null) {
    let depth = 1;
    let i = re.lastIndex;

    while (i < css.length && depth > 0) {
      if (css[i] === '{') depth++;
      else if (css[i] === '}') depth--;
      i++;
    }

    blocks.push({ name: match[1], start: match.index, end: i });
  }

  return blocks;
}

const layerRanges = layerBlocks(strippedCss);

/** 选择器是否写在所有 @layer 之外 (也就是能压过 utilities 层) */
function outsideEveryLayer(selector) {
  const index = strippedCss.indexOf(selector);

  if (index < 0) {
    return false;
  }

  return !layerRanges.some((block) => index > block.start && index < block.end);
}

/* ---------- 1. 第二列不参与退场/插入态 ---------- */
for (const state of ['is-animating', 'is-rendering']) {
  const offenders = rules.filter((rule) => rule.selector.includes('.page-aside') && rule.selector.includes(state));

  check(
    `★ .page-aside 不出现在 ${state} 的无条件规则里`,
    offenders.length === 0,
    showRules(offenders)
  );
}

/* ---------- 2. 第二列的入场被两个类同时把关 ---------- */
{
  const enterRules = rules.filter((rule) => rule.selector.includes('.page-aside') && rule.selector.includes('is-entering'));
  const ungated = enterRules.filter((rule) => !rule.selector.includes('is-aside-swapped'));

  check('第二列有入场规则', enterRules.length > 0, showRules(enterRules));
  check('★ 第二列的入场同时要求 is-entering 与 is-aside-swapped', enterRules.length > 0 && ungated.length === 0, showRules(ungated));
  check(
    '第二列入场用的与卡片同一套变量 (aside-rise + --anim-enter-duration)',
    enterRules.some((rule) => rule.body.includes('aside-rise') && rule.body.includes('var(--anim-enter-duration)')),
    showRules(enterRules)
  );
}

/* ---------- 3. 正文的退场/入场仍在 ---------- */
{
  const exitRule = rules.find((rule) => rule.selector.includes('is-animating') && rule.selector.includes('.transition-fade'));
  const enterRule = rules.find((rule) => rule.selector.includes('is-entering') && rule.selector.includes('.transition-fade'));

  check('正文退场规则真的淡出', !!exitRule && exitRule.body.includes('opacity: 0'), exitRule ? exitRule.selector : '未找到');
  check('正文退场位移走 --anim-exit-distance', !!exitRule && exitRule.body.includes('var(--anim-exit-distance)'));
  check('正文入场规则仍在', !!enterRule && enterRule.body.includes('content-fade'), enterRule ? enterRule.selector : '未找到');
}

/* ---------- 4. 动效强度接到了错峰与位移上 ---------- */
{
  const cardRule = rules.find((rule) => rule.selector.includes('is-entering') && rule.selector.includes('article.card') && !rule.selector.includes('nth-of-type'));
  const staggerRules = rules.filter((rule) => rule.selector.includes('article.card') && rule.selector.includes('nth-of-type'));

  check('卡片入场时长走变量', !!cardRule && cardRule.body.includes('var(--anim-enter-duration)'), cardRule ? cardRule.selector : '未找到');
  check(
    '卡片错峰走变量 (第 1 张是 0ms, 其余都按 --anim-stagger 递增)',
    staggerRules.length >= 5
      && staggerRules.filter((rule) => rule.body.includes('var(--anim-stagger)')).length === staggerRules.length - 1
      && staggerRules.some((rule) => rule.body.includes('animation-delay: 0ms')),
    `${staggerRules.length} 条错峰规则`
  );
  check(
    '上浮位移走变量 (keyframes 里)',
    /translateY\(var\(--anim-enter-distance\)\)/.test(cssSource)
  );
}

/* ---------- 5. main.css 用到的 --anim-* 必须由 PHP 输出 ---------- */
{
  const used = new Set([...cssSource.matchAll(/var\((--anim-[a-z-]+)/g)].map((match) => match[1]));
  const emitted = new Set([...php.matchAll(/'(--anim-[a-z-]+)'/g)].map((match) => match[1]));
  const missing = [...used].filter((name) => !emitted.has(name));

  check('★ PHP 输出了 CSS 用到的每个 --anim-* 变量', missing.length === 0, missing.join(', '));
  check('JS 用的 --anim-window 也在 PHP 输出里', emitted.has('--anim-window'), [...emitted].sort().join(', '));
  console.log(`      CSS 用到: ${[...used].sort().join(', ')}`);
}

/* ---------- 6. 未显示的骨架层不吃点击 ---------- */
{
  const rule = rules.find((r) => r.selector.includes('.page-skeleton') && r.selector.includes(':not(.is-visible)'));

  check('★ 未显示的骨架层 pointer-events: none', !!rule && rule.body.includes('pointer-events: none'), rule ? rule.selector : '未找到');
}

/* ---------- 7. 设置项接线: functions.php → header.php → app.js ---------- */

/** 取出某个顶层函数的函数体 (从 function 名 到下一个顶格 }) */
function functionBody(source, name) {
  const start = source.indexOf(`function ${name}(`);

  if (start < 0) {
    return '';
  }

  const end = source.indexOf('\n}', start);

  return end < 0 ? source.slice(start) : source.slice(start, end);
}

/** 断言一个开关的完整接线 */
function checkSwitch({ option, reader, attribute, usesInApp, appPurpose }) {
  check(`${option}: functions.php 注册了设置项`, new RegExp(`'${option}'`).test(php));
  check(`${option}: functions.php 提供 ${reader}()`, php.includes(`function ${reader}(`));
  check(
    `${option}: header.php 用它输出 ${attribute}`,
    header.includes(`${reader}()`) && header.includes(`${attribute}=`),
    attribute
  );
  check(
    `${option}: app.js 真正读取了它`,
    appJs.includes(`dataset.${attribute.replace('data-', '')}`),
    appJs.includes(`dataset.${attribute.replace('data-', '')}`) ? '' : `未找到 dataset.${attribute.replace('data-', '')}`
  );
  check(`${option}: ${appPurpose}`, appJs.includes(usesInApp), usesInApp);
}

checkSwitch({
  option: 'preloadLinks',
  reader: 'themePreloadLinks',
  attribute: 'data-preload',
  usesInApp: 'if (preloadEnabled())',
  appPurpose: '预取插件被 preloadEnabled() 条件包裹'
});
checkSwitch({
  option: 'showSkeleton',
  reader: 'themeSkeletonEnabled',
  attribute: 'data-skeleton',
  usesInApp: "'0' !== root.dataset.skeleton",
  appPurpose: 'app.js 按它决定要不要显示骨架'
});
checkSwitch({
  option: 'animLevel',
  reader: 'themeAnimLevel',
  attribute: 'data-anim',
  usesInApp: "root.dataset.anim",
  appPurpose: 'app.js 按它判断「关闭」档'
});

/* 预取插件的注册必须在 buildSwupPlugins() 里, 且由开关把关 */
{
  const body = functionBody(appJs, 'buildSwupPlugins');

  check('buildSwupPlugins() 存在', body.length > 0);
  check('★ 预取插件在 buildSwupPlugins() 内按开关注册', body.includes('if (preloadEnabled())') && body.includes('new SwupPreloadPlugin'));
  check('插件顺序仍是 head → 预取 → 进度条 → 滚动', body.indexOf('SwupHeadPlugin') < body.indexOf('SwupPreloadPlugin') && body.indexOf('SwupPreloadPlugin') < body.indexOf('SwupProgressPlugin'));
}

/* ---------- 8. 不画第二列骨架时, 真实那一列浮在遮罩之上 ----------
   遮罩不透明且铺满视口: 盖住「本来就不会变」的侧边栏再随淡出露回来, 就是那次闪烁。
   is-aside-kept 只做两件事 —— 提到遮罩 (z-index: 70) 之上、把点击让给遮罩;
   它不许给那一列加任何动效 (内容根本没变)。 */
{
  const keptRules = rules.filter((rule) => rule.selector.includes('is-aside-kept') && rule.selector.includes('.page-aside'));

  check('★ 有 is-aside-kept 的第二列规则', keptRules.length === 1, showRules(keptRules));

  const body = keptRules[0]?.body ?? '';

  check('★ 提到遮罩之上 (遮罩是 70)', /z-index:\s*7[1-9]/.test(body), body);
  check('★ 只到「看得见」为止: 点击仍归遮罩', /pointer-events:\s*none/.test(body), body);
  check('★ 不给它加动效 (那一列内容没变)', !/animation|opacity|transition|transform/.test(body), body);
  check('★ 需要定位属性才能吃 z-index', /position:\s*(relative|absolute|fixed|sticky)/.test(body), body);

  /* 抬到 71 层之后, 吸顶导航 (50) 要一起抬, 否则滚过一段时卡片会盖住导航 */
  const navRule = rules.find((rule) => rule.selector.includes('is-aside-kept') && rule.selector.includes('#site-nav'));
  const navZIndex = Number(/(?:^|;)\s*z-index:\s*(\d+)/.exec(navRule?.body ?? '')?.[1] ?? 0);

  check('★ 吸顶导航被一起抬高 (盖不过第二列)', !!navRule && navZIndex > 71, navRule ? `${navRule.selector} { ${navRule.body} }` : '未找到');

  /* ★ 关键: 这两条必须写在层外。导航身上有 Tailwind 的 .z-50 (utilities 层),
     层级的优先级高于选择器优先级 —— 写在 components 层里就一定输, 第二列会压住导航。 */
  check(
    '★ is-aside-kept 的第二列规则写在所有 @layer 之外',
    outsideEveryLayer('html.is-aside-kept .page-aside'),
    `层: ${layerRanges.map((block) => block.name).join(', ') || '无'}`
  );
  check(
    '★ is-aside-kept 的导航规则写在所有 @layer 之外 (否则赢不了 .z-50)',
    outsideEveryLayer('html.is-aside-kept #site-nav'),
    `层: ${layerRanges.map((block) => block.name).join(', ') || '无'}`
  );
}

console.log(`\n${failures === 0 ? '全部通过' : failures + ' 项失败'}`);
process.exit(failures === 0 ? 0 : 1);
