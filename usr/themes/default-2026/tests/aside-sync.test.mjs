/**
 * 第二列 (侧边栏 / 文章目录) 同步测试 (可选: 需要 jsdom)
 *
 * 运行: npm run test:dom   (未装 jsdom 时打印 SKIP 并退出 0)
 *
 * 背景: 这一列是 #main 的兄弟节点, 不在 swup 的容器列表里, 所以切页时整块不更新 ——
 * 从列表页进文章看不到目录 (必须 F5), 从文章回列表页又留着上一页的目录。
 * syncPageAside 负责整列同步, 并且要满足两条相反的要求:
 *
 *   - 侧边栏: 内容与当前页面无关 (六个区块都是全站数据, 实测首页与文章页的
 *     侧边栏 HTML 逐字节相同), 所以「侧边栏 → 侧边栏」不能重建, 否则整列重绘、
 *     hover / 焦点状态丢失, 看起来就是侧边栏在闪;
 *   - 目录: 每篇文章的目录都不一样, 所以「目录 → 目录」必须替换。
 *
 * 另外守住: .page-aside 唯一, 以及目录那版 aside 带 lg:self-stretch
 * (没有它, #page-wrap 的 lg:items-start 会让里面的 lg:sticky 无处可滚, 目录会滚走)。
 */
import { readFileSync } from 'node:fs';

let JSDOM;
try {
  ({ JSDOM } = await import('jsdom'));
} catch (error) {
  console.log('SKIP  未安装 jsdom, 跳过第二列同步测试');
  console.log('      安装: npm i -D jsdom   然后: npm run test:dom');
  process.exit(0);
}

const source = readFileSync(new URL('../src/js/app.js', import.meta.url), 'utf8');
/* 与骨架屏测试同一段: 里面既有 syncPageAside, 也有 startContentEnter /
   cancelContentEnter (第二列的入场标记与清理逻辑都依赖它们) */
const start = source.indexOf('const SKELETON_DELAY');
const end = source.indexOf('/** 同一个页面里的锚点跳转不会真正换页');
if (start < 0 || end < 0 || end <= start) {
  throw new Error('无法从 app.js 中定位骨架屏/第二列代码段 (锚点注释被改动了?)');
}
const code = source.slice(start, end);
console.log(`已从 app.js 抽取骨架屏与第二列逻辑: ${code.split('\n').length} 行\n`);

const SHELL = (aside) => `<!doctype html><html lang="zh"><head><title>t</title></head><body>
<div id="page-wrap" class="mx-auto w-full max-w-7xl lg:grid lg:grid-cols-[minmax(0,1fr)_15rem] lg:items-start lg:gap-10">
<main id="main" class="min-w-0 transition-fade">正文</main>
${aside}
</div></body></html>`;

/* 标记与真实模板一致 (sidebar.php / toc.php) */
const ASIDE_SIDEBAR = '<aside id="sidebar" class="page-aside mt-10 min-w-0 space-y-6 lg:mt-0" data-aside="sidebar"><section class="card p-5">最新文章</section></aside>';
const tocAside = (id, title) => '<aside class="page-aside mt-10 min-w-0 lg:mt-0 lg:self-stretch" data-aside="toc" aria-label="文章目录">'
  + `<div id="toc" class="card no-print p-5 lg:sticky lg:top-20 lg:max-h-[calc(100dvh-6rem)] lg:overflow-y-auto"><nav class="js-toc">`
  + `<a class="toc-link" href="#${id}">${title}</a></nav></div></aside>`;

const ASIDE_TOC_A = tocAside('alpha', '第一章');
const ASIDE_TOC_B = tocAside('omega', '另一篇的目录');
const ASIDE_NONE = '';

const LIST_A = SHELL(ASIDE_SIDEBAR);
const LIST_B = SHELL(ASIDE_SIDEBAR);
const POST_A = SHELL(ASIDE_TOC_A);
const POST_B = SHELL(ASIDE_TOC_B);
const NOT_FOUND = SHELL(ASIDE_NONE);

