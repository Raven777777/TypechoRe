<?php

namespace Widget\Action;

use Typecho\Common;
use Typecho\Db;
use Typecho\Db\Query;
use Typecho\Router;
use Widget\ActionInterface;
use Widget\Base\Contents;
use Widget\Base\Metas;
use Typecho\Widget\Exception as WidgetException;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Sitemap protocol 0.9 handler
 *
 * Small sites return a single urlset from /sitemap.xml. Larger sites return a
 * protocol-compliant sitemapindex and serve each bounded page as a urlset.
 */
class Sitemap extends Contents implements ActionInterface
{
    /**
     * URLs emitted by one child sitemap. This is deliberately below the
     * protocol limit of 50,000 to keep memory and response time bounded.
     */
    private const URLS_PER_SITEMAP = 1000;

    /**
     * Hard protocol limit for one urlset.
     */
    private const MAX_URLS_PER_SITEMAP = 50000;

    /**
     * Hard protocol limit for one sitemapindex.
     */
    private const MAX_SITEMAPS_PER_INDEX = 50000;

    /**
     * A <loc> value must be shorter than 2048 characters.
     */
    private const MAX_URL_LENGTH = 2047;

    /**
     * Valid values for the optional <changefreq> tag.
     */
    private const CHANGE_FREQUENCIES = [
        'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never',
    ];

    /**
     * @var int
     */
    private int $sitemapPage = 1;

    /**
     * @var int
     */
    private int $sitemapPages = 1;

    /**
     * @var bool
     */
    private bool $isSitemapIndex = false;

    /**
     * @var array[]
     */
    private array $urls = [];

    /**
     * @return string[]
     */
    public static function route(): array
    {
        return [
            'url'    => '/sitemap.xml',
            'widget' => '\\' . self::class,
            'action' => 'action',
        ];
    }

    /**
     * @return string[]
     */
    public static function pageRoute(): array
    {
        return [
            'url'    => '/sitemap-[page:digital].xml',
            'widget' => '\\' . self::class,
            'action' => 'action',
        ];
    }

    /**
     * @throws Db\Exception
     */
    public function execute()
    {
        $exists = false;
        $requestPage = $this->request->get('page', null, $exists);
        $this->sitemapPage = max(1, intval($requestPage ?? 1));

        $counts = $this->countSources();
        $this->sitemapPages = max(1, (int)ceil($counts['total'] / self::URLS_PER_SITEMAP));
        $isPageRoute = isset(Router::$current) && 'sitemapPage' === Router::$current;
        $this->isSitemapIndex = !$isPageRoute && $counts['total'] > self::URLS_PER_SITEMAP;

        if ($this->sitemapPage > $this->sitemapPages) {
            throw new WidgetException(_t('请求的地址不存在'), 404);
        }

        if (!$this->isSitemapIndex) {
            $this->buildUrls($counts);
        }
    }

    /**
     * @return void
     */
    public function action()
    {
        $this->render();
    }

    /**
     * @return void
     */
    public function render()
    {
        $this->response->setContentType('application/xml');

        if ($this->isSitemapIndex) {
            $this->renderIndex();
        } else {
            $this->renderUrlset();
        }
    }

    /**
     * @return array{content: int, category: int, tag: int, total: int}
     * @throws Db\Exception
     */
    private function countSources(): array
    {
        $contentCount = $this->size($this->publicContentQuery(['table.contents.cid']));

        $metas = Metas::allocWithAlias('sitemap-count');
        $categoryCount = $metas->size($this->metaQuery('category'));
        $tagCount = $metas->size($this->metaQuery('tag'));
        Metas::destroy('sitemap-count');

        return [
            'content'  => $contentCount,
            'category' => $categoryCount,
            'tag'      => $tagCount,
            'total'    => 1 + $contentCount + $categoryCount + $tagCount,
        ];
    }

