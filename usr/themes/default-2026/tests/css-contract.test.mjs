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
 */
import { readFileSync } from 'node:fs';

const cssSource = readFileSync(process.env.CSS_MAIN || new URL('../src/css/main.css', import.meta.url), 'utf8');
const php = readFileSync(new URL('../functions.php', import.meta.url), 'utf8');

let failures = 0;
const check = (label, ok, extra = '') => {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${extra ? '  ' + extra : ''}`);
  if (!ok) failures++;
};

/* ---------- 极简规则抽取: 先剥注释, 再按花括号配平收集 ---------- */
const rules = [];
{
  const css = cssSource.replace(/\/\*[\s\S]*?\*\//g, '');
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

console.log(`\n${failures === 0 ? '全部通过' : failures + ' 项失败'}`);
process.exit(failures === 0 ? 0 : 1);
