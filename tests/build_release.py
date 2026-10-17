#!/usr/bin/env python3
"""Regression test for the release package: exclusions and the SHA-256 manifest."""

from hashlib import sha256
from pathlib import Path, PurePosixPath
import re
import secrets
import stat
import subprocess
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]

if str(ROOT / "tools") not in sys.path:
    sys.path.insert(0, str(ROOT / "tools"))

from minify_assets import Parcel, minify_files, url_targets  # noqa: E402  (需要在 sys.path 调整之后导入)
SUFFIXES = (
    ".db",
    ".db-wal",
    ".db-shm",
    ".db-journal",
    ".sqlite",
    ".sqlite-wal",
    ".sqlite-shm",
    ".sqlite-journal",
    ".sqlite3",
    ".sqlite3-wal",
    ".sqlite3-shm",
    ".sqlite3-journal",
)
MANIFEST = "SHA256SUMS.txt"
# 主题里只服务于开发/构建的东西 (见 tools/build_release.py)
THEME_DEV_DIRS = {"tests", "src"}
THEME_DEV_FILES = {"package.json", "package-lock.json", "pnpm-lock.yaml", "yarn.lock",
                   "vite.config.js", "vite.config.mjs", "vite.config.ts"}
THEME_ENTRY = re.compile(r"^usr/themes/[^/]+/([^/]+)(?:/|$)")
# 后台只服务于开发/构建的目录 (见 tools/build_release.py)
ADMIN_DEV_PREFIXES = ("admin/src/",)
# 打包时会被 Parcel 压缩的样式表: 用来确认压缩确实生效
MINIFIED_CSS = "usr/themes/default/normalize.css"
PLACEHOLDER = "__typechore_asset_"


def theme_dev_entries(names: list[str]) -> list[str]:
    """发布包里不该出现的主题开发/构建文件"""
    offenders = []

    for name in names:
        match = THEME_ENTRY.match(name)

        if match is not None and (match.group(1) in THEME_DEV_DIRS or match.group(1) in THEME_DEV_FILES):
            offenders.append(name)

    return sorted(offenders)


def admin_dev_entries(names: list[str]) -> list[str]:
    """发布包里不该出现的后台开发文件 (admin/src/ 等)"""
    return sorted(name for name in names if name.startswith(ADMIN_DEV_PREFIXES))


def check_minified_css(package: zipfile.ZipFile, names: list[str]) -> tuple[int, int]:
    """检查压缩后的样式表: url() 引用必须与源文件完全一致, 且不能比原文件更大"""
    stylesheets = [name for name in names if name.endswith(".css")]
    shrunken = 0

    for name in stylesheets:
        packaged = package.read(name)
        source = (ROOT.joinpath(*PurePosixPath(name).parts)).read_bytes()

        if PLACEHOLDER in packaged.decode("utf-8", "replace"):
            raise RuntimeError(f"release ZIP keeps an unreplaced Parcel placeholder: {name}")
        if len(packaged) > len(source):
            raise RuntimeError(f"minified stylesheet grew: {name} ({len(source)} -> {len(packaged)} bytes)")

        # 压缩不能改写资源引用: url() 的目标集合必须与源文件一致 (重复项会被合法地去重)
        expected = set(url_targets(source.decode("utf-8", "replace")))
        actual = set(url_targets(packaged.decode("utf-8", "replace")))

        if expected != actual:
            raise RuntimeError(
                f"minification changed url() references in {name}: "
                f"missing {sorted(expected - actual)}, unexpected {sorted(actual - expected)}"
            )
        # Parcel 一旦把图片/字体当依赖抱进来, 输出里会多出 <名字>.<hash>.<扩展名> 的引用
        hashed = sorted(target for target in actual if re.search(r"\.[0-9a-f]{8,64}\.", target))

        if hashed:
            raise RuntimeError(f"minified stylesheet points at a content-hashed asset: {name}: {hashed}")
        if len(packaged) < len(source):
            shrunken += 1

    return len(stylesheets), shrunken


