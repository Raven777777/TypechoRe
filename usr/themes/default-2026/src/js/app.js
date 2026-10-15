/**
 * TypechoRe · Default 2026 主题前端入口
 *
 * - Alpine.js  : 深色模式切换、阅读进度、返回顶部等声明式轻交互
 * - Swup       : 无刷新切页 (优先使用原生 View Transitions API)
 * - 渐进增强   : 代码块复制、目录高亮、移动端导航收起
 *
 * 全部脚本都以「无 JavaScript 也能正常阅读」为前提编写:
 * 移动端导航使用原生 <details>, 评论表单是普通 POST, 文章内容为服务端渲染。
 */

import Alpine from 'alpinejs';
import Swup from 'swup';
import SwupHeadPlugin from '@swup/head-plugin';
import SwupPreloadPlugin from '@swup/preload-plugin';
import SwupProgressPlugin from '@swup/progress-plugin';
import SwupScrollPlugin from '@swup/scroll-plugin';

const THEME_KEY = 'typecho-theme-mode';
const DARK_QUERY = '(prefers-color-scheme: dark)';

/** 是否处于弱网 / 省流模式: 此时不启用无刷新导航与预取 */
function prefersReducedData() {
  const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  if (!connection) {
    return false;
  }
  return connection.saveData === true || /^(slow-2g|2g)$/.test(connection.effectiveType || '');
}

