<?php

/**
 * #[\\Override] 覆盖率审计
 *
 * 用反射找出「确实覆盖了父类/接口方法、但没有标注 #[\Override]」的类方法。
 * PHP 8.5 会在类链接期校验该属性, 因此漏标会导致父类签名漂移时无法被 CI 发现。
 *
 * 说明: trait 中声明的方法不能标注该属性 —— 属性由 trait 原样带到每个使用类,
 * 只要有一个使用类在继承链上没有同名方法就会触发致命错误。因此本脚本跳过
 * trait 方法, 只审计类自身声明的方法 (父类为 abstract 方法时同样适用)。
 *
 * 用法: php tools/audit-override.php [--verbose]
 * 退出码: 0 = 无遗漏, 1 = 存在遗漏或无法加载类
 */

declare(strict_types=1);

$root = dirname(__DIR__);
define('__TYPECHO_ROOT_DIR__', $root);
define('__TYPECHO_PLUGIN_DIR__', '/usr/plugins');

spl_autoload_register(static function (string $class) use ($root): void {
    $map = [
        'Typecho\\' => $root . '/var/Typecho/',
        'Widget\\' => $root . '/var/Widget/',
        'Utils\\' => $root . '/var/Utils/',
        'IXR\\' => $root . '/var/IXR/',
        'lbuchs\\' => $root . '/var/lbuchs/',
    ];

    foreach ($map as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
            return;
        }
    }
});

require_once $root . '/var/Typecho/Common.php';

$verbose = in_array('--verbose', $_SERVER['argv'] ?? [], true);

/** @return string[] */
function discoverClasses(string $dir): array
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    $classes = [];

    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $src = file_get_contents($file->getPathname());
        if (false === $src || !preg_match_all('/^namespace\s+([^;]+);/m', $src, $nsMatches)) {
            continue;
        }

        $namespace = trim($nsMatches[1][0]);
        if (preg_match_all('/^\s*(?:final\s+|abstract\s+)?(?:class|interface|trait)\s+([A-Za-z_][A-Za-z0-9_]*)/m', $src, $classMatches)) {
            foreach ($classMatches[1] as $short) {
                $classes[] = $namespace . '\\' . $short;
            }
        }
    }

    return array_values(array_unique($classes));
}

$projectClasses = discoverClasses($root . '/var');
if ($projectClasses === []) {
    fwrite(STDERR, "FAIL: no class found under var/\n");
    exit(1);
}

$skippedNames = ['__construct', '__destruct', '__clone', '__wakeup', '__sleep', '__set_state', '__call', '__get', '__set'];
$missing = [];
$traitMethods = 0;

foreach ($projectClasses as $class) {
    if (!class_exists($class) && !trait_exists($class) && !interface_exists($class)) {
        continue;
    }

    $reflection = new ReflectionClass($class);
    $isTrait = $reflection->isTrait();

    foreach ($reflection->getMethods() as $method) {
        if ($method->isPrivate() || in_array($method->getName(), $skippedNames, true)) {
            continue;
        }

        // 只审计当前类自身声明的方法 (trait 方法由使用类反射出来, 会重复)
        if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
            continue;
        }

        // trait 提供的方法: 声明类是使用类, 但定义位置在 trait 文件;
        // 这类方法不能带 #[\Override] (只要有任一个使用类不覆盖就会致命错误)
        if (!$isTrait && $method->getFileName() !== $reflection->getFileName()) {
            $traitMethods++;
            continue;
        }

        $overrides = false;
        $parent = $reflection->getParentClass();
        while ($parent) {
            if ($parent->hasMethod($method->getName())) {
                $overrides = true;
                break;
            }
            $parent = $parent->getParentClass();
        }

        if (!$overrides) {
            foreach ($reflection->getInterfaces() as $interface) {
                if ($interface->hasMethod($method->getName())) {
                    $overrides = true;
                    break;
                }
            }
        }

        if (!$overrides) {
            continue;
        }

        if ($isTrait) {
            // 使用类中会再次出现, 这里只统计
            $traitMethods++;
            continue;
        }

        $hasAttribute = false;
        foreach ($method->getAttributes() as $attribute) {
            if ('Override' === $attribute->getName()) {
                $hasAttribute = true;
                break;
            }
        }

        if (!$hasAttribute) {
            $missing[] = sprintf('%s::%s()', $class, $method->getName());
        }
    }
}

if ($verbose && $traitMethods > 0) {
    echo "info: skipped {$traitMethods} overriding method(s) declared in traits (cannot carry #[\\Override])\n";
}

if ($missing !== []) {
    sort($missing);
    fwrite(STDERR, "FAIL: " . count($missing) . " overriding method(s) missing #[\\Override]:\n");
    foreach ($missing as $item) {
        fwrite(STDERR, "  - {$item}\n");
    }
    exit(1);
}

echo "PASS: #[\\Override] audit (no class-declared override is missing the attribute)\n";
