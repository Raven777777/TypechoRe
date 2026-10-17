#!/usr/bin/env python3
"""用 Parcel 压缩发布包里的前端资源 (CSS / HTML)。

Parcel 是打包器而不是单纯的压缩器, 把仓库里的文件直接交给它会有两个副作用:

* CSS 里相对的 ``url(...)`` 会被当成资源依赖解析: 引用的图片/字体被改名 (加 content hash)
  并扁平化到输出目录根部, 破坏发布包与仓库同构的目录布局; 引用目标不存在时构建直接失败;
* 没有顶层 ``import``/``export`` 的脚本 (UMD、vendor 包) 会被包进 IIFE, 顶层变量变成
  局部变量, ``window.jQuery`` 之类的全局入口随之消失。

因此这里只把「自包含」的资源交给 Parcel:

* CSS: 压缩前把每个 ``url(...)`` 换成 ``url(#__typechore_asset_N__)`` 片段占位, 压缩后换回
  原始文本, 引用字节保持完全一致; 含相对 ``@import`` 的样式表跳过 (那会被 Parcel 内联);
* HTML: 仅在没有任何外部引用时压缩。

JS 不在压缩范围内: 发布集里的 JS 要么是 Vite 的构建产物, 要么是 jQuery 这类 UMD/vendor 包,
全部已经压缩过; 剩下 1%~2% 的收益换不来风险 —— 实测把 ``admin/js/jquery.js`` 或
``admin/src/js/pagedown.js`` 交给 Parcel 重新打包后, ``window.jQuery`` / ``window.Markdown``
这些全局入口会消失。要真正减小 JS, 只能在源头 (Vite 构建) 做。

压缩结果只用于发布包, 仓库里的文件不会被改写: 调用方拿到 ``{相对路径: 字节}`` 后自己写 ZIP。
"""

from __future__ import annotations

import os
import re
import shutil
import subprocess
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PARCEL_DIR = ROOT / "tools" / "assets"
PARCEL_BIN = PARCEL_DIR / "node_modules" / "parcel" / "lib" / "bin.js"
PARCEL_TIMEOUT = 180

CSS_SUFFIXES = {".css"}
HTML_SUFFIXES = {".html"}
MINIFY_SUFFIXES = CSS_SUFFIXES | HTML_SUFFIXES

# url( ... ) 里的目标: 引号内的内容原样吃下 (允许括号/空格), 无引号时到右括号前为止
CSS_URL = re.compile(
    r"""url\(\s*(?:"(?P<double>[^"]*)"|'(?P<single>[^']*)'|(?P<bare>(?:\\[\s\S]|[^\s)\\])+))\s*\)""",
    re.IGNORECASE,
)
# Parcel (lightningcss) 会去掉占位 url 的引号, 甚至把占位当成普通字符串输出 (连 # 一起),
# 所以还原要认 url(#token)、url('#token') 与 '#token' 三种写法
CSS_PLACEHOLDER = re.compile(r"url\(\s*['\"]?#(?P<token>__typechore_asset_\d+__)['\"]?\s*\)", re.IGNORECASE)
CSS_BARE_PLACEHOLDER = re.compile(r"#?(?P<token>__typechore_asset_\d+__)")
CSS_COMMENT = re.compile(r"/\*.*?\*/", re.DOTALL)
HTML_REFERENCE = re.compile(
    r"""\b(?:src|href|srcset|poster|data|action|formaction)\s*=\s*(?P<quote>['"]?)(?P<target>[^'"\s>]+)""",
    re.IGNORECASE,
)

PLACEHOLDER_PREFIX = "__typechore_asset_"
CSS_IMPORT = re.compile(
    r"""@import\s+(?:url\(\s*(?:"(?P<double>[^"]*)"|'(?P<single>[^']*)'|(?P<bare>[^\s)"']+))\s*\)|"(?P<plain_double>[^"]*)"|'(?P<plain_single>[^']*)')""",
    re.IGNORECASE,
)
ABSOLUTE_TARGET = re.compile(r"^(?:[a-zA-Z][a-zA-Z0-9+.-]*:|//|/|#)")


class MinifyError(RuntimeError):
    """Parcel 压缩失败"""


class Skip(Exception):
    """这个文件不适合交给 Parcel, 保持原样"""


def _is_relative_target(target: str) -> bool:
    """是否是 Parcel 会接管并改写的相对引用 (data:、http(s):、/ 开头、# 片段都不算)"""
    return ABSOLUTE_TARGET.match(target) is None


def _url_target(match: re.Match[str]) -> str:
    """url(...) / @import 里的目标, 去掉可选引号"""
    for group in ("double", "single", "bare", "plain_double", "plain_single"):
        try:
            value = match.group(group)
        except IndexError:
            continue
        if value is not None:
            return value
    return ""


