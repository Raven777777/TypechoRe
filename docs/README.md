# 文档说明

> TypechoRe 与原版 Typecho 使用的数据库不再保证兼容。请在迁移前备份数据库，不要让两个程序同时连接同一个生产数据库。

代码质量与 PHP 8.5 检查结果见 [代码质量与 PHP 8.5 检查报告](guide/quality.md)。部署和数据恢复见 [部署与恢复](guide/deployment.md)，Passkey 使用见 [Passkey / WebAuthn](guide/passkey.md)。

## 目录结构

```text
docs/
├── guide/       安装、部署、Passkey、标签速选、质量检查
├── themes/      主题和模板扩展（README.md 为入口）
├── api/         API、模板函数和数据库参考
├── plugins/     插件开发与项目插件说明
├── index.md     文档首页
└── _sidebar.md  Docsify 导航
```

本目录文档来源于 https://github.com/benzBrake/typecho-docs ，在其基础上根据 `TypechoRe` 的代码现状进行了逐篇核验与更新。

原文档根据 typecho 官方文档，进行微调整，修正了一部分过时的文档，同时增加了一些其他的文档，部分原创，部分摘抄自网络。

原文档由 [Jrotty](http://blog.zezeshe.com) 提供，允许各种修改与传播。
