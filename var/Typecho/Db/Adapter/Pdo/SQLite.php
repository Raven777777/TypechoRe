<?php

namespace Typecho\Db\Adapter\Pdo;

use Typecho\Config;
use Typecho\Db\Adapter\Pdo;
use Typecho\Db\Adapter\SQLiteTrait;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 数据库Pdo_SQLite适配器
 *
 * @package Db
 */
class SQLite extends Pdo
{
    use SQLiteTrait;

    /**
     * 判断适配器是否可用
     *
     * @access public
     * @return boolean
     */
    public static function isAvailable(): bool
    {
        return parent::isAvailable() && in_array('sqlite', \PDO::getAvailableDrivers());
    }

    /**
     * 初始化数据库
     *
     * @param Config $config 数据库配置
     * @access public
     * @return \PDO
     */
    public function init(Config $config): \PDO
    {
        $pdo = new \PDO("sqlite:{$config->file}");
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 5);
        $this->isSQLite2 = version_compare($pdo->getAttribute(\PDO::ATTR_SERVER_VERSION), '3.0.0', '<');

        /** 并发加固: 非零 busy_timeout + WAL 模式, 读写不再互斥, 消除 "database is locked" */
        if (!$this->isSQLite2) {
            $pdo->exec('PRAGMA journal_mode = WAL;');
            $pdo->exec('PRAGMA synchronous = NORMAL;');
        }
        $pdo->exec('PRAGMA busy_timeout = 5000;');

        return $pdo;
    }

    /**
     * 将数据查询的其中一行作为对象取出,其中字段名对应对象属性
     *
     * @param \PDOStatement $resource 查询的资源数据
     * @return \stdClass|null
     */
    public function fetchObject($resource): ?\stdClass
    {
        $result = $this->fetch($resource);
        return $result ? (object) $result : null;
    }

    /**
     * 将数据查询的其中一行作为数组取出,其中字段名对应数组键值
     *
     * @param \PDOStatement $resource 查询返回资源标识
     * @return array|null
     */
    public function fetch($resource): ?array
    {
        $result = parent::fetch($resource);
        return $result ? $this->filterColumnName($result) : null;
    }

    /**
     * 将数据查询的结果作为数组全部取出,其中字段名对应数组键值
     *
     * @param \PDOStatement $resource 查询的资源数据
     * @return array
     */
    public function fetchAll($resource): array
    {
        return array_map([$this, 'filterColumnName'], parent::fetchAll($resource));
    }
}
