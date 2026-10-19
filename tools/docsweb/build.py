#!/usr/bin/env python3
"""纯文本博客生成器 — 只用 Python 标准库。

posts/**.md -> dist/**.html（目录结构原样保留）
分类 = 文件夹；代码高亮由 static/hl.js 完成。
HTML 内容不变不重写；static/ 里的 css/js 变化时才复制。

时间前缀：2026-11-01-xxx.md
"""
from __future__ import annotations

import hashlib
import html
import os
import posixpath
import re
import shutil
import sys
from concurrent.futures import ProcessPoolExecutor, ThreadPoolExecutor
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from urllib.parse import quote

ROOT = Path(__file__).resolve().parent
POSTS = ROOT / "posts"
DIST = ROOT / "dist"
STATIC = ROOT / "static"

# ── 站点设置 ───────────────────────────────────────
SITE = {
    "title": "Hello_World",
    "heading": "Hello_World",
    "lang": "zh-CN",
    "url": "https://love4z.cn/2233/tprd/",
}
PAGE_SIZE = 20
ARCHIVE = "month"
USE_MTIME_DATE = True
BASE = SITE["url"].rstrip("/")

ASSETS = {"css": "style.css", "js": "hl.js"}

# 并发上限：不要把整机 CPU / IO 打满，否则系统明显"卡"
MAX_PARSE_WORKERS = min(os.cpu_count() or 4, 8)
MAX_IO_WORKERS = min(os.cpu_count() or 4, 16)
# 进程池任务块大小：大幅降低 IPC 调度开销
CHUNKSIZE = 8
# 少于这么多篇时直接串行，进程池启动成本反而更高
SERIAL_THRESHOLD = 32


# ── 微型 Markdown ──────────────────────────────────────


def esc(s: str) -> str:
    return html.escape(s, quote=True)


def inline(s: str) -> str:
    codes: list[str] = []

    def stash(m: re.Match) -> str:
        codes.append(f"<code>{esc(m.group(1))}</code>")
        return f"\x00{len(codes) - 1}\x00"

    s = re.sub(r"`([^`]+)`", stash, s)
    s = esc(s)
    s = re.sub(r"!\[([^\]]*)\]\(([^)\s]+)\)", r'<img src="\2" alt="\1" loading="lazy">', s)
    s = re.sub(r"\[([^\]]+)\]\(([^)\s]+)\)", r'<a href="\2">\1</a>', s)
    s = re.sub(r"\*\*([^*]+)\*\*", r"<b>\1</b>", s)
    s = re.sub(r"\*([^*]+)\*", r"<i>\1</i>", s)
    s = re.sub(r"~~([^~]+)~~", r"<s>\1</s>", s)
    return re.sub(r"\x00(\d+)\x00", lambda m: codes[int(m.group(1))], s)


BLOCK_START = re.compile(
    r"^```|^#{1,4}\s|^>\s?|^\s*\||^\s*(?:[-*+]|\d+\.)\s+|^\s*(?:-{3,}|\*{3,})\s*$"
)
TABLE_SEP = re.compile(r"^\s*\|?\s*:?-+:?\s*(?:\|\s*:?-+:?\s*)+\|?\s*$")


def table_cells(line: str) -> list[str]:
    row = line.strip()
    if row.startswith("|"):
        row = row[1:]
    if row.endswith("|"):
        row = row[:-1]
    cells, buf, code = [], [], False
    for ch in row:
        if ch == "`":
            code = not code
        if ch == "|" and not code:
            cells.append("".join(buf))
            buf = []
        else:
            buf.append(ch)
    cells.append("".join(buf))
    return [inline(c.strip()) for c in cells]


