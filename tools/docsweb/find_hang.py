#!/usr/bin/env python3
"""扫一遍 posts/ 找出会卡死的 md（多进程 + 超时）。"""
import multiprocessing as mp
import sys
from pathlib import Path

from build import POSTS, _parse_post_worker


def try_one(path_str: str) -> None:
    # 在新进程里单独解析，死循环就死在这
    _parse_post_worker((Path(path_str), POSTS))


def main() -> None:
    files = sorted(POSTS.rglob("*.md"))
    bad = []
    for p in files:
        proc = mp.Process(target=try_one, args=(str(p),))
        proc.start()
        proc.join(timeout=5)
        if proc.is_alive():
            bad.append(p)
            proc.terminate()
            proc.join()
            print(f"HANG  {p}", flush=True)
        else:
            print(f"ok    {p}", flush=True)
    print(f"\n{len(bad)} hanging files:", file=sys.stderr)
    for p in bad:
        print(f"  {p}", file=sys.stderr)


if __name__ == "__main__":
    main()