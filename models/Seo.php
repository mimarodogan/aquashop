<?php
/**
 * SEO ayarları erişim katmanı.
 * Tek bir prepared statement ile sayfa SEO verilerini çeker.
 */
function seo_get($slug) {
    static $cache = array();
    $slug = (string)$slug;
    if ($slug === '') return null;
    if (array_key_exists($slug, $cache)) return $cache[$slug];
    try {
        $st = db()->prepare("SELECT page_slug, page_label, meta_title, meta_description, meta_keywords, meta_robots, og_image FROM seo_settings WHERE page_slug = ? LIMIT 1");
        $st->execute(array($slug));
        $row = $st->fetch();
        $cache[$slug] = $row ? $row : null;
    } catch (Exception $e) {
        $cache[$slug] = null;
    }
    return $cache[$slug];
}

function seo_all() {
    try {
        return db()->query("SELECT * FROM seo_settings ORDER BY page_label, page_slug")->fetchAll();
    } catch (Exception $e) {
        return array();
    }
}

function seo_save($data) {
    $st = db()->prepare("INSERT INTO seo_settings (page_slug, page_label, meta_title, meta_description, meta_keywords, meta_robots, og_image)
                         VALUES (?,?,?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE
                           page_label=VALUES(page_label),
                           meta_title=VALUES(meta_title),
                           meta_description=VALUES(meta_description),
                           meta_keywords=VALUES(meta_keywords),
                           meta_robots=VALUES(meta_robots),
                           og_image=VALUES(og_image)");
    $st->execute(array(
        trim($data['page_slug']),
        !empty($data['page_label']) ? trim($data['page_label']) : null,
        !empty($data['meta_title']) ? trim($data['meta_title']) : null,
        !empty($data['meta_description']) ? trim($data['meta_description']) : null,
        !empty($data['meta_keywords']) ? trim($data['meta_keywords']) : null,
        !empty($data['meta_robots']) ? trim($data['meta_robots']) : null,
        !empty($data['og_image']) ? trim($data['og_image']) : null,
    ));
}

function seo_delete($slug) {
    db()->prepare("DELETE FROM seo_settings WHERE page_slug = ?")->execute(array($slug));
}