/** 是否偏好减少动态效果 */
function prefersReducedMotion() {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/* -----------------------------------------------------------------------------
 * 深色模式
 * -------------------------------------------------------------------------- */

/** 把模式 (auto/light/dark) 应用到 <html>, 图标状态由 CSS 依据 data-theme-mode 切换 */
function applyThemeMode(mode) {
  const root = document.documentElement;
  const dark = mode === 'dark' || (mode === 'auto' && window.matchMedia(DARK_QUERY).matches);

  root.classList.toggle('dark', dark);
  root.dataset.themeMode = mode;
  root.style.colorScheme = dark ? 'dark' : 'light';

  const meta = root.querySelector('meta[name="theme-color"]');
  if (meta) {
    meta.setAttribute('content', (dark ? meta.dataset.dark : meta.dataset.light) || '');
  }
}

function storedThemeMode() {
  const fallback = document.documentElement.dataset.defaultThemeMode || 'auto';
  try {
    return localStorage.getItem(THEME_KEY) || fallback;
  } catch (error) {
    return fallback;
  }
}

function storeThemeMode(mode) {
  try {
    localStorage.setItem(THEME_KEY, mode);
  } catch (error) {
    /* 隐私模式下 localStorage 可能不可用, 忽略即可 */
  }
}

/* 系统外观变化时跟随 (仅 auto 模式), 只在模块加载时注册一次 */
window.matchMedia(DARK_QUERY).addEventListener('change', () => {
  if (document.documentElement.dataset.themeMode === 'auto') {
    applyThemeMode('auto');
  }
});

Alpine.data('themeToggle', () => ({
  mode: 'auto',

  init() {
    this.mode = storedThemeMode();
    applyThemeMode(this.mode);
  },

  get nextMode() {
    return this.mode === 'auto' ? 'light' : this.mode === 'light' ? 'dark' : 'auto';
  },

  get label() {
    const names = { auto: '跟随系统', light: '浅色', dark: '深色' };
    return `当前外观: ${names[this.mode]}，点击切换到${names[this.nextMode]}`;
  },

  cycle() {
    this.mode = this.nextMode;
    storeThemeMode(this.mode);
    applyThemeMode(this.mode);
  }
}));

/* -----------------------------------------------------------------------------
 * 阅读进度与返回顶部
 * -------------------------------------------------------------------------- */

Alpine.data('readingProgress', () => ({
  progress: 0,
  onScroll: null,

  init() {
    this.onScroll = () => {
      const scrollable = document.documentElement.scrollHeight - window.innerHeight;
      this.progress = scrollable > 0 ? Math.min(1, window.scrollY / scrollable) : 0;
    };

    this.onScroll();
    window.addEventListener('scroll', this.onScroll, { passive: true });
    window.addEventListener('resize', this.onScroll);
  },

  destroy() {
    window.removeEventListener('scroll', this.onScroll);
    window.removeEventListener('resize', this.onScroll);
  }
}));

Alpine.data('backToTop', () => ({
  visible: false,
  onScroll: null,

  init() {
    this.onScroll = () => {
      /* 阈值随页面可滚动高度自适应: 短页面滚到底也能出现 */
      const scrollable = document.documentElement.scrollHeight - window.innerHeight;
      const threshold = Math.min(600, Math.max(240, scrollable * 0.5));

      this.visible = window.scrollY > threshold;
    };

    this.onScroll();
    window.addEventListener('scroll', this.onScroll, { passive: true });
    window.addEventListener('resize', this.onScroll);
  },

  destroy() {
    window.removeEventListener('scroll', this.onScroll);
    window.removeEventListener('resize', this.onScroll);
  },

  toTop() {
    window.scrollTo({ top: 0, behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
  }
}));

/* -----------------------------------------------------------------------------
 * 增强脚本 (需要在每次页面切换后重新初始化)
 * -------------------------------------------------------------------------- */

const COPY_IDLE = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>';
const COPY_DONE = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';

/** 复制文本, 优先使用异步剪贴板 API, 失败时退回 execCommand */
async function copyText(text) {
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
      return true;
    }
  } catch (error) {
    /* 继续尝试兼容方案 */
  }

  const area = document.createElement('textarea');
  area.value = text;
  area.setAttribute('readonly', 'readonly');
  area.style.position = 'fixed';
  area.style.opacity = '0';
  document.body.appendChild(area);
  area.select();

  let ok = false;
  try {
    ok = document.execCommand('copy');
  } catch (error) {
    ok = false;
  }

  document.body.removeChild(area);
  return ok;
}

/** 为代码块注入语言标签与复制按钮 */
function enhanceCodeBlocks() {
  document.querySelectorAll('#main .prose-theme pre > code').forEach((code) => {
    const pre = code.parentElement;
    if (!pre || pre.querySelector('.code-block-tools')) {
      return;
    }

    /* Typecho 的 Markdown 解析器输出 class="lang-php", 部分插件/高亮库用 class="language-php" */
    const match = /(?:language|lang)-([\w+#-]+)/.exec(code.className);
    const tools = document.createElement('div');
    tools.className = 'code-block-tools';

    if (match) {
      const lang = document.createElement('span');
      lang.className = 'code-lang';
      lang.textContent = match[1];
      tools.appendChild(lang);
    }

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'code-copy';
    button.setAttribute('aria-label', '复制代码');
    button.title = '复制代码';
    button.innerHTML = `<span class="code-copy-idle">${COPY_IDLE}</span><span class="code-copy-done">${COPY_DONE}</span>`;

    button.addEventListener('click', async () => {
      if (await copyText(code.textContent || '')) {
        button.dataset.copied = 'true';
        window.setTimeout(() => {
          delete button.dataset.copied;
        }, 1600);
      }
    });

    tools.appendChild(button);
    pre.appendChild(tools);

    /* 让 <code> 自己成为横向滚动容器: 这样按钮定位在不滚动的 <pre> 上,
       代码左右滚动时按钮不会再跟着移动。 */
    code.classList.add('code-scroll');
    pre.classList.add('code-block');
  });
}

/** 代码块与宽表格的横向滚动容器 */
function enhanceOverflow() {
  document.querySelectorAll('#main .prose-theme > table').forEach((table) => {
    if (table.parentElement && table.parentElement.classList.contains('table-wrap')) {
      return;
    }

    const wrap = document.createElement('div');
    wrap.className = 'table-wrap';
    table.parentElement?.insertBefore(wrap, table);
    wrap.appendChild(table);
  });
}

let releaseTocSpy = null;

/** 目录滚动高亮: 追踪所有 .js-toc 容器 (移动端折叠版 + 桌面版) */
function enhanceTocSpy() {
  if (typeof releaseTocSpy === 'function') {
    releaseTocSpy();
    releaseTocSpy = null;
  }

  const links = new Map();
  document.querySelectorAll('.js-toc a[href^="#"]').forEach((link) => {
    const id = decodeURIComponent(link.hash.slice(1));
    if (!id) {
      return;
    }
    if (!links.has(id)) {
      links.set(id, []);
    }
    links.get(id).push(link);
  });

  const headings = Array.from(document.querySelectorAll('#main .prose-theme :is(h2, h3, h4)[id]'))
    .filter((heading) => links.has(heading.id));

  if (!headings.length) {
    return;
  }

  let frame = 0;
  let current = '';

  const update = () => {
    frame = 0;

    const threshold = 140;
    let active = headings[0];

    for (const heading of headings) {
      if (heading.getBoundingClientRect().top - threshold <= 0) {
        active = heading;
      } else {
        break;
      }
    }

    /* 页面滚到底部时高亮最后一个小节 */
    if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) {
      active = headings[headings.length - 1];
    }

    if (active.id === current) {
      return;
    }

    current = active.id;
    links.forEach((group, id) => {
      group.forEach((link) => link.classList.toggle('is-active', id === current));
    });
  };

  const onScroll = () => {
    if (frame) {
      return;
    }
    frame = window.requestAnimationFrame(update);
  };

  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll);
  update();

  releaseTocSpy = () => {
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('resize', onScroll);
    if (frame) {
      window.cancelAnimationFrame(frame);
    }
  };
}

/**
 * 从 cookie 中读取 Notice 组件的提示 (评论提交结果等) 并显示
 */
function enhanceNotice() {
  const box = document.getElementById('notice-box');
  if (!box) {
    return;
  }

  const match = document.cookie.match(/(?:^|;\s*)([^;]*__typecho_notice)=([^;]*)/);
  if (!match) {
    return;
  }

  try {
    const list = JSON.parse(decodeURIComponent(match[2]));
    if (Array.isArray(list) && list.length) {
      box.textContent = list.join('\n');
      box.hidden = false;
    }
  } catch (error) {
    return;
  }

  document.cookie = `${match[1]}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`;
}

/* Typecho 自带的反垃圾脚本会在首次交互时再插入一个同名字段, 这里保留先插入的那个 */
function dedupeAntiSpamToken() {
  const inputs = document.querySelectorAll('#comment-form input[name="_"]');

  for (let i = 1; i < inputs.length; i++) {
    inputs[i].remove();
  }
}

/**
 * 补上反垃圾隐藏字段
 *
 * Typecho 的 CSRF token 已经写在表单 action 里, 这里只是让 commentsAntiSpam
 * 依赖的隐藏字段在无刷新切页后依然存在 (内联脚本只在 DOMContentLoaded 时初始化)。
 */
function enhanceCommentForm() {
  const form = document.getElementById('comment-form');
  if (!form) {
    return;
  }

  if (!form.querySelector('input[name="_"]')) {
    const action = form.getAttribute('action');
    let token = '';

    try {
      token = action ? new URL(action, window.location.href).searchParams.get('_') || '' : '';
    } catch (error) {
      token = '';
    }

    if (token) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = '_';
      input.value = token;
      form.appendChild(input);
    }
  }

  if (!window.__typechoAntiSpamDedupe) {
    window.__typechoAntiSpamDedupe = true;

    ['scroll', 'mousemove', 'keyup', 'touchstart'].forEach((type) => {
      window.addEventListener(type, dedupeAntiSpamToken, { once: true, passive: true });
    });
  }
}

