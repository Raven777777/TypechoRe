#!/usr/bin/env python3
"""Build a code-only ZIP for deployment to the website document root.

发布包只带运行时需要的东西:

* 站点数据不进包: ``config.inc.php``、SQLite 主库与 WAL/SHM/journal、日志、备份、
  缓存、用户上传文件;
* 开发/构建产物不进包: 主题的 ``tests/`` 与 ``src/``、Node 构建配置与锁文件、
  ``node_modules/``、VCS 与编辑器杂项;
* 额外打包一份 ``SHA256SUMS.txt``: 逐个文件 (清单自己除外) 的 SHA-256,
  格式与 ``sha256sum`` 一致, 解压后在根目录执行 ``sha256sum -c SHA256SUMS.txt``
  即可校验整包。
"""

from hashlib import sha256
from pathlib import Path, PurePosixPath
import re
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SOURCE_DIRS = ("admin", "install", "var", "usr")
ROOT_FILES = (
    ".htaccess",
    "web.config",
    "robots.txt",
    "favicon.ico",
    "LICENSE.txt",
    "index.php",
    "install.php",
)
REQUIRED = {
    "admin/index.php",
    "install/SQLite.php",
    "usr/uploads/.htaccess",
    "var/Typecho/Common.php",
    "index.php",
    "install.php",
    "LICENSE.txt",
}

# 校验清单的文件名: 由本脚本生成并打进 ZIP, 覆盖除它自己以外的每一个文件
MANIFEST_NAME = "SHA256SUMS.txt"

SKIP_DIRS = {"backups", "cache", "tmp", "node_modules"}
SKIP_SUFFIXES = {
    ".db",
    ".sqlite",
    ".sqlite3",
    ".db-wal",
    ".db-shm",
    ".db-journal",
    ".sqlite-wal",
    ".sqlite-shm",
    ".sqlite-journal",
    ".sqlite3-wal",
    ".sqlite3-shm",
    ".sqlite3-journal",
    ".log",
}

# 主题里只服务于开发/构建的东西: dist/ 才是随包分发的构建产物,
# tests/ 是纯 Node 的测试套件, 服务器上既跑不了也用不到。
THEME_DEV_DIRS = {"tests", "src"}
THEME_DEV_FILES = {
    "package.json",
    "package-lock.json",
    "pnpm-lock.yaml",
    "yarn.lock",
    "vite.config.js",
    "vite.config.mjs",
    "vite.config.ts",
}
# 匹配 usr/themes/<主题>/<第一层名字>[/...]
THEME_ENTRY_PATTERN = re.compile(r"^usr/themes/[^/]+/([^/]+)(?:/|$)")

# VCS / 编辑器杂项: 任何位置都不进发布包
SKIP_FILE_NAMES = {".gitignore", ".gitattributes", ".editorconfig"}


def is_theme_dev_only(name: str) -> bool:
    """主题内的开发/构建产物 (tests/ 、src/ 、package.json 、vite.config.js 等)"""
    match = THEME_ENTRY_PATTERN.match(name)

    if match is None:
        return False

    head = match.group(1)

    return head in THEME_DEV_DIRS or head in THEME_DEV_FILES


def included(path: Path) -> bool:
    relative = path.relative_to(ROOT)
    parts = relative.parts
    name = relative.as_posix()

    if path.is_symlink() or not path.is_file():
        return False
    if any(part in SKIP_DIRS for part in parts):
        return False
    if relative.name in SKIP_FILE_NAMES:
        return False
    if is_theme_dev_only(name):
        return False
    # 清单由脚本生成: 磁盘上就算存在同名文件也不打进包, 免得覆盖了真正的清单
    if name == MANIFEST_NAME:
        return False
    if name.startswith("usr/uploads/") and name != "usr/uploads/.htaccess":
        return False
    if path.name.lower() == "config.inc.php" or path.name.lower().startswith(".env"):
        return False
    return path.suffix.lower() not in SKIP_SUFFIXES


def collect_files() -> list[Path]:
    files = [ROOT / name for name in ROOT_FILES if (ROOT / name).is_file()]
    for directory in SOURCE_DIRS:
        files.extend((ROOT / directory).rglob("*"))

    result = sorted((path for path in files if included(path)), key=lambda p: p.relative_to(ROOT).as_posix())
    names = {path.relative_to(ROOT).as_posix() for path in result}
    missing = REQUIRED - names
    if missing:
        raise RuntimeError(f"Required release files are missing: {', '.join(sorted(missing))}")
    return result


