TypechoRe Blogging Platform
===========================

**TypechoRe** is an actively maintained Fork of [Typecho](https://github.com/typecho/typecho) — a PHP-based blogging software.

> 这是 [Typecho](https://github.com/typecho/typecho) 的 Fork 版本。所有底层机制与数据结构保持与上游兼容（ GNU General Public License 2.0 ）。

## Fork 定位

* 安全加固：路径穿越 / SSRF / 反序列化 / CSRF 增强、Cookie 安全属性、SQLite 并发优化等
* 移除对官方服务器的外网请求（如后台 `do=feed`、`do=checkVersion`）
* 独立命名 TypechoRe，保持与原版功能兼容

## Main Features

* Multiple databases support (MariaDB, MySQL, SQLite, PostgreSQL)
* Markdown Support
* Plugin Support
* Theme Support
* Custom Fields
* Custom Pages

## Requirements

* PHP 7.4.0 or higher (PHP 8.5 supported)
* Database (MariaDB, MySQL, SQLite, PostgreSQL)
  * MariaDB or MySQL 5.5.3 or higher
  * SQLite 3.7.11 or higher
  * PostgreSQL 9.1 or higher

## Contributing

Report issues at https://github.com/Raven777777/MoeCounterRe/issues
