<?php
/**
 * TypechoRe · Default 2026 主题函数 (未完成的预览版)
 *
 * 主题的模板结构、设置项与前端实现都还可能调整, 请勿直接用于正式站点。
 *
 * 设计原则:
 * - 模板只负责呈现, 逻辑集中在本文件, 便于主题使用者按需裁剪
 * - 所有输出都经过转义, 选项读取都有默认值, 缺少设置也不会报错
 * - 前端资源带 filemtime 版本号, 可长期缓存; 页面无需构建也能正常显示
 *
 * @package Default 2026 (Preview)
 * @author TypechoRe
 * @version 0.1.0-preview
 * @since 1.3.2
 * @link https://github.com/Raven777777/TypechoRe
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* =============================================================================
 * 主题设置
 * ========================================================================== */

/**
 * 注册主题设置面板
 *
 * @param \Typecho\Widget\Helper\Form $form
 */
function themeConfig(\Typecho\Widget\Helper\Form $form): void
{
    /* ---------------------------------------------------------------- 品牌 */
    $logoUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'logoUrl',
        null,
        null,
        _t('站点 LOGO 地址'),
        _t('填入图片 URL 后, 页头左侧显示 LOGO 图片, 留空则显示站点标题文字')
    );
    $form->addInput($logoUrl->addRule('url', _t('请填写一个合法的 URL 地址')));

    $faviconUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'faviconUrl',
        null,
        null,
        _t('站点图标 (favicon)'),
        _t('浏览器标签页图标地址, 留空则使用浏览器默认图标')
    );
    $form->addInput($faviconUrl->addRule('url', _t('请填写一个合法的 URL 地址')));

    /* ---------------------------------------------------------------- 外观 */
    $accentColor = new \Typecho\Widget\Helper\Form\Element\Text(
        'accentColor',
        null,
        '#4f6bf5',
        _t('强调色'),
        _t('十六进制颜色值, 如 #4f6bf5, 用于链接、按钮与高亮状态')
    );
    $form->addInput($accentColor);

    $colorScheme = new \Typecho\Widget\Helper\Form\Element\Radio(
        'colorScheme',
        [
            'auto'  => _t('跟随系统'),
            'light' => _t('始终浅色'),
            'dark'  => _t('始终深色')
        ],
        'auto',
        _t('默认外观'),
        _t('访客可以在页头手动切换, 选择结果会保存在浏览器本地')
    );
    $form->addInput($colorScheme);

    $fontStyle = new \Typecho\Widget\Helper\Form\Element\Select(
        'fontStyle',
        [
            'sans'  => _t('无衬线 (系统字体)'),
            'serif' => _t('衬线 (适合长文阅读)')
        ],
        'sans',
        _t('正文字体'),
        _t('全部使用系统字体, 不加载任何外部字体文件')
    );
    $form->addInput($fontStyle);

    $layout = new \Typecho\Widget\Helper\Form\Element\Radio(
        'layout',
        [
            'sidebar' => _t('内容 + 侧边栏'),
            'full'    => _t('单栏 (更宽的阅读区域)')
        ],
        'sidebar',
        _t('页面布局'),
        _t('文章页在开启目录时, 右侧显示目录而不是侧边栏')
    );
    $form->addInput($layout);

    /* ---------------------------------------------------------------- 文章 */
    $showToc = new \Typecho\Widget\Helper\Form\Element\Radio(
        'showToc',
        ['1' => _t('显示'), '0' => _t('不显示')],
        '1',
        _t('文章目录'),
        _t('自动为正文中的二至四级标题生成目录与锚点, 标题少于两个时不显示')
    );
    $form->addInput($showToc);

    $showReadingTime = new \Typecho\Widget\Helper\Form\Element\Radio(
        'showReadingTime',
        ['1' => _t('显示'), '0' => _t('不显示')],
        '1',
        _t('预计阅读时间'),
        _t('按中英文混排的字符数估算, 仅供参考')
    );
    $form->addInput($showReadingTime);

    $showRelated = new \Typecho\Widget\Helper\Form\Element\Radio(
        'showRelated',
        ['1' => _t('显示'), '0' => _t('不显示')],
        '1',
        _t('相关文章'),
        _t('按标签关联, 最多显示 3 篇')
    );
    $form->addInput($showRelated);

    $showBreadcrumbs = new \Typecho\Widget\Helper\Form\Element\Radio(
        'showBreadcrumbs',
        ['1' => _t('显示'), '0' => _t('不显示')],
        '1',
        _t('面包屑导航'),
        _t('在页面顶部显示当前位置层级, 同时会输出结构化数据')
    );
    $form->addInput($showBreadcrumbs);

    $excerptLength = new \Typecho\Widget\Helper\Form\Element\Number(
        'excerptLength',
        null,
        110,
        _t('列表摘要字数'),
        _t('列表页每张卡片显示的摘要字数, 建议 60 - 200')
    );
    $form->addInput($excerptLength);

    /* ------------------------------------------------------------ 侧边栏 */
    $sidebarBlock = new \Typecho\Widget\Helper\Form\Element\Checkbox(
        'sidebarBlock',
        [
            'ShowRecentPosts'    => _t('最新文章'),
            'ShowRecentComments' => _t('最近回复'),
            'ShowCategory'       => _t('分类'),
            'ShowArchive'        => _t('归档'),
            'ShowTagCloud'       => _t('标签'),
            'ShowOther'          => _t('其它 (登录 / RSS)')
        ],
        ['ShowRecentPosts', 'ShowRecentComments', 'ShowCategory', 'ShowArchive', 'ShowTagCloud', 'ShowOther'],
        _t('侧边栏内容'),
        _t('仅在「内容 + 侧边栏」布局下显示')
    );
    $form->addInput($sidebarBlock->multiMode());

    /* ---------------------------------------------------------------- 页脚 */
    $footerText = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'footerText',
        null,
        null,
        _t('页脚附加内容'),
        _t('支持 HTML, 可填写备案号、版权声明等, 例如: &lt;a href="https://beian.miit.gov.cn/"&gt;京ICP备 00000000 号&lt;/a&gt;')
    );
    $form->addInput($footerText);

    $customCss = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'customCss',
        null,
        null,
        _t('自定义 CSS'),
        _t('会在主题样式之后输出, 便于覆盖细节样式')
    );
    $form->addInput($customCss);
}

