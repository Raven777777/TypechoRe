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

/* notice-box 的两种外观: 出错 (琥珀) / 一般回执 (灰底) */
const NOTICE_ERROR_CLASS = 'mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm whitespace-pre-line text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200';
const NOTICE_INFO_CLASS = 'mb-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm whitespace-pre-line text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-200';

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

/**
 * 评论框: 自适应高度 + Markdown 工具条 + 表情面板 + 回复/取消回复
 */
function enhanceCommentBox() {
  const textarea = document.getElementById('textarea');
  const form = document.getElementById('comment-form');
  const comments = document.getElementById('comments');

  if (!textarea || !form || !comments) {
    return;
  }

  /* 评论框容器 id 由 Typecho 定 (respond-<theId>), 不要自己拼: theId 不是纯数字。
     模板把完整值写在表单的 data-respond-id 上; 拿不到就退回「找最近的那个」。 */
  const respond = (form.dataset.respondId && document.getElementById(form.dataset.respondId))
    || comments.querySelector('.respond');
  const cancelLink = comments.querySelector('#cancel-comment-reply-link');

  /** 取 #respond-xxx 里的隐藏 parent 字段 (没有就按需创建) */
  const parentField = (create) => {
    let input = form.querySelector('input[name="parent"]');

    if (!input && create) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'parent';
      form.appendChild(input);
    }

    return input;
  };

  /* 插入/取出 #respond-xxx 时, 需要一个位置固定的占位节点记住它原本在哪 */
  const holderFor = () => {
    let holder = comments.querySelector('[data-respond-holder]');

    if (!holder) {
      holder = document.createElement('div');
      holder.id = respond ? respond.id + '-holder' : 'respond-holder';
      holder.hidden = true;
      holder.setAttribute('data-respond-holder', '');
      respond.parentNode.insertBefore(holder, respond);
    }

    return holder;
  };

  /* 表单内容与评论内容一般长, 按内容高度自适应; 太长时退回 auto */
  const autoGrow = () => {
    if (textarea.offsetParent === null && textarea.getClientRects().length === 0) {
      textarea.style.height = 'auto';
      return;
    }

    /* 先归零才能量到真实内容高度; 顺手记住滚动位置, 免得长文时光标乱跳 */
    const scrollTop = textarea.scrollTop;
    textarea.style.height = 'auto';

    const maxHeight = Number.parseFloat(window.getComputedStyle(textarea).maxHeight);
    const limit = Number.isFinite(maxHeight) ? maxHeight : Infinity;
    const contentHeight = textarea.scrollHeight;

    /* hidden 状态下量不到真实高度 (会是 0), 这时不要写死一个 0 */
    textarea.style.height = contentHeight > 0 ? `${Math.min(contentHeight, limit)}px` : 'auto';
    textarea.scrollTop = scrollTop;
  };

  /**
   * 用前后缀包裹选区 (没有选区时插入占位文本并选中它, 便于直接改写)
   *
   * @param {string} prefix
   * @param {string} suffix
   * @param {string} placeholder 没有选中内容时使用的占位文本
   */
  const insert = (prefix, suffix, placeholder = '') => {
    const start = textarea.selectionStart ?? textarea.value.length;
    const end = textarea.selectionEnd ?? start;
    const selected = textarea.value.slice(start, end) || placeholder;

    textarea.value = textarea.value.slice(0, start) + prefix + selected + suffix + textarea.value.slice(end);
    textarea.focus();
    textarea.setSelectionRange(start + prefix.length, start + prefix.length + selected.length);
    autoGrow();
  };

  /** 按行前缀插入 (引用等) */
  const prefixLines = (marker) => {
    const start = textarea.selectionStart ?? 0;
    const lineStart = textarea.value.lastIndexOf('\n', Math.max(0, start - 1)) + 1;

    textarea.value = textarea.value.slice(0, lineStart) + marker + textarea.value.slice(lineStart);
    textarea.focus();
    textarea.setSelectionRange(start + marker.length, start + marker.length);
    autoGrow();
  };

  const insertText = (text) => {
    const start = textarea.selectionStart ?? textarea.value.length;
    const end = textarea.selectionEnd ?? start;

    textarea.value = textarea.value.slice(0, start) + text + textarea.value.slice(end);
    textarea.focus();
    textarea.setSelectionRange(start + text.length, start + text.length);
    autoGrow();
  };

  /* 一律用「元素级」的一次性标记, 不用整个表单的标记:
     提交成功后只换掉评论列表那一段并重跑增强 (见 refreshComments), 表单节点不变、
     但列表里是全新节点 —— 表单级标记会让新评论的「回复」再也不被接手。
     标记写在节点自己身上, 于是同一个节点只会绑一次。 */
  const bindOnce = (element, key, handler) => {
    const flag = 'bound' + key;

    if (element.dataset[flag]) {
      return;
    }

    element.dataset[flag] = '1';
    element.addEventListener('click', handler);
  };

  if (!textarea.dataset.boundGrow) {
    textarea.dataset.boundGrow = '1';
    textarea.addEventListener('input', autoGrow);
  }

  autoGrow();

  form.querySelectorAll('[data-comment-format]').forEach((button) => {
    bindOnce(button, 'Format', () => {
      switch (button.dataset.commentFormat) {
        case 'bold':
          insert('**', '**', '粗体');
          break;
        case 'italic':
          insert('_', '_', '斜体');
          break;
        case 'link':
          insert('[', '](https://)', '链接文字');
          break;
        case 'code':
          insert('`', '`', 'code');
          break;
        case 'quote':
          prefixLines('> ');
          break;
        default:
          break;
      }
    });
  });

  const picker = form.querySelector('.emoji-picker');
  form.querySelectorAll('.emoji-item').forEach((button) => {
    bindOnce(button, 'Emoji', () => {
      insertText(button.dataset.emoji || '');
      if (picker) {
        picker.open = false;
      }
    });
  });

  /* ---------- 回复 / 取消回复 ----------
     把 Typecho 的「回复」搬进这里自己接管, 而不是依赖评论列表里的内联 onclick:

       1. 内联 onclick 一旦抛错就不再返回 false, 浏览器会真的跟着 href 跳一次;
          而 swup 的 ignoreVisit 见 ?replyTo= 会调 location.assign(), 于是整页重载。
          位置也很巧: 只有「当前 URL 与链接不同」的第一次点击才会重载,
          此后再点 (URL 已带 ?replyTo=) 看着就像没反应 —— 正是那种「第一次卡一下」。
       2. 我们自己的处理器一定 preventDefault, 有没有 swup、TypechoComment 有没有就绪
          都不影响; 顺带把 Swup 的地址栏更新也一起压掉 (见 shouldIgnoreVisit)。
       3. 没有 JS 时模板里的内联 onclick 仍然照旧工作。 */
  if (respond) {
    comments.querySelectorAll('a[onclick*="TypechoComment.reply"]').forEach((link) => {
      bindOnce(link, 'Reply', (event) => {
        /* preventDefault 在前, 后面任何一步失败都不会变成整页跳转 */
        event.preventDefault();

        const match = /reply\(\s*'([^']+)'\s*,\s*(\d+)/.exec(link.getAttribute('onclick') || '') || [];
        const target = match[1] ? document.getElementById(match[1]) : null;
        const coid = match[2] || '';
        const parent = parentField(true);

        if (parent) {
          parent.value = coid;
        }

        if (target) {
          /* 先钉好占位节点再搬走评论框, 这样「取消回复」一定能把它送回原位 */
          holderFor();
          target.appendChild(respond);
        } else {
          /* 找不到评论节点时退回「搬到评论列表末尾」, 至少不丢回复动作 */
          holderFor();
          comments.appendChild(respond);
        }

        if (cancelLink) {
          cancelLink.style.display = '';
        }

        autoGrow();
        textarea.focus();
      });
    });

    if (cancelLink) {
      bindOnce(cancelLink, 'Cancel', (event) => {
        event.preventDefault();

        const holder = comments.querySelector('[data-respond-holder]');
        const parent = parentField(false);

        if (parent) {
          parent.remove();
        }

        if (holder && holder.parentNode) {
          holder.parentNode.insertBefore(respond, holder);
          holder.remove();
        }

        cancelLink.style.display = 'none';
        autoGrow();
      });
    }
  }

  /* ---------- 提交评论: 自己接管, 不整页跳转 ----------
     TypechoRe 的评论提交是普通表单 POST, 服务端回 302 跳到评论锚点 —— 原生提交就是
     一次整页导航。这里改成 fetch: 成功后就地刷新评论列表, 失败的提示写进 notice-box。
     字段仍按表单原样提交 (action 里已经带 CSRF token), 所以服务端行为不变。 */
  const noticeBox = document.getElementById('notice-box');
  const submitButton = form.querySelector('button[type="submit"]');

  const showNotice = (message, className) => {
    if (!noticeBox) {
      return;
    }

    noticeBox.className = className;
    noticeBox.textContent = message;
    noticeBox.hidden = false;
  };

  const clearNotice = () => {
    if (noticeBox) {
      noticeBox.hidden = true;
      noticeBox.textContent = '';
    }
  };

  const setBusy = (busy) => {
    if (submitButton) {
      submitButton.disabled = busy;
    }
    form.classList.toggle('is-submitting', busy);
  };

  /* 只为用户输入错误的兜底提示; 真正显示什么由服务端的 __typecho_notice cookie 决定 */
  const SUBMIT_FAILED = form.getAttribute('data-submit-failed') || '提交失败, 请稍后再试。';

  /**
   * 就地刷新评论列表
   *
   * 提交成功后评论区里会多出一条评论 (或审核提示), 但重载整页会把无刷新切页的体验
   * 抹掉。这里只取同一地址的评论那一段换进来; respond 容器里的内容会被原样搬回,
   * 于是用户已经输入的东西与回复状态都不会丢。任何一步失败都静默放弃 —— 宁可评论
   * 列表晚一步更新, 也不能因此报错。
   *
   * @param {string} url 要抓取的地址 (通常是当前页)
   * @param {string} jumpTo 新评论的锚点 id
   */
  const refreshComments = async (url, jumpTo) => {
    const previousRespond = respond || comments.querySelector('.respond');
    /* 占位节点是回复流程的锚, 换列表时要把它一起搬回去 */
    const previousHolder = comments.querySelector('[data-respond-holder]');

    try {
      const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch' }
      });

      if (!response.ok) {
        return;
      }

      const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
      const incoming = doc.getElementById('comments');

      if (!incoming) {
        return;
      }

      const freshList = incoming.querySelector('.comment-list');
      /* 服务端把最新页码放在标题下面的分页里, 这里跟着回第一页 */
      const freshNav = incoming.querySelector('.page-navigator');
      const currentList = comments.querySelector('.comment-list');
      const currentNav = comments.querySelector('.page-navigator');
      /* 「暂无评论」提示在空评论区里; 空评论区抓回来的模板里没有 .comment-list,
         这时退化成整体替换整段 (#comments 的内部), 只是别忘了把评论框搬回去 */
      const anchor = currentList || currentNav;

      if (anchor && freshList) {
        anchor.replaceWith(document.importNode(freshList, true));

        if (freshNav && currentNav) {
          currentNav.replaceWith(document.importNode(freshNav, true));
        }
      } else if (anchor) {
        anchor.remove();
      } else {
        /* 之前没有可锚定的元素: 用新评论区的子节点重铺一遍 */
        const fragment = document.createDocumentFragment();

        incoming.childNodes.forEach((node) => {
          if (node.nodeType === 1 && node.id === previousRespond?.id) {
            return;
          }
          fragment.appendChild(document.importNode(node, true));
        });

        comments.textContent = '';
        comments.appendChild(fragment);
      }

      /* 评论框搬回原位: 保留用户输入 / 焦点 / 回复状态 */
      if (previousRespond) {
        const after = comments.querySelector('.comment-list') || comments.querySelector('.page-navigator');

        if (after) {
          after.after(previousRespond);
        } else if (comments.firstChild) {
          comments.insertBefore(previousRespond, comments.firstChild);
        } else {
          comments.appendChild(previousRespond);
        }
      }

      if (previousHolder && previousHolder.parentNode === null && previousRespond) {
        previousRespond.after(previousHolder);
      }

      /* 新列表里的评论脚本 (回复链接等) 由增强逻辑重新接手 */
      enhanceNotice();
      enhanceCommentBox();

      if (jumpTo) {
        const target = document.getElementById(jumpTo);

        if (target) {
          target.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'center' });
        }
      }
    } catch (error) {
      /* 网络失败: 保持现状, 用户还可以自己刷新 */
    }
  };

  /* 提交监听只挂一次: 这个函数会在每次就地刷新评论列表后重跑 (表单节点不变),
     重复挂载会让一次提交发出多个 POST。 */
  if (!form.dataset.boundSubmit) {
    form.dataset.boundSubmit = '1';
    form.addEventListener('submit', async (event) => {
    /* 先拦下来: 后面无论成功失败都不会变成整页跳转 */
    event.preventDefault();

    clearNotice();
    setBusy(true);

    try {
      const response = await fetch(form.getAttribute('action') || window.location.href, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        redirect: 'follow',
        headers: { 'X-Requested-With': 'fetch' }
      });

      if (!response.ok) {
        showNotice(SUBMIT_FAILED, NOTICE_ERROR_CLASS);
        return;
      }

      /* 服务端把「评论提交成功 / 需要审核」写在 __typecho_notice cookie 里,
         由 enhanceNotice() 负责显示 (与整页提交后的表现一致)。 */
      enhanceNotice();

      /* 审核中的评论不会出现在列表里, 这时给一句明确的回执 */
      if (!noticeBox || noticeBox.hidden) {
        showNotice(
          form.getAttribute('data-waiting-text') || '评论已提交。',
          NOTICE_INFO_CLASS
        );
      }

      const waiting = document.cookie.includes('__typecho_unapproved_comment');
      const jumpTo = waiting ? '' : (form.getAttribute('data-comment-anchor') || '');

      textarea.value = '';
      autoGrow();
      await refreshComments(window.location.href, jumpTo);
    } catch (error) {
      showNotice(SUBMIT_FAILED, NOTICE_ERROR_CLASS);
    } finally {
      setBusy(false);
    }
    });
  }
}

