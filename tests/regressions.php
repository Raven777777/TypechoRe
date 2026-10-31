<?php
/** Regression cases run by integration.php against both temporary SQLite databases. */

use Typecho\Common;
use Typecho\Cookie;
use Typecho\Db;

function regressionWidget(string $class, Db $db, Widget\Options $options): object
{
    $widget = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    foreach (['db' => $db, 'options' => $options] as $name => $value) {
        (new ReflectionProperty(Widget\Base::class, $name))->setValue($widget, $value);
    }
    (new ReflectionProperty(Typecho\Widget::class, 'request'))->setValue($widget,
        new Typecho\Widget\Request(Typecho\Request::getInstance()));
    return $widget;
}

function regressionThrows(callable $call, string $message): void
{
    try {
        $call();
    } catch (Throwable $e) {
        check(!$e instanceof Error, $message . ': ' . $e->getMessage());
        return;
    }
    check(false, $message);
}

class RegressionUser extends Widget\User
{
    #[\Override]
    public function pass(string $group, bool $return = false): bool
    {
        return false;
    }
}

class RegressionAttachmentEdit extends Widget\Contents\Attachment\Edit
{
    #[\Override]
    public function push(array $value): array
    {
        return Typecho\Widget::push($value);
    }

    #[\Override]
    protected function ___attachment(): ?Typecho\Config
    {
        return new Typecho\Config(json_decode($this->text, true));
    }
}

/** 预取 permalink 需要站点的 index 地址, 测试里没有真实请求, 直接给定 */
class RegressionOptions extends Widget\Options
{
    #[\Override]
    protected function ___index(): string
    {
        return '/index.php';
    }
}

