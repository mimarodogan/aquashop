<?php
require_once __DIR__ . '/../../core/bootstrap.php';
$u = current_user();
if (!$u || $u['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}
$ADMIN = $u;
