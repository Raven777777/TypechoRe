<?php
/**
 * 主题纯函数测试 (只加载主题与核心函数, 不碰数据库与 Widget)
 *
 * 运行: cd usr/themes/default-2026 && php tests/theme-functions.php
 *
 * 覆盖两处「改了就会静默出错」的逻辑:
 *   - 目录渲染: 缩进、两层结构、条目过多时的折叠
 *   - 动效档位表: 四档齐全、未知值回落、键名与 themeAnimStyle() 的变量映射一致
 *
 * 与 PHPStan 共存的约定 (踩过两次):
 *   1. 辅助函数一律加 themeTest 前缀 —— 核心的 tests/integration.php 也定义了全局
 *      check(), 撞名会让它的调用被按本文件的签名检查;
 *   2. 不自己定义 _t / _e 桩, 直接用核心 Common.php 里的真实实现 —— 一个「纯函数」
 *      桩会把核心代码里「单独一行的 _t()」判成无效果 (function.resultUnused)。
 */

declare(strict_types=1);

define('__TYPECHO_ROOT_DIR__', dirname(__DIR__, 4));

require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common.php';
require_once dirname(__DIR__) . '/functions.php';

/**
 * 记一次断言结果
 *
 * 计数走引用而不是 global: PHPStan 会把 global 别名当成另一份不变量,
 * 从而认定 $failed 永远是 0 (greater.alwaysFalse)。
 *
 * @param array{pass: int, fail: int} $results
 */
function themeTestCheck(array &$results, string $label, bool $ok, string $extra = ''): void
{
    if ($ok) {
        ++$results['pass'];
        echo "PASS  {$label}" . ($extra ? "  {$extra}" : '') . PHP_EOL;

        return;
    }

    ++$results['fail'];
    echo "FAIL  {$label}" . ($extra ? "  {$extra}" : '') . PHP_EOL;
}

$results = ['pass' => 0, 'fail' => 0];

/* ---------------------------------------------------------------- 目录渲染 */

$two = [
    ['id' => 'a', 'text' => '第一章', 'level' => 2],
    ['id' => 'b', 'text' => '第一节', 'level' => 3]
];

$html = themeTocHtml($two);
themeTestCheck($results, '空目录返回空串', '' === themeTocHtml([]));
themeTestCheck($results, '两级目录: h2 不缩进', str_contains($html, '<a class="toc-link" href="#a">第一章</a>'));
themeTestCheck($results, '两级目录: h3 缩进一档', str_contains($html, '<a class="toc-link pl-4" href="#b">第一节</a>'));
themeTestCheck($results, '条目不多时不折叠', !str_contains($html, 'toc-more'));
themeTestCheck($results, '输出以有序列表包裹', str_starts_with($html, '<ol class="m-0 list-none p-0">') && str_ends_with($html, '</ol>'));

/* 15 条: 桌面版折叠尾部, 移动端版不折叠 */
$many = [];
for ($i = 1; $i <= 15; ++$i) {
    $many[] = ['id' => 'h' . $i, 'text' => '小节 ' . $i, 'level' => $i % 2 ? 2 : 3];
}

$folded = themeTocHtml($many, true);
$flat = themeTocHtml($many, false);

themeTestCheck($results, '桌面版: 15 条时折叠', str_contains($folded, '<details class="toc-more mt-1">'));
themeTestCheck($results, '桌面版: 折叠按钮显示总条数', str_contains($folded, '展开全部 (15 条)'));
themeTestCheck($results, '桌面版: 折叠后仍有收起文案', str_contains($folded, 'toc-toggle-close">收起<'));
themeTestCheck($results, '桌面版: 列表 1 共 12 条 (含折叠内的重复计数前先看总数)', 15 === substr_count($folded, '<li>'));
themeTestCheck($results,
    '桌面版: 折叠内是剩下的 3 条',
    str_contains($folded, 'href="#h13"') && str_contains($folded, 'href="#h15"')
        && !str_contains(substr($folded, 0, strpos($folded, '<details')), 'href="#h13"')
);
themeTestCheck($results, '桌面版: 13 条也折叠', str_contains(themeTocHtml(array_slice($many, 0, 13), true), 'toc-more'));
themeTestCheck($results, '桌面版: 正好 12 条不折叠', !str_contains(themeTocHtml(array_slice($many, 0, 12), true), 'toc-more'));
themeTestCheck($results, '移动端版 (collapsible=false): 再多也不折叠', !str_contains($flat, 'toc-more') && 15 === substr_count($flat, '<li>'));

