/**
 * 正文内联脚本重放测试 (可选: 需要 jsdom)
 *
 * 运行: cd usr/themes/default-2026 && npm i -D jsdom && npm run test:dom
 *
 * 背景: Swup 用 incomingEl.cloneNode(true) 插入新容器, 而从 DOMParser 文档克隆出来的
 * <script> 带着 "already started" 标记 (HTML 规范: script 的克隆步骤会置位它),
 * 浏览器永远不会执行 —— 正文里手写的 <script> 于是只有 F5 才生效。
 *
 * A 段复现这个行为 (不执行), B 段验证 deferContainerScripts + runDeferredScripts
 * 之后它恰好执行一次, C 段验证 data-swup-skip 退出机制, D 段验证外链脚本顺序。
 *
 * 这个测试需要真实 DOM, 所以依赖 jsdom; 没装时只打印 SKIP 并退出 0,
 * 避免给主题强加一个体积不小的依赖 (npm test 里的状态机测试不依赖任何包)。
 */
import { readFileSync } from 'node:fs';

let JSDOM;
try {
  ({ JSDOM } = await import('jsdom'));
} catch (error) {
  console.log('SKIP  未安装 jsdom, 跳过正文脚本测试');
  console.log('      安装: npm i -D jsdom   然后: npm run test:dom');
  process.exit(0);
}

const source = readFileSync(new URL('../src/js/app.js', import.meta.url), 'utf8');
const start = source.indexOf('/** 缴械后临时使用的 type');
const end = source.indexOf('/** 同一个页面里的锚点跳转不会真正换页');
if (start < 0 || end < 0 || end <= start) {
  throw new Error('无法从 app.js 中定位脚本重放代码段 (锚点注释被改动了?)');
}
const code = source.slice(start, end);
/* 第二列的标记常量定义在这一段之前 (骨架屏与切页同步共用), 抽出来补在最前面 */
const asideMarkers = /\n\/\*\* 第二列[\s\S]*?const ASIDE_SWAPPED_CLASS = [^\n]+\n/.exec(source);
if (!asideMarkers) {
  throw new Error('app.js 里找不到第二列标记常量 (ASIDE_SELECTOR / ASIDE_SWAPPED_CLASS)');
}
const scope = `${asideMarkers[0]}${code}`;
console.log(`已从 app.js 抽取脚本重放逻辑: ${code.split('\n').length} 行\n`);

/* 正文里手写 <script> / 自定义标签的最小复现 (结构同真实文章正文) */
const USER_CONTENT = `<p-c>关于我，四折光曲，井水玉藻</p-c>
<p-c>显而易见的，我是一个极其普通的普通人</p-c>
<p-c>For My Love</p-c>
<script>(function(){var e=document.querySelectorAll("p-c");if(!e.length)return;var t=0;e.forEach(function(e){t+=e.textContent.length});var n=0;e.forEach(function(e){var r=document.createTreeWalker(e,NodeFilter.SHOW_TEXT,null,!1),o=[];while(r.nextNode())o.push(r.currentNode);o.forEach(function(e){for(var r=e.nodeValue,o=document.createDocumentFragment(),l=0;l<r.length;l++){var a=r[l],i=document.createElement("span");i.textContent=a,i.style.color="hsl("+n/t*360+",65%,45%)",i.style.fontSize="1.12em",o.appendChild(i),n++}e.parentNode.replaceChild(o,e)}),e.style.display="block"})})();</script>`;

const CURRENT = '<!doctype html><html lang="zh"><head><title>旧页</title></head><body><div id="page-wrap"><main id="main" class="min-w-0 transition-fade"><h1>旧文章</h1></main><aside id="sidebar"></aside></div></body></html>';
const INCOMING = `<!doctype html><html lang="zh"><head><title>新页</title><script type="text/javascript">window.__headRuns=(window.__headRuns||0)+1;</script></head><body><div id="page-wrap"><main id="main" class="min-w-0 transition-fade">${USER_CONTENT}</main><aside id="sidebar"></aside></div></body></html>`;

