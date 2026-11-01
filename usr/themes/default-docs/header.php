<?php
/**
 * 主题模板作用域变量
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$pageNo = $this->getCurrentPage();
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars(str_replace('_', '-', (string) $this->options->lang), ENT_QUOTES, 'UTF-8'); ?>">
<head>
<meta charset="<?php $this->options->charset(); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php if ($pageNo > 1): ?>Page <?php echo $pageNo; ?> · <?php endif; ?><?php ob_start();
$this->archiveTitle([
    'category' => '%s',
    'search'   => '%s',
    'tag'      => '%s',
    'author'   => '%s'
], '', ' · ');
echo htmlspecialchars((string) ob_get_clean(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><?php $this->options->title(); ?></title>
<?php /* 代码高亮: highlight.js 从 CDN 加载 (defer 保证在本页脚本之前就绪), 地址可在主题设置里换;
   样式表先加载, 主题样式在后, 代码块的底色与内边距仍由 style.css 决定 */ ?>
<?php $hljsCdn = docswebHljsCdn(); ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($hljsCdn . '/styles/github-dark.min.css', ENT_QUOTES, 'UTF-8'); ?>">
<script defer src="<?php echo htmlspecialchars($hljsCdn . '/highlight.min.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<link rel="stylesheet" href="<?php $this->options->themeUrl('style.css'); ?>">
<script>
// 顺便给代码块加语言名和复制按钮 (着色交给 highlight.js)
addEventListener('DOMContentLoaded', function () {
    function copyText(text, done) {
        var box = document.createElement('textarea');
        box.value = text;
        box.style.position = 'fixed';
        box.style.opacity = '0';
        document.body.appendChild(box);
        box.select();
        try { if (document.execCommand('copy')) done(); } catch (e) { }
        document.body.removeChild(box);
    }

    document.querySelectorAll('pre > code').forEach(function (code) {
        var name = (code.className.match(/(?:lang-|language-)([a-z0-9+#-]+)/i) || [])[1];
        // 作者手写的 class="php" 也要认，只接受 highlight.js 注册过的语言
        if (!name) {
            var bare = code.className.trim().split(/\s+/)[0];
            if (bare && window.hljs && hljs.getLanguage(bare)) name = bare;
        }
        name = ({'c++': 'cpp', 'c#': 'csharp'})[name] || name;
        if (name) code.className += ' language-' + name;

        var pre = code.parentNode;
        var wrap = document.createElement('div');
        wrap.className = 'code-block';
        pre.parentNode.insertBefore(wrap, pre);
        wrap.appendChild(pre);

        var tools = document.createElement('div');
        tools.className = 'code-tools';
        if (name) {
            var tag = document.createElement('span');
            tag.className = 'code-lang';
            tag.textContent = name;
            tools.appendChild(tag);
        }
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'code-copy';
        btn.textContent = 'copy';
        btn.addEventListener('click', function () {
            var done = function () {
                btn.textContent = 'copied';
                btn.dataset.copied = '1';
                setTimeout(function () {
                    btn.textContent = 'copy';
                    delete btn.dataset.copied;
                }, 1200);
            };
            var text = code.textContent;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done, function () { copyText(text, done); });
            } else {
                copyText(text, done);
            }
        });
        tools.appendChild(btn);
        wrap.appendChild(tools);
    });
    if (window.hljs) hljs.highlightAll();
});
</script>
<?php $this->header('generator=&template='); ?>
</head>
<body>
