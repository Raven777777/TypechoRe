# TypePHP 原生二进制实验（Windows x64）

[TypePHP](https://github.com/swoole/typephp) 是一个把 PHP 编译成 C++、再编译成原生可执行文件的
AOT 编译器。本文记录在本仓库上做的一次可复现实验：**能构建，但产物不是「单文件二进制」，
而且 TypechoRe 自身的代码不能直接 AOT 编译**。

实验全部在仓库外进行（工作目录 `C:\tmp\typephp-build`），仓库里只保留可复现的最小输入：
[`tools/typephp/runner/`](../../tools/typephp/runner)（`project.yml` + `main.php`）。
本次没有把 TypePHP 接进 CI 或发布流程，原因见文末「结论」。

## 结论速览

- **构建成功**：`tpc.exe`（编译器自身，29.5 MiB）与原生可执行文件都产出了，并跑通了官方 Windows smoke 测试。
- **全套工具链 32 分钟**（其中 25 分钟是 vcpkg 从源码编译 gmp/mpfr），PHPX 编译 17 秒，
  `tpc.exe` 自身编译 3 分 47 秒。
- **AOT 编译的是入口文件，不是 TypechoRe 的代码**：`main.php` 被编译成机器码，框架代码仍然由
  链接进来的 Zend 运行时解释执行（`include` 引入）。真正的 `var/Typecho/*` 改写不在本次范围内。
- **产物不自包含**：一个 68 KiB 的 exe 依赖 `php8ts.dll`（14.1 MiB）、`phpx.dll`（1.0 MiB）、
  mpdecimal / gmp / mpfr 的 DLL 与 MSVC 2015–2022 x64 运行时；只有 `--nano` 模式才会把这些
  静态链接进去，而 `--nano` 明确拒绝 `include`/`require`/`eval`，TypechoRe 全站靠模板 `include` 引导，
  所以 **`--nano` 路线对本站不可用**。
- **跑通了真实站点**：把一次 `GET /` 交给原生进程处理，渲染出 37,493 字节的首页 HTML
  （`<title>Hello World</title>`），说明 `var/Typecho/*`、主题模板、SQLite 适配器在原生进程里工作正常。
- **许可**：TypePHP 与 PHPX 是 **GPL-3.0**，本仓库是 **GPL-2.0**。分发自行编译的二进制之前必须
  先确认两者许可兼容（GPLv2-only 与 GPLv3 不兼容），否则只能自己内部使用。

## 为什么不能把 TypechoRe 全站 AOT 编译

实测把带顶层 `require_once` 的文件交给 `tpc.exe` 会直接失败：

```text
Fatal error: All execution code must be within a function, found stray code in C:\tmp\typephp-build\spike\main.php:3
```

AOT 模式的硬约束与本仓库的现状冲突：

| 约束 | TypechoRe 的现状 |
| --- | --- |
| 全局作用域不允许可执行语句，二进制模式必须有 `main()` | `index.php`、`admin/*.php`、主题模板都是「顶层跑代码」 |
| `--nano` 拒绝 `include` / `require` / `eval` | 模板、插件、`var/Typecho/Common.php` 全靠 `require` 串起来 |
| 命名类与函数不能声明在函数体内、不支持 `$$var` | 插件与主题的 `functions.php` 大量动态定义 |
| 需要完整类型标注才能生成快速路径 | 现有代码是 `mixed`/数组为主的老式写法 |

所以现实的形态是「原生入口 + 完整 PHP 运行时」：exe 只负责引导，TypechoRe 继续跑在 Zend 上。
这能减少的只有入口那几十毫秒，**不会**让整站变成静态编译的程序。

## 构建 tpc.exe（Windows x64）

下面是与 CI 配方等价、在 Windows 11 + VS 18/2022 Community 上实测通过的步骤（git-bash）。

前置：VS 2022/2026 的 MSVC x64 工具链（`cl`/`link` + Windows SDK）、CMake、PHP **8.5.x ZTS x64**、
composer、Node 无关。

```bash
ROOT=/c/tmp/typephp-build; mkdir -p "$ROOT"/{logs,tools,dl}; cd "$ROOT"
PHPVER=8.5.10

# 1) 源码
git clone --depth 1 https://github.com/swoole/typephp typephp
git clone https://github.com/swoole/phpx typephp/third_party/phpx

# 2) PHP SDK: 头文件/导入库来自 devel pack (注意在 archives/ 下, releases/ 是 404)
curl -sSL -o dl/devel.zip "https://downloads.php.net/~windows/releases/archives/php-devel-pack-$PHPVER-Win32-vs17-x64.zip"
# php8embed.lib 不在 devel pack 里, 必须从同版本的 PHP 二进制包取
curl -sSL -o dl/php.zip   "https://windows.php.net/downloads/releases/archives/php-$PHPVER-Win32-vs17-x64.zip"
mkdir -p php/SDK/include php/SDK/lib
unzip -q dl/devel.zip -d dl/devel2
cp -r dl/devel2/php-$PHPVER-devel-vs17-x64/include/* php/SDK/include/
cp    dl/devel2/php-$PHPVER-devel-vs17-x64/lib/*     php/SDK/lib/
unzip -o -q dl/php.zip php8embed.lib -d php/ && cp php/php8embed.lib php/SDK/lib/

# 3) gmp/mpfr: VS 自带的 vcpkg 是 manifest-only 模式, 必须自己 clone 一份经典模式的
git clone --depth 1 https://github.com/microsoft/vcpkg vcpkg-src
(cd vcpkg-src && cmd //c bootstrap-vcpkg.bat -disableMetrics \
  && ./vcpkg.exe install gmp:x64-windows mpfr:x64-windows)
I=vcpkg-src/installed/x64-windows
cp -r "$I"/include/* php/SDK/include/; cp "$I"/lib/{gmp,gmpxx,mpfr}.lib php/SDK/lib/; cp "$I"/bin/*.dll php/

# 4) mpdecimal (PHP 的大数依赖, 用仓库自带的 Makefile.vc 构建)
(cd typephp/third_party/phpx/thirdparty/mpdecimal/vcbuild && cmd //c vcbuild64.bat)
D=typephp/third_party/phpx/thirdparty/mpdecimal/vcbuild/dist64
cp "$D"/*.lib php/SDK/lib/; cp "$D"/*.dll php/SDK/lib/ php/; cp "$D"/{mpdecimal.h,decimal.hh} php/SDK/include/

# 5) PHPX (需要 MSVC 环境: cmake 会调用 cl)
cat > logs/phpx.bat <<'BAT'
@echo off
call "C:\Program Files\Microsoft Visual Studio\18\Community\VC\Auxiliary\Build\vcvars64.bat" >nul
set PHP_HOME=C:\tmp\typephp-build\php
set PATH=C:\tmp\typephp-build\tools;%PATH%
cmake -S C:\tmp\typephp-build\typephp\third_party\phpx -B C:\tmp\typephp-build\typephp\third_party\phpx\build ^
  -G Ninja -D CMAKE_BUILD_TYPE=Release -D BUILD_TESTS=OFF -D BUILD_EXT=OFF -D GITHUB_ACTION=ON ^
  -D MPDECIMAL_LIBRARY=%PHP_HOME%\SDK\lib\libmpdec-4.0.1.dll.lib ^
  -D MPDECIMALXX_LIBRARY=%PHP_HOME%\SDK\lib\libmpdec++-4.0.1.dll.lib
cmake --build C:\tmp\typephp-build\typephp\third_party\phpx\build --target phpx --parallel 2
BAT
(cd logs && cmd //c phpx.bat)      # -> phpx\build\phpx.dll (17 秒)

# 6) composer 依赖 + 编译 tpc.exe 自身
curl -sSL -o tools/composer.phar https://getcomposer.org/download/latest-stable/composer.phar
(cd typephp && COMPOSER_PROCESS_TIMEOUT=0 COMPOSER_NO_INTERACTION=1 \
  ../php/php.exe ../tools/composer.phar install --prefer-dist --no-progress)
cat > logs/tpc.bat <<'BAT'
@echo off
call "C:\Program Files\Microsoft Visual Studio\18\Community\VC\Auxiliary\Build\vcvars64.bat" >nul
set PHP_HOME=C:\tmp\typephp-build\php
set PHPX_HOME=C:\tmp\typephp-build\typephp\third_party\phpx
set PHPRC=%PHP_HOME%\php.ini
set PATH=%PHPX_HOME%\build;C:\tmp\typephp-build\tools;%PHP_HOME%;%PATH%
cd /d C:\tmp\typephp-build\typephp
%PHP_HOME%\php.exe bin\tpc.php project.yml --job 1 --no-progress
BAT
(cd logs && cmd //c tpc.bat)       # -> typephp\tpc.exe (3 分 47 秒)
```

`PHP_HOME` 必须包含 `SDK/include/main/php.h`、`SDK/lib/{php8ts.lib,php8embed.lib,gmp.lib,gmpxx.lib,mpfr.lib,libmpdec*.lib}`
与运行期 DLL；`PHPX_HOME` 必须指向含 `lib\phpx.lib`、`build\phpx.dll` 的目录。

## 编译并运行本站的 runner

仓库里的入口是 [`tools/typephp/runner/main.php`](../../tools/typephp/runner/main.php)：它伪造一次
`GET` 请求（`$_SERVER` 全套字段）后 `include` 站点的 `index.php`，再打印状态行或整页 HTML。

```bat
:: 编译 (需要 MSVC 环境, tpc 会调用 cl/link)
cd /d C:\Users\21248\Documents\GitHub\TypechoRe
C:\tmp\typephp-build\typephp\tpc.exe tools\typephp\runner\project.yml --job 2 --no-progress

:: 运行: PATH 要有 phpx\build 与 PHP 目录, PHPRC 决定加载哪些 PHP 扩展
set PHPRC=C:\tmp\typephp-build\php\php.ini
set TYPECHORE_ROOT=C:\Users\21248\Documents\GitHub\TypechoRe
set PATH=C:\tmp\typephp-build\typephp\third_party\phpx\build;C:\tmp\typephp-build\php;C:\Windows\System32;C:\Windows
tools\typephp\runner\typechore-runner.exe
```

实测输出：

```text
uri=/ bytes=37493 title=Hello World
```

把整页 HTML 取出来（`TYPECHORE_DUMP=1` 时状态行走 stderr）：

```bat
set TYPECHORE_DUMP=1
set TYPECHORE_URI=/hello-world.html
tools\typephp\runner\typechore-runner.exe > page.html
```

`PHPRC` 指向的 php.ini 决定可用扩展。实测：**不设** `PHPRC` 时只有静态编译进去的
`json`/`session`/`zlib`/`dom` 可用（`pdo_sqlite=no`、`mb_strlen` 不存在，连 SQLite 都连不上）；
指向普通 php.ini 后 `pdo_sqlite`/`sqlite3`/`mbstring`/`openssl`/`curl`/`dom` 全部可用，
`PDO::getAvailableDrivers()` 返回 `mysql,pgsql,sqlite`，`sqlite::memory:` 建表读写正常。
也就是说：**二进制必须随包带 `php.ini` 与 `ext\`，否则站点起不来。**

## 运行时依赖

| 文件 | 大小 |
| --- | --- |
| `typephp\tpc.exe`（编译器，构建期工具） | 30,973,952 B |
| `phpx.dll` | 1,024,000 B |
| `php8ts.dll` | 14,133,248 B |
| `libmpdec-4.0.1.dll` / `libmpdec++-4.0.1.dll` | mpdecimal 构建产物 |
| `gmp-10.dll` / `gmpxx-4.dll` / `mpfr-6.dll` | vcpkg 产物 |
| `typechore-runner.exe` | 68–72 KiB |

`typechore-runner.exe` 的导入表：`php8ts.dll`、`phpx.dll`、`libmpdec++-4.0.1.dll`、
`MSVCP140.dll`、`VCRUNTIME140.dll`、`VCRUNTIME140_1.dll`、`api-ms-win-crt-*`、`KERNEL32.dll`。
只保留 `C:\Windows\System32` 在 `PATH` 上时进程直接 **exit 127**（加载器找不到 DLL，没有报错输出）。

## 踩过的坑（都已解决）

1. `downloads.php.net/~windows/releases/php-devel-pack-*.zip` 返回 **404**，只有 `releases/archives/` 下才有。
2. `php8embed.lib` **不在** devel pack 里（只有 `php8ts.lib` 与扩展导入库），必须从同版本
   `php-<ver>-Win32-vs17-x64.zip` 里取；PHP 与 devel pack 版本不一致会在链接期失败。
3. VS 自带的 `vcpkg.exe` 是 manifest-only 实例：`error: Could not locate a manifest (vcpkg.json) above the current working directory.`
   必须自己 clone `microsoft/vcpkg` 并 `bootstrap-vcpkg.bat`。
4. Ninja 不在 `PATH`，但 VS 里有一份：`...\Common7\IDE\CommonExtensions\Microsoft\CMake\Ninja\ninja.exe`。
5. 上游 CI 里给 `php_hash.h` 打的补丁在 8.5.10 上**已经是修好的**（`char *base = (char *) ecalloc(...)`），无需再打。
6. `vcpkg install gmp` 会静默编译约 25 分钟（debug+release 两套 triplet），期间没有任何输出，
   适合放后台跑并周期性打日志，否则容易被误判为卡死。
7. 从 git-bash 启动原生 exe 时，环境变量里单独的 `/` 会被 MSYS 路径转换改写成
   `C:\Program Files\Git\`（本站的 `getPathInfo()` 直接因此报 `Path '/C:/Program Files/Git/' not found`）。
   要么用 `cmd` 设置变量，要么写 `//`。
8. TypePHP/PHPX 在 Windows 上**用绝对路径写文件会失败**（`Failed to open stream: Permission denied`，
   相对路径正常），所以 runner 改成把正文写 stdout，由调用方重定向。

## 结论

- 值得保留的是**配方与结论**：TypePHP 能在 Windows 上完整构建、能引导真实站点、性能路径还没到
  值得改写的程度（站点耗时在 SQLite 查询与模板渲染，不在 PHP 入口）。
- 不建议现在就接进 CI：一次全新构建 30 分钟（无缓存）、产物依赖一整套 PHP DLL + php.ini、
  许可需要先确认，而收益只是「入口变成原生代码」。
- 如果将来要做，真正值得尝试的是**只把 CLI 工具**（例如批量导入、备份校验、静态化导出）编译成原生，
  那类脚本天然满足「无全局语句 + 显式 `main()` + 强类型」的约束，也才是 AOT 能出效果的地方。