function checkRegressions(Db $db, string $sqliteFile): void
{
    Typecho\Plugin::init([]);
    $options = (new ReflectionClass(RegressionOptions::class))->newInstanceWithoutConstructor();
    $settings = [
        'secret' => 'regression-secret', 'commentsPageBreak' => 1, 'commentsThreaded' => 1,
        'commentsOrder' => 'ASC', 'commentsPageSize' => 2, 'commentsPageDisplay' => 'first',
        'commentsShowCommentOnly' => 0, 'commentsMaxNestingLevels' => 5,
        'commentsPostIntervalEnable' => 1, 'commentsPostInterval' => 60, 'time' => 1000,
    ];
    $options->push($settings);
    $count = static fn(string $table): int => (int) $db->fetchObject(
        $db->select(['COUNT(*)' => 'num'])->from('table.' . $table))->num;

    // Bound parameters must never replace marker-looking text inside another value.
    foreach (['#param:0#', '#param:9#', "a'b #param:0#"] as $value) {
        $db->query($db->insert('table.options')->rows(['name' => 'marker', 'user' => 0, 'value' => $value]));
        check($db->fetchRow($db->select()->from('table.options')->where('name = ?', 'marker'))['value'] === $value,
            'INSERT rewrote marker-looking data');
        $db->query($db->update('table.options')->rows(['value' => $value])->where('name = ?', 'marker'));
        check($db->fetchRow($db->select()->from('table.options')->where('name = ?', 'marker'))['value'] === $value,
            'UPDATE rewrote marker-looking data');
        $db->query($db->delete('table.options')->where('name = ?', 'marker'));
    }
    // ESCAPE 是 LIKE 的语法关键字, 不能被当成字段名加引号 (搜索接口依赖它)
    $likeSql = $db->select('cid')->from('table.contents')
        ->where("title LIKE ? ESCAPE '!' OR text LIKE ? ESCAPE '!'", '%a%', '%a%');
    check(str_contains((string) $likeSql, 'ESCAPE'), 'query builder mangled the LIKE ESCAPE clause');
    $db->fetchAll($likeSql);

    regressionThrows(fn() => @$db->query('SELECT FROM broken syntax'), 'invalid SQL did not throw a database exception');
    regressionThrows(fn() => $db->query($db->insert('table.options')->rows([
        'name' => 'nul', 'user' => 0, 'value' => "a\0b",
    ])), 'NUL text was silently changed');
    check(!$db->fetchRow($db->select()->from('table.options')->where('name = ?', 'nul')), 'NUL write changed the database');

    $db->truncate('table.comments');
    $db->truncate('table.contents');
    $db->query($db->insert('table.contents')->rows(['cid' => 1, 'type' => 'post', 'authorId' => 1, 'commentsNum' => 0]));
    foreach ([10 => 0, 11 => 10, 20 => 0, 21 => 20, 30 => 0, 35 => 10, 40 => 0] as $coid => $parent) {
        $db->query($db->insert('table.comments')->rows([
            'coid' => $coid, 'cid' => 1, 'parent' => $parent, 'created' => 1000,
            'type' => 'comment', 'status' => 'approved',
        ]));
    }
    $rank = static function (int $coid) use ($db, $options): int {
        $widget = regressionWidget(Widget\Base\Comments::class, $db, $options);
        $row = $db->fetchRow($db->select()->from('table.comments')->where('coid = ?', $coid));
        $widget->push($row);
        return $widget->commentPage;
    };
    $archive = static function (int $page) use ($db, $options): array {
        $widget = regressionWidget(Widget\Comments\Archive::class, $db, $options);
        (new ReflectionProperty(Typecho\Widget::class, 'parameter'))->setValue($widget,
            new Typecho\Config(['parentId' => 1, 'commentPage' => $page]));
        $widget->execute();
        $roots = (new ReflectionProperty(Typecho\Widget::class, 'stack'))->getValue($widget);
        $children = (new ReflectionProperty($widget, 'threadedComments'))->getValue($widget);
        $ids = array_keys($roots);
        foreach ($children as $rows) {
            $ids = array_merge($ids, array_column($rows, 'coid'));
        }
        return [$ids, (new ReflectionProperty($widget, 'total'))->getValue($widget)];
    };
    foreach ([false, true] as $threaded) {
        foreach (['ASC', 'DESC'] as $order) {
            $options->push(array_replace($settings, ['commentsThreaded' => $threaded, 'commentsOrder' => $order]));
            foreach ([10, 11, 20, 21, 30, 35, 40] as $coid) {
                check(in_array($coid, $archive($rank($coid))[0]), "comment {$coid} permalink points to another page");
            }
            if (!$threaded && $order === 'ASC') {
                check($rank(35) === 3, 'flat comment ranks collapsed replies');
            }
            if ($threaded && $order === 'DESC') {
                check($rank(30) === 1, 'descending comment rank counted a late reply as a root');
            }
        }
    }
    $options->push($settings);
    foreach (['waiting', 'spam'] as $status) {
        $db->query($db->update('table.comments')->rows(['status' => $status])->where('coid = ?', 10));
        check($archive(1)[1] === 5 && in_array(11, $archive($rank(11))[0]), 'invisible parent hid its approved reply');
        Cookie::set('__typecho_unapproved_comment', '10:' . hash_hmac('sha256', '10', $settings['secret']));
        check($archive(1)[1] === 4 && $rank(35) === $rank(10), 'signed moderation cookie changed pagination rules');
        Cookie::delete('__typecho_unapproved_comment');
    }
    $db->query($db->delete('table.comments')->where('coid = ?', 10));
    check($archive(1)[1] === 5 && in_array(35, $archive($rank(35))[0]), 'deleted parent hid its reply');
    $db->query($db->update('table.comments')->rows(['type' => 'trackback'])->where('coid = ?', 20));
    $options->push(array_replace($settings, ['commentsShowCommentOnly' => 1]));
    check(in_array(21, $archive($rank(21))[0]) && !in_array(20, $archive($rank(21))[0]),
        'a filtered parent hid its approved comment reply');
    $options->push($settings);
    $db->query($db->insert('table.comments')->rows([
        'coid' => 50, 'cid' => 1, 'parent' => 60, 'created' => 1000, 'status' => 'approved', 'type' => 'comment',
    ]));
    $db->query($db->insert('table.comments')->rows([
        'coid' => 60, 'cid' => 2, 'parent' => 0, 'created' => 1000, 'status' => 'approved', 'type' => 'comment',
    ]));
    check(in_array(50, $archive($rank(50))[0]), 'a parent on another article changed reply pagination');
    $db->query($db->update('table.comments')->rows(['parent' => 40])->where('coid = ?', 40));
    check($rank(40) === 1, 'cyclic ancestry did not terminate');
    $db->truncate('table.comments');

    // Counter changes and their comment write must roll back together.
    $comments = regressionWidget(Widget\Base\Comments::class, $db, $options);
    $db->query('CREATE TRIGGER itest_reject_counter BEFORE UPDATE OF commentsNum ON itest_contents '
        . "BEGIN SELECT RAISE(ABORT, 'counter failure'); END", Db::WRITE);
    regressionThrows(fn() => @$comments->insert(['cid' => 1, 'status' => 'approved']), 'failed counter did not reject comment');
    check($count('comments') === 0, 'counter failure left an inserted comment');
    $db->query('DROP TRIGGER itest_reject_counter', Db::WRITE);
    $coid = $comments->insert(['cid' => 1, 'status' => 'waiting']);
    $db->query('CREATE TRIGGER itest_reject_counter BEFORE UPDATE OF commentsNum ON itest_contents '
        . "BEGIN SELECT RAISE(ABORT, 'counter failure'); END", Db::WRITE);
    regressionThrows(fn() => @$comments->update(['status' => 'approved'], $db->sql()->where('coid = ?', $coid)),
        'failed approval counter did not reject status change');
    check($db->fetchRow($db->select()->from('table.comments')->where('coid = ?', $coid))['status'] === 'waiting',
        'counter failure left a comment approved');
    $db->query('DROP TRIGGER itest_reject_counter', Db::WRITE);
    $comments->update(['status' => 'approved'], $db->sql()->where('coid = ?', $coid));
    check((int) $db->fetchRow($db->select()->from('table.contents')->where('cid = ?', 1))['commentsNum'] === 1,
        'approval did not update comment count');
    $db->query('CREATE TRIGGER itest_reject_counter BEFORE UPDATE OF commentsNum ON itest_contents '
        . "BEGIN SELECT RAISE(ABORT, 'counter failure'); END", Db::WRITE);
    regressionThrows(fn() => @$comments->delete($db->sql()->where('coid = ?', $coid)),
        'failed deletion counter did not reject comment deletion');
    check($count('comments') === 1, 'counter failure left a comment deleted');
    $db->query('DROP TRIGGER itest_reject_counter', Db::WRITE);
    $comments->delete($db->sql()->where('coid = ?', $coid));
    check((int) $db->fetchRow($db->select()->from('table.contents')->where('cid = ?', 1))['commentsNum'] === 0,
        'deletion did not update comment count');

    $user = (new ReflectionClass(RegressionUser::class))->newInstanceWithoutConstructor();
    $user->push(['uid' => 2]);
    $feedback = regressionWidget(Widget\Feedback::class, $db, $options);
    (new ReflectionProperty(Widget\Base::class, 'user'))->setValue($feedback, $user);
    (new ReflectionProperty($feedback, 'content'))->setValue($feedback, (object) ['authorId' => 1]);
    $insert = new ReflectionMethod($feedback, 'insertComment');
    $comment = ['cid' => 1, 'ip' => '192.0.2.10', 'created' => 1000, 'status' => 'approved'];
    $insert->invoke($feedback, $comment);
    regressionThrows(fn() => $insert->invoke($feedback, $comment), 'same-second comment bypassed the interval');
    check($count('comments') === 1, 'interval rejection inserted a comment');
    $comment['created'] = 1060;
    $insert->invoke($feedback, $comment);
    check($count('comments') === 2, 'comment at interval boundary was rejected');
    if (function_exists('pcntl_fork')) {
        $pipes = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $pids = [];
        for ($i = 0; $i < 2; $i++) {
            $pid = pcntl_fork();
            check($pid !== -1, 'could not fork concurrent comment test');
            if ($pid === 0) {
                ob_end_clean();
                fread($pipes[1], 1);
                $childDb = new Db($db->getAdapterName(), $db->getPrefix());
                $childDb->addServer(['file' => $sqliteFile], Db::READ | Db::WRITE);
                Db::set($childDb);
                $child = regressionWidget(Widget\Feedback::class, $childDb, $options);
                (new ReflectionProperty(Widget\Base::class, 'user'))->setValue($child, $user);
                (new ReflectionProperty($child, 'content'))->setValue($child, (object) ['authorId' => 1]);
                try {
                    (new ReflectionMethod($child, 'insertComment'))->invoke($child, array_replace($comment, ['created' => 1120]));
                    exit(0);
                } catch (Typecho\Widget\Exception $e) {
                    exit($e->getCode() === 403 ? 10 : 1);
                } catch (Throwable $e) {
                    fwrite(STDERR, $e->getMessage() . "\n");
                    exit(1);
                }
            }
            $pids[] = $pid;
        }
        fwrite($pipes[0], 'go');
        $results = [];
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            $results[] = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : -1;
        }
        fclose($pipes[0]);
        fclose($pipes[1]);
        sort($results);
        check($results === [0, 10] && $count('comments') === 3, 'concurrent comments bypassed the interval');
    }

    // Mixed IDs must not reuse a denied attachment's widget row or delete a post.
    $attachment = regressionWidget(RegressionAttachmentEdit::class, $db, $options);
    (new ReflectionProperty(Widget\Base::class, 'user'))->setValue($attachment, $user);
    $db->query($db->insert('table.contents')->rows([
        'cid' => 2, 'authorId' => 2, 'type' => 'post', 'text' => 'own post',
    ]));
    $db->query($db->insert('table.contents')->rows([
        'cid' => 3, 'authorId' => 1, 'type' => 'attachment',
        'text' => json_encode(['path' => '/victim.txt', 'type' => 'txt']),
    ]));
    $deleted = 0;
    $ids = [3, 2, 99999];
    (new ReflectionMethod($attachment, 'deleteByIds'))->invokeArgs($attachment, [$ids, &$deleted]);
    check($count('contents') === 3, 'mixed attachment IDs deleted an unauthorized row');

    $db->query($db->insert('table.contents')->rows([
        'cid' => 4, 'authorId' => 2, 'type' => 'attachment',
        'text' => json_encode(['path' => '/cannot-unlink.txt', 'type' => 'txt']),
    ]));
    $ids = [4];
    (new ReflectionMethod($attachment, 'deleteByIds'))->invokeArgs($attachment, [$ids, &$deleted]);
    check($db->fetchRow($db->select()->from('table.contents')->where('cid = ?', 4)) !== null,
        'failed file deletion removed its database record');

    $db->query($db->insert('table.fields')->rows(['cid' => 1, 'name' => 'old', 'type' => 'str', 'str_value' => 'old']));
    $db->query($db->insert('table.relationships')->rows(['cid' => 1, 'mid' => 1]));
    checkPrefetchRegressions($db, $options);
    checkBackupRegressions($db, $sqliteFile, $options);
    require_once __DIR__ . '/audit-regressions.php';
    checkAuditRegressions($db);
    checkDefaultDocsTheme($db);
}

