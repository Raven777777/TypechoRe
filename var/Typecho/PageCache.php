<?php

namespace Typecho;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

final class PageCache
{
    private const TTL = 60;
    private const LIMIT = 200;

    public static function eligible(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
            && empty($_GET)
            && empty($_SERVER['QUERY_STRING'])
            && parse_url($uri, PHP_URL_QUERY) === null
            && empty($_COOKIE)
            && !array_key_exists('HTTP_COOKIE', $_SERVER)
            && !array_key_exists('REDIRECT_HTTP_COOKIE', $_SERVER)
            && !array_key_exists('HTTP_AUTHORIZATION', $_SERVER)
            && !array_key_exists('REDIRECT_HTTP_AUTHORIZATION', $_SERVER)
            && !preg_match('~/(?:admin|action|feed|sitemap|xmlrpc)(?:/|\.|$)~i', $path)
            && empty(Plugin::export()['activated']);
    }

    public static function key(string $generation, string $theme, string $language): string
    {
        return hash('sha256', implode("\n", [
            $_SERVER['HTTP_HOST'] ?? '',
            $_SERVER['REQUEST_URI'] ?? '/',
            \Widget\Options::alloc()->rootUrl,
            $theme,
            $language,
            $generation,
        ]));
    }

    public static function get(string $key): ?string
    {
        $dir = self::directory();
        if ($dir === null) {
            return null;
        }
        $file = $dir . '/' . $key;
        $entry = @unserialize((string) @file_get_contents($file), ['allowed_classes' => false]);
        if (!is_array($entry) || ($entry['expires'] ?? 0) < time() || !is_string($entry['body'] ?? null)) {
            @unlink($file);
            return null;
        }
        return $entry['body'];
    }

    public static function put(string $key, string $body, int $ttl = self::TTL): bool
    {
        $dir = self::directory();
        if ($dir === null) {
            return false;
        }
        $temp = @tempnam($dir, '.tmp-');
        if ($temp === false) {
            return false;
        }
        @chmod($temp, 0600);
        $written = @file_put_contents($temp, serialize([
            'expires' => time() + max(1, min(86400, $ttl)),
            'body' => $body,
        ]), LOCK_EX) !== false
            && @rename($temp, $dir . '/' . $key);
        if (!$written) {
            @unlink($temp);
            return false;
        }
        self::cleanup($dir);
        return true;
    }

    public static function generation(): string
    {
        $dir = self::directory();
        if ($dir === null) {
            return '0';
        }
        $file = $dir . '/generation';
        return is_file($file) ? (string) @file_get_contents($file) : '0';
    }

    public static function invalidate(): void
    {
        $dir = self::directory();
        if ($dir === null) {
            return;
        }
        $file = $dir . '/generation';
        $fp = @fopen($file, 'c+');
        if (!$fp) {
            return;
        }
        @chmod($file, 0600);
        if (@flock($fp, LOCK_EX)) {
            $generation = (string) ((int) stream_get_contents($fp) + 1);
            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, $generation);
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    private static function directory(): ?string
    {
        $uid = function_exists('posix_geteuid') ? posix_geteuid() : fileowner(__TYPECHO_ROOT_DIR__);
        $dir = defined('__TYPECHO_PAGE_CACHE_DIR__')
            ? __TYPECHO_PAGE_CACHE_DIR__
            : sys_get_temp_dir() . '/typechore-page-cache-' . hash('sha256', __TYPECHO_ROOT_DIR__ . ':' . $uid);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return null;
        }
        if (is_link($dir) || @fileowner($dir) !== $uid) {
            return null;
        }
        @chmod($dir, 0700);
        return (@fileperms($dir) & 0077) === 0 ? $dir : null;
    }

    private static function cleanup(string $dir): void
    {
        $files = glob($dir . '/[a-f0-9]*') ?: [];
        if (count($files) <= self::LIMIT) {
            return;
        }
        usort($files, static fn(string $a, string $b): int => (filemtime($a) ?: 0) <=> (filemtime($b) ?: 0));
        foreach (array_slice($files, 0, count($files) - self::LIMIT) as $file) {
            @unlink($file);
        }
    }
}
