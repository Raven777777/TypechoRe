#!/usr/bin/env python3
"""Build a code-only ZIP for deployment to the website document root."""

from pathlib import Path, PurePosixPath
import re
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SOURCE_DIRS = ("admin", "install", "var", "usr")
ROOT_FILES = (".htaccess", "web.config", "robots.txt", "favicon.ico", "index.php", "install.php")
REQUIRED = {
    "admin/index.php",
    "install/SQLite.php",
    "usr/uploads/.htaccess",
    "var/Typecho/Common.php",
    "index.php",
    "install.php",
}
SKIP_DIRS = {"backups", "cache", "tmp", "node_modules"}
SKIP_SUFFIXES = {".db", ".sqlite", ".sqlite3", ".log"}


def included(path: Path) -> bool:
    relative = path.relative_to(ROOT)
    parts = relative.parts
    name = relative.as_posix()

    if path.is_symlink() or not path.is_file():
        return False
    if any(part in SKIP_DIRS for part in parts):
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
                or (name.startswith("usr/uploads/") and name != "usr/uploads/.htaccess")
            ):
                raise RuntimeError(f"Unsafe or runtime file included in release: {name}")


def build() -> Path:
    files = collect_files()
    output_dir = ROOT / "dist"
    output_dir.mkdir(exist_ok=True)
    output = output_dir / f"TypechoRe-{version()}.zip"
    temporary = output.with_suffix(".zip.tmp")

    try:
        with zipfile.ZipFile(temporary, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as package:
            for path in files:
                package.write(path, path.relative_to(ROOT).as_posix())
        verify(temporary)
        temporary.replace(output)
    finally:
        temporary.unlink(missing_ok=True)

    size_mib = output.stat().st_size / (1024 * 1024)
    print(f"Built {output.relative_to(ROOT)} ({len(files)} files, {size_mib:.1f} MiB)")
    print("Excluded config, databases, logs, uploads, backups, caches, node_modules, and development tools.")
    return output


if __name__ == "__main__":
    build()