def file_digest(path: Path) -> str:
    """流式计算文件摘要, 大文件也不会整块读进内存"""
    digest = sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def build_manifest(files: list[Path]) -> str:
    """逐文件 SHA-256 清单 (每行 `<摘要>  <相对路径>`, 与 sha256sum -c 兼容)"""
    lines = []

    for path in files:
        name = path.relative_to(ROOT).as_posix()
        if "\n" in name:
            raise RuntimeError(f"Path cannot be listed in {MANIFEST_NAME}: {name!r}")
        lines.append(f"{file_digest(path)}  {name}")

    return "\n".join(lines) + "\n"


def parse_manifest(text: str) -> dict[str, str]:
    entries: dict[str, str] = {}

    for line in text.splitlines():
        if not line.strip():
            continue

        digest, separator, name = line.partition("  ")

        if separator == "" or len(digest) != 64 or not name:
            raise RuntimeError(f"Malformed line in {MANIFEST_NAME}: {line!r}")

        entries[name] = digest

    return entries


def version() -> str:
    try:
        value = subprocess.check_output(
            ["git", "describe", "--tags", "--always", "--dirty"],
            cwd=ROOT,
            stderr=subprocess.DEVNULL,
            text=True,
        ).strip()
    except (OSError, subprocess.CalledProcessError):
        value = "local"
    return re.sub(r"[^A-Za-z0-9._-]+", "-", value).strip(".-") or "local"


def verify(archive: Path) -> None:
    with zipfile.ZipFile(archive) as package:
        if package.testzip() is not None:
            raise RuntimeError("The generated ZIP failed its CRC check")
        names = package.namelist()
        if len(names) != len(set(names)):
            raise RuntimeError("The generated ZIP contains duplicate paths")
        missing = REQUIRED - set(names)
        if missing:
            raise RuntimeError(f"The generated ZIP is missing: {', '.join(sorted(missing))}")
        for name in names:
            path = PurePosixPath(name)
            if (
                path.name.lower() == "config.inc.php"
                or path.name.lower().startswith(".env")
                or path.suffix.lower() in SKIP_SUFFIXES
                or any(part in SKIP_DIRS for part in path.parts)
                or is_theme_dev_only(name)
                or path.name in SKIP_FILE_NAMES
                or (name.startswith("usr/uploads/") and name != "usr/uploads/.htaccess")
            ):
                raise RuntimeError(f"Unsafe or runtime file included in release: {name}")

        if MANIFEST_NAME not in names:
            raise RuntimeError(f"The generated ZIP is missing {MANIFEST_NAME}")

        entries = parse_manifest(package.read(MANIFEST_NAME).decode("utf-8"))
        packaged = set(names) - {MANIFEST_NAME}

        if set(entries) != packaged:
            uncovered = sorted(packaged - set(entries))
            unknown = sorted(set(entries) - packaged)
            raise RuntimeError(
                f"{MANIFEST_NAME} does not cover the package "
                f"(unlisted: {', '.join(uncovered) or 'none'}; unknown: {', '.join(unknown) or 'none'})"
            )

        for name, digest in entries.items():
            if sha256(package.read(name)).hexdigest() != digest:
                raise RuntimeError(f"{MANIFEST_NAME} digest mismatch for {name}")


def build() -> Path:
    files = collect_files()
    manifest = build_manifest(files)
    output_dir = ROOT / "dist"
    output_dir.mkdir(exist_ok=True)
    output = output_dir / f"TypechoRe-{version()}.zip"
    temporary = output.with_suffix(".zip.tmp")

    try:
        with zipfile.ZipFile(temporary, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as package:
            for path in files:
                package.write(path, path.relative_to(ROOT).as_posix())
            package.writestr(MANIFEST_NAME, manifest)
        verify(temporary)
        temporary.replace(output)
    finally:
        temporary.unlink(missing_ok=True)

    size_mib = output.stat().st_size / (1024 * 1024)
    print(f"Built {output.relative_to(ROOT)} ({len(files)} files + {MANIFEST_NAME}, {size_mib:.1f} MiB)")
    print(f"Checksums: {MANIFEST_NAME} lists every packaged file (verify with: sha256sum -c {MANIFEST_NAME})")
    print(
        "Excluded config, databases, logs, uploads, backups, caches, node_modules, "
        "and other development-only files (theme tests/, src/, build configs)."
    )
    return output


if __name__ == "__main__":
    build()
