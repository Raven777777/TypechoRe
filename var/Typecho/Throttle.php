<?php

namespace Typecho;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 跨会话的失败计数
 *
 * 登录类接口的失败次数必须存在客户端无法重置的地方: session 里计数只要丢掉
 * cookie 就归零, 等于没有限速. 这里用临时目录下的一个小文件, 加锁读改写.
 */
final class Throttle
{
    public const LIMIT = 10;

    /** 计数窗口 (秒) */
    public const WINDOW = 900;

    public static function tooMany(string $scope, string $subject): bool
    {
        $file = self::file($scope, $subject);

        if ($file === null || !is_file($file)) {
            return false;
        }

        [$since, $count] = self::read($file);
        return time() - $since < self::WINDOW && $count >= self::LIMIT;
    }

    public static function fail(string $scope, string $subject): void
    {
        $file = self::file($scope, $subject);

        if ($file === null) {
            return;
        }

        $fp = @fopen($file, 'c+');
        if (!$fp) {
            return;
        }

        @chmod($file, 0600);
        if (@flock($fp, LOCK_EX)) {
            [$since, $count] = self::parse((string) stream_get_contents($fp));
            $now = time();

            if ($since === 0 || $now - $since >= self::WINDOW) {
                $since = $now;
                $count = 0;
            }

            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, $since . '|' . ($count + 1));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        if (1 === random_int(1, 100)) {
            self::gc();
        }
    }

    public static function clear(string $scope, string $subject): void
    {
        $file = self::file($scope, $subject);

        if ($file !== null) {
            @unlink($file);
        }
    }

    /**
     * 清理超出窗口的计数文件
     */
    private static function gc(): void
    {
        $dir = self::directory();

        foreach (glob($dir . '/*') ?: [] as $file) {
            if (time() - (@filemtime($file) ?: 0) > self::WINDOW) {
                @unlink($file);
            }
        }
    }

    /**
     * @return array{0: int, 1: int} [起始时间, 失败次数]
     */
    private static function read(string $file): array
    {
        $fp = @fopen($file, 'r');

        if (!$fp) {
            return [0, 0];
        }

        $value = '';
        if (@flock($fp, LOCK_SH)) {
            $value = (string) stream_get_contents($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        return self::parse($value);
    }

    private static function parse(string $value): array
    {
        if (!preg_match('/^(\d+)\|(\d+)$/', trim($value), $match)) {
            return [0, 0];
        }

        return [(int) $match[1], (int) $match[2]];
    }

    private static function file(string $scope, string $subject): ?string
    {
        $dir = self::directory();

        if ($dir === null) {
            return null;
        }

        return $dir . '/' . hash('sha256', $scope . "\n" . strtolower($subject));
    }

    private static function directory(): ?string
    {
        // shortcut: 与 PageCache 相同, 不通 POSIX / 没有可写临时目录时限速失效 (fail open).
        $uid = function_exists('posix_geteuid') ? posix_geteuid() : null;
        $dir = sys_get_temp_dir() . '/typechore-throttle-' . hash('sha256', __TYPECHO_ROOT_DIR__ . ':' . ($uid ?? 'unknown'));

        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return null;
        }

        if (is_link($dir) || ($uid !== null && @fileowner($dir) !== $uid)) {
            return null;
        }

        @chmod($dir, 0700);

        return is_writable($dir) ? $dir : null;
    }
}