/* 转义 */
$escaped = themeTocHtml([['id' => 'a"b', 'text' => '<b>粗体</b>', 'level' => 2]]);
themeTestCheck($results, '目录条目做了 HTML 转义', !str_contains($escaped, '<b>') && str_contains($escaped, '&lt;b&gt;'));

/* ------------------------------------------------------------ 动效档位表 */

$levels = ['off', 'light', 'normal', 'strong'];
$keys = ['enterDuration', 'enterDistance', 'exitDistance', 'stagger', 'fadeDuration', 'skeletonFade', 'window'];

foreach ($levels as $level) {
    $preset = themeAnimPreset($level);
    themeTestCheck(
        $results,
        "档位 {$level}: 六个参数齐全",
        [] === array_diff($keys, array_keys($preset)) && [] === array_diff(array_keys($preset), $keys),
        implode(',', array_keys($preset))
    );
}

themeTestCheck($results, '未知档位回落到 standard (normal)', themeAnimPreset('whatever') === themeAnimPreset('normal'));

$off = themeAnimPreset('off');
themeTestCheck($results, '关闭档: 无位移', '0px' === $off['enterDistance']);
themeTestCheck($results, '关闭档: 无退出位移', '0px' === $off['exitDistance']);
themeTestCheck($results, '关闭档: 无入场动画时长', '0s' === $off['enterDuration']);
themeTestCheck($results, '关闭档: 窗口为 0 (JS 不挂入场类)', '0ms' === $off['window']);
themeTestCheck($results, '关闭档: 骨架淡出保留一点, 避免硬切', '0s' !== $off['skeletonFade']);

/** 把 "620ms" / "0.34s" 统一换算成毫秒 */
function themeTestMs(string $value): float
{
    return (float) $value * (str_ends_with($value, 'ms') ? 1.0 : 1000.0);
}

$light = themeAnimPreset('light');
$normal = themeAnimPreset('normal');
$strong = themeAnimPreset('strong');

themeTestCheck($results,
    '档位递增: 时长 light < normal < strong',
    themeTestMs($light['enterDuration']) < themeTestMs($normal['enterDuration'])
        && themeTestMs($normal['enterDuration']) < themeTestMs($strong['enterDuration'])
);
themeTestCheck($results,
    '档位递增: 位移 light < normal < strong',
    (float) $light['enterDistance'] < (float) $normal['enterDistance']
        && (float) $normal['enterDistance'] < (float) $strong['enterDistance']
);
themeTestCheck($results,
    '档位递增: 错峰 light < normal < strong',
    themeTestMs($light['stagger']) < themeTestMs($normal['stagger'])
        && themeTestMs($normal['stagger']) < themeTestMs($strong['stagger'])
);
themeTestCheck($results,
    '入场窗口足够长 (窗口 = 动画时长 + 第 6 张的错峰 + 余量)',
    themeTestMs($normal['window']) - (themeTestMs($normal['enterDuration']) + 5 * themeTestMs($normal['stagger'])) > 0.0,
    $normal['window']
);

/* 把三个可见档位的数值钉死: 改档位表就必须同时改这里, 免得 CSS 与 JS 悄悄跑偏 */
themeTestCheck($results,
    'light 档数值固定',
    '0.24s' === $light['enterDuration'] && '8px' === $light['enterDistance']
        && '-3px' === $light['exitDistance'] && '25ms' === $light['stagger'] && '430ms' === $light['window']
);
themeTestCheck($results,
    'normal 档数值固定',
    '0.34s' === $normal['enterDuration'] && '12px' === $normal['enterDistance']
        && '-4px' === $normal['exitDistance'] && '35ms' === $normal['stagger'] && '620ms' === $normal['window']
);
themeTestCheck($results,
    'strong 档数值固定',
    '0.46s' === $strong['enterDuration'] && '20px' === $strong['enterDistance']
        && '-6px' === $strong['exitDistance'] && '60ms' === $strong['stagger'] && '830ms' === $strong['window']
);
themeTestCheck($results,
    '退出位移为负 (旧内容往上走), 幅度随档位增大',
    (float) $light['exitDistance'] < 0.0
        && (float) $light['exitDistance'] > (float) $normal['exitDistance']
        && (float) $normal['exitDistance'] > (float) $strong['exitDistance']
);

echo PHP_EOL . ($results['fail'] > 0 ? "{$results['fail']} 项失败, {$results['pass']} 项通过" : "全部通过 ({$results['pass']} 项)") . PHP_EOL;
exit($results['fail'] > 0 ? 1 : 0);
