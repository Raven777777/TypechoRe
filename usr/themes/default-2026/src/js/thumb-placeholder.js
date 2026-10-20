/**
 * 无图文章的占位缩略图: 文章 id → 柔和几何 SVG
 *
 * 三条约束决定了它长成现在这样:
 *   1. 只在访客浏览器里算 —— 服务器上不产生文件, 数据库里不新增字段, 也没有额外请求;
 *   2. 种子只有文章 id —— 色相、光斑与图案全部由 hash(seed) 决定, 所以同一篇文章
 *      在首页、分类页、搜索结果里永远是同一张图, 标题或时间改了图也不会变;
 *   3. 生成不依赖任何库 —— 纯字符串拼接, 单张耗时以微秒计, 比一次图片请求便宜。
 *
 * 输出用 data URI 而不是内联 <svg>: 卡片缩略图本身是 <img>, 这样可以原样复用它已有的
 * absolute / object-cover / group-hover 缩放与 loading="lazy" (视口外的卡片不会触发
 * 滤镜光栅化); 另外 SVG 在 <img> 里是独立文档, 主题的 svg 选择器与 currentColor
 * 不会渗进去, 各卡片的滤镜 id 也不会互相覆盖。
 */

/** FNV-1a 32 位: 同一字符串永远同一结果, 不依赖运行环境 */
const hash = (s) => {
  let h = 2166136261;
  for (let i = 0; i < s.length; i++) {
    h = Math.imul(h ^ s.charCodeAt(i), 16777619);
  }
  return h >>> 0;
};

/** mulberry32: 由种子派生一个可重复的伪随机序列 */
const rng = (a) => () => {
  a = (a + 0x6d2b79f5) | 0;
  let t = Math.imul(a ^ (a >>> 15), 1 | a);
  t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
  return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
};

/** 正多边形顶点串: poly(3, s) 三角形, poly(6, s) 六边形 */
const poly = (n, s) =>
  Array.from({ length: n }, (_, i) => {
    const a = -Math.PI / 2 + (i * Math.PI * 2) / n;
    return `${(Math.cos(a) * s).toFixed(1)},${(Math.sin(a) * s).toFixed(1)}`;
  }).join(' ');

/**
 * 生成 SVG 源码
 *
 * 注意 hsl() 用逗号语法而不是空格语法: SVG presentation attribute 在老版本 Safari 上
 * 对 CSS Color 4 的空格写法解析不稳, 逗号写法颜色完全一致但没有这个坑。
 *
 * @param {string} seed 种子 (文章 id)
 * @param {number} w 画布宽
 * @param {number} h 画布高
 * @returns {string} 完整 <svg> 源码
 */