/**
 * 主题初始化: 在归档渲染前调整少量运行时设置
 *
 * @param \Widget\Archive $archive
 */
function themeInit(\Widget\Archive $archive): void
{
    /** 主题的嵌套评论视觉层次最多支持 4 层, 避免深层嵌套挤压正文宽度 */
    \Widget\Options::alloc()->commentsMaxNestingLevels = 4;
}

/* =============================================================================
 * 正文处理
 *
 * 说明: 这里不使用 Plugin::factory('Widget\Base\Contents')->contentEx 钩子。
 * 主题的 functions.php 由 Widget\Archive::execute() 在 singleHandle() 之后才载入,
 * 而 singleHandle() 会通过 archiveDescription = plainExcerpt 提前把 content 渲染
 * 并缓存, 导致主题注册的钩子永远错过正文。因此改为在 themeArticle() 里显式处理。
 * (插件不受影响: 插件在引导阶段就注册了钩子, 其过滤结果会在 $archive->content 中生效。)
 * ========================================================================== */

/**
 * 图片懒加载: 首图保留默认行为 (通常就是 LCP 元素), 其余图片延迟加载并异步解码
 *
 * @param string $html
 * @return string
 */
function themeLazyImages(string $html): string
{
    if (false === stripos($html, '<img')) {
        return $html;
    }

    $seen = 0;
    $result = preg_replace_callback(
        '#<img\b[^>]*>#i',
        static function (array $match) use (&$seen): string {
            $tag = $match[0];
            ++$seen;

            if (1 === $seen || false !== stripos($tag, 'loading=')) {
                return $tag;
            }

            $closing = str_ends_with($tag, '/>') ? '/>' : '>';
            $extra = ' loading="lazy" decoding="async"';

            return false === stripos($tag, 'decoding=')
                ? substr($tag, 0, -strlen($closing)) . $extra . $closing
                : $tag;
        },
        $html
    );

    return $result ?? $html;
}

