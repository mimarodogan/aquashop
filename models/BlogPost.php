<?php
function blogpost_find_by_slug($slug) {
    $st = db()->prepare("SELECT p.*, c.name AS cat_name, c.slug AS cat_slug, u.name AS author_name
                         FROM blog_posts p
                         LEFT JOIN blog_categories c ON c.id=p.category_id
                         LEFT JOIN users u ON u.id=p.author_id
                         WHERE p.slug=? AND p.is_published=1");
    $st->execute(array($slug));
    return $st->fetch();
}
function blogpost_recent($limit = 4) {
    $limit = max(1, (int)$limit);
    $st = db()->prepare("SELECT p.*, c.name AS cat_name, c.slug AS cat_slug FROM blog_posts p LEFT JOIN blog_categories c ON c.id=p.category_id WHERE p.is_published=1 ORDER BY COALESCE(p.published_at, p.created_at) DESC LIMIT $limit");
    $st->execute();
    return $st->fetchAll();
}
function blogpost_search($filters = array()) {
    $where = array('p.is_published=1'); $args = array();
    if (!empty($filters['cat'])) { $where[] = 'c.slug=?'; $args[] = $filters['cat']; }
    if (!empty($filters['q']))   { $where[] = '(p.title LIKE ? OR p.excerpt LIKE ?)'; $args[] = '%'.$filters['q'].'%'; $args[] = '%'.$filters['q'].'%'; }
    $sql = "SELECT p.*, c.name AS cat_name, c.slug AS cat_slug FROM blog_posts p LEFT JOIN blog_categories c ON c.id=p.category_id WHERE ".implode(' AND ',$where)." ORDER BY COALESCE(p.published_at,p.created_at) DESC";
    $st = db()->prepare($sql); $st->execute($args);
    return $st->fetchAll();
}
function blogpost_related($postId, $categoryId, $limit = 3) {
    $limit = max(1, (int)$limit);
    $st = db()->prepare("SELECT * FROM blog_posts WHERE is_published=1 AND id<>? AND category_id<=>? ORDER BY COALESCE(published_at,created_at) DESC LIMIT $limit");
    $st->execute(array((int)$postId, $categoryId));
    return $st->fetchAll();
}
function blogpost_increment_views($id) {
    try { db()->prepare('UPDATE blog_posts SET views = views + 1 WHERE id=?')->execute(array((int)$id)); } catch (Exception $e) {}
}