let failures = 0;
const check = (label, ok, extra = '') => {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${extra ? '  ' + extra : ''}`);
  if (!ok) failures++;
};

const newDom = (html) => new JSDOM(html, { runScripts: 'dangerously', pretendToBeVisual: true });

/**
 * 模拟一次 swup 切页, 顺序与 app.js 的 content:replace 钩子一致:
 * 收骨架 → 开始入场 (is-entering) → 同步第二列 (可能加 is-aside-swapped) → 重放脚本
 */
function makeNavigator(dom) {
  const doc = dom.window.document;
  const api = new Function('window', 'document', `${code}\nreturn { deferContainerScripts, runDeferredScripts, syncPageAside, startContentEnter, cancelContentEnter };`)(dom.window, doc);

  const navigate = (incomingHtml) => {
    const incoming = new dom.window.DOMParser().parseFromString(incomingHtml, 'text/html');
    const visit = { to: { document: incoming }, containers: ['#main', '#site-nav'] };
    api.deferContainerScripts(visit);
    doc.querySelector('#main').replaceWith(incoming.querySelector('#main').cloneNode(true));
    api.startContentEnter();
    api.syncPageAside(visit);
    api.runDeferredScripts();

    return doc;
  };

  return { navigate, api, doc };
}

/* ---------- 1. 列表页 → 文章页: 目录要出现, 并且参与入场动画 ---------- */
{
  const { navigate, doc } = makeNavigator(newDom(LIST_A));
  navigate(POST_A);

  check('1. 目录已出现 (#toc)', !!doc.querySelector('#toc'));
  check('1. 侧边栏已让位 (没有 #sidebar)', !doc.querySelector('#sidebar'));
  check('1. .page-aside 唯一', doc.querySelectorAll('.page-aside').length === 1);
  check('1. 目录那一列带 lg:self-stretch (sticky 生效的前提)', doc.querySelector('.page-aside').classList.contains('lg:self-stretch'));
  check('1. 位置仍在 #main 之后', doc.querySelector('#main').nextElementSibling === doc.querySelector('.page-aside'));
  check('1. ★ 真的换了内容 → 挂上 is-aside-swapped (CSS 据此播入场)', doc.documentElement.classList.contains('is-aside-swapped'));
  check('1. ★ 同时有 is-entering, 两者齐备才会播动画', doc.documentElement.classList.contains('is-entering'));
}

/* ---------- 2. 文章页 → 列表页: 目录必须被换回侧边栏 ---------- */
{
  const { navigate, doc } = makeNavigator(newDom(POST_A));
  navigate(LIST_A);

  check('2. 回到列表页后没有残留目录', !doc.querySelector('#toc'));
  check('2. 侧边栏已恢复', !!doc.querySelector('#sidebar'));
  check('2. .page-aside 唯一', doc.querySelectorAll('.page-aside').length === 1);
  check('2. ★ 目录 → 侧边栏同样算「换了」, 要播入场', doc.documentElement.classList.contains('is-aside-swapped'));
}

/* ---------- 3. 列表页 → 列表页: 侧边栏不许重建, 也不许播动效 ---------- */
{
  const dom = newDom(LIST_A);
  const doc = dom.window.document;
  const original = doc.querySelector('.page-aside');
  original.dataset.probe = 'kept';

  const { navigate, api } = makeNavigator(dom);
  navigate(LIST_B);
  const afterFirst = doc.querySelector('.page-aside');
  check('3. ★ 侧边栏节点没有被替换 (还是同一个节点)', afterFirst === original);
  check('3. ★ 自定义标记仍在 (证明确实没重建)', afterFirst.dataset.probe === 'kept');
  check('3. .page-aside 唯一', doc.querySelectorAll('.page-aside').length === 1);
  check('3. ★ 侧边栏没变 → 不挂 is-aside-swapped (不给它播动效)', doc.documentElement.classList.contains('is-aside-swapped') === false);

  navigate(LIST_B);
  check('3. ★ 再切一次仍然没有重建', doc.querySelector('.page-aside') === original);
  check('3. ★ 仍然不挂 is-aside-swapped', doc.documentElement.classList.contains('is-aside-swapped') === false);

  api.cancelContentEnter();
  check('3. 入场结束时两个标记一起清掉', doc.documentElement.classList.contains('is-entering') === false && doc.documentElement.classList.contains('is-aside-swapped') === false);
}

/* ---------- 4. 目录 → 目录: 必须重建 (每篇目录不同), 也要播入场 ---------- */
{
  const dom = newDom(POST_A);
  const doc = dom.window.document;
  const before = doc.querySelector('.page-aside');

  const { navigate } = makeNavigator(dom);
  navigate(POST_B);
  const after = doc.querySelector('.page-aside');

  check('4. ★ 目录被替换了 (节点已换)', after !== before);
  check('4. ★ 新目录内容是后一篇文章的', !!doc.querySelector('a[href="#omega"]') && !doc.querySelector('a[href="#alpha"]'));
  check('4. .page-aside 唯一', doc.querySelectorAll('.page-aside').length === 1);
  check('4. ★ 目录每次都算「换了」, 要播入场', doc.documentElement.classList.contains('is-aside-swapped'));
}

/* ---------- 5. 目标页没有第二列 (404): 要移除 ---------- */
{
  const { navigate, doc } = makeNavigator(newDom(LIST_A));
  navigate(NOT_FOUND);

  check('5. 404 页面上第二列被移除', doc.querySelectorAll('.page-aside').length === 0);
  check('5. #main 仍在', !!doc.querySelector('#main'));
  check('5. 移除路径不挂 is-aside-swapped (没有新节点可播)', doc.documentElement.classList.contains('is-aside-swapped') === false);
}

/* ---------- 6. 当前页没有第二列 → 目标页有: 插到 #main 之后 ---------- */
{
  const { navigate, doc } = makeNavigator(newDom(NOT_FOUND));
  navigate(POST_A);

  check('6. 目录被插进来了', !!doc.querySelector('#toc'));
  check('6. 插在 #main 之后 (与模板结构一致)', doc.querySelector('#main').nextElementSibling === doc.querySelector('.page-aside'));
  check('6. .page-aside 唯一', doc.querySelectorAll('.page-aside').length === 1);
  check('6. ★ 插入也算「换了」, 要播入场', doc.documentElement.classList.contains('is-aside-swapped'));
}

/* ---------- 7. 连续切页 ---------- */
{
  const dom = newDom(LIST_A);
  const { navigate, doc } = makeNavigator(dom);
  const sidebarNode = doc.querySelector('.page-aside');

  navigate(POST_A);
  check('7. 切到目录页', !!doc.querySelector('#toc') && !doc.querySelector('#sidebar'));
  navigate(LIST_A);
  check('7. 切回列表页: 侧边栏重新出现', !!doc.querySelector('#sidebar'));
  const restored = doc.querySelector('.page-aside');
  navigate(LIST_B);
  check('7. 再切列表页: 不再重建', doc.querySelector('.page-aside') === restored && restored !== sidebarNode);
  navigate(NOT_FOUND);
  check('7. 最后切到 404 后整列清空', doc.querySelectorAll('.page-aside').length === 0);
}

/* ---------- 8. 第二列里的脚本: 也要重放一次 ---------- */
{
  const tocWithScript = '<aside class="page-aside mt-10 min-w-0 lg:mt-0 lg:self-stretch" data-aside="toc">'
    + '<div id="toc"><p-c>abc</p-c>'
    + '<script>(function(){var e=document.querySelectorAll("#toc p-c");e.forEach(function(el){var s=document.createElement("span");s.textContent="["+el.textContent+"]";el.textContent="";el.appendChild(s)})})();</script>'
    + '</div></aside>';

  const { navigate, doc } = makeNavigator(newDom(LIST_A));
  navigate(SHELL(tocWithScript));

  check('8. 第二列里的脚本被重放 (span 已生成)', doc.querySelectorAll('#toc p-c span').length === 1, `spans=${doc.querySelectorAll('#toc p-c span').length}`);
  check('8. 没有残留标记节点', doc.querySelectorAll('[data-swup-script]').length === 0);
}

/* ---------- 9. 动效「关闭」档: 换了内容也不播动画 ---------- */
{
  const dom = newDom(LIST_A);
  const doc = dom.window.document;
  /* 模拟主题设置里的动效强度 = 关闭 (header.php 输出的 data-anim) */
  doc.documentElement.dataset.anim = 'off';

  const { navigate } = makeNavigator(dom);
  navigate(POST_A);

  check('9. ★ 关闭档: 不挂 is-entering', doc.documentElement.classList.contains('is-entering') === false);
  check('9. ★ 关闭档: 内容照样换成目录', !!doc.querySelector('#toc') && !doc.querySelector('#sidebar'));
}

console.log(`\n${failures === 0 ? '全部通过' : failures + ' 项失败'}`);
process.exit(failures === 0 ? 0 : 1);