/* 点击别处收起表情面板 (事件委托, 只需注册一次) */
document.addEventListener('click', (event) => {
  document.querySelectorAll('.emoji-picker[open]').forEach((picker) => {
    if (!picker.contains(event.target)) {
      picker.open = false;
    }
  });
});

/** 每次页面内容替换后需要重新执行的增强 */
function enhancePage() {
  enhanceCodeBlocks();
  enhanceOverflow();
  enhanceTocSpy();
  enhanceNotice();
  enhanceCommentForm();
  enhanceCommentBox();
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

  /* 跨域、后台等交给浏览器原生跳转。
     注意 data-no-swup: 这类链接的默认行为已经由我们自己的处理器取消 (评论回复 /
     取消回复), 不能让 swup 再 location.assign() 一次 —— 那就是整页重载。 */
  return target.origin !== window.location.origin
    || IGNORED_PATH.test(target.pathname)
    || (target.searchParams.has('replyTo') && !element.closest('[data-no-swup]'))
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
/** 第二列 (侧边栏 / 文章目录) 的稳定标记, 骨架屏与切页同步都用它 */
const ASIDE_SELECTOR = '.page-aside';
/** 本次跳转真的换了第二列时挂在 <html> 上, 用来决定要不要给它播入场动画 */
const ASIDE_SWAPPED_CLASS = 'is-aside-swapped';
/** 本次骨架屏不画第二列、而当前页那一列又确实存在时挂在 <html> 上 (见 syncAsideKept) */
const ASIDE_KEPT_CLASS = 'is-aside-kept';
/** 第二列「不画骨架, 但也不许被遮罩盖住」的那一档判定结果 (见 asideKindForSession) */
const ASIDE_KEEP = 'keep';
/** 模板写在链接上的目标页第二列形态提示 (见 post-card.php) */
const ASIDE_HINT_ATTR = 'data-aside-target';

let skeletonTimer = 0;
let skeletonHideTimer = 0;
let skeletonVisible = false;
/* 本次骨架屏的第二列画哪一种: none 不画 | keep 不画且把真实那一列留在画面上
   | sidebar / toc 画对应骨架 (由 asideKindForSession 在 visit:start 判定) */
let skeletonNextAsideKind = 'none';
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
  syncSkeletonAside(skeletonNextAsideKind);
  skeletonVisible = true;
  overlay.hidden = false;
  /* 这次不画第二列时, 把「本来就不会变」的那一列托到遮罩之上 (为什么不是每次: 见 syncAsideKept) */
  syncAsideKept();
  /* 等一帧再加类, 否则 hidden 刚取消时过渡不会触发。
     这一帧里可能已经被 hideSkeleton 收掉了 (超快跳转), 所以要再确认一次,
     否则会把 is-visible 加回来, 让遮罩在该消失的时候又亮一下。 */
  window.requestAnimationFrame(() => {
    if (skeletonVisible) {
      overlay.classList.add('is-visible');
    }
  });
}

