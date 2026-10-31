<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 置顶标记 (插件 Sticky 把配置里的 HTML 原样放在 sticky_html 字段)
 */
function docswebStickyBadge(\Widget\Base\Contents $content): string
{
    $html = trim((string) $content->sticky_html);

    return $content->sticky && '' !== $html ? ' ' . $html : '';
}

/**
 * docsweb 风格分页: Page 2 / 5 · Up · Down · Category · Archive
 */
function docswebPager(\Widget\Archive $archive): void
{
    $page = $archive->getCurrentPage();
    $total = $archive->getTotalPage();
    $bits = [];

    if ($total > 1) {
        $bits[] = 'Page ' . $page . ' / ' . $total;
        foreach (['prev' => 'Up', 'next' => 'Down'] as $rel => $label) {
            ob_start();
            $archive->pageLink($label, $rel);
            $link = trim((string) ob_get_clean());
            if ('' !== $link) {
                $bits[] = $link;
            }
        }
    }

    foreach (['Category' => 'page-categories.php', 'Archive' => 'page-archives.php', 'Tags' => 'page-tags.php'] as $label => $template) {
        $url = docswebPageUrl($template);
        if ($url) {
            $bits[] = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $label . '</a>';
        }
    }

    if ($bits) {
        echo '<nav>' . implode(' · ', $bits) . '</nav>';
    }
}

/**
 * 按自定义模板查找分类/归档索引页的地址（索引页由 themeInit 首次启用时创建）
 */
function docswebPageUrl(string $template): ?string
{
    static $urls = null;

    if (null === $urls) {
        $urls = [];
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll($db->select('slug', 'template')->from('table.contents')
            ->where('type = ?', 'page')
            ->where('status = ?', 'publish')
            ->where('template IN ?', ['page-categories.php', 'page-archives.php', 'page-tags.php']));
        $index = \Widget\Options::alloc()->index;
        foreach ($rows as $row) {
            $urls[$row['template']] ??= \Typecho\Router::url('page', ['slug' => $row['slug']], $index);
        }
    }

    return $urls[$template] ?? null;
}

/**
 * 首次启用时用 PHP 建好分类/归档/标签索引页，只运行一次。
 * 撤销的页面保持现状，删除后不会自动重建，并发请求也只建一份。
 */
function themeInit($archive): void
{
    static $done = false;
    // 标记项在引导阶段已随 options 载入, 这里读到就说明建过了, 不必再开写事务
    if ($done || \Widget\Options::alloc()->default_docs_indexes) {
        return;
    }
    $done = true;

    try {
        docswebCreateIndexPages();
    } catch (\Throwable $e) {
        // 只读数据库等初始化失败时不影响页面输出，下次请求会重试
    }
}

/**
 * 在事务内补建索引页
 */
function docswebCreateIndexPages(): void
{
    $db = \Typecho\Db::get();
    $db->transaction(static function () use ($db): void {
        docswebInsertIndexPages($db);
    });
}

/**
 * 事务内的建页逻辑；已初始化过（含并发请求先到者）则跳过
 */
function docswebInsertIndexPages(\Typecho\Db $db): void
{
    $key = 'default_docs_indexes';
    if ($db->fetchRow($db->select('name')->from('table.options')
        ->where('name = ?', $key)->limit(1))) {
        return;
    }
    $db->query($db->insert('table.options')->rows([
        'name'  => $key,
        'user'  => 0,
        'value' => '1',
    ]));

    $author = $db->fetchRow($db->select('uid')->from('table.users')->limit(1));
    $authorId = $author ? (int) $author['uid'] : 1;
    $now = time();
    // created 必须早于本次请求的 options->time，否则 Typecho 归档查询会过滤掉新页面
    $created = $now - 1;

    foreach ([
        ['Categories', 'categories', 'page-categories.php'],
        ['Archives', 'archives', 'page-archives.php'],
        ['Tags', 'tags', 'page-tags.php'],
    ] as [$title, $slug, $template]) {
        // 草稿、私密等任何状态都算已存在，避免重新发布被撤下的页面
        $exists = $db->fetchRow($db->select('cid')->from('table.contents')
            ->where('type = ?', 'page')
            ->where('template = ?', $template)
            ->limit(1));
        if ($exists) {
            continue;
        }
        // 地址已被其他内容占用时跳过，不生成 categories-2 之类的重复页
        if ($db->fetchRow($db->select('cid')->from('table.contents')
            ->where('slug = ?', $slug)->limit(1))) {
            continue;
        }

        $db->query($db->insert('table.contents')->rows([
            'title'        => $title,
            'slug'         => $slug,
            'created'      => $created,
            'modified'     => $now,
            'text'         => '',
            'order'        => 0,
            'authorId'     => $authorId,
            'template'     => $template,
            'type'         => 'page',
            'status'       => 'publish',
            'password'     => null,
            'commentsNum'  => 0,
            'allowComment' => '0',
            'allowPing'    => '0',
            'allowFeed'    => '0',
            'parent'       => 0,
        ]));
    }
}
