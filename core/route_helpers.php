<?php
declare(strict_types=1);
/**
 * Slug çözümleme yardımcıları — router içindeki tekrarlanan
 * "slug bu tablonun bir kaydı mı?" sorgularını tek noktada toplar.
 *
 * İstek başına sonuç önbelleklenir; aynı slug iki kez sorgulanırsa DB'ye gitmez.
 */

function _slug_cache(string $key): array {
    static $cache = [];
    if (!isset($cache[$key])) $cache[$key] = [];
    return $cache[$key];
}

function _slug_cache_set(string $key, string $slug, bool $exists): bool {
    static $cache = [];
    if (!isset($cache[$key])) $cache[$key] = [];
    $cache[$key][$slug] = $exists;
    return $exists;
}

function _slug_exists(string $key, string $slug, string $sql): bool {
    static $cache = [];
    if (isset($cache[$key][$slug])) return $cache[$key][$slug];
    try {
        $st = db()->prepare($sql);
        $st->execute([$slug]);
        $exists = (bool)$st->fetch();
    } catch (Throwable $e) {
        $exists = false;
    }
    $cache[$key][$slug] = $exists;
    return $exists;
}

function route_is_product_slug(string $slug): bool {
    return $slug !== '' && _slug_exists('product', $slug,
        'SELECT id FROM products WHERE slug=? AND is_active=1 LIMIT 1');
}

function route_is_category_slug(string $slug): bool {
    return $slug !== '' && _slug_exists('category', $slug,
        'SELECT id FROM categories WHERE slug=? LIMIT 1');
}

function route_is_blog_post_slug(string $slug): bool {
    return $slug !== '' && _slug_exists('blog_post', $slug,
        'SELECT id FROM blog_posts WHERE slug=? AND is_published=1 LIMIT 1');
}

function route_is_blog_category_slug(string $slug): bool {
    return $slug !== '' && _slug_exists('blog_category', $slug,
        'SELECT id FROM blog_categories WHERE slug=? LIMIT 1');
}

function route_is_cms_page_slug(string $slug): bool {
    return $slug !== '' && _slug_exists('cms_page', $slug,
        'SELECT id FROM pages WHERE slug=? AND is_published=1 LIMIT 1');
}