/**
 * 第二列 (侧边栏 / 文章目录) 该不该出现, 以及该画成哪一种
 *
 * @param {'none'|'keep'|'sidebar'|'toc'} kind
 */
function syncSkeletonAside(kind) {
  const asideSkeleton = document.querySelector('[data-skeleton-aside]');

  if (!asideSkeleton) {
    return;
  }

  const wanted = 'toc' === kind || 'sidebar' === kind ? kind : 'none';

  asideSkeleton.dataset.asideKind = wanted;
  asideSkeleton.hidden = 'none' === wanted;

  /* 侧边栏 / 目录两种骨架都先渲染好, 这里只切换显隐 (见 footer.php) */
  asideSkeleton.querySelectorAll('[data-skeleton-aside-variant]').forEach((variant) => {
    variant.hidden = variant.getAttribute('data-skeleton-aside-variant') !== wanted;
  });
}

/**
 * 骨架屏不画第二列时, 把真实的那一列浮到遮罩之上
 *
 * 遮罩是一层不透明的固定层, 铺满导航之下的整个视口。侧边栏在「列表页 → 列表页」时
 * 一个字都不会变 (见 syncPageAside), 骨架屏里也就不画它 —— 但遮罩照样会把它盖成一块
 * 空白, 等遮罩淡出再露回来, 看起来就是侧边栏闪了一下。这里给 <html> 挂上
 * is-aside-kept, CSS 据此把那一列提到遮罩之上; 遮罩彻底收掉后一起摘掉。
 *
 * 只有判成 ASIDE_KEEP ('keep') 时才托: kind 为 none 表示这一列本来就没有 (或要被移除),
 * 没有东西可托, 也不该把它留在画面上。
 *
 * 显隐跟着 overlay.hidden 走: 淡出那 200ms 里遮罩还在画面上, 这一列必须一直浮着,
 * 否则又会被盖回去, 等于没修。
 */
