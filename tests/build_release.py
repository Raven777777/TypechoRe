#!/usr/bin/env python3
"""Regression test that release packages exclude SQLite databases and sidecars."""

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
            package_names = set(package.namelist())
            leaked = fixture_names.intersection(package_names)

        if "LICENSE.txt" not in package_names:
            raise RuntimeError("GNU GPL license text is missing from the release ZIP")
        if leaked:
            raise RuntimeError(f"SQLite runtime files leaked into release ZIP: {', '.join(sorted(leaked))}")

        print(f"PASS: release ZIP excludes {len(fixtures)} SQLite database/sidecar variants")
    finally:
        for fixture in fixtures:
            fixture.unlink(missing_ok=True)


if __name__ == "__main__":
    main()