/** 每次页面内容替换后需要重新执行的增强 */
function enhancePage() {
  enhanceCodeBlocks();
  enhanceOverflow();
  enhanceTocSpy();
  enhanceNotice();
  enhanceCommentForm();
}

/* 移动端导航: 点击链接后收起 (事件委托, 只需注册一次) */
document.addEventListener('click', (event) => {
  const link = event.target.closest('a');
  const details = link && link.closest('details[data-nav-collapse]');
  if (details) {
    details.open = false;
  }
});

/* -----------------------------------------------------------------------------
 * Swup: 无刷新切页
 * -------------------------------------------------------------------------- */

const IGNORED_PATH = /(^|\/)(admin|action|xmlrpc\.php|install\.php)(\/|$)/i;

function shouldIgnoreVisit(url, element) {
  if (element) {
    if (element.closest('[data-no-swup]')) {
      return true;
    }
    if (element.hasAttribute('onclick') || element.hasAttribute('download')) {
      return true;
    }
    if (element.target && element.target !== '_self') {
      return true;
    }
  }

  if (/^(mailto:|tel:|javascript:)/i.test(url)) {
    return true;
  }

  let target;
  try {
    target = new URL(url, window.location.href);
  } catch (error) {
    return true;
  }

  /* 跨域、后台、评论回复等交给浏览器原生跳转 */
  return target.origin !== window.location.origin
    || IGNORED_PATH.test(target.pathname)
    || target.searchParams.has('replyTo')
    || target.hash === '#cancel-comment-reply-link';
}