function syncAsideKept() {
  const overlay = document.getElementById('page-skeleton');
  const kept = !!overlay && !overlay.hidden
    && ASIDE_KEEP === skeletonNextAsideKind
    && null !== document.querySelector(ASIDE_SELECTOR);

  document.documentElement.classList.toggle(ASIDE_KEPT_CLASS, kept);
}

/**
 * 当前页面第二列的形态 —— 直接看 DOM 里真实的那一列
 *
 * 不读 #page-skeleton 上的 data-aside-kind: 那是服务端首屏渲染时写下的, 而无刷新切页
 * 会把这一列整块换掉、没有人回头更新那个属性 (recordAsideKind 只是把它补上),
 * 于是从第二次跳转起判定就会一直用首屏的形态 —— 首屏是列表页时, 从「有目录的文章」
 * 切回列表页也会被当成「当前是侧边栏」。
 *
 * @returns {'none'|'sidebar'|'toc'}
 */
function currentAsideKind() {
  const aside = document.querySelector(ASIDE_SELECTOR);

  return aside && aside.dataset.aside ? aside.dataset.aside : 'none';
}

/**
 * 从链接上读出目标页第二列的形态 (拿不到就算了)
 *
 * 模板知道目标页会渲染成什么样 (post-card.php 按那篇文章有没有目录写好提示),
 * 而 swup 在 `visit:start` 时还拿不到目标文档 (fetch + DOMParser 都在之后) ——
 * 链接上这个提示是唯一能在等待开始前就确定答案的来源。
 *
 * @param {object} [visit] swup 的 visit 对象
 * @returns {'none'|'sidebar'|'toc'|null} 认不出来时为 null
 */