def md(src: str) -> str:
    lines = src.replace("\r\n", "\n").replace("\r", "\n").split("\n")
    out: list[str] = []
    i = 0

    while i < len(lines):
        line = lines[i]
        if not line.strip():
            i += 1
            continue

        fence = re.match(r"^```\s*(\S*)\s*$", line)
        if fence:
            buf = []
            i += 1
            while i < len(lines) and not re.match(r"^```\s*$", lines[i]):
                buf.append(lines[i])
                i += 1
            i += 1
            out.append(f"<pre>{esc(chr(10).join(buf))}</pre>")
            continue

        h = re.match(r"^(#{1,4})\s+(.*)$", line)
        if h:
            n = len(h.group(1))
            out.append(f"<h{n}>{inline(h.group(2))}</h{n}>")
            i += 1
            continue

        if re.match(r"^\s*(?:-{3,}|\*{3,})\s*$", line):
            out.append("<hr>")
            i += 1
            continue

        if re.match(r"^>\s?", line):
            buf = []
            while i < len(lines) and re.match(r"^>\s?", lines[i]):
                buf.append(re.sub(r"^>\s?", "", lines[i]))
                i += 1
            out.append(f"<blockquote>{md(chr(10).join(buf))}</blockquote>")
            continue

        li = re.match(r"^\s*(?:[-*+]|\d+\.)\s+(.*)$", line)
        if li:
            ordered = bool(re.match(r"^\s*\d+\.", line))
            items = []
            while i < len(lines) and (li := re.match(r"^\s*(?:[-*+]|\d+\.)\s+(.*)$", lines[i])):
                items.append(f"<li>{inline(li.group(1))}</li>")
                i += 1
            tag = "ol" if ordered else "ul"
            out.append(f"<{tag}>{''.join(items)}</{tag}>")
            continue

        if "|" in line and i + 1 < len(lines) and TABLE_SEP.match(lines[i + 1]):
            head = table_cells(line)
            i += 2
            rows = []
            while i < len(lines) and lines[i].strip() and "|" in lines[i]:
                rows.append(table_cells(lines[i]))
                i += 1
            th = "".join(f"<th>{c}</th>" for c in head)
            body = "".join(
                "<tr>" + "".join(f"<td>{c}</td>" for c in row) + "</tr>" for row in rows
            )
            out.append(f"<table><tr>{th}</tr>{body}</table>")
            continue

        buf = [lines[i]]
        i += 1
        while i < len(lines) and lines[i].strip() and not BLOCK_START.match(lines[i]):
            buf.append(lines[i])
            i += 1
        out.append(f"<p>{inline(' '.join(buf))}</p>")

    return "\n".join(out)


# ── 模板 ───────────────────────────────────────────────


@dataclass
class Post:
    out: str
    base: str
    cat: str
    date: str
    title: str
    body: str


def url_path(p: str) -> str:
    return "/".join(quote(seg, safe="") for seg in p.split("/"))


def page(title: str, out: str, body: str) -> str:
    up = "../" * out.count("/")
    return f"""<!doctype html>
<html lang="{SITE['lang']}">
<meta charset="utf-8">
<title>{esc(title)}</title>
<link rel="stylesheet" href="{up}{ASSETS['css']}">
<link rel="alternate" type="application/atom+xml" href="{up}feed.xml">
<script type="module" src="{up}{ASSETS['js']}"></script>
{body}
</body>
</html>"""


def post_list(items: list[Post], root: str, show_cat: bool = False) -> str:
    rows = []
    for p in items:
        cat = f" {esc(p.cat)}" if show_cat and p.cat else ""
        date = f"{p.date} " if p.date else ""
        rows.append(f'{date}<a href="{root}{url_path(p.out)}">{esc(p.title)}</a>{cat}')
    return f"<p>{'<br>'.join(rows)}</p>" if rows else ""


