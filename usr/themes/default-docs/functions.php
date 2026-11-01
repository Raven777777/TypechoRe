<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * highlight.js 的默认目录 (cdnjs 上的 11.x)
 */
const DOCSWEB_HLJS_DEFAULT = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.12.0';

/**
 * 主题设置
 */
function themeConfig(\Typecho\Widget\Helper\Form $form): void
{
    $hljsCdn = new \Typecho\Widget\Helper\Form\Element\Text(
        'hljsCdn',
        null,
        DOCSWEB_HLJS_DEFAULT,
        _t('highlight.js 地址'),
        _t('highlight.js 11 的目录地址, 例如 https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.12.0; 主题从这里加载 highlight.min.js 与 styles/github-dark.min.css, 可以换成自建镜像或站内相对路径, 留空恢复默认')
    );
    $form->addInput($hljsCdn);
}

/**
 * highlight.js 目录 (去掉结尾斜杠, 留空用默认值)
 */
function docswebHljsCdn(): string
{
    $value = \Widget\Options::alloc()->hljsCdn;
    $cdn = is_scalar($value) ? rtrim(trim((string) $value), '/') : '';

    return '' === $cdn ? DOCSWEB_HLJS_DEFAULT : $cdn;
}

/**
 * 置顶标记 (插件 Sticky 把配置里的 HTML 原样放在 sticky_html 字段)
 */
function docswebStickyBadge(\Widget\Base\Contents $content): string
{
    // 插件未启用时这两个字段不存在 (行数据里的动态键, 用 isset 探测)
    if (!isset($content->sticky, $content->sticky_html)) {
        return '';
    }

    $html = trim((string) $content->sticky_html);

    return $content->sticky && '' !== $html ? ' ' . $html : '';
}

/**
 * 输出转义好的链接
 */
function docswebLink(string $url, string $text): string
{
    return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</a>';
}

/**
 * docsweb 风格分页: Page 2 / 5 · Up · Down · Category · Archive
 */
function docswebPager(\Widget\Archive $archive): void
{
    $page = $archive->getCurrentPage();
    $total = $archive->getTotalPage();
    $bits = [];

    if ($total > 1) {
        $bits[] = 'Page ' . $page . ' / ' . $total;
        foreach (['prev' => 'Up', 'next' => 'Down'] as $rel => $label) {
            ob_start();
            $archive->pageLink($label, $rel);
            $link = trim((string) ob_get_clean());
            if ('' !== $link) {
                $bits[] = $link;
            }
        }
    }

    foreach (['Category' => 'categories', 'Archive' => 'archives', 'Tags' => 'tags'] as $label => $slug) {
        $bits[] = '<a href="' . htmlspecialchars(docswebIndexUrl($slug), ENT_QUOTES, 'UTF-8') . '">' . $label . '</a>';
    }

    if ($bits) {
        echo '<nav>' . implode(' · ', $bits) . '</nav>';
    }
}

/**
 * 分类/归档/标签索引页的地址（主题认领这些地址，不建独立页面）
 */
function docswebIndexUrl(string $slug): string
{
    return \Typecho\Router::url('page', ['slug' => $slug], \Widget\Options::alloc()->index);
}

/**
 * 认领 /categories.html、/archives.html、/tags.html：
 * Typecho 只给已存内容路由，未知地址会落到 404 分支，这里改成 200 再渲染索引模板。
 */
function themeInit(\Widget\Archive $archive): void
{
    $slug = preg_replace('/\.html$/i', '', basename(trim((string) $archive->request->getPathInfo(), '/')));
    $titles = ['categories' => 'Categories', 'archives' => 'Archives', 'tags' => 'Tags'];

    if (!isset($titles[$slug]) || !is_file(__DIR__ . '/page-' . $slug . '.php')) {
        return;
    }

    $archive->response->setStatus(200);
    $archive->setArchiveTitle($titles[$slug]);
    $archive->need('page-' . $slug . '.php');
    exit;
}

