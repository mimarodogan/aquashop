<?php
function banner_active() {
    return db()->query("SELECT * FROM banners WHERE is_active=1 ORDER BY sort_order ASC, id DESC")->fetchAll();
}
