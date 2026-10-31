<?php
/**
 * 数据库适配器集成测试
 *
 * 覆盖: 连接、建表 (直接执行 install/*.sql)、插入/查询/更新/删除、
 * lastInsertId、affectedRows、JOIN、SELECT COUNT、getVersion。
 *
 * 用法:
 *   php tests/integration.php
 *   TYPECHORE_TEST_ADAPTER=SQLite,Pdo_SQLite php tests/integration.php
 *
 * 本项目只支持 SQLite, 默认测试两个 SQLite 适配器 (临时文件), 其它适配器名直接判失败。
 */

declare(strict_types=1);

const ROOT = __DIR__ . '/..';
define('__TYPECHO_ROOT_DIR__', ROOT);
define('__TYPECHO_PAGE_CACHE_DIR__', sys_get_temp_dir() . '/typechore-page-cache-integration-' . getmypid());
register_shutdown_function(static function (): void {
    foreach (glob(__TYPECHO_PAGE_CACHE_DIR__ . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir(__TYPECHO_PAGE_CACHE_DIR__);
});

spl_autoload_register(static function (string $class): void {
    foreach (['Typecho\\' => ROOT . '/var/Typecho/', 'Widget\\' => ROOT . '/var/Widget/', 'Utils\\' => ROOT . '/var/Utils/'] as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
            return;
        }
    }
});

require_once ROOT . '/var/Typecho/Common.php';

use Typecho\Db;

class IntegrationFailure extends RuntimeException
{
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new IntegrationFailure($message);
    }
}

/**
 * 该适配器所需的扩展是否满足
 */
function adapterReady(string $adapter): bool
{
    return extension_loaded($adapter === 'SQLite' ? 'sqlite3' : 'pdo_sqlite');
}

function dropTables(Db $db, array $tables): void
{
    foreach ($tables as $table) {
        try {
            $db->query("DROP TABLE IF EXISTS {$db->getPrefix()}{$table}");
        } catch (Typecho\Db\Exception $e) {
            // 表不存在时忽略
        }
    }
}

/**
 * 按 install/*.sql 建表 (替换前缀), 返回表名列表
 *
 * @return string[]
 */
function createTables(Db $db): array
{
    $scripts = file_get_contents(ROOT . '/install/SQLite.sql');
    if (false === $scripts) {
        throw new IntegrationFailure('cannot read install/SQLite.sql');
    }

    $scripts = str_replace('typecho_', $db->getPrefix(), $scripts);

    foreach (explode(';', $scripts) as $script) {
        $script = trim($script);
        if ('' !== $script) {
            $db->query($script, Db::WRITE);
        }
    }

    return ['comments', 'contents', 'fields', 'metas', 'options', 'passkeys', 'relationships', 'users'];
}