def home(items: list[Post], cats: list[str], page_no: int = 1, total: int = 1) -> str:
    out = "index.html" if page_no == 1 else f"page/{page_no}.html"
    root = "../" * out.count("/")

    bits: list[str] = []
    if total > 1:
        bits.append(f"Page {page_no} / {total}")
        if page_no > 1:
            prev = "../index.html" if page_no == 2 else f"{page_no - 1}.html"
            bits.append(f'<a href="{prev}">Up</a>')
        if page_no < total:
            nxt = f"page/{page_no + 1}.html" if page_no == 1 else f"{page_no + 1}.html"
            bits.append(f'<a href="{nxt}">Down</a>')
    if cats:
        bits.append(f'<a href="{root}category/index.html">Category</a>')
    pager = f"<nav>{' · '.join(bits)}</nav>" if bits else ""

    title = SITE["title"] if page_no == 1 else f"Page {page_no} · {SITE['title']}"
    return page(
        title,
        out,
        f"""<body>
<h1>{esc(SITE['heading'])}</h1>
{post_list(items, root, True)}
{pager}""",
    )


def post_page(p: Post, newer: Post | None = None, older: Post | None = None) -> str:
    root = "../" * p.out.count("/")
    crumb = f'<a href="{root}index.html">home</a>'
    if p.cat:
        crumb += f'/<a href="{root}category/{url_path(p.cat)}/index.html">{esc(p.cat)}</a>'
    if p.date:
        crumb += f" {p.date}"
    meta_html = f"<p>{crumb}</p>\n"

    pager = []
    if newer:
        pager.append(f'<a href="{root}{url_path(newer.out)}">Last</a>')
    if older:
        pager.append(f'<a href="{root}{url_path(older.out)}">Old</a>')
    pager_html = f"<nav>{' · '.join(pager)}</nav>" if pager else ""

    return page(
        f"{p.title} · {SITE['title']}",
        p.out,
        f"""<body>
{meta_html}<h1>{esc(p.title)}</h1>
{p.body}
{pager_html}""",
    )


def cat_page(cat: str, items: list[Post]) -> str:
    out = f"category/{cat}/index.html"
    root = "../" * out.count("/")
    return page(
        f"{cat} · {SITE['title']}",
        out,
        f"""<body>
<h1><a href="{root}index.html">home</a>/{esc(cat)}</h1>
{post_list(items, root)}""",
    )

def categories_index(cats: list[str], cat_items: dict[str, list[Post]]) -> str:
    rows = [
        f'<a href="{url_path(c)}/index.html">{esc(c)}</a> ({len(cat_items[c])})'
        for c in cats
    ]
    body = f"<p>{'<br>'.join(rows)}</p>" if rows else "<p>暂无分类</p>"
    return page(
        f"Category · {SITE['title']}",
        "category/index.html",
        f"""<body>
<h1><a href="../index.html">home</a>/category</h1>
{body}""",
    )

def archive_index(periods: list[tuple[str, list[Post]]]) -> str:
    rows = [
        f'<a href="{period}.html">{period}</a> ({len(items)})'
        for period, items in periods
    ]
    body = f"<p>{'<br>'.join(rows)}</p>" if rows else ""
    return page(
        f"归档 · {SITE['title']}",
        "archive/index.html",
        f"""<body>
<h1><a href="../index.html">home</a>/archive</h1>
{body}""",
    )


def archive_page(period: str, items: list[Post]) -> str:
    return page(
        f"{period} · {SITE['title']}",
        f"archive/{period}.html",
        f"""<body>
<h1><a href="../index.html">home</a>/<a href="index.html">archive</a>/{esc(period)}</h1>
{post_list(items, '../')}""",
    )


def not_found(css: str) -> str:
    return f"""<!doctype html>
<html lang="{SITE['lang']}">
<meta charset="utf-8">
<title>404 · {esc(SITE['title'])}</title>
<style>{css}</style>
<body>
<p><a href="{BASE}/">Home</a></p>
<h1>404</h1>
<p>NotFoud</p>
</body>
</html>"""