export function placeholderSvg(seed, w = 400, h = 300) {
  const st = hash(String(seed));
  /* 短 id: 同一页里多张占位图各自引自己的渐变 / 滤镜, 不能撞 */
  const id = st.toString(36).slice(0, 6);
  const r = rng(st);
  const base = r() * 360;
  const m = Math.min(w, h);

  /* 色相偏移生成器: d 偏差、s 饱和、l 亮度 */
  const c = (d = 0, s = 65, l = 65) => `hsl(${(base + d + 360) % 360}, ${s}%, ${l}%)`;

  /* 1) 背景渐变 */
  let defs = `<linearGradient id="b${id}" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="${c(0, 70, 96)}"/>
    <stop offset="1" stop-color="${c(50, 65, 90)}"/>
  </linearGradient>`;
  let body = `<rect width="${w}" height="${h}" fill="url(#b${id})"/>`;

  /* 2) 5 个柔和径向光斑 */
  for (let i = 0; i < 5; i++) {
    defs += `<radialGradient id="r${id}${i}" cx="${(r() * 100).toFixed(1)}%" cy="${(r() * 100).toFixed(1)}%" r="${(38 + r() * 42).toFixed(1)}%">
      <stop offset="0" stop-color="${c((r() - 0.5) * 110, 65 + r() * 25, 62 + r() * 20)}" stop-opacity=".8"/>
      <stop offset="1" stop-color="${c(0, 65, 70)}" stop-opacity="0"/>
    </radialGradient>`;
    body += `<rect width="${w}" height="${h}" fill="url(#r${id}${i})"/>`;
  }

  /* 3) 1–3 个几何图案: 圆 / 圆角矩形 / 三角形 / 六边形 */
  let shapes = '';
  for (let i = 1 + Math.floor(r() * 3); i--; ) {
    const kind = Math.floor(r() * 4);
    const s = m * (0.16 + r() * 0.22);
    const cx = w * (0.22 + r() * 0.56);
    const cy = h * (0.22 + r() * 0.56);
    const rot = r() * 360;
    const el =
      kind === 0
        ? `<circle r="${s.toFixed(1)}"/>`
        : kind === 1
          ? `<rect x="${(-s).toFixed(1)}" y="${(-s * 0.62).toFixed(1)}" width="${(s * 2).toFixed(1)}" height="${(s * 1.24).toFixed(1)}" rx="${(s * 0.32).toFixed(1)}"/>`
          : `<polygon points="${poly(kind === 2 ? 3 : 6, s)}"/>`;
    shapes += `<g transform="translate(${cx.toFixed(1)} ${cy.toFixed(1)}) rotate(${rot.toFixed(1)})" fill="${c((r() - 0.5) * 80, 60 + r() * 20, 62 + r() * 16)}" fill-opacity="${(0.28 + r() * 0.2).toFixed(2)}">${el}</g>`;
  }

  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w} ${h}" preserveAspectRatio="xMidYMid slice"><defs>${defs}
    <filter id="s${id}" x="-25%" y="-25%" width="150%" height="150%"><feGaussianBlur stdDeviation="${(m / 220).toFixed(2)}"/></filter>
    <filter id="n${id}"><feTurbulence type="fractalNoise" baseFrequency=".9" numOctaves="2"/><feColorMatrix type="saturate" values="0"/></filter>
  </defs>${body}<g filter="url(#s${id})">${shapes}</g><rect width="${w}" height="${h}" filter="url(#n${id})" opacity=".05"/></svg>`;
}

/** 已算过的 data URI: 同一篇文章在一页里出现两次 (置顶 + 列表) 时不重复拼接 */
const uriCache = new Map();

/**
 * 生成可直接赋给 img.src 的 data URI
 *
 * encodeURIComponent 会把 # 转成 %23 —— 不转的话 # 之后的内容会被当成片段标识符,
 * 整个 SVG 就断了。
 *
 * @param {string} seed 种子 (文章 id)
 * @param {number} w 画布宽
 * @param {number} h 画布高
 * @returns {string} data URI
 */
export function placeholderDataUri(seed, w = 400, h = 300) {
  const key = `${seed}|${w}x${h}`;
  const cached = uriCache.get(key);

  if (cached) {
    return cached;
  }

  const uri = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(placeholderSvg(seed, w, h))}`;
  uriCache.set(key, uri);

  return uri;
}

/** 还没填图的占位缩略图: 模板只写种子的那些 img */
const PENDING_SELECTOR = 'img[data-thumb-seed]:not([data-thumb-ready])';

/**
 * 给页面上所有待填充的占位缩略图补上 src
 *
 * 由 app.js 调用两次, 但只会真正干活一次:
 *   - swup 的 content:replace —— 换进来的新卡片立刻补图, 不等滚动动画跑完的 page:view,
 *     否则新列表会先露出一排灰底媒体列;
 *   - enhancePage() —— 首屏 (无 swup) 与省流模式走这条。
 * 已经填过的 img 带 data-thumb-ready, 选择器直接把它们排除, 所以重复调用是空操作。
 *
 * @param {ParentNode} [root] 查找范围, 默认整个文档
 */
export function hydrateThumbs(root = document) {
  root.querySelectorAll(PENDING_SELECTOR).forEach((image) => {
    const seed = image.dataset.thumbSeed;

    /* 种子为空 (模板漏写 / cid 为 0) 就保持 hidden: 宁可没有图, 也不要一张来历不明的图 */
    if (!seed) {
      return;
    }

    image.src = placeholderDataUri(seed);
    image.dataset.thumbReady = '1';
    /* hidden 的生效靠 preflight 的 [hidden]{display:none!important}, 不会被 img 的 display 覆盖 */
    image.hidden = false;
  });
}
