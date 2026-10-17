# tools/

构建、打包与质量检查用的脚本。**这些脚本不参与站点运行**：发布包只收集 `admin/`、`install/`、
`var/`、`usr/` 与根目录文件，`tools/` 与 `tests/` 都不会进包（见 `build_release.py` 的 `SOURCE_DIRS`）。

| 路径 | 用途 |
| --- | --- |
| [`build_release.py`](build_release.py) | 打包发布 ZIP：收集文件 → 压缩前端资源 → 生成 `SHA256SUMS.txt` → 自检 → 写 `dist/TypechoRe-<版本>.zip` |
| [`minify_assets.py`](minify_assets.py) | 用 Parcel 压缩发布集里的 CSS 与自包含 HTML（由 `build_release.py` 调用，不单独使用） |
| [`assets/`](assets/) | Parcel 工具链的 npm 工程（`npm ci --prefix tools/assets`），只有构建期需要 |
| [`quality.ps1`](quality.ps1) | 完整质量门禁：PHP lint、冒烟测试、集成测试、发布包测试、PHPStan、E2E、Semgrep |
| [`e2e.php`](e2e.php) | 起一个临时站点并用真实 HTTP 请求跑安装向导、前台、后台、评论、上传 |
| [`audit-override.php`](audit-override.php) | `#[\Override]` 属性覆盖审计（哪些方法该标、哪些标错了） |
| [`phpstan-bootstrap.php`](phpstan-bootstrap.php) | PHPStan 的引导文件：补声明只有运行时才定义的常量 |
| [`typephp/`](typephp/) | TypePHP 原生二进制实验的入口（`project.yml` + `main.php`），配方见 [docs/guide/typephp.md](../docs/guide/typephp.md) |

## 常用命令

```bash
# 打包（没有 Node 或没装 tools/assets/ 依赖时只警告并跳过压缩）
python tools/build_release.py

# 打包并强制压缩前端资源（缺依赖直接报错，CI 用这个）
python tools/build_release.py --require-minify

# 只验证打包逻辑（含对压缩结果的断言）
python tests/build_release.py

# 一次性质量门禁
pwsh tools/quality.ps1
```

## 谁调用谁

```text
tools/quality.ps1
├── tests/smoke.php, tests/webauthn.php, tests/integration.php
├── tools/audit-override.php
├── tests/build_release.py
│   └── tools/build_release.py
│       └── tools/minify_assets.py
│           └── tools/assets/node_modules/parcel
├── tools/e2e.php
└── phpstan（.tools/phpstan/phpstan.phar）、Semgrep（.tools/semgrep-venv）
```

`.tools/` 是 `quality.ps1` 在本地下载的第三方工具（PHPStan、Semgrep 虚拟环境），已被 `.gitignore` 忽略，
删掉后下一次运行会自动重新准备。