def sitemap(posts: list[Post], cats: list[str], periods: list[tuple[str, list[Post]]]) -> str:
    rows = [f"<url><loc>{esc(BASE)}/</loc></url>"]
    for p in posts:
        last = f"<lastmod>{p.date}</lastmod>" if p.date else ""
        rows.append(f"<url><loc>{esc(BASE + '/' + url_path(p.out))}</loc>{last}</url>")
    if cats:
        rows.append(f"<url><loc>{esc(BASE)}/category/index.html</loc></url>")
    for c in cats:
        loc = BASE + "/category/" + url_path(c) + "/index.html"
        rows.append(f"<url><loc>{esc(loc)}</loc></url>")
    if periods:
        rows.append(f"<url><loc>{esc(BASE)}/archive/index.html</loc></url>")
        for period, _ in periods:
            rows.append(f"<url><loc>{esc(BASE + '/archive/' + period + '.html')}</loc></url>")
    return (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'  # 修掉误写的 Markdown 链接
        + "\n".join(rows)
        + "\n</urlset>\n"
    )


def robots() -> str:
    return f"User-agent: *\nAllow: /\n\nSitemap: {BASE}/sitemap.xml\n"


def atom(posts: list[Post]) -> str:
    dated = [p for p in posts if p.date]
    updated = (dated[0].date if dated else "1970-01-01") + "T00:00:00Z"
    entries = []
    for p in dated:
        url = f"{BASE}/{url_path(p.out)}"
        entries.append(
            f"<entry><title>{esc(p.title)}</title>"
            f'<link href="{esc(url)}"/><id>{esc(url)}</id>'
            f"<updated>{p.date}T00:00:00Z</updated>"
            f"<published>{p.date}T00:00:00Z</published>"
            f'<content type="html" xml:base="{esc(url)}">{esc(p.body)}</content></entry>'
        )
    return (
        '<?xml version="1.0" encoding="utf-8"?>\n'
        f'<feed xmlns="http://www.w3.org/2005/Atom" xml:base="{esc(BASE)}/">\n'  # 修掉误写的 Markdown 链接
        f"<title>{esc(SITE['title'])}</title>\n"
        f'<link rel="self" href="{esc(BASE)}/feed.xml"/>\n'
        f'<link rel="alternate" href="{esc(BASE)}/"/>\n'
        f"<id>{esc(BASE)}/</id>\n"
        f"<updated>{updated}</updated>\n"
        f"<author><name>{esc(SITE['title'])}</name></author>\n"
        + "".join(entries)
        + "\n</feed>\n"
    )


# ── 进度条（节流版）────────────────────────────────────


def print_progress(current: int, total: int, prefix: str = "Progress") -> None:
    if total <= 0:
        return
    bar_length = 30
    percent = min(max(current / total, 0.0), 1.0)
    filled = int(round(bar_length * percent))
    bar = "=" * filled + ("->" if filled < bar_length else "")
    bar = bar[:bar_length].ljust(bar_length, " ")
    sys.stdout.write(f"\r{prefix} |{bar}| {current}/{total} ({int(percent * 100)}%)")
    sys.stdout.flush()
    if current >= total:
        sys.stdout.write("\n")
        sys.stdout.flush()