/**
 * default-docs: 首次启用由 PHP 建索引页 (走真实的事务入口), 撤下/删除的页面不重建, 不产生 -2 重复页
 */
function checkDefaultDocsTheme(Db $db): void
{
    require_once ROOT . '/usr/themes/default-docs/functions.php';
    foreach (['contents', 'options', 'users'] as $table) {
        $db->truncate('table.' . $table);
    }
    $db->query($db->insert('table.users')->rows(['uid' => 7, 'name' => 'theme', 'mail' => 'theme@example.test']));
    // 管理员已撤下的标签页
    $db->query($db->insert('table.contents')->rows([
        'cid' => 1, 'title' => 'Tags', 'slug' => 'tags', 'type' => 'page',
        'status' => 'draft', 'template' => 'page-tags.php', 'created' => 1,
    ]));
    // 分类地址被其他内容占用
    $db->query($db->insert('table.contents')->rows([
        'cid' => 2, 'title' => 'Other', 'slug' => 'categories', 'type' => 'post',
        'status' => 'publish', 'created' => 1,
    ]));

    docswebCreateIndexPages();

    $page = static fn(string $template): ?array => $db->fetchRow($db->select()->from('table.contents')
        ->where('type = ?', 'page')->where('template = ?', $template)->limit(1));
    $archives = $page('page-archives.php');
    check($archives !== null && $archives['status'] === 'publish' && (int) $archives['authorId'] === 7,
        'index page was not created by PHP');
    check($page('page-categories.php') === null, 'index page reused a slug taken by other content');
    $tags = $page('page-tags.php');
    check($tags !== null && $tags['status'] === 'draft', 'a withdrawn index page was republished');
    check($db->fetchRow($db->select('cid')->from('table.contents')->where('slug = ?', 'tags-2')) === null
        && $db->fetchRow($db->select('cid')->from('table.contents')->where('slug = ?', 'categories-2')) === null,
        'duplicate suffixed index pages were created');
    check($db->fetchRow($db->select('name')->from('table.options')->where('name = ?', 'default_docs_indexes')) !== null,
        'index page setup was not marked as done');

    // 删除后再次运行不会重建
    $db->query($db->delete('table.contents')->where('template = ?', 'page-archives.php'));
    docswebCreateIndexPages();
    check($page('page-archives.php') === null, 'a deleted index page was recreated');
}

