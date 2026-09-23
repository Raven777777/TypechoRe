**TypechoRe**

**TypechoRe** 是一个简单轻巧的博客程序，是 [Typecho](https://github.com/typecho/typecho) 的积极维护 Fork 版本。基于 `PHP`，使用多种数据库（`MariaDB`、`MySQL`、`PostgreSQL`、`SQLite`）储存数据，在 `GPL Version 2` 许可证下发行的开源程序，使用 `Git` 做版本管理。

**Typecho** 是由两个单词 `type` 和 `echo` 组成的，在发音的时候也发这两个音 `/taɪpˌ'ekoʊ/`

**Fork 定位**

* 安全加固：路径穿越 / SSRF / 反序列化 / CSRF 增强、Cookie 安全属性、SQLite 并发优化等
* 移除对官方服务器的外网请求（如后台 `do=feed`、`do=checkVersion`）
* 独立命名 `TypechoRe`，在安全、Passkey、数据库和运行时行为上独立于上游

> **数据库兼容性警告**：TypechoRe 已修改密码/authCode 存储方式并增加 Passkey 表，不再保证与原版 Typecho 数据库完全相通。请不要让两个程序连接同一个生产数据库。

**文档说明**

此文档根据 typecho 官方文档，进行微调整，修正了一部分过时的文档，同时增加了一些其他的文档，部分原创，部分摘抄自网络。在此基础上，本仓库针对 `TypechoRe` 的代码现状进行了逐篇核验与更新。

**提供**

原文档由 [Jrotty](http://blog.zezeshe.com) 提供，允许各种修改与传播。

**纠错/修正文档**

文档部分存在问题可提供修改意见或直接在仓库提交修改：https://github.com/Raven777777/TypechoRe/issues

**最近维护时间**

2026年9月23日