/** 顶部固定导航的高度, 用于锚点滚动补偿 */
function headerOffset() {
  const nav = document.getElementById('site-nav');
  return nav ? nav.offsetHeight + 12 : 0;
}

/* -----------------------------------------------------------------------------
 * 切页骨架屏 + 内容上浮入场 (progressive enhancement)
 *
 * 无刷新切页时内容会在几十到几百毫秒后出现, 直接替换会显得「突兀」。
 * 这里做两件事:
 *   1. 等待期间显示与真实布局同构的骨架屏 (shimmer), 代替过去的转圈符号;
 *   2. 容器替换后就给 <html> 挂 is-entering, 让新卡片错峰上浮淡入 ——
 *      和骨架淡出交叠在一起, 大片内容的切换就变成连续的一次「浮起」。
 * 延迟 130ms 才显示骨架: 命中预取 / 缓存的跳转根本不会出现骨架, 不会闪一下。
 * -------------------------------------------------------------------------- */

const SKELETON_DELAY = 130;
const SKELETON_FADE = 220;
/* 与 CSS 里最长的卡片动画对齐 (175ms 错峰 + 340ms 动画 + 余量);
   实际值由 <html> 上的 --anim-window 决定, 这里只是读不到变量时的兜底 */
const CONTENT_ENTER_DURATION = 620;

let skeletonTimer = 0;
let skeletonHideTimer = 0;
let skeletonVisible = false;
let enterTimer = 0;
/* 本次跳转是否要播放入场动画 (同页锚点跳转不播) */
let enterNextContent = false;
/* 动效配置缓存: 每页只在首次用到时读一次 */
let animCache = null;

/**
 * 把 CSS 变量里的时间值换算成毫秒 ("0.34s" / "340ms" 都支持)
 *
 * @param {CSSStyleDeclaration|null} styles
 * @param {string} name
 * @param {number} fallback
 * @returns {number}
 */
function readTimeVar(styles, name, fallback) {
  const raw = styles ? styles.getPropertyValue(name).trim() : '';

  if (!raw) {
    return fallback;
  }

  const value = Number.parseFloat(raw);

  if (!Number.isFinite(value)) {
    return fallback;
  }

  return raw.endsWith('ms') ? value : value * 1000;
}

/**
 * 读取主题设置输出的动效配置
 *
 * header.php 会把档位写成 data-anim / data-skeleton, 把数值写成 CSS 变量,
 * 所以 CSS 与 JS 用的是同一份真相 (改了 CSS 时长, 这里的窗口也会跟着变)。
 *
 * @returns {{level: string, skeleton: boolean, enterWindow: number, skeletonFade: number}}
 */
