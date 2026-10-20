/**
 * 占位缩略图生成器回归测试
 *
 * 运行: npm test  (在主题目录下, 只依赖 Node, 不需要装任何包)
 *
 * 守四条容易静默坏掉的约定:
 *   1. 确定性 —— 同一 seed 必须产出逐字符相同的 SVG。种子就是文章 id, 一旦同一篇文章
 *      在首页和搜索结果里长得不一样, 读者会以为那是两张不同的配图;
 *   2. 区分度 —— 不同 id 不能撞成同一张图 (色相全由 hash 派生);
 *   3. 可嵌入 —— data URI 里 # 必须编码成 %23, 否则 # 之后会被当成片段标识符, 图直接断掉;
 *   4. 填充行为 —— hydrateThumbs() 只认带种子的空 img, 填完解 hidden, 种子为空时保持隐藏。
 *
 * 只测纯逻辑: 不加载 DOM, 需要 DOM 的地方用最小桩对象 (root 由参数传入)。
 */
import { hydrateThumbs, placeholderDataUri, placeholderSvg } from '../src/js/thumb-placeholder.js';

let pass = 0;
const failures = [];

/**
 * 记一次断言
 *
 * @param {string} label 断言描述
 * @param {boolean} ok 结果
 * @param {string} [extra] 失败时附带的实际值
 */
function check(label, ok, extra = '') {
  if (ok) {
    pass += 1;
    console.log(`PASS  ${label}`);
    return;
  }

  failures.push(label);
  console.log(`FAIL  ${label}${extra ? `  ${extra}` : ''}`);
}

const PREFIX = 'data:image/svg+xml;charset=utf-8,';

/* ---------- 1: 形态 ---------- */
{
  const svg = placeholderSvg('42');

  check('1. 以 <svg 开头、以 </svg> 结尾', svg.startsWith('<svg ') && svg.endsWith('</svg>'));
  check('1. 画布是 400×300 (与卡片 4:3 的取景一致)', svg.includes('viewBox="0 0 400 300"'));
  check('1. 用 slice 填满而不是留白', svg.includes('preserveAspectRatio="xMidYMid slice"'));
  check('1. 不含脚本 / 外链', !/<script|href=/i.test(svg));
}

/* ---------- 2: 确定性 ---------- */
{
  check('2. 同一 seed 两次生成完全相同', placeholderSvg('42') === placeholderSvg('42'));
  check('2. 数字与字符串种子等价', placeholderSvg(42) === placeholderSvg('42'));

  const ids = Array.from({ length: 120 }, (_, i) => placeholderSvg(String(i + 1)));
  check('2. 120 篇文章没有一张重复', new Set(ids).size === ids.length, `${new Set(ids).size}/120`);
}

/* ---------- 3: data URI ---------- */
{
  const seed = '42';
  const uri = placeholderDataUri(seed);

  check('3. 前缀是 data:image/svg+xml', uri.startsWith(PREFIX));
  check('3. 不含裸 # (会被当成片段标识符)', !uri.includes('#'));
  check('3. 不含裸尖括号 (会截断属性)', !/[<>]/.test(uri));
  check('3. 解码后与 SVG 源码逐字符一致', decodeURIComponent(uri.slice(PREFIX.length)) === placeholderSvg(seed));
  check('3. 同 seed 命中缓存 (返回同一字符串)', placeholderDataUri(seed) === uri);
  check('3. 换尺寸不进同一份缓存', placeholderDataUri(seed, 160, 120).includes(encodeURIComponent('viewBox="0 0 160 120"')));
}

/* ---------- 4: hydrateThumbs ---------- */
{
  /** 造一个只有 hydrateThumbs 会用到的字段的假 img */
  const fakeImage = (seed) => ({ dataset: { thumbSeed: seed }, src: '', hidden: true });
  const root = (images) => ({ querySelectorAll: () => images });

  const first = fakeImage('42');
  hydrateThumbs(root([first]));

  check('4. 填上了 data URI', first.src.startsWith(PREFIX));
  check('4. 解除了 hidden', first.hidden === false);
  check('4. 打上 ready 标记 (下次不会被重复处理)', first.dataset.thumbReady === '1');

  const empty = fakeImage('');
  hydrateThumbs(root([empty]));

  check('4. 种子为空时保持隐藏', empty.hidden === true && empty.src === '');

  /* 一次调用里多张卡片各自用各自的种子 */
  const many = ['1', '2', '3'].map(fakeImage);
  hydrateThumbs(root(many));

  check('4. 一批卡片互不串图', new Set(many.map((image) => image.src)).size === 3);
}

console.log(`\n${pass} 项通过, ${failures.length} 项失败`);
process.exit(failures.length === 0 ? 0 : 1);
