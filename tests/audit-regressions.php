<?php
/** Focused audit regressions, run on both temporary SQLite adapters. */

class AuditOptions extends RegressionOptions
{
    #[\Override]
    protected function ___rootUrl(): string { return $this->row['rootUrl']; }
    #[\Override]
    protected function ___adminUrl(): string { return $this->rootUrl . '/admin/'; }
    #[\Override]
    protected function ___index(): string { return $this->rootUrl . '/index.php'; }
    #[\Override]
    protected function ___time(): int { return $this->row['time']; }
}

class AuditEditor extends RegressionUser
{
    #[\Override]
    public function pass(string $group, bool $return = false): bool { return true; }
}

class AuditPageEdit extends Widget\Contents\Page\Edit
{
    #[\Override]
    public function getTemplates(): array { return []; }
}

function checkAuditRegressions(Typecho\Db $db): void
{
    foreach (['contents', 'comments', 'fields', 'users', 'options', 'passkeys'] as $table) {
        $db->truncate('table.' . $table);
    }
    Typecho\Plugin::init([]);
    $options = (new ReflectionClass(AuditOptions::class))->newInstanceWithoutConstructor();
    $settings = ['rootUrl' => 'https://blog.test', 'time' => 1000, 'secret' => 'audit-secret', 'theme' => 'default',
        'commentsShowCommentOnly' => 1, 'commentsPostIntervalEnable' => 1, 'commentsPostInterval' => 60,
        'commentsUniqueIpInterval' => 60, 'commentsRequireModeration' => 0, 'frontPage' => 'page:20'];
    $options->push($settings);
    $row = static fn(string $table, string $field, int $id): ?array => $db->fetchRow(
        $db->select()->from('table.' . $table)->where($field . ' = ?', $id));

    // A private, draft, future, deleted, or orphaned parent must not fill a public list slot.
    foreach ([1 => ['publish', 900], 2 => ['private', 900], 3 => ['draft', 900], 4 => ['publish', 1100]] as $cid => [$status, $created]) {
        $db->query($db->insert('table.contents')->rows(compact('cid', 'status', 'created') + ['type' => 'post']));
        $db->query($db->insert('table.comments')->rows(['cid' => $cid, 'coid' => $cid, 'created' => 900,
            'type' => 'comment', 'status' => 'approved', 'ownerId' => 8, 'text' => 'marker-' . $cid]));
    }
    $db->query($db->insert('table.comments')->rows(['cid' => 99, 'coid' => 5, 'created' => 900,
        'type' => 'comment', 'status' => 'approved']));
    $db->query($db->insert('table.comments')->rows(['cid' => 1, 'coid' => 6, 'created' => 900,
        'type' => 'comment', 'status' => 'approved', 'ownerId' => 8, 'authorId' => 8]));
    foreach ([[0, false], [1, false], [2, false], [0, true], [1, true], [2, true]] as [$parentId, $ignoreAuthor]) {
        $recent = regressionWidget(Widget\Comments\Recent::class, $db, $options);
        (new ReflectionProperty(Typecho\Widget::class, 'parameter'))->setValue($recent,
            new Typecho\Config(['pageSize' => 1, 'parentId' => $parentId, 'ignoreAuthor' => $ignoreAuthor]));
        $recent->execute();
        $stack = (new ReflectionProperty(Typecho\Widget::class, 'stack'))->getValue($recent);
        check(array_column($stack, 'cid') === ($parentId === 2 ? [] : [1]), 'recent comments exposed a nonpublic parent or applied limit before visibility');
        if ($ignoreAuthor && $parentId !== 2) {
            check(array_column($stack, 'coid') === [1], 'recent comments did not exclude the author before applying the limit');
        }
    }
    $db->truncate('table.comments');

    // Exercise the actual trackback path, not just the shared insertion helper.
    $server = $_SERVER;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REMOTE_ADDR'] = '192.0.2.44';
    unset($_SERVER['HTTP_REFERER']);
    $user = (new ReflectionClass(RegressionUser::class))->newInstanceWithoutConstructor();
    $user->push(['uid' => 0]);
    $feedback = regressionWidget(Widget\Feedback::class, $db, $options);
    (new ReflectionProperty(Widget\Base::class, 'user'))->setValue($feedback, $user);
    (new ReflectionProperty($feedback, 'content'))->setValue($feedback,
        (object) ['cid' => 1, 'authorId' => 8, 'author' => (object) ['uid' => 8]]);
    $request = new Typecho\Widget\Request(new Typecho\Request(), new Typecho\Config([
        'blog_name' => 'Example', 'url' => 'https://source.test/one', 'excerpt' => 'Trackback',
    ]));
    (new ReflectionProperty(Typecho\Widget::class, 'request'))->setValue($feedback, $request);
    Typecho\Plugin::factory(Widget\Feedback::class)->finishTrackback = static function (): void {
        throw new RuntimeException('trackback inserted');
    };
    $trackback = new ReflectionMethod($feedback, 'trackback');
    regressionThrows(fn() => $trackback->invoke($feedback), 'trackback did not complete');
    $request->setParam('url', 'https://source.test/two');
    regressionThrows(fn() => $trackback->invoke($feedback), 'trackback ignored the posting interval');
    check(count($db->fetchAll($db->select()->from('table.comments'))) === 1, 'repeated trackback bypassed posting intervals');
    $options->push(array_replace($settings, ['time' => 1060]));
    regressionThrows(fn() => $trackback->invoke($feedback), 'boundary trackback did not complete');
    check(count($db->fetchAll($db->select()->from('table.comments'))) === 2, 'trackback at interval boundary was rejected');
    $_SERVER = $server;
    Typecho\Plugin::init([]);
    $options->push($settings);

    // Recheck cleanup candidates under the deletion lock, preserving newly associated files.
    $attachment = regressionWidget(RegressionAttachmentEdit::class, $db, $options);
    $user->push(['uid' => 2]);
    (new ReflectionProperty(Widget\Base::class, 'user'))->setValue($attachment, $user);
    $uploadRoot = (string) constant('__TYPECHO_UPLOAD_ROOT_DIR__');
    $path = $uploadRoot . '/usr/uploads/audit-attachment.txt';
    is_dir(dirname($path)) || mkdir(dirname($path), 0700, true);
    file_put_contents($path, 'attachment bytes');
    $db->query($db->insert('table.contents')->rows(['cid' => 10, 'type' => 'attachment', 'authorId' => 2, 'parent' => 0,
        'text' => json_encode(['path' => '/usr/uploads/audit-attachment.txt', 'type' => 'txt'])]));
    $candidates = [10];
    $db->query($db->update('table.contents')->rows(['parent' => 1])->where('cid = ?', 10));
    $deleted = 0;
    $delete = new ReflectionMethod($attachment, 'deleteByIds');
    $delete->invokeArgs($attachment, [$candidates, &$deleted, true]);
    check($row('contents', 'cid', 10) !== null && file_get_contents($path) === 'attachment bytes',
        'cleanup deleted an attachment associated after candidate selection');
    $db->query($db->insert('table.comments')->rows(['cid' => 10, 'created' => 900]));
    foreach (['contents', 'comments'] as $table) {
        $db->query('CREATE TRIGGER itest_audit_failure BEFORE DELETE ON itest_' . $table
            . " BEGIN SELECT RAISE(ABORT, 'audit deletion failure'); END", Typecho\Db::WRITE);
        regressionThrows(function () use ($delete, $attachment, $candidates, &$deleted): void {
            @$delete->invokeArgs($attachment, [$candidates, &$deleted]);
        }, 'attachment deletion did not propagate a database failure');
        check($row('contents', 'cid', 10) !== null && file_get_contents($path) === 'attachment bytes',
            'failed database deletion lost attachment bytes or its row');
        check(!glob($uploadRoot . '/.typechore-delete-*'), 'rollback left staged attachment bytes');
        $db->query('DROP TRIGGER itest_audit_failure', Typecho\Db::WRITE);
    }
    $delete->invokeArgs($attachment, [$candidates, &$deleted]);
    check($row('contents', 'cid', 10) === null && !file_exists($path), 'explicit deletion failed for an archived attachment');

    // Page removal and every dependent write must roll back together.
    $db->query($db->insert('table.contents')->rows(['cid' => 20, 'type' => 'page', 'parent' => 0]));
    $db->query($db->insert('table.contents')->rows(['cid' => 21, 'type' => 'page', 'parent' => 20]));
    $db->query($db->insert('table.contents')->rows(['cid' => 22, 'type' => 'attachment', 'parent' => 20]));
    $db->query($db->insert('table.comments')->rows(['cid' => 20, 'created' => 900]));
    $db->query($db->insert('table.fields')->rows(['cid' => 20, 'name' => 'sample']));
    $db->query($db->insert('table.options')->rows(['name' => 'frontPage', 'user' => 0, 'value' => 'page:20']));
    $page = regressionWidget(AuditPageEdit::class, $db, $options);
    (new ReflectionProperty(Widget\Base::class, 'user'))->setValue($page,
        (new ReflectionClass(AuditEditor::class))->newInstanceWithoutConstructor());
    $page->request->setParam('cid', [20]);
    foreach (['comments', 'fields'] as $table) {
        $db->query('CREATE TRIGGER itest_audit_failure BEFORE DELETE ON itest_' . $table
            . " BEGIN SELECT RAISE(ABORT, 'audit deletion failure'); END", Typecho\Db::WRITE);
        regressionThrows(fn() => @$page->deletePage(), 'page deletion ignored a dependent failure');
        check($row('contents', 'cid', 20) !== null && (int) $row('contents', 'cid', 21)['parent'] === 20
            && (int) $row('contents', 'cid', 22)['parent'] === 20
            && $db->fetchRow($db->select()->from('table.options')->where('name = ?', 'frontPage'))['value'] === 'page:20',
            'failed page deletion committed partial cleanup');
        $db->query('DROP TRIGGER itest_audit_failure', Typecho\Db::WRITE);
    }
    Typecho\Plugin::factory(AuditPageEdit::class)->finishDelete = static function (): void {
        throw new RuntimeException('page deleted');
    };
    regressionThrows(fn() => $page->deletePage(), 'page did not reach completion hook');
    check($row('contents', 'cid', 20) === null && (int) $row('contents', 'cid', 21)['parent'] === 0
        && (int) $row('contents', 'cid', 22)['parent'] === 0, 'successful page deletion did not finish cleanup');
    Typecho\Plugin::init([]);

    // Round-trip real generated action URLs, including an installation prefix that repeats a route.
    foreach (['', '/blog', '/action'] as $base) {
        $options->push(array_replace($settings, ['rootUrl' => 'https://blog.test' . $base]));
        $security = regressionWidget(Widget\Security::class, $db, $options);
        (new ReflectionProperty($security, 'token'))->setValue($security, 'audit-secret');
        $urls = [$security->getIndex('/action/login'), $security->getAdminUrl('manage-users.php'),
            $security->getRootUrl('index.php/action/options-permalink'),
            $security->getTokenUrl('https://blog.test' . $base . $base . '/protected/')];
        foreach ($urls as $url) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
            $_SERVER['REQUEST_URI'] = parse_url($url, PHP_URL_PATH)
                . (parse_url($url, PHP_URL_QUERY) ? '?' . parse_url($url, PHP_URL_QUERY) : '');
            $request = new Typecho\Widget\Request(new Typecho\Request(), new Typecho\Config($params));
            (new ReflectionProperty(Typecho\Widget::class, 'request'))->setValue($security, $request);
            $security->protect();
            check($security->getToken(null) === $params['_'], 'generated CSRF token did not match its final target');
        }
    }
    $_SERVER = $server;
    check(!Typecho\Common::isSameOrigin('https://evil.invalid\\@blog.test/admin/', 'https://blog.test'),
        'browser-external backslash URL passed same-origin validation');

    checkUserSequenceUpgrade($db);
}