function animConfig() {
  const root = document.documentElement;
  const styles = typeof window.getComputedStyle === 'function' ? window.getComputedStyle(root) : null;

  return {
    level: (root.dataset && root.dataset.anim) || 'normal',
    skeleton: !root.dataset || '0' !== root.dataset.skeleton,
    enterWindow: readTimeVar(styles, '--anim-window', CONTENT_ENTER_DURATION),
    skeletonFade: readTimeVar(styles, '--anim-skeleton-fade', SKELETON_FADE)
  };
}

/** 动效配置 (每页只解析一次) */
function animSettings() {
  if (null === animCache) {
    animCache = animConfig();
  }

  return animCache;
}

/**
 * 主题设置: 链接预取是否开启
 *
 * header.php 把它写成 <html data-preload="1|0">。关掉后不注册预取插件 ——
 * 悬停不再后台取页面, 命中缓存的跳转变少, 骨架屏那 130ms 延迟正好派上用场。
 *
 * @returns {boolean}
 */
function preloadEnabled() {
  const root = document.documentElement;

  return !root.dataset || '0' !== root.dataset.preload;
}

/**
 * 组装 swup 插件, 顺序: head → [预取] → 进度条 → 滚动
 *
 * 预取插件按主题设置接线: 关掉后完全不注册, 悬停不再后台取页面。
 * 单独抽成函数是为了让「设置开关 → 插件列表」这条线能被测试直接调用。
 *
 * @returns {Array<object>}
 */
function buildSwupPlugins() {
  const plugins = [new SwupHeadPlugin({ persistAssets: true, awaitAssets: true })];

  if (preloadEnabled()) {
    plugins.push(new SwupPreloadPlugin({ preloadHoveredLinks: true, preloadVisibleLinks: false, throttle: 4 }));
  }

  plugins.push(
    new SwupProgressPlugin({ className: 'swup-progress-bar', delay: 150, transition: 260, finishAnimation: true }),
    new SwupScrollPlugin({
      offset: () => headerOffset(),
      animateScroll: prefersReducedMotion()
        ? false
        : { betweenPages: true, samePageWithHash: true, samePage: true }
    })
  );

  return plugins;
}

/**
 * 猜目标页面该用哪种骨架形态。
 *
 * 点文章卡片 → 目标是文章页; 其余 (导航 / 分页 / 分类 / 侧栏) 按列表页处理。
 * 需要精确控制时可以在链接或卡片上写 data-skeleton="list|single"。
 * 这只是一层形态近似, 猜错也只影响等待那几百毫秒的观感。
 *
 * @param {Element|null|undefined} triggerEl 触发跳转的元素
 * @returns {'list'|'single'}
 */
function skeletonKind(triggerEl) {
  const hinted = triggerEl && triggerEl.closest('[data-skeleton]');

  if (hinted) {
    const kind = hinted.getAttribute('data-skeleton');

    if ('list' === kind || 'single' === kind) {
      return kind;
    }
  }

  return triggerEl && triggerEl.closest('#main article.card') ? 'single' : 'list';
}

function showSkeleton(kind) {
  const overlay = document.getElementById('page-skeleton');
  if (!overlay || skeletonVisible) {
    return;
  }

  overlay.dataset.kind = kind;
  skeletonVisible = true;
  overlay.hidden = false;
  /* 等一帧再加类, 否则 hidden 刚取消时过渡不会触发。
     这一帧里可能已经被 hideSkeleton 收掉了 (超快跳转), 所以要再确认一次,
     否则会把 is-visible 加回来, 让遮罩在该消失的时候又亮一下。 */
  window.requestAnimationFrame(() => {
    if (skeletonVisible) {
      overlay.classList.add('is-visible');
    }
  });
}