function targetAsideKind(visit) {
  const el = visit && visit.trigger ? visit.trigger.el : null;
  const hinted = el && 'function' === typeof el.closest ? el.closest(`[${ASIDE_HINT_ATTR}]`) : null;
  const kind = hinted ? hinted.getAttribute(ASIDE_HINT_ATTR) : '';

  return 'none' === kind || 'sidebar' === kind || 'toc' === kind ? kind : null;
}

/**
 * 按目标形态判断这次跳转的第二列骨架该画哪一种
 *
 * 关键约束: `visit:start` 时 **拿不到目标文档** —— swup 要等 fetch + DOMParser 之后才有
 * `visit.to.document` (早期版本读它, 结果每次都判成「会换」)。所以答案只能来自两处:
 * 链接上的提示 (targetAsideKind, 精确) 与当前页自己的形态 (currentAsideKind)。
 *
 * 判定结果有四档:
 *
 *   keep    —— 不画骨架, 把真实那一列留在遮罩之上 (它不会变, 见 syncAsideKept);
 *   none    —— 不画, 也不托 (当前页没有这一列, 或目标页没有);
 *   toc / sidebar —— 画对应骨架: 这一列真的会换, 该被遮住再换成新的。
 *
 * 「目录」这一档每次都要重画 (每篇文章的目录都不同; 同一页内的锚点跳转不会走到这里);
 * 「侧边栏」是全站数据, 两边都是它时一个字都不会变。
 *
 * 拿不到提示、目标是文章 / 独立页面时, 无法提前知道那篇有没有目录 —— 这时**必须保守**:
 * 当前页正是侧边栏的话, 它很可能一个字都不变, 那就按 keep 处理、别画骨架。
 * 反过来 (一律按目录画) 会让「首页 → 没有目录的文章」先用目录骨架盖住侧边栏,
 * 等遮罩淡出再露回侧边栏 —— 侧边栏没变却闪了一下, 就是线上报的那个 bug。
 *
 * @param {'list'|'single'} sessionKind
 * @param {object} [visit] swup 的 visit 对象 (用来读链接上的形态提示)
 * @returns {'none'|'keep'|'sidebar'|'toc'}
 */
