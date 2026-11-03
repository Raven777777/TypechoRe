<?php
/** Filesystem checks use a separate upload root, never the working site's files. */

function checkFileRegressions(): void
{
    $root = sys_get_temp_dir() . '/typechore-files-' . bin2hex(random_bytes(6));
    mkdir($root, 0700);
    define('__TYPECHO_UPLOAD_ROOT_DIR__', $root);
    $owner = getmypid();
    register_shutdown_function(static function () use ($root, $owner): void {
        if (getmypid() !== $owner) {
            return;
        }
        foreach (glob($root . '/*') ?: [] as $file) {
            is_dir($file) ? @rmdir($file) : @unlink($file);
        }
        foreach (glob($root . '/.*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($root);
    });
    Typecho\Plugin::init([]);

    // checkFileType() reads the allowed attachment types from Options.
    $dbFile = sys_get_temp_dir() . '/typechore-files-db-' . bin2hex(random_bytes(6)) . '.db';
    register_shutdown_function(static function () use ($dbFile): void {
        @unlink($dbFile);
    });
    $db = new Typecho\Db('SQLite', 'filetest_');
    $db->addServer(['file' => $dbFile], Typecho\Db::READ | Typecho\Db::WRITE);
    Typecho\Db::set($db);
    $db->query('CREATE TABLE filetest_options ("name" varchar(32) NOT NULL, "user" int(10) NOT NULL DEFAULT 0, "value" text)', Typecho\Db::WRITE);
    $db->query($db->insert('table.options')->rows(['name' => 'theme', 'user' => 0, 'value' => 'default']));
    $db->query($db->insert('table.options')->rows(['name' => 'attachmentTypes', 'user' => 0, 'value' => '@doc@']));

    $path = $root . '/usr/uploads/attachment.txt';
    mkdir(dirname($path), 0700, true);
    file_put_contents($path, 'original');
    chmod($path, 0640);
    $content = ['attachment' => (object) [
        'path' => '/usr/uploads/attachment.txt', 'type' => 'txt', 'name' => 'attachment.txt', 'mime' => 'text/plain',
    ]];
    $modified = Widget\Upload::modifyHandle($content, ['name' => 'new.txt', 'bytes' => 'replacement']);
    check(is_array($modified) && $modified['size'] === 11 && file_get_contents($path) === 'replacement',
        'upload replacement failed or reported wrong size');
    if (DIRECTORY_SEPARATOR === '/') {
        check((fileperms($path) & 0777) === 0640, 'upload replacement changed permissions');
    }
    check(Widget\Upload::modifyHandle($content, ['name' => 'new.txt', 'tmp_name' => '/not-an-upload']) === false,
        'invalid uploaded file was accepted');
    check(file_get_contents($path) === 'replacement' && !glob($root . '/.upload-*'),
        'failed upload damaged the old file or left a temporary file');

    $theme = $root . '/theme.php';
    file_put_contents($theme, 'original theme');
    chmod($theme, 0640);
    $save = new ReflectionMethod(Widget\Themes\Edit::class, 'writeThemeFile');
    check($save->invoke(null, $theme, 'new theme') && file_get_contents($theme) === 'new theme', 'theme save failed');

    // Limit writes in a child to force a real short write without filling the disk.
    if (function_exists('pcntl_fork') && function_exists('posix_setrlimit')) {
        $pid = pcntl_fork();
        check($pid !== -1, 'could not fork short-write test');
        if ($pid === 0) {
            restore_error_handler(); // This child deliberately triggers an E_WARNING to exercise short-write handling.
            pcntl_signal(SIGXFSZ, SIG_IGN);
            posix_setrlimit(POSIX_RLIMIT_FSIZE, 8, 8);
            $themeSaved = $save->invoke(null, $theme, str_repeat('x', 1024));
            $uploadSaved = Widget\Upload::modifyHandle($content, ['name' => 'new.txt', 'bytes' => str_repeat('y', 1024)]);
            exit(!$themeSaved && $uploadSaved === false ? 0 : 1);
        }
        pcntl_waitpid($pid, $status);
        check(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0, 'short writes were published');
        check(file_get_contents($theme) === 'new theme' && file_get_contents($path) === 'replacement',
            'short write damaged the old theme or upload');
    }
    if (function_exists('symlink') && @symlink($theme, $root . '/linked.php')) {
        check($save->invoke(null, $root . '/linked.php', 'linked theme') && is_link($root . '/linked.php')
            && file_get_contents($theme) === 'linked theme', 'saving a theme symlink replaced the link instead of its target');
    }
    check(!glob($root . '/.theme-*') && !glob($root . '/.upload-*'), 'atomic write left temporary files');
    check(Widget\Upload::deleteHandle($content) && !file_exists($path), 'attachment deletion ignored configured upload root');
    check(Widget\Upload::deleteHandle($content), 'deleting an already missing attachment failed');
    mkdir($root . '/usr/uploads/cannot-unlink.txt');
    check(!Widget\Upload::deleteHandle(['attachment' => (object) ['path' => '/usr/uploads/cannot-unlink.txt']]),
        'attachment deletion reported a filesystem failure as success');

    Typecho\Widget::destroy();
    $db->flushPool();
    @unlink($dbFile);
}
