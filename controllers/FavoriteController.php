<?php
require_once __DIR__ . '/../core/bootstrap.php';

if (!current_user()) {
    flash_set('err','Favorilere eklemek için giriş yapmalısınız.');
    redirect(SITE_URL . '/login.php');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? null)) {
    redirect(SITE_URL . '/index.php');
}
$pid = (int)($_POST['id'] ?? 0);
if ($pid > 0) fav_toggle($pid);

// Y-10 GÜVENLİK: open redirect — same-host whitelist
$back = safe_back_url($_POST['back'] ?? '', url('products'));
redirect($back);
