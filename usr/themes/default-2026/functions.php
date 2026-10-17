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
        _t('十六进制颜色值, 如 #4f6bf5。只填一个颜色, 主题会自动派生出「填充 / 填充上的字 / 当文字用」三个变体, 保证每种用途的对比度都达标')
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

    $animLevel = new \Typecho\Widget\Helper\Form\Element\Radio(
        'animLevel',
        [
            'off'    => _t('关闭'),
            'light'  => _t('轻盈'),
            'normal' => _t('标准'),
            'strong' => _t('明显')
        ],
        'normal',
        _t('动效强度'),
        _t('控制切页时新内容的上浮距离、时长与卡片错峰幅度。「关闭」为直接替换内容 (骨架屏仍保留极短淡出, 避免硬切)')
    );
    $form->addInput($animLevel);

    $showSkeleton = new \Typecho\Widget\Helper\Form\Element\Radio(
        'showSkeleton',
        ['1' => _t('显示'), '0' => _t('不显示')],
        '1',
        _t('切页骨架屏'),
        _t('等待新内容时的占位骨架 (与动效强度相互独立), 关闭后等待期间不显示任何占位')
    );
    $form->addInput($showSkeleton);

    /* ---------------------------------------------------------------- 文章 */
    $showToc = new \Typecho\Widget\Helper\Form\Element\Radio(
        'showToc',
        ['1' => _t('显示'), '0' => _t('不显示')],
        '1',
        _t('文章目录'),
        _t('自动为正文中的二至三级标题生成目录与锚点 (四级标题仍会生成锚点, 但不进目录), 可见条目少于两个时不显示')
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

    $excerptLength = new \Typecho\Widget\Helper\Form\Element\Number(
        'excerptLength',
        null,
        110,
        _t('列表摘要字数'),
        _t('列表页每张卡片显示的摘要字数, 建议 60 - 200 (超出 10 - 500 的部分会被忽略)')
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

    /* ------------------------------------------------------------ 性能 */
    $preloadLinks = new \Typecho\Widget\Helper\Form\Element\Radio(
        'preloadLinks',
        ['1' => _t('开启'), '0' => _t('关闭')],
        '1',
        _t('链接预取'),
        _t('鼠标悬停在文章卡片、导航等链接上时, 后台提前把目标页面取回来, 点击后几乎瞬间打开; 关闭可节省流量与服务器请求, 但首次点击会变慢')
    );
    $form->addInput($preloadLinks);

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
 * 主题初始化: 内核在归档渲染前会调用这个钩子 (主题目前不需要在这里改任何设置)
 *
 * @param \Widget\Archive $archive
 */
function themeInit(\Widget\Archive $archive): void
{
    /**
     * 这里**故意什么都不改**: 评论行为全部交给后台 admin/options-discussion.php 的设置
     * (是否分页 / 每页多少条 / 默认第几页 / 正序还是倒序 / 是否嵌套回复 / 最多几层),
     * 内核 `Widget\Comments\Archive::execute()` 已经按这些选项查库、分页、排序 —— 主题插手
     * 只会让后台设置看起来失灵。
     *
     * 曾经在这里把 commentsMaxNestingLevels 收紧成 4 (担心深层嵌套挤窄正文): 于是后台设的
     * 「5 层」不生效 —— 越过上限的那一层会被内核挂到上上层 (`execute()` 里 levels 超限的那段),
     * 到第 4 层连「回复」链接也不再输出 (`___isTopLevel()`)。现在评论区是扁平排版, 层级只
     * 决定「回复挂在哪、能不能再回」, 视觉上已经没有缩进压力。
     *
     * 评论 Markdown 由内核负责, 主题不再兜底
     *
     * `Widget\Base\Comments::___content()` 最后会把评论压成 `<p>` + `<br>`, 但它在
     * `commentsMarkdown` 打开时会一并放行 Markdown 生成的标签, 链接地址还有一层协议
     * 检查。这里曾经注册过一个 `contentEx` 过滤器想把标签加回来 —— 但 `contentEx` 跑在
     * 那次过滤**之前**, 补出来的标签会被紧接着的 `stripTags()` 再剥一次, 那个兜底从来
     * 没生效过 (还让「Markdown 失效」看起来已经被修掉了)。修在内核里, 两个主题一起受益。
     */
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
/**
 * 在标签收尾(`>` 或 `/>`)之前插入属性
 */
function themeInsertAttr(string $tag, string $attrs): string
{
    $closing = str_ends_with($tag, '/>') ? '/>' : '>';
    $head = substr($tag, 0, -strlen($closing));

    return rtrim($head) . $attrs . $closing;
}

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

            /* 首图通常是 LCP 元素: 保它的加载优先级, 只补异步解码 */
            if (1 === $seen) {
                if (false === stripos($tag, 'fetchpriority=')) {
                    $tag = themeInsertAttr($tag, ' fetchpriority="high"');
                }

                return false === stripos($tag, 'decoding=') ? themeInsertAttr($tag, ' decoding="async"') : $tag;
            }

            $lazy = false === stripos($tag, 'loading=');
            $async = false === stripos($tag, 'decoding=');

            if (!$lazy && !$async) {
                return $tag;
            }

            $extra = ($lazy ? ' loading="lazy"' : '') . ($async ? ' decoding="async"' : '');

            return themeInsertAttr($tag, $extra);
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
 * 主题选项默认值 (全主题唯一来源)
 *
 * themeConfig() 里的表单默认值、前台读取时的回退值都从这里取,
 * 避免同一个数字在三个文件里各写一份、改一处漏两处。
 *
 * @return array<string, mixed>
 */
function themeDefaults(): array
{
    return [
        'accentColor'     => '#4f6bf5',
        'colorScheme'     => 'auto',
        'fontStyle'       => 'sans',
        'layout'          => 'sidebar',
        'animLevel'       => 'normal',
        'showSkeleton'    => '1',
        'showToc'         => '1',
        'showReadingTime' => '1',
        'excerptLength'   => '110',
        'preloadLinks'    => '1',
        'sidebarBlock'    => ['ShowRecentPosts', 'ShowRecentComments', 'ShowCategory', 'ShowArchive', 'ShowTagCloud', 'ShowOther'],
    ];
}

/**
 * 取单个默认值
 *
 * @param string $name
 * @param string $fallback
 * @return string
 */
function themeDefault(string $name, string $fallback = ''): string
{
    $defaults = themeDefaults();
    $value = $defaults[$name] ?? null;

    return is_scalar($value) ? (string) $value : $fallback;
}

/**
 * 把一个「会 echo 的东西」(内核的 title() / author() / excerpt() 等) 转成安全字符串
 *
 * 内核这些方法直接把原始值打出来, 用户填的标题里带 `<` 就会破标记,
 * 模板里一律用 `<?php echo themeText(fn () => $this->title()); ?>` 代替裸 echo。
 *
 * @param callable $printer
 * @return string
 */
function themeText(callable $printer): string
{
    ob_start();
    $printer();

    return themeEsc((string) ob_get_clean());
}

/**
 * 列表摘要字数 (夹取到 10 - 500)
 *
 * 后台填 0 / 负数会让 substr 得到畸形结果, 填极大值等于把全文搬上列表页。
 *
 * @return int
 */
function themeExcerptLength(): int
{
    return max(10, min(500, themeOptionInt('excerptLength', (int) themeDefault('excerptLength', '110'))));
}

/**
 * 当前请求是否是单篇 / 独立页面
 *
 * @return bool
 */
function themeIsSingle(): bool
{
    try {
        return (bool) \Widget\Archive::alloc()->is('single');
    } catch (\Throwable) {
        return false;
    }
}

/**
 * #rgb / #rrggbb → [r, g, b]
 *
 * @param string $hex
 * @return array{0: int, 1: int, 2: int}
 */
function themeColorRgb(string $hex): array
{
    $hex = ltrim($hex, '#');

    if (3 === strlen($hex) || 4 === strlen($hex)) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    return [
        (int) hexdec(substr($hex, 0, 2)),
        (int) hexdec(substr($hex, 2, 2)),
        (int) hexdec(substr($hex, 4, 2)),
    ];
}

/**
 * [r, g, b] → #rrggbb
 *
 * @param array<int, int|float> $rgb
 * @return string
 */
function themeColorHex(array $rgb): string
{
    return sprintf('#%02x%02x%02x', (int) round($rgb[0]), (int) round($rgb[1]), (int) round($rgb[2]));
}

/**
 * 单个通道的线性化 (WCAG 相对亮度)
 *
 * @param int|float $channel 0 - 255
 * @return float
 */
function themeColorLinear($channel): float
{
    $value = ((float) $channel) / 255;

    return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
}

/**
 * 相对亮度
 *
 * @param array<int, int|float> $rgb
 * @return float
 */
function themeColorLuminance(array $rgb): float
{
    return 0.2126 * themeColorLinear($rgb[0])
        + 0.7152 * themeColorLinear($rgb[1])
        + 0.0722 * themeColorLinear($rgb[2]);
}

/**
 * 两色对比度 (1 - 21)
 *
 * @param array<int, int|float> $a
 * @param array<int, int|float> $b
 * @return float
 */
function themeColorContrast(array $a, array $b): float
{
    $la = themeColorLuminance($a);
    $lb = themeColorLuminance($b);
    $light = max($la, $lb);
    $dark = min($la, $lb);

    return ($light + 0.05) / ($dark + 0.05);
}

/**
 * 两色按权重混合 ($weight 是 $a 的占比)
 *
 * @param array<int, int|float> $a
 * @param array<int, int|float> $b
 * @param float $weight 0 - 1
 * @return array{0: int, 1: int, 2: int}
 */
function themeColorMix(array $a, array $b, float $weight): array
{
    return [
        (int) round($a[0] * $weight + $b[0] * (1 - $weight)),
        (int) round($a[1] * $weight + $b[1] * (1 - $weight)),
        (int) round($a[2] * $weight + $b[2] * (1 - $weight)),
    ];
}

/**
 * 把强调色朝 $target 混合, 直到与 $bg 的对比度不低于 $min (取最接近原色的那一档)
 *
 * @param array<int, int|float> $accent
 * @param array<int, int|float> $target
 * @param array<int, int|float> $bg
 * @param float $min
 * @return array{0: int, 1: int, 2: int}
 */
function themeColorInk(array $accent, array $target, array $bg, float $min): array
{
    $best = $target;

    for ($step = 0; $step <= 100; $step += 2) {
        $candidate = themeColorMix($accent, $target, $step / 100);

        if (themeColorContrast($candidate, $bg) < $min) {
            break;
        }

        $best = $candidate;
    }

    return $best;
}

/**
 * 强调色派生的四个 CSS 变量 (只输出一串声明, 由模板写进 style 属性)
 *
 * 后台只让填一个颜色, 但一个颜色实际上要扮三种角色:
 *   填充 (按钮底色 / 分页当前页) → 需要「填充上的字」用哪个颜色
 *   文字 (正文链接 / 可点元素)   → 需要压暗到 4.5:1 以上
 *   焦点环 / 描边              → 同文字色, 不能拿填充色直接当描边用
 * 直接拿 #66ccff 这类亮色当文字用, 白底上只有 1.8:1 —— 所以在这里按亮度算。
 *
 * @return string
 */
function themeAccentStyle(): string
{
    static $style = null;

    if (null !== $style) {
        return $style;
    }

    $accent = themeAccent();
    $rgb = themeColorRgb($accent);

    /** 浅色模式: 朝深蓝压暗, 与白底达到 4.6:1 */
    $inkLight = themeColorInk($rgb, [6, 18, 31], [255, 255, 255], 4.6);
    /** 深色模式: 朝白提亮, 与页面底色达到 4.6:1 */
    $inkDark = themeColorInk($rgb, [255, 255, 255], [10, 10, 12], 4.6);

    /** 填充上的字: 两个候选里对比度高的那个 */
    $onDark = themeColorContrast($rgb, [11, 16, 32]) >= themeColorContrast($rgb, [255, 255, 255]);
    $onAccentRgb = $onDark ? [11, 16, 32] : [255, 255, 255];

    /**
     * 填充色本身也要能托住上面的字 (例如 #4f6bf5 + 白字只有 4.42:1),
     * 差一点点时把填充朝字色的反方向推几档, 视觉上几乎看不出来, 对比度却达标。
     */
    $fill = $rgb;

    for ($step = 0; $step < 60 && themeColorContrast($fill, $onAccentRgb) < 4.5; ++$step) {
        $fill = themeColorMix($fill, $onDark ? [255, 255, 255] : [0, 0, 0], 0.96);
    }

    return $style = '--theme-accent: ' . $accent
        . '; --theme-accent-fill: ' . themeColorHex($fill)
        . '; --theme-accent-on: ' . themeColorHex($onAccentRgb)
        . '; --theme-accent-ink: ' . themeColorHex($inkLight)
        . '; --theme-accent-ink-dark: ' . themeColorHex($inkDark);
}

/**
 * 自定义 CSS (剥掉能提前闭合 <style> 的序列, 避免把后续标记变成可执行内容)
 *
 * @return string
 */
function themeCustomCss(): string
{
    $css = themeOption('customCss');

    if ('' === trim($css)) {
        return '';
    }

    return str_ireplace(['</style', '</script'], ['<\\/style', '<\\/script'], $css);
}

/**
 * 页脚附加内容 (允许安全 HTML: 去掉脚本类标签与事件属性)
 *
 * 后台说明里承诺「支持 HTML」, 所以不能整体转义; 但也不能把后台输入的
 * script / onclick / javascript: 原样吐到前台。同一个值在 footer.php 里
 * 既当 title 又当正文, 所以统一走这个出口。
 *
 * @return string
 */
function themeFooterHtml(): string
{
    $html = themeOption('footerText');

    if ('' === trim($html)) {
        return '';
    }

    /**
     * 白名单式过滤: 正则「删事件属性 / javascript:」的写法总能被 `<svg/onload=>`
     * 或未加引号的 href 绕过, 这里改成标签+属性白名单, 链接协议由
     * Common::stripTags() 内部统一校验 (只放行 http/https/mailto/相对地址)。
     */
    return \Typecho\Common::stripTags(
        $html,
        '<a href="" title="" rel="" target="">'
        . '<b></b><strong></strong><i></i><em></em><u></u><s></s><del></del><ins></ins>'
        . '<code></code><pre></pre><br><p></p><span></span><small></small><sub></sub><sup></sup>'
        . '<ul></ul><ol></ol><li></li><blockquote></blockquote><mark></mark>'
        . '<img src="" alt="" title="" width="" height="">'
    );
}

/**
 * 强调色 (校验十六进制格式, 非法时回退到默认值)
 *
 * @return string
 */
function themeAccent(): string
{
    $accent = themeOption('accentColor', themeDefault('accentColor', '#4f6bf5'));

    return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $accent)
        ? $accent
        : themeDefault('accentColor', '#4f6bf5');
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
 * 动效
 *
 * 一套数值同时被 CSS 与 JS 使用, 所以统一由这里输出成 <html> 上的 CSS 变量:
 *   main.css 直接 var() 取用, app.js 读同一批变量换算自己的定时器
 *   (否则改了 CSS 时长, JS 里那个「入场窗口」就不准了)。
 * ========================================================================== */

/**
 * 动效强度选项的合法值
 *
 * @return string off|light|normal|strong
 */
function themeAnimLevel(): string
{
    $level = themeOption('animLevel', 'normal');

    return in_array($level, ['off', 'light', 'normal', 'strong'], true) ? $level : 'normal';
}

/**
 * 各档位的具体参数 (纯函数, 便于单独测试)
 *
 * enter* 管新内容的入场 (上浮 + 淡入), exitDistance 管旧内容淡出时上移多少
 * (退出时长复用 fadeDuration); window 告诉 JS「入场动画要留多久」,
 * 取值 = 卡片动画时长 + 最大错峰 + 余量。
 *
 * @param string $level off|light|normal|strong
 * @return array<string, string>
 */
function themeAnimPreset(string $level): array
{
    $presets = [
        'off' => [
            'enterDuration' => '0s',
            'enterDistance' => '0px',
            'exitDistance'  => '0px',
            'stagger'       => '0ms',
            'fadeDuration'  => '0s',
            /* 骨架屏淡出保留一点: 0 会让它硬切, 看起来像页面在闪 */
            'skeletonFade'  => '0.16s',
            'window'        => '0ms'
        ],
        'light' => [
            'enterDuration' => '0.24s',
            'enterDistance' => '8px',
            'exitDistance'  => '-3px',
            'stagger'       => '25ms',
            'fadeDuration'  => '0.14s',
            'skeletonFade'  => '0.16s',
            'window'        => '430ms'
        ],
        'normal' => [
            'enterDuration' => '0.34s',
            'enterDistance' => '12px',
            'exitDistance'  => '-4px',
            'stagger'       => '35ms',
            'fadeDuration'  => '0.18s',
            'skeletonFade'  => '0.22s',
            'window'        => '620ms'
        ],
        'strong' => [
            'enterDuration' => '0.46s',
            'enterDistance' => '20px',
            'exitDistance'  => '-6px',
            'stagger'       => '60ms',
            'fadeDuration'  => '0.24s',
            'skeletonFade'  => '0.3s',
            'window'        => '830ms'
        ]
    ];

    return $presets[$level] ?? $presets['normal'];
}

/**
 * 骨架屏是否启用
 *
 * @return bool
 */
function themeSkeletonEnabled(): bool
{
    return themeOptionBool('showSkeleton', true);
}

/**
 * 链接预取是否启用 (悬停即后台取回目标页面)
 *
 * @return bool
 */
function themePreloadLinks(): bool
{
    return themeOptionBool('preloadLinks', true);
}

/**
 * 挂到 <html> style 上的动效 CSS 变量
 *
 * @return string 形如 "--anim-enter-duration: 0.34s; ..."
 */
function themeAnimStyle(): string
{
    $preset = themeAnimPreset(themeAnimLevel());

    $map = [
        'enterDuration' => '--anim-enter-duration',
        'enterDistance' => '--anim-enter-distance',
        'exitDistance'  => '--anim-exit-distance',
        'stagger'       => '--anim-stagger',
        'fadeDuration'  => '--anim-fade-duration',
        'skeletonFade'  => '--anim-skeleton-fade',
        'window'        => '--anim-window'
    ];

    $style = '';

    foreach ($map as $key => $name) {
        $style .= $name . ': ' . $preset[$key] . '; ';
    }

    return trim($style);
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
        'chevron-down'   => '<path d="m6 9 6 6 6-6"/>',
        'chevron-up'     => '<path d="m18 15-6-6-6 6"/>',
        'clock'          => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'folder'         => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
        'home'           => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'link'           => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'list'           => '<path d="M3 12h.01"/><path d="M3 18h.01"/><path d="M3 6h.01"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M8 6h13"/>',
        'menu'           => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
        'message-circle' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
        'monitor'        => '<rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/>',
        'moon'           => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'quote'          => '<path d="M16 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h1a1 1 0 0 1 1 1v1a2 2 0 0 1-2 2h-1a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h1a6 6 0 0 0 6-6V5a2 2 0 0 0-2-2z"/><path d="M5 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h1a1 1 0 0 1 1 1v1a2 2 0 0 1-2 2H4a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h1a6 6 0 0 0 6-6V5a2 2 0 0 0-2-2z"/>',
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

            /* 只收录二至三级标题: 目录保持两层, 过深会刷屏。
               四级标题仍然会走到这里并拿到锚点 id, 只是不进目录 —— 已有深链不受影响。 */
            if ($level <= 3) {
                $toc[] = ['id' => $id, 'text' => $text, 'level' => $level];
            }

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
 * 渲染目录条目
 *
 * @param array<int, array{id: string, text: string, level: int}> $items
 * @return string
 */
function themeTocItems(array $items): string
{
    $html = '';

    foreach ($items as $item) {
        $indent = 3 === $item['level'] ? ' pl-4' : '';

        $html .= '<li><a class="toc-link' . $indent . '" href="#' . themeEsc($item['id']) . '">'
            . themeEsc($item['text']) . '</a></li>';
    }

    return $html;
}

/**
 * 输出文章目录 HTML
 *
 * @param array<int, array{id: string, text: string, level: int}> $toc
 * @param bool $collapsible 条目过多时是否折叠尾部 (桌面版用; 移动端本身已在 <details> 里)
 * @return string
 */
function themeTocHtml(array $toc, bool $collapsible = false): string
{
    if ([] === $toc) {
        return '';
    }

    /** 超过这个条数就折叠, 避免长文目录把侧栏撑爆 */
    $limit = 12;
    $fold = $collapsible && count($toc) > $limit;

    $html = '<ol class="m-0 list-none p-0">'
        . themeTocItems($fold ? array_slice($toc, 0, $limit) : $toc)
        . '</ol>';

    if ($fold) {
        /* 用原生 <details> 折叠, 无 JS 也能展开 */
        $html .= '<details class="toc-more mt-1">'
            . '<summary class="toc-toggle"><span class="toc-toggle-open">'
            . sprintf(_t('展开全部 (%d 条)'), count($toc))
            . '</span><span class="toc-toggle-close">' . _t('收起') . '</span></summary>'
            . '<ol class="m-0 list-none p-0 mt-1">' . themeTocItems(array_slice($toc, $limit)) . '</ol>'
            . '</details>';
    }

    return $html;
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
 * 没有扩展名的地址视为图片
 *
 * 图片地址可能不带后缀 (图床 / 动态缩略图接口), 所以不能反过来「必须匹配图片后缀」,
 * 只能判断「明确带了文件后缀, 而且后缀不是图片」。
 *
 * @param string $path
 * @return bool
 */
function themeIsImagePath(string $path): bool
{
    /** 去掉查询串与锚点, 只保留路径部分, 例如 photo.jpg?w=800#top */
    $cleaned = preg_replace('/[?#].*$/', '', trim($path, " \t\n\r\0\x0B<>\"'")) ?? '';
    $extension = strtolower(pathinfo($cleaned, PATHINFO_EXTENSION));

    if ('' === $extension) {
        return true;
    }

    static $images = ['jpg', 'jpeg', 'jpe', 'jfif', 'pjpeg', 'png', 'apng', 'gif', 'webp',
        'avif', 'bmp', 'svg', 'svgz', 'ico', 'cur', 'tif', 'tiff', 'heic', 'heif', 'jxl'];

    return in_array($extension, $images, true);
}

/**
 * 从原始文本中提取首图地址
 *
 * 直接解析 Markdown / HTML 原文, 避免为列表页的每篇文章渲染整篇内容。
 * Markdown 语法可以给任意文件写 `![名字](file.zip)`, 所以这里会逐个检查后缀,
 * 跳过压缩包、PDF 之类的附件, 免得它们被当成图片塞进卡片。
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
    if (preg_match_all('/!\[[^\]]*\]\(\s*<?([^\s)>]+)/', $text, $matches)) {
        foreach ($matches[1] as $candidate) {
            if (!themeIsImagePath($candidate)) {
                continue;
            }

            $url = themeResolveUrl($candidate);

            if ('' !== $url) {
                return $url;
            }
        }
    }

    /** HTML 图片 */
    if (preg_match_all('#<img\b[^>]*?\ssrc\s*=\s*(["\'])(.*?)\1#is', $text, $matches)) {
        foreach ($matches[2] as $candidate) {
            if (!themeIsImagePath($candidate)) {
                continue;
            }

            $url = themeResolveUrl($candidate);

            if ('' !== $url) {
                return $url;
            }
        }
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

    /**
     * 3. 图片附件 (跳过压缩包 / 文档等非图片附件, 取第一张真正的图片)
     *
     * 附件要单独查一次库: 列表页一张卡片查一次就是 N+1 (首页 10 篇 = 10 次额外查询),
     * 而列表页的卡片本来就有「无图」形态。所以只有单篇页才走这一步,
     * 列表页的无图文章直接落回无图卡 —— 少 10 次查询, 视觉上也更干净。
     */
    if (!$archive->is('single')) {
        return $cache[$cid] = null;
    }

    $attachments = $archive->attachments(20);

    while ($attachments->next()) {
        /** @var object|null $attachment */
        $attachment = $attachments->attachment;

        /* 少数插件写入的附件可能没有 isImage 标记, 按非图片处理更安全 */
        if (null === $attachment || empty($attachment->isImage)) {
            continue;
        }

        $attachmentUrl = themeResolveUrl((string) $attachment->url);

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
    $defaults = themeDefaults()['sidebarBlock'];

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
    /* 版心宽度由 main.css 的 --container-page 单点控制 (现在是 70rem),
       full 布局 (无侧栏) 收窄到 4xl。 */
    return 'full' === themeOption('layout', 'sidebar') ? 'max-w-4xl' : 'max-w-[var(--container-page)]';
}

/**
 * 目录条目数 (只用来决定「这一页该显示目录还是侧边栏」)
 *
 * 单篇页直接用已经解析好的结果。列表页每张卡片也只需要一个布尔值,
 * 而原来的写法会把每张卡片的整篇 Markdown 渲染一遍 (10 篇 = 10 次全文解析),
 * 只为算一个提示位 —— 在原始 text 上数标题便宜得多, 而且猜错的代价只是
 * 一次侧边栏遮罩 (app.js 本身也按保守档处理)。
 *
 * @param \Widget\Archive $archive
 * @return int
 */
function themeTocEntryCount(\Widget\Archive $archive): int
{
    if ($archive->is('single')) {
        return count(themeArticle($archive)['toc']);
    }

    $text = (string) $archive->text;

    if ('' === $text) {
        return 0;
    }

    $count = (int) preg_match_all('~^\s{0,3}\#{2,3}\s+\S~m', $text);
    $count += (int) preg_match_all('#<h[23]\b#i', $text);

    return $count;
}

/**
 * 某篇文章 / 独立页面上的第二列 (侧边栏 / 文章目录) 形态
 *
 * 单栏布局 ('full') 没有第二列 —— 栅格都没画, 硬塞一份 `<aside>` 只会掉在评论区下方
 * 成为页面底部的一坨 (而且单栏的人本来就不要侧栏)。所以单栏下这里一律返回 none,
 * 文章的目录改由 post.php 在正文上方用折叠块输出 (与窄屏那一版共用同一段标记)。
 *
 * 有第二列时: 页面自己有目录 (条目 >= 2) 就是目录 (toc.php), 否则是侧边栏。
 *
 * post-card.php 会把它写成卡片链接上的 data-aside-target: 从列表页点进某一篇时,
 * app.js 在**拿到目标文档之前**就能确定第二列会不会变 —— 猜错会让骨架遮罩盖住
 * 内容其实没变的侧边栏 (见 app.js 的 asideKindForSession)。
 *
 * @param \Widget\Archive $archive 这篇文章所在的归档组件 (列表页循环里也能用)
 * @return string sidebar|toc|none
 */
function themeContentAsideKind(\Widget\Archive $archive): string
{
    if ('full' === themeOption('layout', 'sidebar')) {
        return 'none';
    }

    if (themeOptionBool('showToc') && themeTocEntryCount($archive) >= 2) {
        return 'toc';
    }

    return themeSkeletonSidebar() ? 'sidebar' : 'none';
}

/**
 * 列表页 / 归档页上的第二列形态 (与具体是哪个列表页无关)
 *
 * @return string sidebar|none
 */
function themeListAsideKind(): string
{
    if ('full' === themeOption('layout', 'sidebar')) {
        return 'none';
    }

    return themeSkeletonSidebar() ? 'sidebar' : 'none';
}

/**
 * 当前页面第二列 (侧边栏 / 文章目录) 的形态标记
 *
 * 输出到 #page-skeleton 的 data-aside-kind (服务端渲染时的记录; 无刷新切页换掉这一列后
 * 由 app.js 的 recordAsideKind() 补写)。判定本身看 DOM 里真实那一列, 见
 * app.js 的 currentAsideKind()。
 *
 * @param \Widget\Archive $archive
 * @return string sidebar|toc|none
 */
function themeAsideKind(\Widget\Archive $archive): string
{
    return $archive->is('single') ? themeContentAsideKind($archive) : themeListAsideKind();
}

/**
 * 侧边栏是否有内容可显示 (与 sidebar.php 的判定保持一致)
 *
 * @return bool
 */
function themeSkeletonSidebar(): bool
{
    if ('full' === themeOption('layout', 'sidebar')) {
        return false;
    }

    foreach (['ShowRecentPosts', 'ShowRecentComments', 'ShowCategory', 'ShowArchive', 'ShowTagCloud', 'ShowOther'] as $block) {
        if (themeHasBlock($block)) {
            return true;
        }
    }

    return false;
}

/**
 * 窄屏的分类 / 归档横滑带
 *
 * 侧边栏在 < 64rem 时整列隐藏, 分类和归档在手机上就没有入口了, 所以补一条只在窄屏出现
 * 的胶囊带 (.browse-strip 在宽屏 display:none)。
 *
 * 位置统一在内容上方 —— 列表页、文章页、页面页都一样。以前文章页把它放在页脚,
 * 手机上要滑到「分页之后」才冒出来, 看着像是分页多出来的一列, 也和列表页对不上。
 *
 * @param string $label       读屏用的名字
 * @param bool   $withArchive 是否带上最近 3 个月归档 (文章 / 页面页用)
 * @param bool   $withActive  是否给当前分类加高亮 (归档页用)
 */
function themeBrowseStrip(string $label, bool $withArchive = false, bool $withActive = false): void
{
    if (!themeSkeletonSidebar()) {
        return;
    }

    $archiveSlug = $withActive ? (string) \Widget\Archive::alloc()->getArchiveSlug() : '';
    ?>
    <nav class="browse-strip mb-5" aria-label="<?php echo themeEsc($label); ?>">
        <?php \Widget\Metas\Category\Rows::alloc()->to($stripCategories); ?>
        <?php while ($stripCategories->next()): ?>
            <a class="chip shrink-0<?php echo $withActive && (string) $stripCategories->slug === $archiveSlug ? ' is-active' : ''; ?>" href="<?php echo themeEsc((string) $stripCategories->permalink); ?>"><?php echo themeEsc((string) $stripCategories->name); ?><span class="widget-count"><?php echo (int) $stripCategories->count; ?></span></a>
        <?php endwhile; ?>
        <?php if ($withArchive): ?>
            <?php \Widget\Contents\Post\Date::alloc('type=month&format=Y-m&pageSize=3')
                ->parse('<a class="chip shrink-0" href="{permalink}">{date}</a>'); ?>
        <?php endif; ?>
    </nav>
    <?php
}

/**
 * 正文容器类名 (含内边距与栅格)
 *
 * @return string
 */
function themeContainerClass(): string
{
    $base = 'mx-auto w-full px-4 py-8 sm:px-6 sm:py-10 lg:px-8 ' . themePageWidthClass();

    if ('full' === themeOption('layout', 'sidebar')) {
        return $base;
    }

    /* 这一页根本没有第二列时不要留一个 15rem 的空洞 (页面右侧会空出一整栏) */
    $asideKind = themeIsSingle() ? themeContentAsideKind(\Widget\Archive::alloc()) : themeListAsideKind();

    if ('none' === $asideKind) {
        return $base;
    }

    return $base . ' lg:grid lg:grid-cols-[minmax(0,1fr)_15rem] lg:items-start lg:gap-10';
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

/* ============================================================================
   评论区渲染 (方案 B: 分组 + 回复卡片)
   ----------------------------------------------------------------------------
   内核的 listComments() 会调用全局函数 threadedComments($comments, $options)
   (存在即接管默认输出), 所以这里完全接管标记, 换来三件事:
   1. 一条主评论 + 它的全部回复 = 一个块, 块之间用一条细线分开;
   2. 回复不再往右缩进 (缩进堆到第三层正文只剩三分之一宽度), 而是换成浅底卡片,
      卡片头写「回复 某人」——层级只由卡片表达;
   3. 同一条主评论下的所有后代回复被拍平到同一层 (见 themeCommentResolveDeferred),
      所以第三层不会又套一层卡片。
   ============================================================================ */

/**
 * 渲染一条评论 (由内核逐条回调)
 *
 * @param \Widget\Comments\Archive $comments 当前指向某条评论的评论组件
 * @param \Typecho\Config         $options  listComments() 的展现配置
 */
function threadedComments(\Widget\Comments\Archive $comments, \Typecho\Config $options): void
{
    /** 渲染顺序是深度优先且同步的, 所以用一个栈就能知道「这条回复是回给谁的」 */
    static $authorStack = [];

    $levels = (int) $comments->levels;
    $author = trim((string) $comments->author);
    $parentAuthor = $levels > 0 && [] !== $authorStack ? (string) end($authorStack) : '';
    $coid = (int) $comments->coid;
    $theId = (string) $comments->theId;

    echo '<article id="', themeEsc($theId), '" class="comment-body ', $levels > 0 ? 'rcomment' : 'tcomment', '">';

    echo '<header class="c-head">';
    /* 头像只给主评论: 回复卡片里再放一颗 26px 头像只会把行撑高 (demo 里也没有) */
    if (0 === $levels) {
        echo '<span class="c-avatar" data-initial="', themeEsc(mb_substr($author, 0, 1)), '">';
        $comments->gravatar((int) $options->avatarSize);
        echo '</span>';
    }

    /* 回复卡片先写「回复 某人」; 同一个人接着自己说话时不再重复昵称 */
    if ('' !== $parentAuthor) {
        echo '<span class="c-to">', themeEsc(_t('回复')), ' <b>', themeEsc($parentAuthor), '</b></span>';
    }

    if (0 === $levels || $author !== $parentAuthor) {
        echo '<span class="c-name">', themeEsc($author), '</span>';
    }
    echo '<time class="c-time" datetime="', $comments->date('c'), '">',
        themeEsc($comments->date((int) $comments->created >= (int) strtotime((string) date('Y') . '-01-01 00:00:00')
            ? 'm-d H:i'
            : 'Y-m-d')),
        '</time>';

    if ('approved' !== $comments->status) {
        echo '<em class="comment-awaiting-moderation">', themeEsc(_t('等待审核')), '</em>';
    }

    /* 回复链接: 顶层评论也要能点 (内核的 reply() 只给子评论输出链接) */
    $respondId = 'respond-' . (string) $comments->parentContent->theId;
    $permalink = (string) $comments->permalink;
    $base = str_ends_with($permalink, '#' . $theId)
        ? substr($permalink, 0, -strlen($theId) - 1)
        : $permalink;

    /* 回复链接跟在时间后面, 靠 ml-auto 推到行尾 —— 独占一行的话每条评论白白高出 30px */
    /* data-reply-*: app.js 靠这三个属性知道「回复谁」(target = 评论节点 id,
       to = 父评论 coid, author = 昵称)。onclick 只作为无 JS / 兜底路径。
       别只留 onclick: 里面的 theId 是 JSON 编码的 (双引号), 从 JS 里用单引号正则
       去抠会抠空, parent 传成空串, 回复就变成了新的顶层评论。 */
    echo '<a class="c-reply" href="', themeEsc($base . '?replyTo=' . $coid . '#' . $respondId),
        '" rel="nofollow" data-reply-target="', themeEsc($theId),
        '" data-reply-to="', $coid,
        '" data-reply-author="', themeEsc($author),
        '" onclick="return TypechoComment.reply(',
        /** JS 字符串用 JSON 编码后再做 HTML 转义, 不能用 themeEsc 直接拼进单引号 */
        themeEsc((string) json_encode((string) $theId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)),
        ', ', $coid, ', this)">', themeEsc(_t('回复')), '</a>';

    echo '</header>';

    echo '<div class="c-body">';
    $comments->content();
    echo '</div>';

    echo '</article>';

    /* 回复一律排在卡片外面: 三层再套一层卡片会缩成一小块, 所以深层回复用
       「占位符 + 事后替换」拍平成同一层 (见 themeCommentResolveDeferred)。 */
    if (0 === $levels) {
        $GLOBALS['themeThreadDefer'] = [];
        $authorStack[] = $author;
        ob_start();
        $comments->threadedComments();
        $inner = (string) ob_get_clean();
        array_pop($authorStack);

        /* 没人回复时不要输出空壳: .thread-replies 上的 mt-3 会给每条评论多垫 12px */
        $inner = themeCommentResolveDeferred($inner);
        if ('' !== trim($inner)) {
            echo '<div class="thread-replies">', $inner, '</div>';
        }
    } elseif ($comments->children) {
        /* 第二层以下的回复不塞进父卡片里, 先缓冲起来, 由块末尾统一摊平 */
        $authorStack[] = $author;
        ob_start();
        $comments->threadedComments();
        $inner = (string) ob_get_clean();
        array_pop($authorStack);

        $key = "\x02DEFER{$coid}\x02";
        echo $key;
        $GLOBALS['themeThreadDefer'][$key] = $inner;
    }
}

/**
 * 把「延迟渲染的深层回复」就地替换回原位 (可多层嵌套, 逐层展开)
 */
function themeCommentResolveDeferred(string $html): string
{
    $store = $GLOBALS['themeThreadDefer'] ?? [];
    $guard = 0;
    /** 层数上限跟实际待展开的块数挂钩, 不再用固定 10 层 (深层嵌套会留下字面占位符) */
    $max = count($store) + 2;

    while ([] !== $store && $guard++ < $max && preg_match('/\x02DEFER\d+\x02/', $html, $match)) {
        $html = str_replace($match[0], (string) ($store[$match[0]] ?? ''), $html);
    }

    $GLOBALS['themeThreadDefer'] = [];

    return $html;
}
