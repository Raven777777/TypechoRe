/**
 * 评论功能的 PHP 侧契约测试 (纯 Node, 不需要 PHP 运行时)
 *
 * 运行: npm test
 *
 * 这一块有两个曾经真实出问题的约定, 都是「改了源码但看不出坏」的类型:
 *
 *  1. **Markdown 渲染**: TypechoRe 的 `Comments::___content()` 最后会调
 *     `Common::stripTags($html, '<p><br>' . $options->commentsHTMLTagAllowed)`,
 *     而 `commentsHTMLTagAllowed` 默认为空 —— 于是 Markdown 渲染出来的
 *     strong / a / pre / ul 会被整段剥掉, 用户看到的就是「Markdown 没生效」。
 *     主题因此在 `contentEx` 上重做一次渲染并自带白名单 (functions.php)。
 *     这里钉住: 白名单必须覆盖 Markdown 会生成的标签, 且渲染必须由
 *     `commentsMarkdown` 把关。
 *  2. **工具条与设置同步**: 站点关掉 Markdown 后, 评论框不能再显示
 *     「支持 Markdown」和 B / I / 链接等按钮 (comments.php)。
 *
 * 还有一条与骨架屏有关: 第二列骨架必须与 list/single 形态面板**平级**,
 * 不能挂在面板里面 —— 挂在里面就会跟着形态切换, 于是「侧边栏没变」的跳转
 * 也会闪一块侧边栏骨架 (footer.php)。
 */
import { readFileSync } from 'node:fs';

const root = new URL('../', import.meta.url);
const php = readFileSync(new URL('functions.php', root), 'utf8');
const comments = readFileSync(new URL('comments.php', root), 'utf8');
const footer = readFileSync(new URL('footer.php', root), 'utf8');

