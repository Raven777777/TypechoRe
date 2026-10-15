#!/usr/bin/env python3
"""Regression test for the release package: exclusions and the SHA-256 manifest."""

from hashlib import sha256
from pathlib import Path, PurePosixPath
import re
import secrets
import subprocess
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]
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


def theme_dev_entries(names: list[str]) -> list[str]:
    """发布包里不该出现的主题开发/构建文件"""
    offenders = []

    for name in names:
        match = THEME_ENTRY.match(name)

        if match is not None and (match.group(1) in THEME_DEV_DIRS or match.group(1) in THEME_DEV_FILES):
            offenders.append(name)

    return sorted(offenders)


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


def main() -> None:
    prefix = f"typechore-release-filter-{secrets.token_hex(4)}"
    fixtures = [ROOT / "usr" / f"{prefix}{suffix}" for suffix in SUFFIXES]

    try:
        for fixture in fixtures:
            fixture.write_bytes(b"test-only SQLite runtime artifact")

        result = subprocess.run(
            [sys.executable, str(ROOT / "tools" / "build_release.py")],
            cwd=ROOT,
            capture_output=True,
            text=True,
        )
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
            covered = check_manifest(package, package_names)
            theme_dist = sorted(name for name in package_names if re.match(r"^usr/themes/[^/]+/dist/", name))

        if "LICENSE.txt" not in package_names:
            raise RuntimeError("GNU GPL license text is missing from the release ZIP")
        if leaked:
            raise RuntimeError(f"SQLite runtime files leaked into release ZIP: {', '.join(sorted(leaked))}")
        if dev_files:
            raise RuntimeError(f"development-only theme files leaked into release ZIP: {', '.join(dev_files)}")
        if not theme_dist:
            raise RuntimeError("theme build artifacts (dist/) are missing from the release ZIP")

        print(f"PASS: release ZIP excludes {len(fixtures)} SQLite database/sidecar variants")
        print(
            f"PASS: release ZIP excludes theme development files "
            f"(tests/, src/, Node build configs) and keeps {len(theme_dist)} dist/ artifact(s)"
        )
        print(f"PASS: {MANIFEST} covers all {covered} packaged files with matching SHA-256 digests")
    finally:
        for fixture in fixtures:
            fixture.unlink(missing_ok=True)


if __name__ == "__main__":
    main()