/* =============================================================================
 * 选项读取
 * ========================================================================== */

/**
 * 读取主题选项 (带默认值)
 *
 * @param string $name
 * @param string $default
 * @return string
 */
function themeOption(string $name, string $default = ''): string
{
    $value = \Widget\Options::alloc()->{$name};

    if (null === $value || '' === $value || [] === $value) {
        return $default;
    }

    return is_scalar($value) ? (string) $value : $default;
}

/**
 * 读取主题选项并转换为布尔值
 *
 * @param string $name
 * @param bool $default
 * @return bool
 */
function themeOptionBool(string $name, bool $default = true): bool
{
    return '1' === themeOption($name, $default ? '1' : '0');
}

/**
 * 读取主题选项并转换为整数
 *
 * @param string $name
 * @param int $default
 * @return int
 */
function themeOptionInt(string $name, int $default): int
{
    return (int) themeOption($name, (string) $default);
}

/**
 * 读取多选主题选项
 *
 * @param string $name
 * @return string[]
 */
function themeOptionList(string $name): array
{
    $value = \Widget\Options::alloc()->{$name};

    if (is_array($value)) {
        return array_values(array_map('strval', $value));
    }

    return is_scalar($value) && '' !== $value ? [(string) $value] : [];
}

/**
 * HTML 转义输出
 *
 * @param mixed $value
 * @return string
 */