def check_entry_modes(package: zipfile.ZipFile) -> None:
    """每个条目都得对同组/其他用户可读: writestr() 默认的 0o600 会让发布包在 Linux 上不可服务"""
    worst = []

    for info in package.infolist():
        mode = stat.S_IMODE(info.external_attr >> 16)

        if mode and mode & 0o444 != 0o444:
            worst.append(f"{info.filename} ({oct(mode)})")

    if worst:
        raise RuntimeError(f"ZIP entries are not world-readable: {', '.join(worst[:5])}")


def check_manifest(package: zipfile.ZipFile, names: list[str]) -> int:
    """清单必须覆盖除它自己以外的每个文件, 且摘要与包内字节一致"""
    if MANIFEST not in names:
        raise RuntimeError(f"release ZIP is missing {MANIFEST}")

    entries: dict[str, str] = {}

    for line in package.read(MANIFEST).decode("utf-8").splitlines():
        if not line.strip():
            continue

        # sha256sum -c 的格式: 64 位十六进制 + 两个空格 + 路径
        match = re.fullmatch(r"([0-9a-f]{64})  (.+)", line)

        if match is None:
            raise RuntimeError(f"{MANIFEST} line is not sha256sum compatible: {line!r}")

        entries[match.group(2)] = match.group(1)

    packaged = set(names) - {MANIFEST}

    if set(entries) != packaged:
        unlisted = sorted(packaged - set(entries))
        unknown = sorted(set(entries) - packaged)
        raise RuntimeError(
            f"{MANIFEST} does not cover the package (unlisted: {unlisted or 'none'}; unknown: {unknown or 'none'})"
        )

    for name, digest in entries.items():
        if sha256(package.read(name)).hexdigest() != digest:
            raise RuntimeError(f"{MANIFEST} digest mismatch for {name}")

    return len(entries)


def check_minified_html(minifier: Parcel) -> tuple[str, str]:
    """检查 HTML 压缩: 自包含页面会变小, 引用同目录资源的页面一律不动

    返回 ``(压缩前, 压缩后)`` 的自包含样本内容, 供调用方打印。
    """
    work = ROOT / f".tmp-html-{secrets.token_hex(4)}"
    work.mkdir()
    standalone = work / "standalone.html"
    linked = work / "linked.html"
    standalone.write_text(
        "<!DOCTYPE html>\n<html lang=\"zh-CN\">\n  <head>\n    <title>release check</title>\n"
        "    <!-- stripped -->\n    <style>\n      .a { color : red ; }\n    </style>\n  </head>\n"
        "  <body>\n    <h1 class=\"a\">hello</h1>\n  </body>\n</html>\n",
        encoding="utf-8",
    )
    linked.write_text(
        '<!DOCTYPE html><html><head><link rel="stylesheet" href="theme.css"></head>'
        "<body><h1>hello</h1></body></html>\n",
        encoding="utf-8",
    )

    try:
        original = standalone.read_text(encoding="utf-8")
        overrides = minify_files([standalone, linked], "required")
    finally:
        for entry in (standalone, linked):
            entry.unlink(missing_ok=True)
        work.rmdir()

    key = standalone.resolve().relative_to(ROOT).as_posix()
    if key not in overrides:
        raise RuntimeError("self-contained HTML page was not minified")
    if linked.resolve().relative_to(ROOT).as_posix() in overrides:
        raise RuntimeError("HTML page referencing a relative stylesheet must be left untouched")

    resaved = overrides[key].decode("utf-8")
    for needle in ("hello", "<title", "color"):
        if needle not in resaved:
            raise RuntimeError(f"HTML minification dropped {needle!r}: {resaved!r}")
    if "stripped" in resaved:
        raise RuntimeError("HTML minification kept an HTML comment")

    return original, resaved