    /**
     * @param array{content: int, category: int, tag: int, total: int} $counts
     * @return void
     * @throws Db\Exception
     */
    private function buildUrls(array $counts)
    {
        $this->urls = [];
        $offset = ($this->sitemapPage - 1) * self::URLS_PER_SITEMAP;
        $remaining = self::URLS_PER_SITEMAP;

        if (1 === $this->sitemapPage) {
            $this->addUrl(
                Common::url('/', $this->options->rootUrl),
                $this->latestContentTime(),
                'daily',
                '1.0'
            );
            $remaining--;
        }

        // The home page occupies the first global URL slot.
        $contentOffset = max(0, $offset - 1);
        if ($remaining > 0 && $contentOffset < $counts['content']) {
            $limit = min($remaining, $counts['content'] - $contentOffset);
            $this->addContentUrls($contentOffset, $limit);
            $remaining -= $limit;
        }

        $metaOffset = max(0, $offset - 1 - $counts['content']);
        if ($remaining > 0) {
            $categoryOffset = min($metaOffset, $counts['category']);
            $categoryLimit = min($remaining, $counts['category'] - $categoryOffset);

            if ($categoryLimit > 0) {
                $this->addMetaUrls('category', '0.6', $categoryOffset, $categoryLimit);
                $remaining -= $categoryLimit;
            }

            $tagOffset = max(0, $metaOffset - $counts['category']);
            if ($remaining > 0 && $tagOffset < $counts['tag']) {
                $tagLimit = min($remaining, $counts['tag'] - $tagOffset);
                $this->addMetaUrls('tag', '0.4', $tagOffset, $tagLimit);
            }
        }
    }

    /**
     * @param string[]|null $fields
     * @return Query
     * @throws Db\Exception
     */
    private function publicContentQuery(?array $fields = null): Query
    {
        $fields ??= [
            'table.contents.cid',
            'table.contents.slug',
            'table.contents.created',
            'table.contents.modified',
            'table.contents.type',
            'table.contents.authorId',
            'table.contents.order',
            'table.contents.parent',
        ];

        return $this->select(...$fields)
            ->from('table.contents')
            ->where('table.contents.type = ? OR table.contents.type = ?', 'post', 'page')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.created < ?', $this->options->time)
            ->where("table.contents.password IS NULL OR table.contents.password = ''")
            ->order('table.contents.cid', Db::SORT_ASC);
    }

    /**
     * @return int|null
     * @throws Db\Exception
     */
    private function latestContentTime(): ?int
    {
        $row = $this->db->fetchObject($this->publicContentQuery([
            'MAX(table.contents.modified)' => 'modified',
            'MAX(table.contents.created)'  => 'created',
        ]));

        if (null === $row || (empty($row->modified) && empty($row->created))) {
            return null;
        }

        return max(intval($row->modified ?? 0), intval($row->created ?? 0));
    }

    /**
     * @param int $offset
     * @param int $limit
     * @return void
     * @throws Db\Exception
     */
    private function addContentUrls(int $offset, int $limit)
    {
        if ($limit <= 0) {
            return;
        }

        $alias = 'sitemap-contents-' . $this->sitemapPage;
        $contents = Contents::allocWithAlias($alias);
        $rows = $this->db->fetchAll(
            $this->publicContentQuery()->limit($limit)->offset($offset)
        );

        $contents->pushAll($rows);
        while ($contents->next()) {
            $this->addUrl(
                $contents->permalink,
                max(intval($contents->created), intval($contents->modified)),
                'weekly',
                'post' == $contents->type ? '0.8' : '0.6'
            );
        }

        Contents::destroy($alias);
    }

    /**
     * @param string $type
     * @return Query
     * @throws Db\Exception
     */
    private function metaQuery(string $type): Query
    {
        return $this->db->select('table.metas.*')
            ->from('table.metas')
            ->where('table.metas.type = ?', $type)
            ->where('table.metas.count > ?', 0)
            ->order('table.metas.mid', Db::SORT_ASC);
    }

