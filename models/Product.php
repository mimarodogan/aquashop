<?php
function product_find_by_slug($slug) {
    $st = db()->prepare("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.slug=? AND p.is_active=1 AND p.deleted_at IS NULL");
    $st->execute(array($slug));
    return $st->fetch();
}
function product_find($id) {
    $st = db()->prepare("SELECT * FROM products WHERE id=? AND deleted_at IS NULL");
    $st->execute(array((int)$id));
    return $st->fetch();
}
function product_active_random($limit = 3, $excludeIds = array()) {
    $limit = max(1, (int)$limit);
    if ($excludeIds) {
        $in = implode(',', array_fill(0, count($excludeIds), '?'));
        $st = db()->prepare("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 AND p.deleted_at IS NULL AND p.id NOT IN ($in) ORDER BY RAND() LIMIT $limit");
        $st->execute($excludeIds);
    } else {
        $st = db()->prepare("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 AND p.deleted_at IS NULL ORDER BY RAND() LIMIT $limit");
        $st->execute();
    }
    return $st->fetchAll();
}
function product_featured($limit = 12) {
    $limit = max(1, (int)$limit);
    $st = db()->prepare("SELECT * FROM products WHERE is_active=1 AND deleted_at IS NULL AND is_featured=1 ORDER BY created_at DESC LIMIT $limit");
    $st->execute();
    return $st->fetchAll();
}
function product_search($filters = array()) {
    $where = array('p.is_active = 1', 'p.deleted_at IS NULL'); $args = array();
    if (!empty($filters['cat'])) { $where[] = 'c.slug = ?'; $args[] = $filters['cat']; }
    if (!empty($filters['q']))   { $where[] = '(p.name LIKE ? OR p.short_desc LIKE ?)'; $args[] = '%'.$filters['q'].'%'; $args[] = '%'.$filters['q'].'%'; }
    $sort = isset($filters['sort']) ? $filters['sort'] : 'new';
    switch ($sort) {
        case 'price_asc':  $orderBy = 'p.price ASC'; break;
        case 'price_desc': $orderBy = 'p.price DESC'; break;
        case 'name':       $orderBy = 'p.name ASC'; break;
        default:           $orderBy = 'p.created_at DESC';
    }
    $sql = "SELECT p.*, c.name AS cat_name, c.slug AS cat_slug FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE ".implode(' AND ',$where)." ORDER BY $orderBy";
    $st = db()->prepare($sql); $st->execute($args);
    return $st->fetchAll();
}
function product_related($productId, $categoryId, $limit = 4) {
    $limit = max(1, (int)$limit);
    $st = db()->prepare("SELECT * FROM products WHERE is_active=1 AND deleted_at IS NULL AND id<>? AND category_id=? ORDER BY RAND() LIMIT $limit");
    $st->execute(array((int)$productId, (int)$categoryId));
    return $st->fetchAll();
}