class Progress:
    """最多刷新 ~40 次，大幅减少终端写入开销（Windows 终端 \r 很慢）。"""
    __slots__ = ("total", "prefix", "step", "last")

    def __init__(self, total: int, prefix: str) -> None:
        self.total = total
        self.prefix = prefix
        self.step = max(1, total // 40) if total else 1
        self.last = 0

    def update(self, current: int) -> None:
        if current >= self.total or current - self.last >= self.step:
            self.last = current
            print_progress(current, self.total, self.prefix)


# ── 解析 Worker ────────────────────────────────────────


def _parse_post_worker(args: tuple[Path, Path]) -> Post:
    path, posts_root = args
    rel = path.relative_to(posts_root).as_posix()
    raw = path.read_text(encoding="utf-8-sig")
    parent = rel.rpartition("/")[0]
    base = rel.rsplit("/", 1)[-1][:-3]
    meta = re.match(r"^(\d{4}-\d{2}-\d{2})-(.+)$", base)
    date = meta.group(1) if meta else ""
    if not date and USE_MTIME_DATE:
        date = datetime.fromtimestamp(path.stat().st_mtime).strftime("%Y-%m-%d")
    lines = raw.replace("\r\n", "\n").replace("\r", "\n").split("\n")

    first = next((n for n, line in enumerate(lines) if line.strip()), -1)
    title = meta.group(2) if meta else base
    if first >= 0:
        h1 = re.match(r"^#\s+(.+)$", lines[first])
        if h1:
            title = h1.group(1).strip()
            del lines[first]

    return Post(
        out=rel[:-3] + ".html",
        base=base,
        cat=parent,
        date=date,
        title=title,
        body=md("\n".join(lines)),
    )


def _parse_post_task(args: tuple[Path, Path]) -> tuple[Path, Post | None, Exception | None]:
    path, posts_root = args
    try:
        return path, _parse_post_worker(args), None
    except Exception as exc:  # 单个失败不影响整体
        return path, None, exc


def _write_output(item: tuple[str, str | bytes]) -> tuple[str, Exception | None]:
    rel, content = item
    try:
        write(DIST / rel, content)
        return rel, None
    except Exception as exc:
        return rel, exc


# ── 构建 ───────────────────────────────────────────────


def write(path: Path, content: str | bytes) -> None:
    data = content.encode("utf-8") if isinstance(content, str) else content
    if path.exists():
        # 先比大小，大小相同再读全文比较，大文件可省一次读
        if path.stat().st_size == len(data) and path.read_bytes() == data:
            return
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(data)


def _copy_if_changed(src: Path, dest: Path) -> None:
    """按 size + mtime 判断，避免每次构建都把大文件读进内存。"""
    try:
        ss = src.stat()
        if dest.exists():
            ds = dest.stat()
            if ds.st_size == ss.st_size and ds.st_mtime_ns >= ss.st_mtime_ns:
                return
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(src, dest)
    except OSError:
        pass


def _copy_tasks(tasks: list[tuple[Path, Path]]) -> None:
    if not tasks:
        return
    with ThreadPoolExecutor(max_workers=MAX_IO_WORKERS) as executor:
        list(executor.map(lambda a: _copy_if_changed(*a), tasks))


def copy_static() -> None:
    if not STATIC.exists():
        return
    _copy_tasks([
        (src, DIST / src.relative_to(STATIC))
        for src in STATIC.rglob("*")
        if src.is_file()
    ])


def copy_post_assets() -> None:
    if not POSTS.exists():
        return
    _copy_tasks([
        (src, DIST / src.relative_to(POSTS))
        for src in POSTS.rglob("*")
        if src.is_file() and src.suffix.lower() not in (".md", ".html")
    ])


def build() -> None:
    files = sorted(POSTS.rglob("*.md")) if POSTS.exists() else []
    posts: list[Post] = []

    # ── 解析：小批量串行，大批量用进程池 + chunksize ──
    if files:
        tasks = [(path, POSTS) for path in files]
        progress = Progress(len(files), "Parsing")
        if len(tasks) < SERIAL_THRESHOLD:
            for i, task in enumerate(tasks, 1):
                path, post, err = _parse_post_task(task)
                if err:
                    print(f"\n[Error parsing {path}] {err}", flush=True)
                else:
                    posts.append(post)
                progress.update(i)
        else:
            with ProcessPoolExecutor(max_workers=MAX_PARSE_WORKERS) as executor:
                for i, (path, post, err) in enumerate(
                    executor.map(_parse_post_task, tasks, chunksize=CHUNKSIZE), 1
                ):
                    if err:
                        print(f"\n[Error parsing {path}] {err}", flush=True)
                    else:
                        posts.append(post)
                    progress.update(i)

    # 旧站绝对链接 /guide/foo 解析成本站相对 .html
    targets = {p.out[:-5]: p.out for p in posts}
    for p in posts:
        base = p.out.rpartition("/")[0] or "."

        def repl(m: re.Match) -> str:
            path, sep, frag = m.group(1).partition("#")
            target = targets.get(path.strip("/"))
            if not target:
                return m.group(0)
            return f'href="{posixpath.relpath(target, base)}{sep}{frag}"'

        p.body = re.sub(r'href="(/[^"]+)"', repl, p.body)

    # 一次 tuple 排序 = 原来的两次稳定排序，无日期排最后
    posts.sort(key=lambda p: (p.date, p.base), reverse=True)

    cats = sorted({p.cat for p in posts if p.cat})
    # 分类 → 文章 一次遍历建好（含子分类归属），避免 O(n×m)
    cat_items: dict[str, list[Post]] = {c: [] for c in cats}
    for p in posts:
        if not p.cat:
            continue
        parts = p.cat.split("/")
        for i in range(1, len(parts) + 1):
            bucket = cat_items.get("/".join(parts[:i]))
            if bucket is not None:
                bucket.append(p)

    DIST.mkdir(parents=True, exist_ok=True)

    print("Copying assets...")
    copy_static()
    copy_post_assets()

    css_path = STATIC / "style.css"
    js_path = STATIC / "hl.js"
    css = css_path.read_text(encoding="utf-8") if css_path.exists() else ""
    js = js_path.read_bytes() if js_path.exists() else b""
    if css:
        ASSETS["css"] = f"style.css?v={hashlib.sha256(css.encode()).hexdigest()[:8]}"
    if js:
        ASSETS["js"] = f"hl.js?v={hashlib.sha256(js).hexdigest()[:8]}"

    if ARCHIVE not in (None, False, "year", "month"):
        raise SystemExit('ARCHIVE must be "year", "month" or None')

    periods: list[tuple[str, list[Post]]] = []
    if ARCHIVE:
        width = 4 if ARCHIVE == "year" else 7
        buckets: dict[str, list[Post]] = {}
        for p in posts:
            if p.date:
                buckets.setdefault(p.date[:width], []).append(p)
        periods = sorted(buckets.items(), reverse=True)

    if PAGE_SIZE and PAGE_SIZE > 0:
        pages = [posts[i : i + PAGE_SIZE] for i in range(0, len(posts), PAGE_SIZE)] or [[]]
    else:
        pages = [posts]
    total_pages = len(pages)

    outputs: list[tuple[str, str | bytes]] = []
    for n, items in enumerate(pages, 1):
        rel = "index.html" if n == 1 else f"page/{n}.html"
        outputs.append((rel, home(items, cats, n, total_pages)))

    if periods:
        outputs.append(("archive/index.html", archive_index(periods)))
        for period, items in periods:
            outputs.append((f"archive/{period}.html", archive_page(period, items)))

    for n, p in enumerate(posts):
        newer = posts[n - 1] if n else None
        older = posts[n + 1] if n + 1 < len(posts) else None
        outputs.append((p.out, post_page(p, newer, older)))

    if cats:
        outputs.append(("category/index.html", categories_index(cats, cat_items)))
    for c in cats:
        outputs.append((f"category/{c}/index.html", cat_page(c, cat_items[c])))

    outputs.append(("404.html", not_found(css)))
    outputs.append(("feed.xml", atom(posts)))
    outputs.append(("sitemap.xml", sitemap(posts, cats, periods)))
    outputs.append(("robots.txt", robots()))

    # ── 写入：executor.map 保持顺序，进度条节流 ──
    written: set[str] = set()
    progress = Progress(len(outputs), "Writing")
    with ThreadPoolExecutor(max_workers=MAX_IO_WORKERS) as executor:
        for i, (rel, err) in enumerate(executor.map(_write_output, outputs), 1):
            if err:
                print(f"\n[Error writing {rel}] {err}", flush=True)
            else:
                written.add(rel)
            progress.update(i)

    for stale in DIST.rglob("*.html"):
        if stale.relative_to(DIST).as_posix() not in written:
            stale.unlink()
    for folder in sorted(
        (p for p in DIST.rglob("*") if p.is_dir()), key=lambda p: len(p.parts), reverse=True
    ):
        try:
            folder.rmdir()
        except OSError:
            pass

    print(f"[ok] {len(posts)} posts, {len(cats)} categories -> dist/", flush=True)


if __name__ == "__main__":
    build()