    /**
     * @param string $type
     * @param string $priority
     * @param int $offset
     * @param int $limit
     * @return void
     * @throws Db\Exception
     */
    private function addMetaUrls(string $type, string $priority, int $offset, int $limit)
    {
        if ($limit <= 0) {
            return;
        }

        $alias = 'sitemap-metas-' . $type;
        $metas = Metas::allocWithAlias($alias);
        $rows = $this->db->fetchAll(
            $this->metaQuery($type)->limit($limit)->offset($offset)
        );

        foreach ($rows as $row) {
            $metas->push($row);
            $this->addUrl($metas->permalink, null, 'weekly', $priority);
        }

        Metas::destroy($alias);
    }

    /**
     * @param string $url
     * @param int|null $lastmod
     * @param string|null $changefreq
     * @param string|int|float|null $priority
     * @return void
     */
    private function addUrl(string $url, ?int $lastmod, ?string $changefreq, $priority = null)
    {
        $url = $this->escapeUrl($url);

        if ('' === $url || mb_strlen($url, 'UTF-8') >= self::MAX_URL_LENGTH) {
            return;
        }

        $this->urls[] = [
            'loc'        => $url,
            'lastmod'    => null === $lastmod ? null : $this->w3cDate($lastmod),
            'changefreq' => $changefreq,
            'priority'   => $priority,
        ];
    }

    /**
     * @return void
     */
    private function renderUrlset()
    {
        $urls = self::pluginHandle()->filter('sitemapUrls', $this->urls, $this);
        if (is_array($urls)) {
            $urls = array_slice($urls, 0, self::MAX_URLS_PER_SITEMAP);
        } else {
            $urls = $this->urls;
        }

        $this->openXml('urlset');
        $written = 0;

        foreach ($urls as $url) {
            if (!is_array($url) || !isset($url['loc']) || !is_string($url['loc'])) {
                continue;
            }

            $loc = $this->escapeUrl($url['loc']);
            if ('' === $loc || !$this->isSameHost($loc)
                || mb_strlen($loc, 'UTF-8') >= self::MAX_URL_LENGTH) {
                continue;
            }

            echo '  <url>' . "\n";
            echo '    <loc>' . $this->escapeXml($loc) . '</loc>' . "\n";

            $lastmod = $url['lastmod'] ?? null;
            if ($this->isValidW3cDate($lastmod)) {
                echo '    <lastmod>' . $this->escapeXml((string)$lastmod) . '</lastmod>' . "\n";
            }

            $changefreq = $url['changefreq'] ?? null;
            if (is_string($changefreq) && in_array(strtolower($changefreq), self::CHANGE_FREQUENCIES, true)) {
                echo '    <changefreq>' . $this->escapeXml(strtolower($changefreq)) . '</changefreq>' . "\n";
            }

            $priority = $this->normalizePriority($url['priority'] ?? null);
            if (null !== $priority) {
                echo '    <priority>' . $priority . '</priority>' . "\n";
            }

            echo '  </url>' . "\n";

            if (++$written % 100 === 0) {
                $this->flushOutput();
            }
        }

        echo '</urlset>' . "\n";
        $this->flushOutput();
    }

    /**
     * @return void
     */
    private function renderIndex()
    {
        $sitemaps = [];
        $limit = min($this->sitemapPages, self::MAX_SITEMAPS_PER_INDEX);

        for ($page = 1; $page <= $limit; $page++) {
            $sitemaps[] = [
                'loc' => Common::url('/sitemap-' . $page . '.xml', $this->options->rootUrl),
            ];
        }

        $filtered = self::pluginHandle()->filter('sitemapIndex', $sitemaps, $this);
        if (is_array($filtered)) {
            $sitemaps = array_slice($filtered, 0, self::MAX_SITEMAPS_PER_INDEX);
        }

        $this->openXml('sitemapindex');
        $written = 0;

        foreach ($sitemaps as $sitemap) {
            if (!is_array($sitemap) || !isset($sitemap['loc']) || !is_string($sitemap['loc'])) {
                continue;
            }

            $loc = $this->escapeUrl($sitemap['loc']);
            if ('' === $loc || !$this->isSameHost($loc)
                || mb_strlen($loc, 'UTF-8') >= self::MAX_URL_LENGTH) {
                continue;
            }

            echo '  <sitemap>' . "\n";
            echo '    <loc>' . $this->escapeXml($loc) . '</loc>' . "\n";

            $lastmod = $sitemap['lastmod'] ?? null;
            if ($this->isValidW3cDate($lastmod)) {
                echo '    <lastmod>' . $this->escapeXml((string)$lastmod) . '</lastmod>' . "\n";
            }

            echo '  </sitemap>' . "\n";

            if (++$written % 100 === 0) {
                $this->flushOutput();
            }
        }

        echo '</sitemapindex>' . "\n";
        $this->flushOutput();
    }