/**
 * 列表页预取: 每行的分类/标签必须和逐行查询时一致, 且不影响没有关联的行
 */
function checkPrefetchRegressions(Db $db, Widget\Options $options): void
{
    Typecho\Router::setRoutes([
        'index' => ['url' => '/', 'widget' => '\Widget\Archive'],
        'category' => ['url' => '/category/[directory]/', 'widget' => '\Widget\Archive'],
        'tag' => ['url' => '/tag/[slug]/', 'widget' => '\Widget\Archive'],
    ]);

    $db->query($db->insert('table.metas')->rows([
        'mid' => 11, 'name' => 'Root', 'slug' => 'root', 'type' => 'category', 'parent' => 0, 'order' => 1, 'count' => 1,
    ]));
    $db->query($db->insert('table.metas')->rows([
        'mid' => 12, 'name' => 'Child', 'slug' => 'child', 'type' => 'category', 'parent' => 11, 'order' => 2, 'count' => 1,
    ]));
    $db->query($db->insert('table.metas')->rows([
        'mid' => 13, 'name' => 'Tag', 'slug' => 'tag-one', 'type' => 'tag', 'parent' => 0, 'order' => 0, 'count' => 1,
    ]));
    // 用没被前面用例碰过的 cid, 否则默认分类的关联会混进来
    $db->query($db->insert('table.relationships')->rows(['cid' => 21, 'mid' => 12]));
    $db->query($db->insert('table.relationships')->rows(['cid' => 22, 'mid' => 13]));

    $archive = regressionWidget(Widget\Archive::class, $db, $options);
    $stack = [
        ['cid' => 21, 'authorId' => 1, 'title' => 'first'],
        ['cid' => 22, 'authorId' => 1, 'title' => 'second'],
        ['cid' => 23, 'authorId' => 1, 'title' => 'third'],
    ];
    (new ReflectionProperty(Typecho\Widget::class, 'stack'))->setValue($archive, $stack);
    $archive->prefetchRelated();
    $rows = (new ReflectionProperty(Typecho\Widget::class, 'stack'))->getValue($archive);

    check($rows[0]['title'] === 'first', 'prefetch dropped unrelated row data');
    check(array_column($rows[0]['#categories'], 'name') === ['Child'], 'prefetch attached the wrong category');
    check($rows[0]['#categories'][0]['permalink'] === '/index.php/category/root/child/', 'prefetch built the wrong hierarchical category permalink');
    check($rows[0]['#categories'][0]['order'] === 2 && $rows[0]['#categories'][0]['parent'] === 11,
        'prefetch dropped category fields the templates use');
    check($rows[0]['#tags'] === [], 'prefetch attached a tag to a post without tags');
    check($rows[1]['#tags'][0]['permalink'] === '/index.php/tag/tag-one/', 'prefetch built the wrong tag permalink');
    check($rows[1]['#categories'] === [], 'prefetch attached a category to a post without categories');
    check($rows[2]['#categories'] === [] && $rows[2]['#tags'] === [], 'prefetch invented relations for a bare post');

    // 单条内容不预取: 不能为了省一次查询反而多查两次
    $single = regressionWidget(Widget\Archive::class, $db, $options);
    (new ReflectionProperty(Typecho\Widget::class, 'stack'))->setValue($single, [['cid' => 21, 'title' => 'only']]);
    $single->prefetchRelated();
    $singleRow = (new ReflectionProperty(Typecho\Widget::class, 'stack'))->getValue($single)[0];
    check(!array_key_exists('#categories', $singleRow), 'prefetch ran for a single item');
}