def url_targets(text: str) -> list[str]:
    """样式表里所有外部引用的目标 (url(...) 与 @import), 去引号, 供压缩前后比对

    lightningcss 会把 ``@import url(x)`` 改写成 ``@import "x"``, 只比 url() 会误报。
    """
    targets = [_url_target(match) for match in CSS_URL.finditer(text)]
    targets += [_url_target(match) for match in CSS_IMPORT.finditer(text)]
    return [target for target in targets if target]


class Parcel:
    """懒加载的 Parcel CLI 包装: 找不到 node 或没装依赖时说明原因, 由调用方决定是否致命"""

    def __init__(self) -> None:
        node = shutil.which("node.exe") or shutil.which("node")
        if node is None:
            self.problem = "找不到 node 可执行文件"
        elif not PARCEL_BIN.is_file():
            self.problem = f"缺少 {PARCEL_BIN.relative_to(ROOT).as_posix()} (先执行: npm ci --prefix tools/assets)"
        else:
            self.problem = ""
        self.node = node

    @property
    def available(self) -> bool:
        return not self.problem

    def run(self, name: str, data: bytes) -> bytes:
        """把一个自包含文件编译到临时目录, 返回压缩后的字节

        入口文件放在干净的临时工作目录里, Parcel 因此解析不到任何外部依赖:
        能被压缩的必然是自包含资源, 不会意外把仓库里的其它文件卷进发布包。
        """
        workdir = Path(tempfile.mkdtemp(prefix="typechore-parcel-"))

        try:
            entry = workdir / "in" / name
            entry.parent.mkdir(parents=True)
            entry.write_bytes(data)
            output_dir = workdir / "out"
            command = [
                str(self.node),
                str(PARCEL_BIN),
                "build",
                f"in/{name}",
                "--dist-dir",
                output_dir.as_posix(),
                # 文件名保持原样, 不加 content hash; 也不要 source map
                "--no-content-hash",
                "--no-source-maps",
                # 不做自动安装、不复用缓存: 构建必须可重复
                "--no-autoinstall",
                "--no-cache",
                "--log-level=warn",
            ]
            try:
                result = subprocess.run(
                    command,
                    cwd=workdir,
                    capture_output=True,
                    # Parcel 的输出带制表符和框线; 按 UTF-8 解、坏字节替换, 别让 locale 编码炸出来
                    encoding="utf-8",
                    errors="replace",
                    env={**os.environ, "NODE_ENV": "production"},
                    timeout=PARCEL_TIMEOUT,
                )
            except (OSError, subprocess.TimeoutExpired) as error:
                raise MinifyError(f"无法运行 Parcel: {error}") from error

            output = output_dir / name
            if result.returncode != 0 or not output.is_file():
                detail = (result.stderr or result.stdout or "(没有输出)").strip().splitlines()
                tail = " / ".join(line.strip() for line in detail[-4:] if line.strip())
                raise MinifyError(f"Parcel 构建失败 (exit {result.returncode}): {tail}")

            packed = output.read_bytes()
            if not packed.strip():
                raise MinifyError("Parcel 输出为空")
            return packed
        finally:
            # Parcel 的 worker 进程可能还握着文件句柄 (Windows 上是 WinError 32),
            # 清理失败不能变成构建失败: 临时目录留着比丢掉压缩结果强
            shutil.rmtree(workdir, ignore_errors=True)


def _protect_urls(css: str) -> tuple[str, dict[str, tuple[str, str]]]:
    """把 url(...) 换成片段占位, 返回占位到「原始 url(...) 文本, 目标」的映射

    data: URL 体积大且与路径无关, ``#fragment`` 指向文档内部 (SVG filter 之类),
    两者都原样保留 —— 尤其是片段: 占位也写成 ``url(#...)``, 混在一起就分不清了。
    """
    originals: dict[str, tuple[str, str]] = {}

    def replace(match: re.Match[str]) -> str:
        target = _url_target(match)
        if target.lower().startswith("data:") or target.startswith("#"):
            return match.group(0)
        token = f"{PLACEHOLDER_PREFIX}{len(originals)}__"
        originals[token] = (match.group(0), target)
        return f"url(#{token})"

    return CSS_URL.sub(replace, css), originals


def _restore_urls(css: str, originals: dict[str, tuple[str, str]], source: str) -> str:
    """还原占位; lightningcss 可能把占位从 ``url(#x)`` 改写成 ``"#x"`` 字符串"""

    def restore_url(match: re.Match[str]) -> str:
        return originals[match.group("token")][0]

    def restore_bare(match: re.Match[str]) -> str:
        # 只剩裸占位时它一定是 @import 之类被换成字符串的目标, 只还原目标本身
        return originals[match.group("token")][1]

    restored = CSS_PLACEHOLDER.sub(restore_url, css)
    restored = CSS_BARE_PLACEHOLDER.sub(restore_bare, restored)
    leftover = restored.find(PLACEHOLDER_PREFIX)

    # 源文件自己就带有同名字面量时 (注释、SVG id) 不把它当成还原失败
    if leftover != -1 and PLACEHOLDER_PREFIX not in source:
        raise MinifyError(f"压缩后出现无法还原的占位符: {restored[leftover : leftover + 48]!r}")
    return restored


