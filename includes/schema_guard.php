<?php
/**
 * Runtime schema guard helpers.
 * Admin pages may be opened on databases that have not yet run every migration.
 * These helpers add backward-compatible columns/indexes without destructive changes.
 */

if (!function_exists('schema_column_exists')) {
function schema_column_exists(string $table, string $column): bool {
    $st = db()->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$table, $column]);
    return (int)$st->fetchColumn() > 0;
}}

if (!function_exists('schema_index_exists')) {
function schema_index_exists(string $table, string $index): bool {
    $st = db()->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $st->execute([$table, $index]);
    return (int)$st->fetchColumn() > 0;
}}

if (!function_exists('schema_add_column')) {
function schema_add_column(string $table, string $column, string $definition): void {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) return;
    if (schema_column_exists($table, $column)) return;
    db()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
}}

if (!function_exists('schema_add_index')) {
function schema_add_index(string $table, string $index, string $columns): void {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $index)) return;
    if (schema_index_exists($table, $index)) return;
    db()->exec("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$columns})");
}}

if (!function_exists('admin_ensure_runtime_schema')) {
function admin_ensure_runtime_schema(): void {
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        schema_add_column('products', 'sku', 'VARCHAR(80) NULL DEFAULT NULL');
        schema_add_column('products', 'brand', 'VARCHAR(120) NULL DEFAULT NULL');
        schema_add_column('products', 'has_variations', 'TINYINT(1) NOT NULL DEFAULT 0');
        schema_add_column('products', 'deleted_at', 'DATETIME NULL DEFAULT NULL');
        schema_add_index('products', 'idx_deleted_at', '`deleted_at`');
    } catch (\Throwable $e) {
        error_log('[schema_guard] products: ' . $e->getMessage());
    }

    try {
        schema_add_column('blog_posts', 'blog_author_id', 'INT NULL DEFAULT NULL');
    } catch (\Throwable $e) {
        error_log('[schema_guard] blog_posts: ' . $e->getMessage());
    }

    try {
        schema_add_column('pages', 'cover_image', 'VARCHAR(255) NULL DEFAULT NULL');
    } catch (\Throwable $e) {
        error_log('[schema_guard] pages: ' . $e->getMessage());
    }

    try {
        schema_add_column('orders', 'source', "ENUM('web','pos') NOT NULL DEFAULT 'web'");
        schema_add_column('orders', 'payment_status', "ENUM('pending','paid','failed','refunded','partial_refund') NOT NULL DEFAULT 'pending'");
        schema_add_column('orders', 'paid_at', 'DATETIME NULL DEFAULT NULL');
        schema_add_column('orders', 'shipping_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
        schema_add_column('orders', 'subtotal', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
        schema_add_column('orders', 'vat_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
        schema_add_column('order_items', 'stock_applied_at', 'DATETIME NULL DEFAULT NULL');
    } catch (\Throwable $e) {
        error_log('[schema_guard] orders: ' . $e->getMessage());
    }
}}
