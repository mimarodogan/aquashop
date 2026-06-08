<?php
function current_user() {
    if (!empty($_SESSION['user_id'])) {
        static $user = null;
        if ($user === null) {
            $st = db()->prepare('SELECT * FROM users WHERE id = ?');
            $st->execute(array($_SESSION['user_id']));
            $row = $st->fetch();
            $user = $row ? $row : null;
        }
        return $user;
    }
    return null;
}
function require_admin() {
    $u = current_user();
    // D-1: doğru pretty URL'e yönlendir (eski /login.php artık 404 olabilir)
    if (!$u || $u['role'] !== 'admin') redirect(url('login'));
}
