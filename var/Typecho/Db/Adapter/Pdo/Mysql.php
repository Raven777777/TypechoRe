<?php

namespace Typecho\Db\Adapter\Pdo;

use Typecho\Config;
use Typecho\Db\Adapter\MysqlTrait;
use Typecho\Db\Adapter\Pdo;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 数据库Pdo_Mysql适配器
 *
 * @package Db
 */
class Mysql extends Pdo
{
    use MysqlTrait;

    /**
     * 判断适配器是否可用
     *
     * @access public
     * @return boolean
     */
    public static function isAvailable(): bool
    {
        return parent::isAvailable() && in_array('mysql', \PDO::getAvailableDrivers());
    }

    /**
     * 对象引号过滤
     *
     * @access public
     * @param string $string
     * @return string
     */
    public function quoteColumn(string $string): string
    {
        return '`' . $string . '`';
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
        $isModernPdoMysql = class_exists('\Pdo\Mysql');
        $sslCaAttr = $isModernPdoMysql ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA;
        $sslVerifyAttr = $isModernPdoMysql
            ? \Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT
            : \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT;
        $useBufferedQueryAttr = $isModernPdoMysql
            ? \Pdo\Mysql::ATTR_USE_BUFFERED_QUERY
            : \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY;

        $options = [];
        if (!empty($config->sslCa)) {
            $options[$sslCaAttr] = $config->sslCa;

            if (isset($config->sslVerify)) {
                // FIXME: https://github.com/php/php-src/issues/8577
                $options[$sslVerifyAttr] = $config->sslVerify;
            }
        }

        // 把 charset 写进 DSN 而不仅仅依赖 SET NAMES: 后者不会让客户端转义逻辑
        // (PDO::quote / mysqlnd) 感知字符集, 多字节编码下存在宽字节注入风险
        $charset = $config->charset ? ';charset=' . $config->charset : '';

        $dsn = !empty($config->dsn)
            ? $config->dsn
            : (strpos($config->host, '/') !== false
                ? "mysql:dbname={$config->database};unix_socket={$config->host}{$charset}"
                : "mysql:dbname={$config->database};host={$config->host};port={$config->port}{$charset}");

        $pdo = new \PDO(
            $dsn,
            $config->user,
            $config->password,
            $options
        );

        $pdo->setAttribute($useBufferedQueryAttr, true);

        if ($config->charset) {
            $pdo->exec("SET NAMES '{$config->charset}'");
        }

        return $pdo;
    }

    /**
     * 引号转义函数
     *
     * @param mixed $string 需要转义的字符串
     * @return string
     */
    public function quoteValue($string): string
    {
        return '\'' . str_replace(['\'', '\\'], ['\'\'', '\\\\'], $string) . '\'';
    }
}