let failures = 0;
const check = (label, ok, extra = '') => {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${extra ? '  ' + extra : ''}`);
  if (!ok) failures++;
};

const newDom = () => new JSDOM(CURRENT, { runScripts: 'dangerously', pretendToBeVisual: true });
const withApi = (doc) => new Function('document', `${scope}\nreturn { deferContainerScripts, runDeferredScripts };`)(doc);
const expectedSpans = (incomingDoc) =>
  Array.from(incomingDoc.querySelectorAll('p-c')).reduce((n, el) => n + el.textContent.length, 0);

/* ---------- A. 复现: swup 原本的插入方式不会执行正文脚本 ---------- */
{
  const dom = newDom();
  const doc = dom.window.document;
  const incoming = new dom.window.DOMParser().parseFromString(INCOMING, 'text/html');
  doc.querySelector('#main').replaceWith(incoming.querySelector('#main').cloneNode(true));

  check('A. swup 的 cloneNode(true) 路径下正文脚本不执行', doc.querySelectorAll('p-c span').length === 0);
  check('A. <p-c> 元素本身已就位 (只差脚本没跑)', doc.querySelectorAll('p-c').length === 3);
}

/* ---------- B. 修复路径: 缴械 → 替换 → 重放 ---------- */
{
  const dom = newDom();
  const doc = dom.window.document;
  const incoming = new dom.window.DOMParser().parseFromString(INCOMING, 'text/html');
  const expected = expectedSpans(incoming);
  const headType = incoming.querySelector('head script').getAttribute('type');
  const api = withApi(doc);

  const visit = { to: { document: incoming }, containers: ['#main', '#site-nav'] };
  api.deferContainerScripts(visit);

  const placeholder = incoming.querySelector('#main script');
  check(
    'B. 缴械: 容器内脚本换成不可执行类型并记录原 type',
    placeholder.getAttribute('type') === 'text/swup-deferred'
      && placeholder.getAttribute('data-swup-script') === ''
  );
  check('B. 缴械不碰 head 里的脚本', incoming.querySelector('head script').getAttribute('type') === headType);

  doc.querySelector('#main').replaceWith(incoming.querySelector('#main').cloneNode(true));
  api.runDeferredScripts();

  check('B. ★ 修复后正文脚本执行了 (每个字符一个 span)', doc.querySelectorAll('p-c span').length === expected, `spans=${doc.querySelectorAll('p-c span').length} expected=${expected}`);
  check('B. 标记节点已换成真 <script>, 无残余', doc.querySelectorAll('script[data-swup-script]').length === 0);
  check('B. 脚本节点仍在 #main 内 (与 F5 结构一致)', doc.querySelectorAll('#main script').length === 1);

  api.runDeferredScripts();
  check('B. 重复调用不会二次执行', doc.querySelectorAll('p-c span').length === expected);

  const incoming2 = new dom.window.DOMParser().parseFromString(INCOMING, 'text/html');
  api.deferContainerScripts({ to: { document: incoming2 }, containers: ['#main'] });
  doc.querySelector('#main').replaceWith(incoming2.querySelector('#main').cloneNode(true));
  api.runDeferredScripts();
  check('B. 连续两次切页后不会叠加执行', doc.querySelectorAll('p-c span').length === expected);
}

/* ---------- C. data-swup-skip 退出机制 ---------- */
{
  const dom = newDom();
  const doc = dom.window.document;
  const html = '<!doctype html><html><head></head><body><main id="main"><p-c>abc</p-c>'
    + '<script data-swup-skip>(function(){var e=document.querySelectorAll("p-c");e.forEach(function(el){el.innerHTML="<span>x</span>"})})();</script>'
    + '</main></body></html>';
  const incoming = new dom.window.DOMParser().parseFromString(html, 'text/html');
  const api = withApi(doc);

  api.deferContainerScripts({ to: { document: incoming }, containers: ['#main'] });
  doc.querySelector('#main').replaceWith(incoming.querySelector('#main').cloneNode(true));
  api.runDeferredScripts();

  check('C. data-swup-skip 的脚本保持不执行', doc.querySelectorAll('p-c span').length === 0);
  check('C. 它的 type 也没被改写', !doc.querySelector('#main script').getAttribute('type'));
}

/* ---------- D. 外链脚本的执行顺序 ---------- */
{
  const dom = newDom();
  const doc = dom.window.document;
  const html = '<!doctype html><html><head></head><body><main id="main">'
    + '<script src="/a.js"></script>'
    + '<script src="/b.js" async></script>'
    + '<script src="/c.js" defer></script>'
    + '<script type="module" src="/d.js"></script>'
    + '</main></body></html>';
  const incoming = new dom.window.DOMParser().parseFromString(html, 'text/html');
  const api = withApi(doc);

  api.deferContainerScripts({ to: { document: incoming }, containers: ['#main'] });
  doc.querySelector('#main').replaceWith(incoming.querySelector('#main').cloneNode(true));
  api.runDeferredScripts();

  const s = (src) => doc.querySelector(`#main script[src="${src}"]`);
  check('D. 普通外链脚本强制按插入顺序 (async=false)', s('/a.js').async === false);
  check('D. 显式 async 的尊重作者', s('/b.js').hasAttribute('async'));
  check('D. defer 脚本也纳入顺序执行', s('/c.js').async === false);
  check('D. type=module 保持 module', s('/d.js').getAttribute('type') === 'module');
  check('D. 四个外链脚本都在', doc.querySelectorAll('#main script[src]').length === 4);
}

console.log(`\n${failures === 0 ? '全部通过' : failures + ' 项失败'}`);
process.exit(failures === 0 ? 0 : 1);