function asideKindForSession(sessionKind, visit) {
  const current = currentAsideKind();
  const target = targetAsideKind(visit);

  /* 链接给了精确答案: 两边形态一样就不动它, 不一样就画目标的骨架 */
  if (target) {
    if ('toc' === target) {
      return 'toc';
    }

    return target === current ? ASIDE_KEEP : target;
  }

  /* 目标形态未知: 列表页的第二列必定是侧边栏 (与当前页无关);
     文章 / 独立页面则只有「当前页不是侧边栏」时才敢按目录画 —— 是侧边栏就有可能不变。 */
  const guessed = 'list' === sessionKind ? 'sidebar' : ('sidebar' === current ? null : 'toc');

  if (null === guessed) {
    return ASIDE_KEEP;
  }

  /* 侧边栏是全站数据: 两边都是它时一个字都不会变 (目录每篇都不同, 必须重画) */
  return 'sidebar' === guessed && 'sidebar' === current ? ASIDE_KEEP : guessed;
}

/**
 * 把当前第二列的形态补写回 #page-skeleton 的 data-aside-kind
 *
 * footer.php 只在服务端渲染时写一次, 而切页会换掉这一列 —— 判定不再读它
 * (见 currentAsideKind), 但让这个属性与实际页面保持一致, 排查问题时才不会被误导。
 */
