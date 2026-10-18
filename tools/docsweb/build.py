#!/usr/bin/env python3
"""纯文本博客生成器 — 只用 Python 标准库。

posts/**.md -> dist/**.html（目录结构原样保留）
分类 = 文件夹；代码高亮由 static/hl.js 完成。
HTML 内容不变不重写；static/ 里的 css/js 变化时才复制。
"""
from __future__ import annotations

import hashlib
import html
import posixpath
import re
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
    "url": "https://love4z.cn/2233/tprd/",  # 改成你的域名，sitemap / robots / 404 用它
}
PAGE_SIZE = 20   # 首页每页文章数；0 = 不分页
ARCHIVE = "month"  # "year" / "month" / None
USE_MTIME_DATE = True  # 文件名没有 YYYY-MM-DD- 前缀时，用文件修改日期补上
BASE = SITE["url"].rstrip("/")

ASSETS = {"css": "style.css", "js": "hl.js"}


# ── 微型 Markdown ──────────────────────────────────────


def esc(s: str) -> str:
    return html.escape(s, quote=True)


def inline(s: str) -> str:
    # 行内代码先抽出来，避免里面的 * _ 被当成强调
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

        # 围栏代码块
        fence = re.match(r"^```(\S*)\s*$", line)
        if fence:
            buf = []
            i += 1
            while i < len(lines) and not re.match(r"^```\s*$", lines[i]):
                buf.append(lines[i])
                i += 1
            i += 1  # 跳过结束围栏
            out.append(f"<pre>{esc(chr(10).join(buf))}</pre>")
            continue

        # 标题
        h = re.match(r"^(#{1,4})\s+(.*)$", line)
        if h:
            n = len(h.group(1))
            out.append(f"<h{n}>{inline(h.group(2))}</h{n}>")
            i += 1
            continue

        # 分隔线
        if re.match(r"^\s*(?:-{3,}|\*{3,})\s*$", line):
            out.append("<hr>")
            i += 1
            continue

        # 引用
        if re.match(r"^>\s?", line):
            buf = []
            while i < len(lines) and re.match(r"^>\s?", lines[i]):
                buf.append(re.sub(r"^>\s?", "", lines[i]))
                i += 1
            out.append(f"<blockquote>{md(chr(10).join(buf))}</blockquote>")
            continue

        # 列表（单层）
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

        # 表格
        if "|" in line and i + 1 < len(lines) and TABLE_SEP.match(lines[i + 1]):
            head = table_cells(line)
            i += 2  # 跳过表头和分隔行
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

        # 段落（到空行或下一个块为止）
        buf = []
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
    # 逐段编码，文件夹/slug 里的空格、中文、# 都不会弄坏链接
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

    nav = ""
    if page_no == 1 and cats:
        links = " · ".join(
            f'<a href="category/{url_path(c)}/index.html">{esc(c)}</a>' for c in cats
        )
        nav = f"<p>分类：{links}</p>"

    pager = ""
    if total > 1:
        bits = [f"第 {page_no} / {total} 页"]
        if page_no > 1:
            prev = "../index.html" if page_no == 2 else f"{page_no - 1}.html"
            bits.append(f'<a href="{prev}">上一页</a>')
        if page_no < total:
            nxt = f"page/{page_no + 1}.html" if page_no == 1 else f"{page_no + 1}.html"
            bits.append(f'<a href="{nxt}">下一页</a>')
        pager = f"<nav>{' · '.join(bits)}</nav>"

    title = SITE["title"] if page_no == 1 else f"第 {page_no} 页 · {SITE['title']}"
    return page(
        title,
        out,
        f"""<body>
<h1>{esc(SITE['heading'])}</h1>
{nav}
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
        pager.append(f'<a href="{root}{url_path(newer.out)}">较新</a>')
    if older:
        pager.append(f'<a href="{root}{url_path(older.out)}">较旧</a>')
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
    # 404 会被服务器在任意路径下返回，所以自带样式、用绝对地址回首页
    return f"""<!doctype html>
<html lang="{SITE['lang']}">
<meta charset="utf-8">
<title>404 · {esc(SITE['title'])}</title>
<style>{css}</style>
<body>
<p><a href="{BASE}/">首页</a></p>
<h1>404</h1>
<p>页面不存在。</p>
</body>
</html>"""


def sitemap(posts: list[Post], cats: list[str], periods: list[tuple[str, list[Post]]]) -> str:
    rows = [f"<url><loc>{esc(BASE)}/</loc></url>"]
    for p in posts:
        last = f"<lastmod>{p.date}</lastmod>" if p.date else ""
        rows.append(f"<url><loc>{esc(BASE + '/' + url_path(p.out))}</loc>{last}</url>")
    for c in cats:
        loc = BASE + "/category/" + url_path(c) + "/index.html"
        rows.append(f"<url><loc>{esc(loc)}</loc></url>")
    if periods:
        rows.append(f"<url><loc>{esc(BASE)}/archive/index.html</loc></url>")
        for period, _ in periods:
            rows.append(f"<url><loc>{esc(BASE + '/archive/' + period + '.html')}</loc></url>")
    return (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
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
        f'<feed xmlns="http://www.w3.org/2005/Atom" xml:base="{esc(BASE)}/">\n'
        f"<title>{esc(SITE['title'])}</title>\n"
        f'<link rel="self" href="{esc(BASE)}/feed.xml"/>\n'
        f'<link rel="alternate" href="{esc(BASE)}/"/>\n'
        f"<id>{esc(BASE)}/</id>\n"
        f"<updated>{updated}</updated>\n"
        f"<author><name>{esc(SITE['title'])}</name></author>\n"
        + "".join(entries)
        + "\n</feed>\n"
    )


# ── 构建 ───────────────────────────────────────────────


def write(path: Path, content: str | bytes) -> None:
    data = content.encode("utf-8") if isinstance(content, str) else content
    if path.exists() and path.read_bytes() == data:
        return
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(data)


def copy_static() -> None:
    if not STATIC.exists():
        return
    for src in STATIC.rglob("*"):
        if src.is_file():
            write(DIST / src.relative_to(STATIC), src.read_bytes())


def copy_post_assets() -> None:
    # 文章里引用的图片、脚本等原件，按 posts/ 的目录结构复制过去
    if not POSTS.exists():
        return
    for src in POSTS.rglob("*"):
        if src.is_file() and src.suffix.lower() not in (".md", ".html"):
            write(DIST / src.relative_to(POSTS), src.read_bytes())


def build() -> None:
    files = sorted(POSTS.rglob("*.md")) if POSTS.exists() else []
    posts: list[Post] = []

    for path in files:
        rel = path.relative_to(POSTS).as_posix()
        raw = path.read_text(encoding="utf-8-sig")
        parent = rel.rpartition("/")[0]
        base = rel.rsplit("/", 1)[-1][:-3]
        meta = re.match(r"^(\d{4}-\d{2}-\d{2})-(.+)$", base)
        date = meta.group(1) if meta else ""
        if not date and USE_MTIME_DATE:
            date = datetime.fromtimestamp(path.stat().st_mtime).strftime("%Y-%m-%d")
        lines = raw.replace("\r\n", "\n").replace("\r", "\n").split("\n")

        # 首个非空行若是 # 标题，就作为页面标题并从正文移除
        first = next((n for n, line in enumerate(lines) if line.strip()), -1)
        title = meta.group(2) if meta else base
        if first >= 0:
            h1 = re.match(r"^#\s+(.+)$", lines[first])
            if h1:
                title = h1.group(1).strip()
                del lines[first]

        posts.append(
            Post(
                out=rel[:-3] + ".html",
                base=base,
                cat=parent,
                date=date,
                title=title,
                body=md("\n".join(lines)),
            )
        )

    # 旧站绝对链接 /guide/foo 解析成本站的相对 .html 链接
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

    # 日期倒序，无日期的排最后（稳定排序保留同日期内的文件名倒序）
    posts.sort(key=lambda p: p.base, reverse=True)
    posts.sort(key=lambda p: p.date, reverse=True)
    cats = sorted({p.cat for p in posts if p.cat})

    DIST.mkdir(parents=True, exist_ok=True)
    copy_static()
    copy_post_assets()

    # 内容哈希做缓存版本号：文件还是固定的，只有内容变了 HTML 才换 URL
    css = (STATIC / "style.css").read_text(encoding="utf-8")
    js = (STATIC / "hl.js").read_bytes()
    ASSETS["css"] = f"style.css?v={hashlib.sha256(css.encode()).hexdigest()[:8]}"
    ASSETS["js"] = f"hl.js?v={hashlib.sha256(js).hexdigest()[:8]}"

    written: set[str] = set()

    def emit(rel: str, content: str) -> None:
        write(DIST / rel, content)
        written.add(rel)

    if ARCHIVE not in (None, False, "year", "month"):
        raise SystemExit('ARCHIVE must be "year", "month" or None')

    # 时间归档：没有带日期（YYYY-MM-DD-）的文章时自动隐藏
    periods: list[tuple[str, list[Post]]] = []
    if ARCHIVE:
        width = 4 if ARCHIVE == "year" else 7
        buckets: dict[str, list[Post]] = {}
        for p in posts:
            if p.date:
                buckets.setdefault(p.date[:width], []).append(p)
        periods = sorted(buckets.items(), reverse=True)
    # 首页分页
    if PAGE_SIZE and PAGE_SIZE > 0:
        pages = [posts[i : i + PAGE_SIZE] for i in range(0, len(posts), PAGE_SIZE)] or [[]]
    else:
        pages = [posts]
    total = len(pages)
    for n, items in enumerate(pages, 1):
        emit("index.html" if n == 1 else f"page/{n}.html", home(items, cats, n, total))

    if periods:
        emit("archive/index.html", archive_index(periods))
        for period, items in periods:
            emit(f"archive/{period}.html", archive_page(period, items))

    for n, p in enumerate(posts):
        newer = posts[n - 1] if n else None
        older = posts[n + 1] if n + 1 < len(posts) else None
        emit(p.out, post_page(p, newer, older))
    for c in cats:
        items = [p for p in posts if p.cat == c or p.cat.startswith(c + "/")]
        emit(f"category/{c}/index.html", cat_page(c, items))
    emit("404.html", not_found(css))
    write(DIST / "feed.xml", atom(posts))
    write(DIST / "sitemap.xml", sitemap(posts, cats, periods))
    write(DIST / "robots.txt", robots())

    # 删掉已不存在的文章页面，再清掉空目录
    for stale in DIST.rglob("*.html"):
        rel = stale.relative_to(DIST).as_posix()
        if rel not in written:
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
