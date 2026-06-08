<?php
/**
 * Settings sayfası 5 alt sayfaya bölündü (settings/index.php hub'ı).
 * Eski URL'lere gelenleri buraya yönlendir.
 */
require_once __DIR__ . '/core/auth.php';
header('Location: ' . SITE_URL . '/admin_panel/settings/index.php');
exit;
