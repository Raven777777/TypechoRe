<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
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
 * 按自定义模板查找分类/归档索引页的地址
 */
function docswebPageUrl(string $template): ?string
{
    static $urls = [];

    if (!array_key_exists($template, $urls)) {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow($db->select('slug')->from('table.contents')
            ->where('type = ?', 'page')
            ->where('status = ?', 'publish')
            ->where('template = ?', $template)
            ->limit(1));
        $urls[$template] = $row
            ? \Typecho\Router::url('page', ['slug' => $row['slug']], \Widget\Options::alloc()->index)
            : null;
    }

    return $urls[$template];
}

/**
 * 首次渲染时补建分类/归档索引页，已存在则跳过
 */
function themeInit($archive): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $db = \Typecho\Db::get();
    $author = $db->fetchRow($db->select('uid')->from('table.users')->limit(1));
    $authorId = $author ? (int) $author['uid'] : 1;
    $now = time();
    // created 必须早于本次请求的 options->time，否则 Typecho 归档查询会过滤掉新页面
    $created = $now - 1;

    foreach ([
        ['Categories', 'categories', 'page-categories.php'],
        ['Archives', 'archives', 'page-archives.php'],
        ['Tags', 'tags', 'page-tags.php'],
    ] as [$title, $baseSlug, $template]) {
        $exists = $db->fetchRow($db->select('cid')->from('table.contents')
            ->where('type = ?', 'page')
            ->where('status = ?', 'publish')
            ->where('template = ?', $template)
            ->limit(1));
        if ($exists) {
            continue;
        }

        $slug = $baseSlug;
        $i = 2;
        while ($db->fetchRow($db->select('cid')->from('table.contents')
            ->where('slug = ?', $slug)->limit(1))) {
            $slug = $baseSlug . '-' . $i++;
        }

        try {
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
        } catch (\Throwable $e) {
            // 建页失败（如只读数据库）时不影响页面输出
            continue;
        }
    }
}