let failures = 0;
const check = (label, ok, extra = '') => {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${extra ? '  ' + extra : ''}`);
  if (!ok) failures++;
};

/** 取出某个顶层函数的函数体 (从 function 名 到下一个顶格 }) */
function functionBody(source, name) {
  const start = source.indexOf(`function ${name}(`);

  if (start < 0) {
    return '';
  }

  const end = source.indexOf('\n}', start);

  return end < 0 ? source.slice(start) : source.slice(start, end);
}

/** 把 <p> <a href> 这种白名单语法拆成标签名集合 */
function allowedTags(spec) {
  return new Set([...spec.matchAll(/<([a-z0-9]+)[^>]*>/gi)].map((match) => match[1].toLowerCase()));
}

/** 取出白名单标签各自允许的属性 (与 Typecho\Common::stripTags 的语义一致) */
function allowedAttributes(spec) {
  const map = new Map();

  for (const match of spec.matchAll(/<([a-z0-9]+)((?:\s+[a-z-]+)*)>/gi)) {
    map.set(match[1].toLowerCase(), new Set(match[2].trim().split(/\s+/).filter(Boolean)));
  }

  return map;
}

/**
 * 复刻 Typecho\Common::stripTags() 的判定 (见 var/Typecho/Common.php):
 * 不在白名单里的标签整段丢掉, 属性只留白名单里列出的那些。
 *
 * 这样就能在 Node 里验证「Markdown 的输出经过服务端过滤后还剩什么」——
 * 线上那个 bug 正是被这一步把整段标签剥掉的。
 */
function stripTags(html, spec) {
  const tags = allowedTags(spec);
  const attributes = allowedAttributes(spec);

  return html.replace(/<\/?([a-z0-9]+)((?:\s+[^>]*)?)>/gi, (whole, rawTag, rawAttrs) => {
    const tag = rawTag.toLowerCase();

    if (!tags.has(tag)) {
      return '';
    }

    if ('/' === whole[1]) {
      return `</${tag}>`;
    }

    const kept = [...(rawAttrs || '').matchAll(/([a-z-]+)(?:="[^"]*")?/gi)]
      .map((match) => match[1].toLowerCase())
      .filter((name) => (attributes.get(tag) || new Set()).has(name));

    return '<' + tag + (kept.length ? ' ' + kept.join(' ') : '') + '>';
  });
}

/* ---------- 1. 评论 Markdown 渲染 ---------- */
{
  check('functions.php 定义了 themeCommentAllowedTags()', php.includes('function themeCommentAllowedTags('));
  check('functions.php 定义了 themeStripCommentTags()', php.includes('function themeStripCommentTags('));

  /* 白名单是拼接出来的多个字符串字面量, 全部取出再拼 */
  const spec = [...functionBody(php, 'themeCommentAllowedTags').matchAll(/'([^']*<[^']*)'/g)].map((match) => match[1]).join(' ');
  check('白名单是一段可解析的标签串', '' !== spec, spec);

  const tags = allowedTags(spec);

  /* Markdown 真正会用到的标签: 少一个就意味着那种语法被静默吃掉 */
  const needed = ['p', 'br', 'strong', 'em', 'del', 'code', 'pre', 'blockquote', 'ul', 'ol', 'li', 'a'];

  for (const tag of needed) {
    check(`★ 白名单允许 <${tag}> (Markdown 会生成)`, tags.has(tag), [...tags].join(','));
  }

  /* 不允许任何能执行脚本 / 带事件的标签 */
  for (const tag of ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'img']) {
    check(`白名单不含 <${tag}>`, !tags.has(tag));
  }

  check(
    '★ 链接只允许 href / title / rel 三个属性',
    /<a href title rel>/.test(spec),
    spec.match(/<a[^>]*>/)?.[0] || '未找到 <a> 声明'
  );

  /* ★ 拿一段真实的 Markdown 输出来过一遍服务端过滤:
     这就是线上「Markdown 没生效」的现场 —— 白名单缺哪个标签, 那一块就会被整段剥掉。 */
  const markdownOutput = [
    '<h2>小标题</h2>',
    '<p>正文里有 <strong>粗体</strong>、<em>斜体</em>、<del>删除线</del> 和 <code>code</code>。</p>',
    '<blockquote><p>引用</p></blockquote>',
    '<ul><li>列表项</li></ul>',
    '<ol><li>有序项</li></ol>',
    '<pre><code>echo 1;</code></pre>',
    '<p><a href="https://example.com" title="链接" rel="nofollow">链接</a></p>',
    '<hr>'
  ].join('\n');

  const kept = stripTags(markdownOutput, spec);

  for (const snippet of ['<strong>粗体</strong>', '<em>斜体</em>', '<del>删除线</del>', '<code>code</code>', '<blockquote>', '<ul>', '<li>列表项</li>', '<ol>', '<pre>', '<h2>小标题</h2>', '<hr>', '<a href']) {
    check(`★ 过滤后仍保留 ${snippet}`, kept.includes(snippet), kept.replace(/\n/g, ' '));
  }

  /* 白名单里没有的属性必须被丢掉 (不能把 onclick 之类带进来) */
  const withEvent = stripTags('<p><a href="/x" onclick="evil()">x</a></p>', spec);
  check('★ 过滤后丢掉了白名单外的属性 (onclick)', !withEvent.includes('onclick'), withEvent);

  /* 非白名单标签整段消失 */
  const withScript = stripTags('<p>ok</p><script>alert(1)</script><img src=x onerror=y>', spec);
  check('★ 过滤后丢掉了 script / img', !withScript.includes('<script') && !withScript.includes('<img'), withScript);

  check('过滤走的是 Typecho 自己的 stripTags (与内核同一套规则)', functionBody(php, 'themeStripCommentTags').includes('Common::stripTags'));

  /* 接线: contentEx 钩子 + commentsMarkdown 把关 + 用原始文本重渲染 */
  const init = functionBody(php, 'themeInit');
  check('★ themeInit() 在 contentEx 上注册了过滤器', init.includes("filter('contentEx'"), init.includes('contentEx') ? '' : '未注册');
  check('★ 渲染由 commentsMarkdown 把关 (关掉时原样返回)', init.includes('commentsMarkdown') && init.includes('return (string) $content'));
  check('★ 用评论原始文本重渲染, 而不是已经剥过标签的 HTML', init.includes('$comment->text ?: $content'));
  check('重渲染前先做 XSS 过滤', init.includes('themeStripCommentTags('));
  check('用的还是内核的 Markdown 解析器', init.includes('Markdown::convert'));
}

/* ---------- 2. 评论框的 Markdown 工具条跟随设置 ---------- */
{
  check(
    '★ 工具条按钮被 commentsMarkdown 包起来',
    /commentsMarkdown\s*\)\s*:\s*\?>[\s\S]*?data-comment-format="bold"[\s\S]*?endif;\s*\?>/.test(comments),
    '模板里没看到把按钮包在 if 里的结构'
  );
  check('提示语随设置切换', comments.includes('支持 Markdown · 提交后可能需要审核') && comments.includes("_t('提交后可能需要审核')"));
  check('表情面板不受 Markdown 设置影响 (始终可用)', /<details class="emoji-picker">/.test(comments));

  /* 提交走主题自己的 fetch: 表单必须带标记, 且 JS 里有 preventDefault */
  check('★ 表单带 data-comment-submit 标记', comments.includes('data-comment-submit'));
  check('表单带新评论锚点 (提交后就地跳转)', comments.includes('data-comment-anchor='));
  check('表单带失败与审核中的文案', comments.includes('data-submit-failed=') && comments.includes('data-waiting-text='));
}

/* ---------- 3. 第二列骨架不跟着形态面板切换 ---------- */
{
  const asideIndex = footer.indexOf('data-skeleton-aside');
  check('footer.php 输出了第二列骨架', asideIndex > 0);

  /* 形态面板的区间: list 面板开始 → single 面板结束 */
  const panelStart = footer.indexOf('data-skeleton-panel="list"');
  const panelEnd = footer.indexOf('data-skeleton-aside');

  check('★ 第二列骨架写在形态面板之后 (与它们平级, 不挂在面板里面)', asideIndex > panelStart && asideIndex > (footer.lastIndexOf('data-skeleton-panel="single"')), `aside=${asideIndex} panelStart=${panelStart}`);

  /* 两种形态都预渲染好, 由 JS 切换 */
  check('同时输出侧边栏与目录两种骨架', footer.includes('data-skeleton-aside-variant="sidebar"') && footer.includes('data-skeleton-aside-variant="toc"'));
  check('两种骨架默认都是隐藏的', (footer.match(/data-skeleton-aside-variant="(?:sidebar|toc)" hidden/g) || []).length === 2);
  check('本页第二列形态写在 data-aside-kind 上 (供 JS 判定)', footer.includes('data-aside-kind="<?php echo themeEsc($skeletonAsideKind); ?>"'));
  check('形态由 themeAsideKind() 提供', php.includes('function themeAsideKind(') && footer.includes('themeAsideKind($this)'));

  /* 形态判定要覆盖三种情况 */
  const asideKind = functionBody(php, 'themeAsideKind');
  check('形态判定: 单栏布局 → none', asideKind.includes("'full' === themeOption('layout', 'sidebar')") && asideKind.includes("return 'none'"));
  check('形态判定: 有目录的文章页 → toc', asideKind.includes("'toc'"));
  check('形态判定: 其余 → sidebar', asideKind.includes("'sidebar'"));
}

console.log(`\n${failures === 0 ? '全部通过' : failures + ' 项失败'}`);
process.exit(failures === 0 ? 0 : 1);