function hideSkeleton() {
  /* 只取消「还没显示出来」的那个定时器。
     进入淡出流程后, 那个 hidden 定时器必须留着 —— 如果在这里清掉,
     遮罩会停在 hidden=false 的透明状态, 继续拦截整片内容区的点击
     (swup 的 visit:end 会在淡出结束前就再调一次 hideSkeleton)。 */
  window.clearTimeout(skeletonTimer);
  skeletonTimer = 0;

  const overlay = document.getElementById('page-skeleton');

  if (!overlay) {
    skeletonVisible = false;
    return;
  }

  /* 已经收过了: 不要重复排队, 也不要动正在跑的淡出定时器 */
  if (!skeletonVisible) {
    return;
  }

  skeletonVisible = false;
  overlay.classList.remove('is-visible');

  /* 等淡出动画结束再真正隐藏, 时长与 CSS 的 --anim-skeleton-fade 保持一致 */
  window.clearTimeout(skeletonHideTimer);
  skeletonHideTimer = window.setTimeout(() => {
    skeletonHideTimer = 0;

    if (!skeletonVisible) {
      overlay.hidden = true;
    }
  }, animSettings().skeletonFade);
}

function scheduleSkeleton(kind) {
  hideSkeleton();

  /* 主题设置里关掉骨架屏后, 等待期间不显示任何占位 */
  if (!animSettings().skeleton) {
    return;
  }

  skeletonTimer = window.setTimeout(() => showSkeleton(kind), SKELETON_DELAY);
}

/** 新内容入场: 由 CSS 的 html.is-entering 规则提供动画 */
function startContentEnter() {
  cancelContentEnter();

  const config = animSettings();

  /* 「关闭」档: 不做入场动画, 内容直接替换 */
  if ('off' === config.level || config.enterWindow <= 0) {
    return;
  }

  document.documentElement.classList.add('is-entering');
  enterTimer = window.setTimeout(cancelContentEnter, config.enterWindow);
}

/**
 * 结束 (或取消) 入场动画。
 *
 * 必须在下次跳转开始前清掉: 动画的 fill-mode 是 both, 留着会盖住旧内容
 * 淡出时的 opacity/transform 过渡。
 */
function cancelContentEnter() {
  window.clearTimeout(enterTimer);
  enterTimer = 0;
  document.documentElement.classList.remove('is-entering', ASIDE_SWAPPED_CLASS);
}

/* -----------------------------------------------------------------------------
 * 让正文里的内联脚本在切页后也能执行
 *
 * Swup 插入新容器用的是 incomingEl.cloneNode(true), 而从 DOMParser 文档克隆出来的
 * <script> 带着 "already started" 标记 (HTML 规范: script 的克隆步骤会置位它),
 * 浏览器永远不会执行 —— 所以写在文章 / 独立页面正文里的 <script> 只有按 F5
 * 走解析器路径时才生效。
 *
 * 这里在替换前先把新文档里的脚本「缴械」(type 改成浏览器不认识的类型),
 * 替换后再按原样造一个新的 script 插回去执行: 顺序、属性、内容都保持原样,
 * 且每个脚本恰好执行一次 —— 不依赖 swup 以后会不会换插入方式。
 *
 * 需要保持「只在 F5 时执行」的脚本, 在标签上写 data-swup-skip 即可。
 * -------------------------------------------------------------------------- */

/** 缴械后临时使用的 type, 浏览器不会执行它 */
const DEFERRED_SCRIPT_TYPE = 'text/swup-deferred';
/** 标记缴械过的脚本, 同时记录它原本的 type */
const DEFERRED_SCRIPT_ATTR = 'data-swup-script';
/** 第二列 (侧边栏 / 文章目录) 的稳定标记 */
const ASIDE_SELECTOR = '.page-aside';
/** 本次跳转真的换了第二列时挂在 <html> 上, 用来决定要不要给它播入场动画 */
const ASIDE_SWAPPED_CLASS = 'is-aside-swapped';

/**
 * 替换前: 把新文档里 (容器与第二列范围内) 的脚本缴械并标记
 *
 * @param {object} visit swup 的 visit 对象
 */
