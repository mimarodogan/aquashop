<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../includes/schema_guard.php';
$u = current_user();
if (!$u || $u['role'] !== 'admin') {
    redirect(url('login'));
}
admin_ensure_runtime_schema();
$ADMIN = $u;