function themeEsc($value): string
{
    /** 请求参数可能是数组 (如 ?s[]=1), 这里直接忽略, 避免 Array to string 警告 */
    if (!is_scalar($value) && null !== $value) {
        return '';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * 强调色 (校验十六进制格式, 非法时回退到默认值)
 *
 * @return string
 */
function themeAccent(): string
{
    $accent = themeOption('accentColor', '#4f6bf5');

    return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $accent) ? $accent : '#4f6bf5';
}

/**
 * 默认外观模式
 *
 * @return string auto|light|dark
 */
function themeColorScheme(): string
{
    $scheme = themeOption('colorScheme', 'auto');

    return in_array($scheme, ['auto', 'light', 'dark'], true) ? $scheme : 'auto';
}

/* =============================================================================
 * 资源与图标
 * ========================================================================== */

/**
 * 主题资源地址 (自动追加 filemtime 版本号, 便于长期缓存)
 *
 * 返回值已进行 HTML 转义, 模板中直接输出即可。
 *
 * @param string $file 相对主题目录的路径
 * @return string
 */
function themeAsset(string $file): string
{
    $options = \Widget\Options::alloc();
    $theme = (string) ($options->missingTheme ?: $options->theme);

    /** 使用 themeUrl() 方法而不是同名属性: 前者会根据当前站点地址重新拼接, 站点换域名后依然正确 */
    $url = $options->themeUrl($file, $theme);

    if (!is_string($url)) {
        return '';
    }

    $path = $options->themeFile($theme, $file);
    $mtime = is_file($path) ? filemtime($path) : false;

    return themeEsc(false === $mtime ? $url : $url . '?v=' . $mtime);
}

/**
 * 输出内置 SVG 图标 (Lucide 图标集, 内联输出不产生额外请求)
 *
 * @param string $name 图标名
 * @param string $class CSS 类名
 * @return string
 */
function themeIcon(string $name, string $class = 'size-5'): string
{
    $icons = [
        'arrow-left'     => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
        'arrow-right'    => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'calendar'       => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'chevron-up'     => '<path d="m18 15-6-6-6 6"/>',
        'clock'          => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'folder'         => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
        'home'           => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'list'           => '<path d="M3 12h.01"/><path d="M3 18h.01"/><path d="M3 6h.01"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M8 6h13"/>',
        'menu'           => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
        'message-circle' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
        'monitor'        => '<rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/>',
        'moon'           => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'rss'            => '<path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/>',
        'search'         => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'sun'            => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
        'tag'            => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
        'user'           => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'
    ];

    if (!isset($icons[$name])) {
        return '';
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
        . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . themeEsc($class)
        . '" aria-hidden="true">' . $icons[$name] . '</svg>';
}

/* =============================================================================
 * 文章解析: 目录 / 阅读时间 / 缩略图
 * ========================================================================== */

/**
 * 生成标题锚点用的 slug
 *
 * @param string $text 标题文本
 * @param array<string, bool> $used 已占用的锚点 (引用传递)
 * @return string
 */
function themeSlug(string $text, array &$used = []): string
{
    $slug = mb_strtolower(trim($text), 'UTF-8');
    $slug = preg_replace('/[\s\x{3000}]+/u', '-', $slug) ?? '';
    $slug = preg_replace('/[^\p{L}\p{N}\-_]+/u', '', $slug) ?? '';
    $slug = trim($slug, '-_');

    if ('' === $slug) {
        $slug = 'section';
    }

    $slug = mb_substr($slug, 0, 60, 'UTF-8');

    /** 页面内已存在的锚点, 以及可能与其他元素冲突的保留字 */
    $reserved = ['main', 'site-nav', 'sidebar', 'comments', 'comment-form', 'respond', 'response',
        'footer', 'header', 'content', 'toc', 'notice-box', 'cancel-comment-reply-link'];

    if (in_array($slug, $reserved, true)) {
        $slug = 'toc-' . $slug;
    }

    $candidate = $slug;
    $index = 2;

    while (isset($used[$candidate])) {
        $candidate = $slug . '-' . $index;
        ++$index;
    }

    $used[$candidate] = true;

    return $candidate;
}

/**
 * 估算阅读时间 (分钟): 中日韩字符按字计算, 拉丁文按词计算
 *
 * @param string $html
 * @return int
 */
function themeReadingTime(string $html): int
{
    $text = trim(strip_tags($html));

    if ('' === $text) {
        return 1;
    }

    $cjk = preg_match_all('/[\x{3040}-\x{30ff}\x{3400}-\x{4dbf}\x{4e00}-\x{9fff}\x{ac00}-\x{d7af}\x{ff66}-\x{ff9d}]/u', $text);
    $latin = preg_match_all('/[A-Za-z0-9]+/u', $text);
    $words = (int) $cjk + (int) $latin;

    return max(1, (int) ceil($words / 300));
}

/**
 * 解析文章正文: 为标题注入锚点并生成目录
 *
 * 结果按 cid 缓存, post.php 与 toc.php 各调用一次不会重复计算。
 *
 * @param \Widget\Archive $archive
 * @return array{html: string, toc: array<int, array{id: string, text: string, level: int}>, readingTime: int}
 */
function themeArticle(\Widget\Archive $archive): array
{
    /** @var array<int, array{html: string, toc: array<int, array{id: string, text: string, level: int}>, readingTime: int}> $cache */
    static $cache = [];

    $cid = (int) $archive->cid;

    if (isset($cache[$cid])) {
        return $cache[$cid];
    }

    $html = themeLazyImages((string) $archive->content);

    /** 没有标题时不必解析 */
    if (
        false === stripos($html, '<h2') && false === stripos($html, '<h3')
        && false === stripos($html, '<h4')
    ) {
        return $cache[$cid] = ['html' => $html, 'toc' => [], 'readingTime' => themeReadingTime($html)];
    }

    /** 先取出代码块, 避免其中的 <h2> 之类文本被误判 */
    $protected = [];
    $stripped = preg_replace_callback(
        '#<(pre|code)\b[^>]*>.*?</\1>#is',
        static function (array $match) use (&$protected): string {
            $key = "\x01" . count($protected) . "\x01";
            $protected[$key] = $match[0];

            return $key;
        },
        $html
    );

    if (null === $stripped) {
        $stripped = $html;
    }

    /** @var array<int, array{id: string, text: string, level: int}> $toc */
    $toc = [];
    /** @var array<string, bool> $used */
    $used = [];
    $withAnchors = preg_replace_callback(
        '#<h([2-4])((?:\s[^>]*)?)>(.*?)</h\1>#is',
        static function (array $match) use (&$toc, &$used): string {
            $level = (int) $match[1];
            $attr = $match[2];
            $text = trim(html_entity_decode(strip_tags($match[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ('' === $text) {
                return $match[0];
            }

            if (preg_match('/\sid\s*=\s*(["\'])(.*?)\1/i', $attr, $found)) {
                $id = $found[2];
                $used[$id] = true;
            } else {
                $id = themeSlug($text, $used);
                $attr .= ' id="' . $id . '"';
            }

            $toc[] = ['id' => $id, 'text' => $text, 'level' => $level];

            return '<h' . $level . $attr . '>' . $match[3] . '</h' . $level . '>';
        },
        $stripped
    );

    if (null === $withAnchors) {
        $withAnchors = $stripped;
    }

    if ($protected) {
        $withAnchors = str_replace(array_keys($protected), array_values($protected), $withAnchors);
    }

    return $cache[$cid] = [
        'html'        => $withAnchors,
        'toc'         => $toc,
        'readingTime' => themeReadingTime($html)
    ];
}

/**
 * 输出文章目录 HTML
 *
 * @param array<int, array{id: string, text: string, level: int}> $toc
 * @return string
 */
function themeTocHtml(array $toc): string
{
    if ([] === $toc) {
        return '';
    }

    $items = '';

    foreach ($toc as $item) {
        $indent = match ($item['level']) {
            3 => ' pl-4',
            4 => ' pl-8',
            default => ''
        };

        $items .= '<li><a class="toc-link' . $indent . '" href="#' . themeEsc($item['id']) . '">'
            . themeEsc($item['text']) . '</a></li>';
    }

    return '<ol class="m-0 list-none p-0">' . $items . '</ol>';
}

/**
 * 判断 URL 是否安全 (用于缩略图等来自内容/自定义字段的地址)
 *
 * @param string $url
 * @return bool
 */
function themeIsSafeUrl(string $url): bool
{
    $url = trim($url);

    if ('' === $url) {
        return false;
    }

    if (preg_match('#^(https?:)?//#i', $url) || str_starts_with($url, '/')) {
        return true;
    }

    /** 含协议但不是 http(s) 的一律拒绝 (javascript:, data:, vbscript: 等) */
    return !preg_match('#^[a-z][a-z0-9+.-]*:#i', $url);
}

/**
 * 把内容中取到的地址补全为绝对地址
 *
 * @param string $url
 * @return string
 */
function themeResolveUrl(string $url): string
{
    $url = trim($url, " \t\n\r\0\x0B<>\"'");

    if ('' === $url || !themeIsSafeUrl($url)) {
        return '';
    }

    if (preg_match('#^(https?:)?//#i', $url)) {
        return $url;
    }

    $resolved = \Typecho\Common::url($url, (string) \Widget\Options::alloc()->siteUrl);

    return $resolved;
}

/**
 * 从原始文本中提取首图地址
 *
 * 直接解析 Markdown / HTML 原文, 避免为列表页的每篇文章渲染整篇内容。
 *
 * @param string $text
 * @return string 找不到时返回空字符串
 */
function themeFirstImageUrl(string $text): string
{
    if ('' === trim($text)) {
        return '';
    }

    /** Markdown 图片: ![alt](url) */
    if (preg_match('/!\[[^\]]*\]\(\s*<?([^\s)>]+)/', $text, $found)) {
        return themeResolveUrl($found[1]);
    }

    /** HTML 图片 */
    if (preg_match('#<img\b[^>]*?\ssrc\s*=\s*(["\'])(.*?)\1#is', $text, $found)) {
        return themeResolveUrl($found[2]);
    }

    return '';
}

/**
 * 取文章缩略图: 自定义字段 thumbnail > 正文首图 > 图片附件
 *
 * @param \Widget\Archive $archive
 * @return array{url: string, alt: string}|null
 */
function themeThumbnail(\Widget\Archive $archive): ?array
{
    /** @var array<int, array{url: string, alt: string}|null> $cache */
    static $cache = [];

    $cid = (int) $archive->cid;

    if (array_key_exists($cid, $cache)) {
        return $cache[$cid];
    }

    $title = (string) $archive->title;

    /** 1. 自定义字段 thumbnail */
    $custom = (string) ($archive->fields->thumbnail ?? '');
    $customUrl = themeResolveUrl($custom);

    if ('' !== $customUrl) {
        return $cache[$cid] = ['url' => $customUrl, 'alt' => $title];
    }

    /** 2. 正文首图 */
    $contentUrl = themeFirstImageUrl((string) $archive->text);

    if ('' !== $contentUrl) {
        return $cache[$cid] = ['url' => $contentUrl, 'alt' => $title];
    }

    /** 3. 图片附件 */
    $attachments = $archive->attachments(1);

    if ($attachments->have()) {
        $attachmentUrl = themeResolveUrl((string) $attachments->attachment->url);

        if ('' !== $attachmentUrl) {
            return $cache[$cid] = ['url' => $attachmentUrl, 'alt' => $title];
        }
    }

    return $cache[$cid] = null;
}

/* =============================================================================
 * 结构化数据
 * ========================================================================== */

/**
 * 站点级 JSON-LD
 *
 * @return string
 */
function themeSiteJsonLd(): string
{
    $options = \Widget\Options::alloc();
    $data = [
        '@context'        => 'https://schema.org',
        '@type'           => 'WebSite',
        'name'            => (string) $options->title,
        'url'             => (string) $options->siteUrl,
        'inLanguage'      => (string) $options->lang,
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => [
                '@type'       => 'EntryPoint',
                'urlTemplate' => rtrim((string) $options->siteUrl, '/') . '/?s={search_term_string}'
            ],
            'query-input' => 'required name=search_term_string'
        ]
    ];

    if ('' !== (string) $options->description) {
        $data['description'] = (string) $options->description;
    }

    $json = json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );

    return false === $json ? '' : '<script type="application/ld+json">' . $json . '</script>';
}

/**
 * 文章级 JSON-LD
 *
 * @param \Widget\Archive $archive
 * @param array{url: string, alt: string}|null $thumbnail
 * @return string
 */
function themePostJsonLd(\Widget\Archive $archive, ?array $thumbnail = null): string
{
    $options = \Widget\Options::alloc();
    $data = [
        '@context'         => 'https://schema.org',
        '@type'            => 'BlogPosting',
        'headline'         => (string) $archive->title,
        'url'              => (string) $archive->permalink,
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => (string) $archive->permalink],
        'datePublished'    => $archive->date->format('c'),
        'dateModified'     => (new \Typecho\Date((int) ($archive->modified ?: $archive->created)))->format('c'),
        'author'           => [
            '@type' => 'Person',
            'name'  => (string) $archive->author->screenName,
            'url'   => (string) $archive->author->permalink
        ],
        'publisher'        => [
            '@type' => 'Organization',
            'name'  => (string) $options->title,
            'url'   => (string) $options->siteUrl
        ],
        'inLanguage'       => (string) $options->lang,
        'wordCount'        => mb_strlen(trim(strip_tags((string) $archive->content)), 'UTF-8')
    ];

    $description = trim((string) $archive->plainExcerpt);

    if ('' !== $description) {
        $data['description'] = $description;
    }

    if (null !== $thumbnail) {
        $data['image'] = $thumbnail['url'];
    }

    /** @var array<int, array{name: string}> $tags */
    $tags = $archive->tags;

    if ([] !== $tags) {
        $data['keywords'] = implode(', ', array_column($tags, 'name'));
    }

    $json = json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );

    return false === $json ? '' : '<script type="application/ld+json">' . $json . '</script>';
}

/* =============================================================================
 * 其它模板辅助
 * ========================================================================== */

/**
 * 面包屑导航
 *
 * @param \Widget\Archive $archive
 * @return string
 */
function themeBreadcrumbs(\Widget\Archive $archive): string
{
    $items = [];
    $options = \Widget\Options::alloc();
    $items[] = ['name' => _t('首页'), 'url' => (string) $options->siteUrl];

    if ($archive->is('single')) {
        /** @var array<int, array{mid: int|string, name: string, permalink: string}> $categories */
        $categories = $archive->categories;

        if ([] !== $categories) {
            $category = $categories[0];
            $items[] = ['name' => (string) $category['name'], 'url' => (string) $category['permalink']];
        }

        $items[] = ['name' => (string) $archive->title, 'url' => ''];
    } else {
        $items[] = ['name' => trim($archive->getArchiveTitle() ?? '') ?: (string) $options->title, 'url' => ''];
    }

    $html = '<nav class="no-print mb-5 text-xs text-zinc-500 dark:text-zinc-400" aria-label="'
        . themeEsc(_t('当前位置')) . '"><ol class="flex flex-wrap items-center gap-1.5">';

    $last = count($items) - 1;

    foreach ($items as $index => $item) {
        $html .= '<li class="flex items-center gap-1.5">';

        if ($index > 0) {
            $html .= '<span aria-hidden="true" class="text-zinc-300 dark:text-zinc-600">/</span>';
        }

        if ($index === $last || '' === $item['url']) {
            $html .= '<span class="max-w-[16rem] truncate font-medium text-zinc-700 dark:text-zinc-300">'
                . themeEsc($item['name']) . '</span>';
        } else {
            $html .= '<a class="transition-colors hover:text-accent" href="' . themeEsc($item['url']) . '">'
                . themeEsc($item['name']) . '</a>';
        }

        $html .= '</li>';
    }

    return $html . '</ol></nav>';
}

/**
 * 输出经典分页导航
 *
 * @param \Widget\Archive $archive
 */
function themePageNav(\Widget\Archive $archive): void
{
    $archive->pageNav(
        themeIcon('arrow-left', 'size-4'),
        themeIcon('arrow-right', 'size-4'),
        1,
        '...',
        [
            'wrapTag'      => 'nav',
            'wrapClass'    => 'page-navigator',
            'itemTag'      => 'li',
            'textTag'      => 'span',
            'currentClass' => 'current',
            'prevClass'    => 'prev',
            'nextClass'    => 'next'
        ]
    );
}

/**
 * 侧边栏区块是否启用
 *
 * 多选项无法区分「从未保存过设置」与「用户全部取消勾选」,
 * 因此先看主题配置是否已经写入过: 未写入时用默认值, 已写入则完全尊重用户选择。
 *
 * @param string $block
 * @return bool
 */
function themeHasBlock(string $block): bool
{
    $defaults = ['ShowRecentPosts', 'ShowRecentComments', 'ShowCategory', 'ShowArchive', 'ShowTagCloud', 'ShowOther'];

    if (!themeOptionsSaved()) {
        return in_array($block, $defaults, true);
    }

    return in_array($block, themeOptionList('sidebarBlock'), true);
}

/**
 * 主题配置是否已经保存过 (未保存时使用内置默认值)
 *
 * @return bool
 */
function themeOptionsSaved(): bool
{
    static $saved = null;

    if (null === $saved) {
        $options = \Widget\Options::alloc();
        $theme = (string) ($options->missingTheme ?: $options->theme);
        $saved = null !== $options->{'theme:' . $theme};
    }

    return $saved;
}

/**
 * 页面容器宽度类名 (页头 / 正文 / 页脚共用, 保证左右对齐)
 *
 * @return string
 */
function themePageWidthClass(): string
{
    return 'full' === themeOption('layout', 'sidebar') ? 'max-w-4xl' : 'max-w-7xl';
}

/**
 * 正文容器类名 (含内边距与栅格)
 *
 * @return string
 */
function themeContainerClass(): string
{
    $base = 'mx-auto w-full px-4 py-8 sm:px-6 sm:py-10 lg:px-8 ' . themePageWidthClass();

    return 'full' === themeOption('layout', 'sidebar')
        ? $base
        : $base . ' lg:grid lg:grid-cols-[minmax(0,1fr)_15rem] lg:items-start lg:gap-10';
}

/**
 * 取相邻文章 (上一篇 / 下一篇)
 *
 * 不使用 thePrev()/theNext() 是因为它们固定输出 <a> 标签包住标题,
 * 无法让整张卡片都可点击; 这里按官方文档推荐的方式自行查询。
 *
 * @param \Widget\Archive $archive
 * @param string $direction prev|next
 * @return array{title: string, permalink: string}|null
 */
function themeAdjacentPost(\Widget\Archive $archive, string $direction): ?array
{
    $db = \Typecho\Db::get();
    $options = \Widget\Options::alloc();

    $select = $db->select()->from('table.contents')
        ->where('table.contents.status = ?', 'publish')
        ->where('table.contents.type = ?', $archive->type)
        ->where("table.contents.password IS NULL OR table.contents.password = ''")
        ->limit(1);

    if ('prev' === $direction) {
        $select->where('table.contents.created < ?', $archive->created)
            ->order('table.contents.created', \Typecho\Db::SORT_DESC);
    } else {
        $select->where('table.contents.created > ?', $archive->created)
            ->where('table.contents.created < ?', $options->time)
            ->order('table.contents.created', \Typecho\Db::SORT_ASC);
    }

    $post = \Widget\Contents\From::allocWithAlias(
        'theme-' . $direction . ':' . $archive->cid,
        ['query' => $select]
    );

    if (!$post->have()) {
        return null;
    }

    return [
        'title'     => (string) $post->title,
        'permalink' => (string) $post->permalink
    ];
}

/**
 * 输出标签云 (按文章数加权字号)
 */
function themeTagCloud(): void
{
    /** @var array<int, array{name: string, count: int|string, permalink: string}> $tags */
    $tags = \Widget\Metas\Tag\Cloud::alloc('sort=count&desc=1&ignoreZeroCount=1&limit=30')
        ->toArray(['name', 'count', 'permalink']);

    if ([] === $tags) {
        return;
    }

    $counts = array_map(static fn(array $tag): float => log(1 + max(0, (int) $tag['count'])), $tags);
    $min = min($counts);
    $max = max($counts);
    $range = $max - $min;

    echo '<ul class="flex flex-wrap gap-2 p-0 list-none">';

    foreach ($tags as $index => $tag) {
        $ratio = $range > 0 ? (float) (($counts[$index] - $min) / $range) : 0.5;
        $size = round(0.75 + $ratio * 0.25, 3);

        echo '<li><a class="chip" style="font-size:' . themeEsc((string) $size . 'rem') . '" href="'
            . themeEsc((string) $tag['permalink']) . '" title="' . themeEsc((string) $tag['name']) . ' · '
            . (int) $tag['count'] . themeEsc(_t(' 篇')) . '">' . themeEsc((string) $tag['name']) . '</a></li>';
    }

    echo '</ul>';
}