function runAdapter(string $adapter): void
{
    $sqliteFile = sys_get_temp_dir() . '/typechore-integration-' . bin2hex(random_bytes(4)) . '.db';
    $tables = ['comments', 'contents', 'fields', 'metas', 'options', 'passkeys', 'relationships', 'users'];
    $db = new Db($adapter, 'itest_');

    try {
        $db->addServer(['file' => $sqliteFile], Db::READ | Db::WRITE);
        Db::set($db);

        check($db->getAdapterName() === $adapter, "adapter name mismatch: {$db->getAdapterName()}");

        dropTables($db, $tables);
        $created = createTables($db);
        check($created === $tables, 'unexpected table list from install SQL');

        $version = $db->getVersion();
        check('' !== $version, 'getVersion() returned an empty string');

        // --- insert (query() 对 INSERT 返回自增主键) ---
        $generationBeforeInsert = \Typecho\PageCache::generation();
        $rows = [
            'name' => 'integration_option',
            'user' => 0,
            'value' => 'first',
        ];
        $optionId = $db->query($db->insert('table.options')->rows($rows));
        check(is_int($optionId) && $optionId > 0, 'INSERT did not return a positive id');
        check(\Typecho\PageCache::generation() !== $generationBeforeInsert, 'write outside transaction did not invalidate page cache');

        // --- fetchRow ---
        $row = $db->fetchRow($db->select()->from('table.options')->where('name = ?', 'integration_option'));
        check(is_array($row) && $row['value'] === 'first', 'fetchRow() returned wrong data');

        // --- update + affectedRows ---
        $affected = $db->query(
            $db->update('table.options')->rows(['value' => 'second'])->where('name = ?', 'integration_option')
        );
        check($affected === 1, "update affected {$affected} rows, expected 1");

        // --- fetchObject ---
        $object = $db->fetchObject(
            $db->select(['COUNT(*)' => 'num'])->from('table.options')->where('name = ?', 'integration_option')
        );
        check($object !== null && (int) $object->num === 1, 'fetchObject() with COUNT returned wrong result');

        // --- 多表 JOIN (contents + relationships + metas) ---
        $mid = $db->query(
            $db->insert('table.metas')->rows([
                'name' => 'IntegrationCat',
                'slug' => 'integration-cat',
                'type' => 'category',
            ])
        );
        check(is_int($mid) && $mid > 0, 'metas insert did not return an id');

        $cid = $db->query(
            $db->insert('table.contents')->rows([
                'title' => 'Integration Post',
                'slug' => 'integration-post',
                'created' => time(),
                'text' => 'hello',
                'type' => 'post',
                'status' => 'publish',
            ])
        );
        check(is_int($cid) && $cid > 0, 'contents insert did not return an id');

        $db->query($db->insert('table.relationships')->rows(['cid' => $cid, 'mid' => $mid]));

        $joined = $db->fetchRow(
            $db->select('table.contents.title', 'table.metas.name')->from('table.contents')
                ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
                ->join('table.metas', 'table.metas.mid = table.relationships.mid')
                ->where('table.contents.cid = ?', $cid)
                ->limit(1)
        );
        check(
            is_array($joined) && $joined['title'] === 'Integration Post' && $joined['name'] === 'IntegrationCat',
            'JOIN query returned wrong data'
        );

        // --- fetchAll + 过滤回调 ---
        $all = $db->fetchAll(
            $db->select('name')->from('table.options')->where('name = ?', 'integration_option'),
            static fn(array $row): array => ['n' => $row['name']]
        );
        check($all === [['n' => 'integration_option']], 'fetchAll() with filter returned wrong data');

        // --- delete + affectedRows ---
        $affected = $db->query($db->delete('table.options')->where('name = ?', 'integration_option'));
        check($affected === 1, "delete affected {$affected} rows, expected 1");

        $row = $db->fetchRow($db->select()->from('table.options')->where('name = ?', 'integration_option'));
        check(null === $row, 'deleted row is still readable');

        // --- transaction rollback and transaction-local reads ---
        $generationBeforeRollback = \Typecho\PageCache::generation();
        try {
            $db->transaction(function () use ($db): void {
                $db->query($db->insert('table.options')->rows([
                    'name' => 'rolled_back_option', 'user' => 0, 'value' => 'temporary',
                ]));
                check(null !== $db->fetchRow($db->select()->from('table.options')
                    ->where('name = ?', 'rolled_back_option')), 'transaction read did not see its write');
                throw new RuntimeException('rollback test');
            });
        } catch (RuntimeException $e) {
            check($e->getMessage() === 'rollback test', 'unexpected transaction exception');
        }
        check(null === $db->fetchRow($db->select()->from('table.options')
            ->where('name = ?', 'rolled_back_option')), 'transaction rollback kept its write');
        check(\Typecho\PageCache::generation() === $generationBeforeRollback, 'rolled-back write invalidated page cache');
        $db->transaction(function () use ($db): void {
            $db->query($db->insert('table.options')->rows([
                'name' => 'committed_option', 'user' => 0, 'value' => 'committed',
            ]));
        });
        check(\Typecho\PageCache::generation() !== $generationBeforeRollback, 'committed write did not invalidate page cache');

        // --- truncate + 表清空 ---
        $generationBeforeTruncate = \Typecho\PageCache::generation();
        $db->truncate('table.relationships');
        check(\Typecho\PageCache::generation() !== $generationBeforeTruncate, 'truncate did not invalidate page cache');
        $remaining = $db->fetchObject($db->select(['COUNT(*)' => 'num'])->from('table.relationships'));
        check($remaining !== null && (int) $remaining->num === 0, 'truncate() did not empty the table');

        // --- 1.3.2 升级脚本: 为旧站点补建 passkeys 表 (幂等) ---
        dropTables($db, ['passkeys']);
        \Utils\Upgrade::v1_3_2($db);
        $passkeys = $db->fetchObject($db->select(['COUNT(*)' => 'num'])->from('table.passkeys'));
        check($passkeys !== null && 0 === (int) $passkeys->num, 'upgrade v1_3_2 did not create the passkeys table');
        \Utils\Upgrade::v1_3_2($db);
        $db->query(
            $db->insert('table.passkeys')->rows([
                'uid' => 1,
                'credential_id' => 'integration-credential',
                'public_key' => 'key',
                'name' => 'integration',
                'created' => time(),
            ])
        );

        dropTables($db, $tables);
        $db->flushPool();

        echo "PASS: {$adapter} integration\n";
    } finally {
        if (is_file($sqliteFile)) {
            @unlink($sqliteFile);
        }
        $db->flushPool();
    }
}

$requested = getenv('TYPECHORE_TEST_ADAPTER');
$adapters = $requested
    ? array_values(array_filter(array_map('trim', explode(',', $requested))))
    : ['SQLite', 'Pdo_SQLite'];

$skipped = [];
$failed = [];
$passed = 0;

foreach ($adapters as $adapter) {
    if (!in_array($adapter, ['SQLite', 'Pdo_SQLite'], true)) {
        $failed[] = "{$adapter}: 只支持 SQLite";
        continue;
    }

    if (!adapterReady($adapter)) {
        $skipped[] = "{$adapter} (extension missing)";
        continue;
    }

    try {
        runAdapter($adapter);
        $passed++;
    } catch (Throwable $e) {
        $failed[] = $adapter . ': ' . $e->getMessage();
        fwrite(STDERR, "[FAIL] {$adapter}: " . $e->getMessage() . "\n");
        fwrite(STDERR, $e->getTraceAsString() . "\n");
    }
}

foreach ($skipped as $skip) {
    echo "SKIP: {$skip}\n";
}

if ($failed !== []) {
    fwrite(STDERR, "FAIL: database integration (" . implode('; ', $failed) . ")\n");
    exit(1);
}

if (0 === $passed) {
    fwrite(STDERR, "FAIL: no adapter could be tested (set TYPECHORE_TEST_ADAPTER)\n");
    exit(1);
}

echo "PASS: database integration ({$passed} adapter(s))\n";