function deferContainerScripts(visit) {
  const incoming = visit && visit.to && visit.to.document;
  const containers = (visit && visit.containers) || [];

  if (!incoming || !containers.length) {
    return;
  }

  const roots = containers
    .map((selector) => incoming.querySelector(selector))
    .filter(Boolean);

  /* 第二列不在 swup 的容器列表里 (见 syncPageAside), 但也可能带脚本 */
  const aside = incoming.querySelector(ASIDE_SELECTOR);

  if (aside) {
    roots.push(aside);
  }

  roots.forEach((root) => {
    root.querySelectorAll('script').forEach((script) => {
      if (script.hasAttribute('data-swup-skip')) {
        return;
      }

      script.setAttribute(DEFERRED_SCRIPT_ATTR, script.getAttribute('type') || '');
      script.setAttribute('type', DEFERRED_SCRIPT_TYPE);
    });
  });
}

/** 替换后: 把缴械过的脚本按原样还原并执行 */
function runDeferredScripts() {
  document.querySelectorAll(`script[${DEFERRED_SCRIPT_ATTR}]`).forEach((placeholder) => {
    const originalType = placeholder.getAttribute(DEFERRED_SCRIPT_ATTR);
    const script = document.createElement('script');

    Array.from(placeholder.attributes).forEach(({ name, value }) => {
      if (name !== DEFERRED_SCRIPT_ATTR) {
        script.setAttribute(name, value);
      }
    });

    if (originalType) {
      script.setAttribute('type', originalType);
    } else {
      script.removeAttribute('type');
    }

    /* 动态插入的脚本默认是 async, 会把外链脚本的执行顺序打乱;
       作者显式写了 async 就尊重作者, 否则强制按插入顺序执行。 */
    if (script.src && !script.hasAttribute('async')) {
      script.async = false;
    }

    script.textContent = placeholder.textContent;
    /* 插入到文档即执行; 顺带把标记节点换成真正的脚本节点 */
    placeholder.replaceWith(script);
  });
}

/**
 * 同步第二列 (侧边栏 / 文章目录)。
 *
 * 这一列是 #main 的兄弟节点, 不在 swup 的容器列表里, 于是切页时整块不更新:
 * 从列表页进文章看不到目录 (必须 F5), 从文章回列表页又留着上一页的目录。
 * 这里按目标文档把整列换掉: 目标页有 → 替换 (当前没有就插到 #main 后面),
 * 目标页没有 (404 等) → 移除。
 *
 * 不把它注册成 swup 容器是有意的: 容器在目标文档里缺失会让 swup 判定
 * Container mismatch 并放弃接管导航, 而这一列在「单栏布局 / 侧边栏区块全关 /
 * 404」等情况下本来就可能不存在。
 *
 * @param {object} visit swup 的 visit 对象
 */
function syncPageAside(visit) {
  const incomingDoc = visit && visit.to && visit.to.document;
  const current = document.querySelector(ASIDE_SELECTOR);
  const incoming = incomingDoc ? incomingDoc.querySelector(ASIDE_SELECTOR) : null;

  if (current && incoming) {
    /* 侧边栏六个区块都是全站数据, 内容与当前页面无关 (实测首页与文章页的
       侧边栏 HTML 逐字节相同), 所以同类型时不重建: 重建会整列重绘, 还会丢掉
       hover / 焦点状态, 看起来就是侧边栏在闪, 也没必要为它播入场动画。
       目录则必须每次替换 —— 不同文章的目录不同。 */
    if ('sidebar' === current.dataset.aside && 'sidebar' === incoming.dataset.aside) {
      return;
    }

    current.replaceWith(incoming.cloneNode(true));
    markAsideSwapped();
    return;
  }

  if (current) {
    current.remove();
    return;
  }

  /* 当前页没有这一列: 按模板结构插到 #main 之后 (目标文档里的位置也一样) */
  if (incoming) {
    const main = document.querySelector('#main');

    if (main) {
      main.after(incoming.cloneNode(true));
      markAsideSwapped();
    }
  }
}

/**
 * 标记「这次跳转真的换了第二列」
 *
 * CSS 里所有第二列的入场规则都要求 <html> 同时有 is-entering 与本标记,
 * 于是「侧边栏没变」的跳转不会给那一列播任何动效。
 */
