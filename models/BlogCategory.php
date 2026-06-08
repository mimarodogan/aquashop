<?php
function blogcat_all_with_counts() {
    return db()->query("SELECT c.*, (SELECT COUNT(*) FROM blog_posts WHERE category_id=c.id AND is_published=1) AS cnt FROM blog_categories c ORDER BY name")->fetchAll();
}
function blogcat_find_by_slug($slug) {
    $st = db()->prepare("SELECT * FROM blog_categories WHERE slug=?");
    $st->execute(array($slug));
    return $st->fetch();
}