    /**
     * @param string $root
     * @return void
     */
    private function openXml(string $root)
    {
        $schema = 'urlset' === $root ? 'sitemap.xsd' : 'siteindex.xsd';

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<' . $root . ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            . ' xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9'
            . ' http://www.sitemaps.org/schemas/sitemap/0.9/' . $schema . '">' . "\n";
    }

    /**
     * URL-escape according to RFC-3986 while preserving existing valid
     * percent-encoded octets. XML entity escaping is applied separately.
     *
     * @param string $url
     * @return string
     */
    private function escapeUrl(string $url): string
    {
        $url = $this->toUtf8(trim($url));

        return (string)preg_replace_callback(
            '/%[0-9A-Fa-f]{2}|[^A-Za-z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]+/u',
            function (array $matches): string {
                return str_starts_with($matches[0], '%') ? $matches[0] : rawurlencode($matches[0]);
            },
            $url
        );
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function escapeXml($value): string
    {
        if (is_scalar($value)) {
            $value = (string)$value;
        } else {
            $value = '';
        }

        return htmlspecialchars($this->toUtf8($value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Convert a value from the configured charset to UTF-8 before XML output.
     */
    private function toUtf8(string $value): string
    {
        $charset = strtoupper((string)$this->options->charset);
        if ('UTF-8' === $charset || 'UTF8' === $charset) {
            return $value;
        }

        if (function_exists('mb_convert_encoding')) {
            return (string)mb_convert_encoding($value, 'UTF-8', $this->options->charset);
        }

        if (function_exists('iconv')) {
            $converted = @iconv($this->options->charset, 'UTF-8//IGNORE', $value);
            return false === $converted ? '' : $converted;
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function isValidW3cDate($value): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        $value = trim((string)$value);

        return preg_match(
            '/^\d{4}-\d{2}-\d{2}(?:[Tt]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:[Zz]|[+-]\d{2}:\d{2})?)?$/',
            $value
        ) === 1;
    }

    /**
     * @param mixed $priority
     * @return string|null
     */
    private function normalizePriority($priority): ?string
    {
        if (!is_numeric($priority)) {
            return null;
        }

        $priority = floatval($priority);
        if ($priority < 0.0 || $priority > 1.0) {
            return null;
        }

        return number_format($priority, 1, '.', '');
    }

    /**
     * @param int $timestamp
     * @return string
     */
    private function w3cDate(int $timestamp): string
    {
        $offset = intval($this->options->timezone);
        $sign = $offset < 0 ? '-' : '+';
        $offset = abs($offset);
        $hours = intdiv($offset, 3600);
        $minutes = intdiv($offset % 3600, 60);

        return gmdate('Y-m-d\TH:i:s', $timestamp)
            . $sign . sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * @param string $url
     * @return bool
     */
    private function isSameHost(string $url): bool
    {
        $siteHost = parse_url($this->options->rootUrl, PHP_URL_HOST);
        if (!is_string($siteHost) || '' === $siteHost) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);
        return is_string($host) && 0 === strcasecmp($host, $siteHost);
    }

    /**
     * @return void
     */
    private function flushOutput()
    {
        // 只冲刷当前缓冲内容, 不结束缓冲区:
        // ob_end_flush() 会关闭插件或框架开启的缓冲区, 导致后续的 gzip/回调失效
        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }
}