def main() -> None:
    prefix = f"typechore-release-filter-{secrets.token_hex(4)}"
    fixtures = [ROOT / "usr" / f"{prefix}{suffix}" for suffix in SUFFIXES]
    minifier = Parcel()

    try:
        for fixture in fixtures:
            fixture.write_bytes(b"test-only SQLite runtime artifact")

        command = [sys.executable, str(ROOT / "tools" / "build_release.py")]
        if minifier.available:
            # 装了 Parcel 就必须真的压缩, 而不是默默退化成原样打包
            command.append("--require-minify")

        result = subprocess.run(command, cwd=ROOT, capture_output=True, text=True)
        if result.returncode != 0:
            raise RuntimeError(
                f"release build failed (exit {result.returncode}):\n{result.stdout}\n{result.stderr}"
            )
        match = re.search(r"^Built\s+(.+?)\s+\(", result.stdout, re.MULTILINE)
        if match is None:
            raise RuntimeError(f"could not locate built ZIP in output:\n{result.stdout}")

        archive_name = match.group(1).replace("\\", "/")
        archive = ROOT.joinpath(*PurePosixPath(archive_name).parts)
        fixture_names = {fixture.relative_to(ROOT).as_posix() for fixture in fixtures}
        with zipfile.ZipFile(archive) as package:
            package_names = package.namelist()
            leaked = fixture_names.intersection(package_names)
            dev_files = theme_dev_entries(package_names)
            admin_dev = admin_dev_entries(package_names)
            admin_assets = [name for name in package_names if name.startswith(("admin/js/", "admin/css/"))]
            covered = check_manifest(package, package_names)
            check_entry_modes(package)
            css_total, css_shrunk = check_minified_css(package, package_names)
            theme_dist = sorted(name for name in package_names if re.match(r"^usr/themes/[^/]+/dist/", name))
            normalize = package.read(MINIFIED_CSS) if MINIFIED_CSS in package_names else b""

        if "LICENSE.txt" not in package_names:
            raise RuntimeError("GNU GPL license text is missing from the release ZIP")
        if leaked:
            raise RuntimeError(f"SQLite runtime files leaked into release ZIP: {', '.join(sorted(leaked))}")
        if dev_files:
            raise RuntimeError(f"development-only theme files leaked into release ZIP: {', '.join(dev_files)}")
        if admin_dev:
            raise RuntimeError(f"admin development sources leaked into release ZIP: {', '.join(admin_dev)}")
        if not admin_assets:
            raise RuntimeError("admin runtime assets (admin/js/, admin/css/) are missing from the release ZIP")
        if not theme_dist:
            raise RuntimeError("theme build artifacts (dist/) are missing from the release ZIP")
        if minifier.available:
            if css_shrunk == 0:
                raise RuntimeError("Parcel minification did not shrink a single stylesheet")
            source_size = (ROOT.joinpath(*PurePosixPath(MINIFIED_CSS).parts)).stat().st_size
            if len(normalize) * 2 > source_size:
                raise RuntimeError(
                    f"{MINIFIED_CSS} was not minified in the release ZIP "
                    f"({source_size} -> {len(normalize)} bytes)"
                )
            html_before, html_after = check_minified_html(minifier)

        print(f"PASS: release ZIP excludes {len(fixtures)} SQLite database/sidecar variants")
        print(
            f"PASS: release ZIP excludes theme development files "
            f"(tests/, src/, Node build configs) and keeps {len(theme_dist)} dist/ artifact(s)"
        )
        print(
            f"PASS: release ZIP excludes admin/src/ sources and keeps {len(admin_assets)} admin runtime asset(s)"
        )
        if minifier.available:
            print(
                f"PASS: Parcel minified {css_shrunk}/{css_total} stylesheets "
                f"({MINIFIED_CSS}: {source_size} -> {len(normalize)} bytes) without touching url() references"
            )
            print(
                "PASS: Parcel minifies a self-contained HTML page "
                f"({len(html_before)} -> {len(html_after)} bytes) and leaves "
                "pages that reference relative assets untouched"
            )
        else:
            print(f"SKIP: stylesheet minification not checked ({minifier.problem})")
        print(
            f"PASS: {MANIFEST} covers all {covered} packaged files with matching SHA-256 digests, "
            "and every ZIP entry is world-readable (no 0o600 writestr() entries)"
        )
    finally:
        for fixture in fixtures:
            fixture.unlink(missing_ok=True)


if __name__ == "__main__":
    main()