function checkUserSequenceUpgrade(Typecho\Db $db): void
{
    // Recreate the old schema, preserving custom indexes, triggers, and columns during upgrade.
    $db->query('DROP TABLE itest_users', Typecho\Db::WRITE);
    $schema = file_get_contents(ROOT . '/install/SQLite.sql');
    preg_match('/CREATE TABLE typecho_users.*?;/s', $schema, $match);
    $db->query(str_replace(['typecho_', ' AUTOINCREMENT'], ['itest_', ''], $match[0]), Typecho\Db::WRITE);
    $db->query('ALTER TABLE itest_users ADD COLUMN custom text', Typecho\Db::WRITE);
    $db->query('CREATE INDEX itest_custom_user ON itest_users(custom)', Typecho\Db::WRITE);
    $db->query('CREATE TRIGGER itest_custom_trigger AFTER INSERT ON itest_users BEGIN SELECT 1; END', Typecho\Db::WRITE);
    $db->query($db->insert('table.users')->rows(['uid' => 1, 'name' => 'admin', 'custom' => 'preserved']));
    $db->query($db->insert('table.contents')->rows(['cid' => 100, 'authorId' => 99, 'type' => 'post', 'status' => 'private']));
    $db->query('CREATE VIEW itest_user_view AS SELECT uid FROM itest_users', Typecho\Db::WRITE);
    $db->query('CREATE TABLE itest_user_child(uid INTEGER REFERENCES itest_users(uid) ON DELETE CASCADE)', Typecho\Db::WRITE);
    $db->query('INSERT INTO itest_user_child VALUES (1), (999)', Typecho\Db::WRITE);
    $db->query('PRAGMA foreign_keys = ON', Typecho\Db::WRITE);
    regressionThrows(fn() => Utils\Upgrade::v1_3_4($db), 'upgrade ignored a foreign key violation');
    check(!str_contains($db->fetchRow($db->select('sql')->from('sqlite_master')->where('name = ?', 'itest_users'))['sql'],
        'AUTOINCREMENT'), 'failed user upgrade committed its schema change');
    $db->query('DELETE FROM itest_user_child WHERE uid = 999', Typecho\Db::WRITE);
    Utils\Upgrade::v1_3_4($db);
    Utils\Upgrade::v1_3_4($db);
    check((int) $db->fetchRow($db->query('PRAGMA foreign_keys', Typecho\Db::WRITE))['foreign_keys'] === 1,
        'user upgrade did not restore foreign key enforcement');
    check($db->fetchRow('SELECT uid FROM itest_user_child')['uid'] == 1
        && $db->fetchRow('SELECT uid FROM itest_user_view')['uid'] == 1, 'user upgrade lost plugin child records or broke a view');
    check($db->fetchRow($db->select()->from('table.users')->where('uid = ?', 1))['custom'] === 'preserved', 'user upgrade lost a custom column');
    check(count($db->fetchAll($db->select()->from('sqlite_master')->where('name = ? OR name = ?',
        'itest_custom_user', 'itest_custom_trigger'))) === 2, 'user upgrade lost an index or trigger');
    $users = regressionWidget(Widget\Base\Users::class, $db,
        (new ReflectionClass(RegressionOptions::class))->newInstanceWithoutConstructor());
    $uid = $users->insert(['name' => 'new-user']);
    check($uid > 99, 'new user inherited an orphaned author ID');
    $users->delete($db->sql()->where('uid = ?', $uid));
    check($users->insert(['name' => 'next-user']) > $uid, 'deleted maximum user ID was reused');
}
