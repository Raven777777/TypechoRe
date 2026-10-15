/**
 * 骨架屏状态机回归测试
 *
 * 运行: npm test  (在主题目录下, 只依赖 Node, 不需要装任何包)
 *
 * 复现场景: 进入站点 → 点开一篇文章 → 返回主页。
 * 断言: 任何一次跳转结束后 #page-skeleton 都必须回到 hidden=true ——
 *       它是 position:fixed + 覆盖整个内容区的层, 只要停在「透明但还在」
 *       的状态, 文章卡片和侧边栏就会全部点不动。
 *
 * 实现上直接从 src/js/app.js 抽取真实代码 (按注释里的两个锚点切片) 再配一个
 * 虚拟时钟执行, 所以它测的是线上那份逻辑, 不是抄来的副本。想验证它没空转,
 * 可以把 APP_JS 指向一份故意改坏的副本, 断言应当失败。
 */
import { readFileSync } from 'node:fs';

const APP = process.env.APP_JS || new URL('../src/js/app.js', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1');
const source = readFileSync(APP, 'utf8');

/* 代码段锚点: 与 app.js 里的注释保持一致 */
const start = source.indexOf('const SKELETON_DELAY');
const end = source.indexOf('/** 同一个页面里的锚点跳转不会真正换页');
if (start < 0 || end < 0 || end <= start) {
  throw new Error('无法从 app.js 中定位骨架屏代码段 (锚点注释被改动了?)');
}
const code = source.slice(start, end);
console.log(`已从 app.js 抽取骨架屏逻辑: ${code.split('\n').length} 行\n`);

/* ---------- 虚拟时钟 ---------- */
let now = 0;
let timerId = 0;
const timers = [];
const fakeSetTimeout = (fn, ms = 0) => {
  const id = ++timerId;
  timers.push({ id, at: now + ms, fn });
  return id;
};
const fakeClearTimeout = (id) => {
  const i = timers.findIndex((t) => t.id === id);
  if (i >= 0) timers.splice(i, 1);
};
/** 推进虚拟时间, 按到期顺序执行回调 */
function advance(ms) {
  const target = now + ms;
  for (;;) {
    const due = timers.filter((t) => t.at <= target).sort((a, b) => a.at - b.at)[0];
    if (!due) break;
    timers.splice(timers.indexOf(due), 1);
    now = due.at;
    due.fn();
  }
  now = target;
}

/** 每段场景开始前重置时钟与遮罩 */
function reset() {
  now = 0;
  timers.length = 0;
  overlay.hidden = true;
  overlay.classList.remove('is-visible');
  asideSkeleton.hidden = true;
  documentStub.documentElement.classList.remove('is-aside-kept');
}

/* ---------- 最小 DOM 桩 ---------- */
const makeClassList = () => {
  const set = new Set();
  return {
    add: (...c) => c.forEach((x) => set.add(x)),
    remove: (...c) => c.forEach((x) => set.delete(x)),
    /* 两个参数的 toggle 是 app.js 用来挂/摘 is-aside-kept 的写法 */
    toggle: (c, force) => {
      const wanted = undefined === force ? !set.has(c) : !!force;
      if (wanted) {
        set.add(c);
      } else {
        set.delete(c);
      }
      return wanted;
    },
    contains: (c) => set.has(c),
    toString: () => [...set].join(' ')
  };
};

const overlay = { hidden: true, dataset: {}, classList: makeClassList() };
/* 骨架屏里的第二列 (侧边栏 / 文章目录): 两种形态都先渲染好, 切换显隐 */
const asideVariants = ['sidebar', 'toc'].map((name) => ({
  hidden: true,
  getAttribute: (attribute) => (attribute === 'data-skeleton-aside-variant' ? name : null)
}));
const asideSkeleton = {
  hidden: true,
  dataset: {},
  querySelectorAll: () => asideVariants
};
/* 只让显隐状态可读: 与外层结构无关 */
const variantOf = (name) => asideVariants.find((v) => v.getAttribute('data-skeleton-aside-variant') === name);
const asideShownKind = () => (asideSkeleton.hidden ? 'none' : asideSkeleton.dataset.asideKind || null);
/* 真实的那一列 (侧边栏 / 文章目录): 它在不在、是哪一种由这个开关模拟 ——
   app.js 现在直接看 DOM (currentAsideKind), 因为遮罩上的 data-aside-kind 是首屏
   渲染时写下的, 切页换掉这一列之后不会有人更新它。 */
let realAsideKind = 'sidebar';
const documentStub = {
  getElementById: (id) => (id === 'page-skeleton' ? overlay : null),
  querySelector: (selector) => {
    if (selector === '[data-skeleton-aside]') {
      return asideSkeleton;
    }
    if (selector === '.page-aside') {
      return 'none' === realAsideKind ? null : { dataset: { aside: realAsideKind } };
    }
    return null;
  },
  documentElement: { classList: makeClassList(), dataset: {} },
  body: { dataset: {} }
};
const windowStub = {
  setTimeout: fakeSetTimeout,
  clearTimeout: fakeClearTimeout,
  requestAnimationFrame: (cb) => fakeSetTimeout(cb, 16)
};

/* ---------- swup 插件的替身: 只记录自己是哪一个, 用来断言插件列表 ---------- */
const madePlugins = [];
const stubPlugin = (name) => class {
  constructor(options) {
    this.name = name;
    this.options = options;
    madePlugins.push(name);
  }
};
const SwupStubs = {
  head: stubPlugin('head'),
  preload: stubPlugin('preload'),
  progress: stubPlugin('progress'),
  scroll: stubPlugin('scroll')
};
const stubHeaderOffset = () => 68;
const stubReducedMotion = () => false;

/* ---------- 用真实源码建一个可调用的作用域 ---------- */
const factory = new Function(
  'window',
  'document',
  'SwupHeadPlugin',
  'SwupPreloadPlugin',
  'SwupProgressPlugin',
  'SwupScrollPlugin',
  'headerOffset',
  'prefersReducedMotion',
  `${code}
  return {
    scheduleSkeleton,
    showSkeleton,
    hideSkeleton,
    asideKindForSession,
    currentAsideKind,
    targetAsideKind,
    startContentEnter,
    cancelContentEnter,
    animConfig,
    readTimeVar,
    preloadEnabled,
    buildSwupPlugins,
    resetAnim: () => { animCache = null; },
    state: () => ({ skeletonVisible, skeletonTimer, skeletonHideTimer })
  };`
);
const api = factory(
  windowStub,
  documentStub,
  SwupStubs.head,
  SwupStubs.preload,
  SwupStubs.progress,
  SwupStubs.scroll,
  stubHeaderOffset,
  stubReducedMotion
);

/** 模拟一次「页面加载」: 换掉 <html> 上的动效配置并清掉缓存 */
function setAnimConfig({ level, skeleton, vars } = {}) {
  const root = documentStub.documentElement;
  root.dataset.anim = level;
  root.dataset.skeleton = skeleton;
  windowStub.getComputedStyle = vars
    ? () => ({ getPropertyValue: (name) => vars[name] || '' })
    : undefined;
  api.resetAnim();
}

/* ---------- 断言 ---------- */
let failures = 0;
const check = (label, ok, extra = '') => {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${extra ? '  ' + extra : ''}`);
  if (!ok) failures++;
};
const snapshot = () => ({
  hidden: overlay.hidden,
  visible: overlay.classList.contains('is-visible'),
  ...api.state()
});

/* 场景 1: 慢跳转 (骨架已显示), 且 swup 的 visit:end 落在淡出结束之前 */
reset();
api.scheduleSkeleton('list', 'none');     // visit:start
advance(150);                            // 130ms 后显示 + rAF(16ms) 加类
check('骨架已显示', overlay.hidden === false && overlay.classList.contains('is-visible'), JSON.stringify(snapshot()));

api.hideSkeleton();                      // content:replace: 收骨架
api.startContentEnter();                 // content:replace: 开始入场
advance(20);
check('淡出期间 is-visible 已移除', overlay.classList.contains('is-visible') === false, JSON.stringify(snapshot()));

advance(160);                            // ~180ms: visit:end 又调一次 hideSkeleton
api.hideSkeleton();
check('visit:end 时遮罩正在淡出 (hidden=false 是预期的)', overlay.hidden === false, JSON.stringify(snapshot()));

advance(400);                            // 等淡出定时器走完
check('★ 跳转结束后遮罩已真正隐藏', overlay.hidden === true, JSON.stringify(snapshot()));
check('★ 且不残留 is-visible', overlay.classList.contains('is-visible') === false, JSON.stringify(snapshot()));

/* 场景 1b: 显示后同一帧内就被收掉 (超快跳转), rAF 不许把类加回来 */
reset();
api.scheduleSkeleton('list', 'none');
advance(135);                            // 130ms: hidden=false, rAF 已排队
api.hideSkeleton();                      // 同一帧内 content:replace
advance(30);                             // rAF 到期 —— 不允许加回 is-visible
check('★ 同一帧内收掉后 rAF 不会把 is-visible 加回来', overlay.classList.contains('is-visible') === false, JSON.stringify(snapshot()));
advance(400);
check('★ 该路径最终仍是 hidden', overlay.hidden === true, JSON.stringify(snapshot()));

/* 场景 2: 极快跳转 (骨架没来得及显示) */
reset();
api.scheduleSkeleton('single', 'toc');
advance(60);
api.hideSkeleton();                      // content:replace
advance(80);
api.hideSkeleton();                      // visit:end
advance(500);
check('★ 快速跳转后遮罩保持隐藏', overlay.hidden === true && !overlay.classList.contains('is-visible'), JSON.stringify(snapshot()));

/* 场景 3: 连点两次 */
reset();
api.scheduleSkeleton('list', 'none');
advance(150);
api.scheduleSkeleton('single', 'toc');    // 第二次 visit:start
advance(150);
check('第二次跳转仍能显示骨架', overlay.hidden === false && overlay.classList.contains('is-visible'), JSON.stringify(snapshot()));
api.hideSkeleton();                      // content:replace
api.startContentEnter();
advance(180);
api.hideSkeleton();                      // visit:end
advance(700);                            // 覆盖 220ms 淡出 + 620ms 入场定时器
check('★ 连点后遮罩已真正隐藏', overlay.hidden === true, JSON.stringify(snapshot()));
check('连点后入场类已清掉', documentStub.documentElement.classList.contains('is-entering') === false);

/* 场景 4: 显示中的遮罩仍然拦截点击 (CSS 里只有 :not(.is-visible) 才 pointer-events:none) */
reset();
api.scheduleSkeleton('list', 'none');
advance(150);
check('显示中的遮罩保持可拦截 (is-visible 存在)', overlay.classList.contains('is-visible') === true);

/* ---------- 主题设置输出的动效配置 ---------- */

/* 场景 5: 没有任何配置 (老页面 / JS 跑在样式之前) 时用内置兜底值 */
setAnimConfig({});
check('5. 读不到配置时 level 兜底 normal', 'normal' === api.animConfig().level);
check('5. 读不到配置时骨架屏默认开启', api.animConfig().skeleton === true);
check('5. 读不到变量时入场窗口兜底 620ms', 620 === api.animConfig().enterWindow);
check('5. 读不到变量时骨架淡出兜底 220ms', 220 === api.animConfig().skeletonFade);

/* 场景 6: CSS 变量解析 (s / ms / 空值 / 垃圾值) */
setAnimConfig({ vars: { '--anim-window': '0.83s', '--anim-skeleton-fade': '300ms' } });
check('6. "0.83s" 解析为 830ms', 830 === api.animConfig().enterWindow);
check('6. "300ms" 解析为 300ms', 300 === api.animConfig().skeletonFade);
check('6. 空值回落到兜底', 620 === api.readTimeVar({ getPropertyValue: () => '' }, '--anim-window', 620));
check('6. 垃圾值回落到兜底', 620 === api.readTimeVar({ getPropertyValue: () => 'fast' }, '--anim-window', 620));

/* 场景 7: 「明显」档的窗口确实更长, 入场类在窗口结束时才被摘掉 */
setAnimConfig({ level: 'strong', skeleton: '1', vars: { '--anim-window': '830ms' } });
reset();
api.startContentEnter();
check('7. 入场类已挂上', documentStub.documentElement.classList.contains('is-entering'));
advance(700);
check('7. 700ms 时仍在入场窗口内 (没被提前摘掉)', documentStub.documentElement.classList.contains('is-entering'));
advance(200);
check('7. 830ms 后被摘掉', documentStub.documentElement.classList.contains('is-entering') === false);

/* 场景 8: 「关闭」档 — 不加入场类, 也不排定时器 */
setAnimConfig({ level: 'off', skeleton: '1', vars: { '--anim-window': '0ms' } });
reset();
api.startContentEnter();
check('8. ★ 关闭档不挂 is-entering', documentStub.documentElement.classList.contains('is-entering') === false);
advance(1000);
check('8. 关闭档之后也不会冒出来', documentStub.documentElement.classList.contains('is-entering') === false);

/* 场景 9: 关掉骨架屏 — 等待期间不显示任何占位 */
setAnimConfig({ level: 'normal', skeleton: '0', vars: { '--anim-window': '620ms' } });
reset();
api.scheduleSkeleton('list', 'none');
advance(600);
check('9. ★ 关掉骨架屏后不显示遮罩', overlay.hidden === true && overlay.classList.contains('is-visible') === false, JSON.stringify(snapshot()));
api.hideSkeleton();
advance(400);
check('9. 关掉骨架屏后遮罩始终隐藏', overlay.hidden === true, JSON.stringify(snapshot()));

/* 场景 10: 重新打开骨架屏后一切照旧 (配置是按次读取的) */
setAnimConfig({ level: 'normal', skeleton: '1', vars: { '--anim-skeleton-fade': '120ms' } });
reset();
api.scheduleSkeleton('list', 'none');
advance(150);
check('10. 重新开启后骨架正常显示', overlay.hidden === false && overlay.classList.contains('is-visible'));
api.hideSkeleton();
advance(200);                            // 淡出 120ms (变量值) 已过
check('10. 淡出时长跟随 --anim-skeleton-fade (120ms) 收干净', overlay.hidden === true, JSON.stringify(snapshot()));

/* ---------- 场景 12: 骨架屏里的第二列 (侧边栏 / 文章目录) ----------
   第二列常常与当前页无关 (列表页之间互切时侧边栏一个字都不变), 这时不该出现骨架。
   判定要记住一个硬约束: swup 到 content:replace 才解析目标文档, 所以 visit:start
   里 `visit.to.document` 是 undefined —— 早期版本读它, 结果每次都判成「会换」,
   于是首页 → /me.html 这种列表页互切也会闪一块侧边栏骨架。

   线上后来又报了一例: 首页 → 没有目录的文章 (/archives/76/) 时, 第二列同样是侧边栏、
   一个字都不变, 但当时「目标是文章页 → 一律按目录画」, 目录骨架盖住了侧边栏, 遮罩
   淡出再露回来 —— 看起来就是侧边栏刷新了一下。现在目标形态由卡片链接上的
   data-aside-target 给出 (post-card.php), 拿不到提示又可能不变时按 keep 处理。 */
setAnimConfig({ level: 'normal', skeleton: '1', vars: { '--anim-skeleton-fade': '220ms' } });

/* 当前页约定为「列表页 + 侧边栏」(最常见的起点): 形态由 DOM 里那一列决定,
   遮罩上的 data-aside-kind 只是服务端渲染时的记录, 一并写上以贴近真实页面 */
const setCurrentPageAside = (kind) => {
  realAsideKind = kind;
  overlay.dataset.asideKind = kind;
};

/** 带链接形态提示的 visit 替身 (真实页面里是卡片链接上的 data-aside-target) */
const hinted = (kind) => ({ trigger: { el: { closest: () => ({ getAttribute: () => kind }) } } });

/* 12a: 列表页 → 列表页: 一定不换 */
setCurrentPageAside('sidebar');
check('12a. ★ 目标是列表页 → 判定不画第二列 (首页 → /me.html 这类互切)', api.asideKindForSession('list') === 'keep');
reset();
api.scheduleSkeleton('list', api.asideKindForSession('list'));
advance(150);
check('12a. 骨架显示但第二列不出现', overlay.hidden === false && asideSkeleton.hidden === true, JSON.stringify({ overlay: overlay.hidden, aside: asideShownKind() }));
api.hideSkeleton();
advance(300);

/* 12b: 列表页 → 文章页 (拿不到提示): 那篇文章可能没有目录, 第二列有可能不变 → 保守不画 */
check('12b. ★ 列表页 → 文章页 (无提示): 判定不画第二列 (可能一个字都不变)', api.asideKindForSession('single') === 'keep');
reset();
api.scheduleSkeleton('single', api.asideKindForSession('single'));
advance(150);
check('12b. ★ 保守档下不画第二列骨架, 并把真实那一列托到遮罩之上',
  asideSkeleton.hidden === true && documentStub.documentElement.classList.contains('is-aside-kept'),
  JSON.stringify({ kind: asideShownKind(), kept: documentStub.documentElement.classList.contains('is-aside-kept') }));
api.hideSkeleton();
advance(300);

/* 12b-2: 链接给了提示就用提示 —— 目标那篇有目录 → 画目录骨架 (它确实会换) */
check('12b-2. ★ 提示 toc + 当前是侧边栏 → 画目录骨架', api.asideKindForSession('single', hinted('toc')) === 'toc');
reset();
api.scheduleSkeleton('single', api.asideKindForSession('single', hinted('toc')));
advance(150);
check('12b-2. 画的是目录形态', asideShownKind() === 'toc' && variantOf('toc').hidden === false && variantOf('sidebar').hidden === true, JSON.stringify({ kind: asideShownKind() }));
api.hideSkeleton();
advance(300);
check('12b-2. 收起后第二列骨架也一起收回', asideSkeleton.hidden === true);

/* 12b-3: 提示说目标那篇没有目录 → 与当前页一致, 一个字都不会变 → 不画也不动 */
check('12b-3. ★ 提示 sidebar + 当前是侧边栏 → 不画 (线上首页 → /archives/76/ 就是这一档)',
  api.asideKindForSession('single', hinted('sidebar')) === 'keep');
check('12b-3. 提示里的其它值一律当没有提示', api.asideKindForSession('single', hinted('whatever')) === 'keep');

/* 12c: 文章页 (有目录) → 文章页: 同样按目录画 (每篇目录都不同) */
setCurrentPageAside('toc');
check('12c. ★ 文章页 → 文章页: 判定画目录骨架', api.asideKindForSession('single') === 'toc');
check('12c. ★ 文章页 (有目录) → 列表页: 画侧边栏骨架 (目录要让位)', api.asideKindForSession('list') === 'sidebar');
check('12c. ★ 提示说目标那篇没有目录: 目录照样要让位给侧边栏', api.asideKindForSession('single', hinted('sidebar')) === 'sidebar');

/* 12d: 当前页没有第二列时, 目标页有什么都会是新出现的 */
setCurrentPageAside('none');
check('12d. ★ 没有第二列 → 列表页: 画侧边栏骨架', api.asideKindForSession('list') === 'sidebar');
check('12d. ★ 没有第二列 → 文章页: 画目录骨架', api.asideKindForSession('single') === 'toc');
check('12d. currentAsideKind() 读的是 DOM (没有那一列就是 none)', api.currentAsideKind() === 'none');
setCurrentPageAside('sidebar');
check('12d. currentAsideKind() 跟着 DOM 走 (不读遮罩上那份首屏记录)', api.currentAsideKind() === 'sidebar');
check('12d. ★ 没有任何提示时 targetAsideKind() 返回 null',
  api.targetAsideKind({ trigger: { el: { closest: () => null } } }) === null && api.targetAsideKind(undefined) === null);
check('12d. 链接上的提示能读出来', api.targetAsideKind(hinted('toc')) === 'toc' && api.targetAsideKind(hinted('sidebar')) === 'sidebar');

/* 12e: 连着两次跳转, 上一次的显隐状态不许带到下一次 */
reset();
api.scheduleSkeleton('single', 'toc');
advance(150);
check('12e. 第一次 (会换) 显示了第二列', asideShownKind() === 'toc');
api.hideSkeleton();
advance(300);
/* 从「有目录的文章页」切到列表页: 判定要画侧边栏骨架, 这里验的是「下一次不画时必须收起」 */
setCurrentPageAside('toc');
api.scheduleSkeleton('list', api.asideKindForSession('list'));
advance(150);
check('12e. 目录 → 列表页: 画的是侧边栏形态', asideShownKind() === 'sidebar' && variantOf('sidebar').hidden === false, JSON.stringify({ kind: asideShownKind() }));
api.hideSkeleton();
advance(300);
setCurrentPageAside('sidebar');
api.scheduleSkeleton('list', api.asideKindForSession('list'));
advance(150);
check('12e. ★ 紧接着「不换」的那次跳转里第二列必须收起来', overlay.hidden === false && asideSkeleton.hidden === true, JSON.stringify({ overlay: overlay.hidden, aside: asideShownKind() }));
api.hideSkeleton();
advance(300);

/* 12f: 骨架屏关掉时, 第二列也不该留下 */
setAnimConfig({ level: 'normal', skeleton: '0', vars: { '--anim-skeleton-fade': '220ms' } });
reset();
api.scheduleSkeleton('single', 'toc');
advance(600);
check('12f. ★ 关掉骨架屏后第二列骨架不会冒出来', overlay.hidden === true && asideSkeleton.hidden === true, JSON.stringify({ overlay: overlay.hidden, aside: asideShownKind() }));
api.hideSkeleton();
advance(400);
check('12f. 关掉骨架屏后第二列始终隐藏', asideSkeleton.hidden === true);
setAnimConfig({ level: 'normal', skeleton: '1', vars: { '--anim-skeleton-fade': '220ms' } });

/* ---------- 场景 13: 不画第二列时, 真实的那一列要被托到遮罩之上 ----------
   遮罩是不透明的固定层, 铺满导航之下的整个视口。侧边栏在「列表页 → 列表页」时一个字
   都不会变, 骨架屏里也就不画它 —— 但遮罩照样会把它盖成一块空白, 等遮罩淡出再露回来,
   看起来就是侧边栏闪了一下 (线上就是这么报的)。所以这一列要挂 is-aside-kept 浮上去,
   并且一直浮到遮罩真正隐藏为止 (淡出那 200ms 遮罩还在画面上)。 */
const keptFlag = () => documentStub.documentElement.classList.contains('is-aside-kept');

{
  setCurrentPageAside('sidebar');
  reset();
  api.scheduleSkeleton('list', api.asideKindForSession('list'));   // → keep: 不画第二列
  advance(150);
  check('13a. ★ 不画第二列时挂上 is-aside-kept', keptFlag() && overlay.hidden === false, JSON.stringify({ kept: keptFlag(), overlay: overlay.hidden, aside: asideShownKind() }));

  api.hideSkeleton();
  advance(30);
  check('13a. ★ 淡出期间仍然浮着 (遮罩还在画面上)', keptFlag(), JSON.stringify(snapshot()));

  advance(300);
  check('13a. ★ 遮罩真正隐藏后才摘掉', overlay.hidden === true && keptFlag() === false, JSON.stringify(snapshot()));
}

{
  /* 会换第二列 (目录 → 侧边栏) 时不挂: 那一列要被骨架遮住, 不能浮上来 */
  setCurrentPageAside('toc');
  reset();
  api.scheduleSkeleton('list', api.asideKindForSession('list'));
  advance(150);
  check('13b. ★ 画第二列骨架时不挂 is-aside-kept', keptFlag() === false && asideShownKind() === 'sidebar', JSON.stringify({ kept: keptFlag(), aside: asideShownKind() }));
  api.hideSkeleton();
  advance(300);
}

{
  /* 当前页没有第二列 (404): 没有东西可托, 不许挂 */
  setCurrentPageAside('none');
  reset();
  api.scheduleSkeleton('list', api.asideKindForSession('list'));
  advance(150);
  check('13c. ★ 当前页没有第二列时不挂 is-aside-kept', keptFlag() === false && overlay.hidden === false, JSON.stringify({ kept: keptFlag(), overlay: overlay.hidden }));
  api.hideSkeleton();
  advance(300);
  check('13c. 收尾后依旧没有残留', keptFlag() === false);
  setCurrentPageAside('sidebar');
}

{
  /* 极快跳转: 骨架根本没显示过, 不许留下 is-aside-kept */
  reset();
  api.scheduleSkeleton('list', 'none');
  advance(60);
  api.hideSkeleton();
  advance(500);
  check('13d. ★ 骨架没显示过时不留 is-aside-kept', keptFlag() === false && overlay.hidden === true, JSON.stringify(snapshot()));
}

/* ---------- 场景 11: 链接预取开关 (主题设置 → <html data-preload> → 插件列表) ---------- */
{
  const names = () => api.buildSwupPlugins().map((plugin) => plugin.name);

  /* 读不到属性 (老页面 / 脚本跑在 <html> 之前) 时默认开启, 与主题默认值一致 */
  delete documentStub.documentElement.dataset.preload;
  check('11. 读不到配置时预取默认开启', api.preloadEnabled() === true);
  check('11. ★ 默认插件列表 = head → 预取 → 进度条 → 滚动', JSON.stringify(names()) === JSON.stringify(['head', 'preload', 'progress', 'scroll']), names().join(' → '));

  documentStub.documentElement.dataset.preload = '1';
  check('11. data-preload="1" 时开启', api.preloadEnabled() === true);
  check('11. ★ 显式开启时仍然注册预取插件', names().includes('preload'));

  documentStub.documentElement.dataset.preload = '0';
  check('11. ★ data-preload="0" 时关闭', api.preloadEnabled() === false);
  check('11. ★ 关闭后不再注册预取插件', JSON.stringify(names()) === JSON.stringify(['head', 'progress', 'scroll']), names().join(' → '));

  /* 其它插件不受影响, 且预取插件的参数仍是悬停预载 */
  documentStub.documentElement.dataset.preload = '1';
  const preload = api.buildSwupPlugins().find((plugin) => 'preload' === plugin.name);
  check('11. 预取插件仍配置为悬停预载', preload.options.preloadHoveredLinks === true && preload.options.throttle === 4, JSON.stringify(preload.options));
  delete documentStub.documentElement.dataset.preload;
}

console.log(`\n${failures === 0 ? '全部通过' : failures + ' 项失败'}`);
process.exit(failures === 0 ? 0 : 1);
