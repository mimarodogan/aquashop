<?php
function page_find_by_slug($slug) {
    $st = db()->prepare("SELECT * FROM pages WHERE slug=? AND is_published=1");
    $st->execute(array($slug));
    return $st->fetch();
}
function page_all() {
    return db()->query("SELECT * FROM pages ORDER BY title")->fetchAll();
}
