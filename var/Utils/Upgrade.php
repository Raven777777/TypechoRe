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
     * 注册/登录 Passkey 会直接报 SQL 错误。这里按当前适配器补建 (幂等)。
     *
     * @param Db $db
     * @param Options|null $options 未使用, 保持与其它升级脚本一致的签名
     * @return string
     * @throws \Typecho\Db\Exception
     */
    public static function v1_3_2(Db $db, ?Options $options = null): string
    {
        $table = $db->getPrefix() . 'passkeys';
        $driver = $db->getAdapter()->getDriver();

        switch ($driver) {
            case 'mysql':
                $db->query(
                    "CREATE TABLE IF NOT EXISTS `{$table}` (
                        `id` int(10) unsigned NOT NULL auto_increment,
                        `uid` int(10) unsigned NOT NULL,
                        `credential_id` varchar(512) NOT NULL,
                        `public_key` text NOT NULL,
                        `sign_count` int(10) unsigned NOT NULL default '0',
                        `transports` varchar(255) NOT NULL default '',
                        `name` varchar(100) NOT NULL,
                        `created` int(10) unsigned NOT NULL,
                        `last_used` int(10) unsigned NOT NULL default '0',
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `credential_id` (`credential_id`),
                        KEY `uid` (`uid`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                    Db::WRITE
                );
                break;

            case 'pgsql':
                $db->query("CREATE SEQUENCE IF NOT EXISTS \"{$table}_seq\"", Db::WRITE);
                $db->query(
                    "CREATE TABLE IF NOT EXISTS \"{$table}\" (
                        \"id\" INT NOT NULL DEFAULT nextval('{$table}_seq'),
                        \"uid\" INT NOT NULL,
                        \"credential_id\" VARCHAR(512) NOT NULL UNIQUE,
                        \"public_key\" TEXT NOT NULL,
                        \"sign_count\" INT NOT NULL DEFAULT 0,
                        \"transports\" VARCHAR(255) NOT NULL DEFAULT '',
                        \"name\" VARCHAR(100) NOT NULL,
                        \"created\" INT NOT NULL,
                        \"last_used\" INT NOT NULL DEFAULT 0,
                        PRIMARY KEY (\"id\")
                    )",
                    Db::WRITE
                );
                break;

            default:
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
                break;
        }

        return _t('已创建 Passkey 数据表');
    }
}