def _minify_css(parcel: Parcel, name: str, css: str) -> str:
    # 注释里的 @import 不算依赖: 先去掉注释再扫描
    imports = [_url_target(match) for match in CSS_IMPORT.finditer(CSS_COMMENT.sub("", css))]

    if any(_is_relative_target(target) for target in imports):
        raise Skip("含相对 @import, Parcel 会把被导入的样式内联进输出")

    protected, originals = _protect_urls(css)
    output = parcel.run(name, protected.encode("utf-8", "surrogateescape")).decode("utf-8", "surrogateescape")
    return _restore_urls(output, originals, css)


def _minify_html(parcel: Parcel, name: str, html: str) -> str:
    references = [match.group("target") for match in HTML_REFERENCE.finditer(html)]
    relative = [target for target in references if _is_relative_target(target)]

    if relative:
        raise Skip(f"引用了相对路径 {relative[0]}, Parcel 会改写或打包该资源")
    # 内联样式里也可能有 url(...) (<style> 块、style="..." 属性), 它同样会被当成依赖去解析
    if re.search(r"url\(", html, re.IGNORECASE):
        raise Skip("内联样式里有 url(...), Parcel 会把它当资源依赖解析")
    return parcel.run(name, html.encode("utf-8", "surrogateescape")).decode("utf-8", "surrogateescape")


def _minify_text(parcel: Parcel, name: str, path: Path, text: str) -> str:
    if path.suffix.lower() in CSS_SUFFIXES:
        return _minify_css(parcel, name, text)
    return _minify_html(parcel, name, text)


def _kib(size: int) -> str:
    return f"{size / 1024:.1f} KiB"


def _display_name(path: Path) -> str:
    """仓库内的路径显示成相对路径, 仓库外的 (测试临时文件) 原样显示"""
    try:
        return path.resolve().relative_to(ROOT).as_posix()
    except ValueError:
        return path.as_posix()


def minify_files(paths: list[Path], mode: str = "auto") -> dict[str, bytes]:
    """压缩发布集里的自包含前端资源, 返回 ``{相对路径: 压缩后的字节}``

    ``mode``: ``off`` 不压缩; ``auto`` 单个文件失败时保留原文; ``required`` 缺 Parcel 或一个文件
    都没压下来时抛错 (CI 用)。
    """
    if mode not in {"off", "auto", "required"}:
        raise ValueError(f"Unknown minify mode: {mode}")
    if mode == "off":
        return {}

    candidates = [path for path in paths if path.suffix.lower() in MINIFY_SUFFIXES]

    if not candidates:
        return {}

    parcel = Parcel()

    if not parcel.available:
        message = f"跳过前端资源压缩: {parcel.problem}"
        if mode == "required":
            raise MinifyError(message)
        print(f"WARNING: {message}")
        return {}

    overrides: dict[str, bytes] = {}
    rows: list[tuple[str, int, int]] = []
    skipped: list[tuple[str, str]] = []

    for path in candidates:
        name = _display_name(path)
        original = path.read_bytes()

        try:
            text = original.decode("utf-8", "surrogateescape")
            minified = _minify_text(parcel, path.name, path, text).encode("utf-8", "surrogateescape")
        except Skip as reason:
            skipped.append((name, str(reason)))
            continue
        except MinifyError as error:
            if mode == "required":
                raise
            print(f"WARNING: {name} 压缩失败, 保留原文: {error}")
            continue

        if len(minified) >= len(original):
            skipped.append((name, f"压缩后没有变小 ({_kib(len(original))} → {_kib(len(minified))})"))
            continue

        overrides[name] = minified
        rows.append((name, len(original), len(minified)))

    before = sum(original_size for _, original_size, _ in rows)
    after = sum(len(data) for data in overrides.values())

    if rows:
        saved = before - after
        print(
            f"Minified {len(rows)} of {len(candidates)} front-end assets with Parcel: "
            f"{_kib(before)} -> {_kib(after)} (-{_kib(saved)}, -{saved / before:.1%})"
        )
        for name, original_size, minified_size in rows:
            print(f"  {name:<52} {_kib(original_size):>10} -> {_kib(minified_size):>10}")
    else:
        print(f"No front-end assets needed minification ({len(candidates)} candidate(s) already minimal)")

    for name, reason in skipped:
        print(f"  kept as-is: {name} ({reason})")

    # --require-minify 的语义是「必须真的压下来」, 而不是「Parcel 装了」:
    # 全部文件都被跳过 (例如每个样式表都加了相对 @import) 时不能安静地绿
    if not rows and mode == "required":
        reasons = "; ".join(f"{name}: {reason}" for name, reason in skipped) or "无可压缩文件"
        raise MinifyError(f"前端资源压缩没有生效, 发布包里全是原始字节 ({reasons})")

    return overrides