function markAsideSwapped() {
  document.documentElement.classList.add(ASIDE_SWAPPED_CLASS);
}

/** 同一个页面里的锚点跳转不会真正换页, 不必显示骨架也不需要入场动画 */
function isSamePageHashVisit(visit) {
  const to = visit?.to;

  if (!to || !to.hash || !to.url) {
    return false;
  }

  /* visit.to 只有 url / hash, 没有 pathname, 这里自己解析出来比对 */
  try {
    const target = new URL(to.url, window.location.href);

    return target.pathname === window.location.pathname
      && target.search === window.location.search;
  } catch (error) {
    return false;
  }
}

function boot() {
  Alpine.start();

  if (prefersReducedData()) {
    /* 省流模式: 不做预取, 也不接管导航, 只保留阅读相关的增强 */
    enhancePage();
    return;
  }

  const swup = new Swup({
    containers: ['#main', '#site-nav'],
    /* 不用原生 View Transitions: 它只能整块快照交叉淡入, 没法逐张卡片错峰上浮,
       而且 is-rendering 期间内容被隐藏, 会和骨架屏淡出叠出空白。这里改由
       html.is-animating / html.is-entering 两个类精确控制时间轴。 */
    native: false,
    animateHistoryBrowsing: false,
    linkSelector: 'a[href]',
    ignoreVisit: (url, { el } = {}) => shouldIgnoreVisit(url, el),
    plugins: buildSwupPlugins()
  });

  /* 供模板内联脚本或 Alpine 组件 (x-on:swup:page:view.document) 使用 */
  window.swup = swup;

  swup.hooks.on('visit:start', (visit) => {
    document.querySelectorAll('#site-nav details[open]').forEach((details) => {
      details.open = false;
    });

    /* 取消上一次还没走完的入场动画, 否则它的 fill 会盖住这次的淡出 */
    cancelContentEnter();

    /* 只在 visit:start 判断: 这时 window.location 还是旧地址 */
    enterNextContent = !isSamePageHashVisit(visit);

    if (enterNextContent) {
      scheduleSkeleton(skeletonKind(visit.trigger && visit.trigger.el));
    }
  });

  /* 替换前先把新文档里的脚本缴械 (swup 的 cloneNode 本来也不会执行它们) */
  swup.hooks.before('content:replace', deferContainerScripts);

  /* 容器换完就收骨架并开始上浮: 不等 page:view —— 滚动插件会先做一段平滑滚动,
     等它结束会让骨架屏多停留几百毫秒。 */
  swup.hooks.on('content:replace', (visit) => {
    hideSkeleton();

    if (enterNextContent) {
      startContentEnter();
      /* 第二列 (侧边栏 / 文章目录) 不在容器列表里, 这里整列同步。
         必须排在 runDeferredScripts() 之前: 换进来的那一列如果带脚本,
         要由重放逻辑负责执行。 */
      syncPageAside(visit);
    }

    /* 正文里的内联脚本: 此时新节点已就位, 按原顺序重放一次。
       放在 enhancePage() 之前, 与 F5 时「正文脚本先跑、主题增强后跑」一致。 */
    runDeferredScripts();
  });

  swup.hooks.on('page:view', () => {
    enhancePage();
  });

  /* 跳转结束时收掉骨架屏; 请求失败 (swup 会退化成整页跳转) 时一并收干净。
     注意 swup 4 没有 visit:error 这个钩子 (旧代码注册的它只会打印一条警告),
     也不要用 visit:abort: 用户连点两次时 abort 晚于新 visit 的 start,
     会把新跳转刚排上的骨架定时器清掉。 */
  swup.hooks.on('visit:end', hideSkeleton);
  swup.hooks.on('visit:fail', () => {
    hideSkeleton();
    cancelContentEnter();
  });

  enhancePage();
}

/* Head 插件会替换 <head>, 内联脚本可能被重复执行; 用全局标记保证只初始化一次 */
if (!window.__typechoThemeBooted) {
  window.__typechoThemeBooted = true;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
}
