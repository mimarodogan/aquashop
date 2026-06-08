<?php
require_once __DIR__ . '/../core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? null)) {
    redirect(SITE_URL . '/cart.php');
}
$action = $_POST['action'] ?? '';
$pid    = (int)($_POST['id'] ?? 0);
switch ($action) {
    case 'add':    cart_add($pid, max(1, (int)($_POST['qty'] ?? 1))); cart_persist(); break;
    case 'update': cart_update($pid, max(0, (int)($_POST['qty'] ?? 0))); cart_persist(); break;
    case 'remove': cart_remove($pid); cart_persist(); break;
    case 'clear':  cart_clear(); cart_abandon_clear(); break;
}
redirect(SITE_URL . '/cart.php');