function checkBackupRegressions(Db $db, string $sqliteFile, Widget\Options $options): void
{
    $backup = regressionWidget(Widget\Backup::class, $db, $options);
    $restore = new ReflectionMethod($backup, 'restoreData');
    $build = new ReflectionMethod($backup, 'buildBuffer');
    $header = '%TYPECHO_BACKUP_0002%';
    $record = $build->invoke($backup, 1, ['cid' => '9', 'title' => 'restored', 'type' => 'post']);
    $user = $build->invoke($backup, 5, ['uid' => '1', 'name' => 'restored-admin', 'group' => 'administrator']);
    $file = tempnam(sys_get_temp_dir(), 'typechore-restore-');
    $cookies = $_COOKIE;
    $snapshot = static function () use ($db): array {
        $result = [];
        foreach (['contents', 'comments', 'users', 'fields', 'relationships', 'metas', 'passkeys', 'options'] as $table) {
            $result[$table] = $db->fetchAll($db->select()->from('table.' . $table));
        }
        return $result;
    };
    try {
        $before = $snapshot();
        $badSchema = Common::buildBackupBuffer('1', '{"title":-1}', 'x');
        $zeroHeader = pack('vvV', 1, 0, 0);
        $zeroHeader .= hash('sha256', $zeroHeader);
        foreach ([substr($record, 0, -1) . '!', $record, $badSchema, $zeroHeader,
            pack('vvV', 1, 2, 0x7fffffff) . '{}', 'garbage'] as $bad) {
            // Valid user first also tests that failed imports do not publish new login cookies.
            file_put_contents($file, $header . $user . $record . $bad . $header);
            regressionThrows(fn() => @$restore->invoke($backup, $file), 'corrupt/duplicate backup was accepted');
            check($snapshot() === $before, 'failed restore changed existing tables');
            check($_COOKIE === $cookies, 'failed restore changed authentication cookies');
        }
        file_put_contents($file, $header . $record . $user . $header);
        $restore->invoke($backup, $file);
        check($db->fetchRow($db->select()->from('table.contents'))['title'] === 'restored', 'valid restore failed');
        foreach (['comments', 'fields', 'relationships', 'metas', 'passkeys'] as $table) {
            check($db->fetchAll($db->select()->from('table.' . $table)) === [], 'restore retained source-empty table ' . $table);
        }
        $stored = $db->fetchRow($db->select()->from('table.users'));
        check(Common::validateAuthCode(Cookie::get('__typecho_authCode'), $stored['authCode']), 'restored login cookie does not match committed user');
        check($snapshot()['options'] === $before['options'], 'restore changed site options');
        // 备份里只有普通用户: 清空后没人能进后台, 必须整体回滚
        $subscriber = $build->invoke($backup, 5, ['uid' => '2', 'name' => 'plain', 'group' => 'subscriber']);
        $before = $snapshot();
        file_put_contents($file, $header . $subscriber . $record . $header);
        regressionThrows(fn() => @$restore->invoke($backup, $file), 'backup without an administrator was restored');
        check($snapshot() === $before, 'restore without an administrator changed existing tables');

        file_put_contents($file, $header . $header);
        $restore->invoke($backup, $file);
        check($db->fetchAll($db->select()->from('table.contents')) === [], 'empty backup retained old rows');
    } finally {
        unlink($file);
        $_COOKIE = $cookies;
    }

    // Run a writer between export pages while SQLite is in WAL mode.
    $db->query('PRAGMA journal_mode=WAL', Db::READ);
    for ($cid = 1; $cid <= 21; $cid++) {
        $db->query($db->insert('table.contents')->rows(['cid' => $cid, 'title' => 'snapshot-' . $cid]));
    }
    $writer = new SQLite3($sqliteFile);
    $calls = 0;
    // A user stream lets the test pause exactly after the twentieth exported record.
    RegressionBackupStream::$onWrite = static function () use (&$calls, $writer): void {
        if (++$calls === 20) {
            check($writer->exec('DELETE FROM itest_contents WHERE cid = 1') && $writer->changes() === 1,
                'snapshot test writer did not delete its row');
        }
    };
    stream_wrapper_register('regbackup', RegressionBackupStream::class);
    $stream = fopen('regbackup://export', 'wb');
    try {
        (new ReflectionMethod($backup, 'exportData'))->invoke($backup, $stream);
        check(count(RegressionBackupStream::$buffers) === 21, 'concurrent deletion made backup skip a row');
        check((int) $db->fetchObject($db->select(['COUNT(*)' => 'num'])->from('table.contents'))->num === 20,
            'snapshot test did not overlap a committed write');
    } finally {
        fclose($stream);
        stream_wrapper_unregister('regbackup');
        RegressionBackupStream::$buffers = [];
        $writer->close();
    }
}

class RegressionBackupStream
{
    public $context;
    public static $onWrite;
    public static array $buffers = [];

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_write(string $data): int
    {
        self::$buffers[] = $data;
        (self::$onWrite)();
        return strlen($data);
    }
}