function recordAsideKind() {
  const overlay = document.getElementById('page-skeleton');

  if (overlay) {
    overlay.dataset.asideKind = currentAsideKind();
  }
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
      /* 连第二列一起收回: 上一次留下的显隐状态不该带到下一次跳转 */
      syncSkeletonAside('none');
      /* 遮罩已经收掉, 真实那一列不必再浮着 */
      syncAsideKept();
    }
  }, animSettings().skeletonFade);
}

/**
 * 排一次骨架屏
 *
 * @param {'list'|'single'} kind 形态
 * @param {'none'|'keep'|'sidebar'|'toc'} asideKind 第二列怎么处理 (none / keep = 不画)
 */
function scheduleSkeleton(kind, asideKind) {
  hideSkeleton();

  /* 主题设置里关掉骨架屏后, 等待期间不显示任何占位 */
  if (!animSettings().skeleton) {
    return;
  }

  skeletonNextAsideKind = asideKind || 'none';
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

  if (current && incoming && !asideUnchanged(current, incoming)) {
    current.replaceWith(incoming.cloneNode(true));
    markAsideSwapped();
  } else if (current && !incoming) {
    current.remove();
  } else if (!current && incoming) {
    /* 当前页没有这一列: 按模板结构插到 #main 之后 (目标文档里的位置也一样) */
    const main = document.querySelector('#main');

    if (main) {
      main.after(incoming.cloneNode(true));
      markAsideSwapped();
    }
  }

  recordAsideKind();
}

/**
 * 两边的第二列是不是同一块 (只有侧边栏能这么判)
 *
 * 侧边栏六个区块都是全站数据, 内容与当前页面无关 (实测首页与文章页的侧边栏 HTML
 * 逐字节相同), 所以同类型时不重建: 重建会整列重绘, 还会丢掉 hover / 焦点状态,
 * 看起来就是侧边栏在闪, 也没必要为它播入场动画。目录则必须每次替换 —— 不同文章的
 * 目录不同。
 *
 * @param {Element} current 当前页的第二列
 * @param {Element} incoming 目标页的第二列
 * @returns {boolean}
 */
function asideUnchanged(current, incoming) {
  return 'sidebar' === current.dataset.aside && 'sidebar' === incoming.dataset.aside;
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

/** 同一个页面里的锚点跳转不会真正换页, 不必显示骨架也不需要入场动画 */function isSamePageHashVisit(visit) {
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
      const sessionKind = skeletonKind(visit.trigger && visit.trigger.el);
      /* 第二列会不会被换掉: 拿不到目标文档, 只能靠链接上的形态提示与当前页的形态
         判断, 拿不准时不画它的骨架 (见 asideKindForSession)。 */
      scheduleSkeleton(sessionKind, asideKindForSession(sessionKind, visit));
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
