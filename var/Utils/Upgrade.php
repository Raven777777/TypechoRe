<?php

namespace Utils;

use Typecho\Db;
use Widget\Options;

/**
 * 升级程序
 *
 * @category typecho
 * @package Upgrade
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Upgrade
{
    /**
     * @param Db $db
     * @param Options $options
     */
    public static function v1_3_0(Db $db, Options $options)
    {
        $routingTable = $options->routingTable;

        $routingTable['comment_page'] = [
            'url'    => '[permalink:string]/comment-page-[commentPage:digital]',
            'widget' => '\Widget\CommentPage',
            'action' => 'action'
        ];

        $routingTable['feed'] = [
            'url'    => '/feed[feed:string:0]',
            'widget' => '\Widget\Feed',
            'action' => 'render'
        ];

        unset($routingTable[0]);

        $db->query($db->update('table.options')
            ->rows(['value' => json_encode($routingTable)])
            ->where('name = ?', 'routingTable'));

        // fix options->commentsRequireURL
        $db->query($db->update('table.options')
            ->rows(['name' => 'commentsRequireUrl'])
            ->where('name = ?', 'commentsRequireURL'));

        // fix draft
        $db->query($db->update('table.contents')
            ->rows(['type' => 'revision'])
            ->where('parent <> 0 AND (type = ? OR type = ?)', 'post_draft', 'page_draft'));

        // fix attachment serialize
        $lastId = 0;
        do {
            $rows = $db->fetchAll(
                $db->select('cid', 'text')->from('table.contents')
                    ->where('cid > ?', $lastId)
                    ->where('type = ?', 'attachment')
                    ->order('cid', Db::SORT_ASC)
                    ->limit(100)
            );

            $rowCount = count($rows);
            if ($rowCount > 0) {
                $lastId = $rows[$rowCount - 1]['cid'];
            }

            foreach ($rows as $row) {
                if (strpos($row['text'], 'a:') !== 0) {
                    continue;
                }

                $value = @unserialize($row['text'], ['allowed_classes' => false]);
                if ($value !== false) {
                    $db->query($db->update('table.contents')
                        ->rows(['text' => json_encode($value)])
                        ->where('cid = ?', $row['cid']));
                }
            }
        } while ($rowCount === 100);

        $rows = $db->fetchAll($db->select()->from('table.options'));

        foreach ($rows as $row) {
            if (
                in_array($row['name'], ['plugins', 'actionTable', 'panelTable'])
                || strpos($row['name'], 'plugin:') === 0
                || strpos($row['name'], 'theme:') === 0
            ) {
                $value = @unserialize($row['value'], ['allowed_classes' => false]);
                if ($value !== false) {
                    $db->query($db->update('table.options')
                        ->rows(['value' => json_encode($value)])
                        ->where('name = ?', $row['name']));
                }
            }
        }
    }

    /**
     * 1.3.2: 创建 Passkey 数据表
     *
     * Passkey 功能首次合入时只改了安装 SQL, 已有站点升级后缺少 typecho_passkeys,
     * 注册/登录 Passkey 会直接报 SQL 错误。这里补建 (幂等)。
     *
     * @param Db $db
     * @param Options|null $options 未使用, 保持与其它升级脚本一致的签名
     * @return string
     * @throws \Typecho\Db\Exception
     */
    public static function v1_3_2(Db $db, ?Options $options = null): string
    {
        $table = $db->getPrefix() . 'passkeys';

        $db->query(
            "CREATE TABLE IF NOT EXISTS {$table} (
                \"id\" INTEGER PRIMARY KEY AUTOINCREMENT,
                \"uid\" INTEGER NOT NULL,
                \"credential_id\" varchar(512) NOT NULL UNIQUE,
                \"public_key\" text NOT NULL,
                \"sign_count\" int(10) NOT NULL DEFAULT 0,
                \"transports\" varchar(255) NOT NULL DEFAULT '',
                \"name\" varchar(100) NOT NULL,
                \"created\" int(10) NOT NULL,
                \"last_used\" int(10) NOT NULL DEFAULT 0
            )",
            Db::WRITE
        );
        $db->query("CREATE INDEX IF NOT EXISTS {$table}_uid ON {$table} (\"uid\")", Db::WRITE);

        return _t('已创建 Passkey 数据表');
    }

    /**
     * 1.3.3: 标签/分类归档缺少 relationships.mid 索引, 会扫描整张 contents 表 (幂等)
     *
     * @param Db $db
     * @param Options|null $options 未使用
     * @return string
     * @throws \Typecho\Db\Exception
     */
    public static function v1_3_3(Db $db, ?Options $options = null): string
    {
        $db->query(
            "CREATE INDEX IF NOT EXISTS {$db->getPrefix()}relationships_mid ON {$db->getPrefix()}relationships (\"mid\")",
            Db::WRITE
        );

        return _t('已为分类/标签索引添加索引');
    }

    /** Keep deleted authors' IDs from being assigned to unrelated new accounts. */
    public static function v1_3_4(Db $db, ?Options $options = null): string
    {
        $foreignKeys = (int) $db->fetchRow($db->query('PRAGMA foreign_keys', Db::WRITE))['foreign_keys'];
        $legacyAlter = (int) $db->fetchRow($db->query('PRAGMA legacy_alter_table', Db::WRITE))['legacy_alter_table'];
        try {
            // Table replacement must not cascade-delete plugin records or rewrite their references.
            $db->query('PRAGMA foreign_keys = OFF', Db::WRITE);
            $db->query('PRAGMA legacy_alter_table = ON', Db::WRITE);
            $db->transaction(function () use ($db): void {
                if ($db->fetchRow('PRAGMA foreign_keys')['foreign_keys']) {
                    throw new \Typecho\Db\Exception('User ID upgrade must run outside an existing transaction');
                }
                $table = $db->getPrefix() . 'users';
                $schema = $db->fetchRow($db->select('sql')->from('sqlite_master')
                    ->where('type = ? AND name = ?', 'table', $table))['sql'];
                if (!str_contains(strtoupper($schema), 'AUTOINCREMENT')) {
                    $objects = $db->fetchAll($db->select('sql')->from('sqlite_master')
                        ->where('tbl_name = ? AND sql IS NOT NULL', $table)
                        ->where('type = ? OR type = ?', 'index', 'trigger'));
                    $temporary = $table . '_uid_upgrade';
                    $columns = substr($schema, strpos($schema, '('));
                    $columns = preg_replace('/\bPRIMARY\s+KEY\b/i', 'PRIMARY KEY AUTOINCREMENT', $columns, 1);
                    $db->query('CREATE TABLE "' . $temporary . '" ' . $columns, Db::WRITE);
                    $db->query('INSERT INTO "' . $temporary . '" SELECT * FROM "' . $table . '"', Db::WRITE);
                    $db->query('DROP TABLE "' . $table . '"', Db::WRITE);
                    $db->query('ALTER TABLE "' . $temporary . '" RENAME TO "' . $table . '"', Db::WRITE);
                    foreach ($objects as $object) {
                        $db->query($object['sql'], Db::WRITE);
                    }
                }

                $maximum = (int) ($db->fetchRow($db->select('seq')->from('sqlite_sequence')
                    ->where('name = ?', $table))['seq'] ?? 0);
                foreach (['users' => ['uid'], 'contents' => ['authorId'],
                    'comments' => ['authorId', 'ownerId'], 'options' => ['user'], 'passkeys' => ['uid']] as $name => $fields) {
                    foreach ($fields as $field) {
                        $row = $db->fetchRow($db->select(['MAX(' . $field . ')' => 'uid'])->from('table.' . $name));
                        $maximum = max($maximum, (int) $row['uid']);
                    }
                }
                $db->query($db->delete('sqlite_sequence')->where('name = ?', $table));
                $db->query($db->insert('sqlite_sequence')->rows(['name' => $table, 'seq' => $maximum]));
                if ($db->fetchRow('PRAGMA foreign_key_check') !== null) {
                    throw new \Typecho\Db\Exception('Foreign key check failed during user ID upgrade');
                }
            });
        } finally {
            $db->query('PRAGMA legacy_alter_table = ' . $legacyAlter, Db::WRITE);
            $db->query('PRAGMA foreign_keys = ' . $foreignKeys, Db::WRITE);
        }
        return _t('用户编号不再重复使用');
    }
}
