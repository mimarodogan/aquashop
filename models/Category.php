<?php
function category_all() {
    return db()->query("SELECT * FROM categories ORDER BY sort_order ASC, name ASC")->fetchAll();
}
function category_find_by_slug($slug) {
    $st = db()->prepare("SELECT * FROM categories WHERE slug=?");
    $st->execute(array($slug));
    return $st->fetch();
}
