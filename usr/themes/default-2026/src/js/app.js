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

function boot() {
  Alpine.start();

  if (prefersReducedData()) {
    /* 省流模式: 不做预取, 也不接管导航, 只保留阅读相关的增强 */
    enhancePage();
    return;
  }

  const swup = new Swup({
    containers: ['#main', '#site-nav'],
    native: true,
    animateHistoryBrowsing: false,
    linkSelector: 'a[href]',
    ignoreVisit: (url, { el } = {}) => shouldIgnoreVisit(url, el),
    plugins: [
      new SwupHeadPlugin({ persistAssets: true, awaitAssets: true }),
      new SwupPreloadPlugin({ preloadHoveredLinks: true, preloadVisibleLinks: false, throttle: 4 }),
      new SwupProgressPlugin({ className: 'swup-progress-bar', delay: 150, transition: 260, finishAnimation: true }),
      new SwupScrollPlugin({
        offset: () => headerOffset(),
        animateScroll: prefersReducedMotion()
          ? false
          : { betweenPages: true, samePageWithHash: true, samePage: true }
      })
    ]
  });

  /* 供模板内联脚本或 Alpine 组件 (x-on:swup:page:view.document) 使用 */
  window.swup = swup;

  swup.hooks.on('visit:start', () => {
    document.querySelectorAll('#site-nav details[open]').forEach((details) => {
      details.open = false;
    });
  });

  swup.hooks.on('page:view', () => {
    enhancePage();
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
