<?php
/**
 * Settings alt sayfaları için paylaşılan POST handler.
 *
 * Kullanım (her alt sayfa kendi alan listesini tanımlar):
 *   settings_save_fields(
 *     textKeys:     ['site_name','site_tagline', ...],
 *     checkboxKeys: ['topbar_enabled', ...],
 *     passwordKeys: ['smtp_pass'],   // boş bırakılırsa mevcut korunur
 *     redirectTo:   'identity.php'
 *   );
 *
 * Helper'ı include eden sayfa zaten auth + bootstrap yüklemiş olmalı.
 */

if (!function_exists('settings_save_fields')) {
    function settings_save_fields(array $textKeys, array $checkboxKeys = [], array $passwordKeys = [], string $redirectTo = ''): bool {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return false;
        if (!csrf_check($_POST['csrf'] ?? null)) {
            flash_set('err', 'Geçersiz form (CSRF).');
            return false;
        }

        $st = db()->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );

        foreach ($textKeys as $k) {
            $st->execute([$k, trim((string)($_POST[$k] ?? ''))]);
        }
        foreach ($checkboxKeys as $k) {
            $st->execute([$k, !empty($_POST[$k]) ? '1' : '0']);
        }
        foreach ($passwordKeys as $k) {
            // Boş geldiyse mevcut değeri koru (yanlışlıkla silmesin)
            if (isset($_POST[$k]) && $_POST[$k] !== '') {
                $st->execute([$k, $_POST[$k]]);
            }
        }

        flash_set('ok', 'Ayarlar kaydedildi.');
        if ($redirectTo !== '') {
            redirect($redirectTo);
        }
        return true;
    }
}

if (!function_exists('settings_sub_header')) {
    /**
     * Settings alt sayfaları için tutarlı başlık (breadcrumb + başlık + açıklama).
     */
    function settings_sub_header(string $title, string $desc = ''): void {
        echo '<div class="settings-subhead">';
        echo   '<div class="ss-meta">';
        echo     '<div class="ss-bc"><a href="index.php">← Tüm Ayarlar</a></div>';
        echo     '<h2>' . e($title) . '</h2>';
        if ($desc !== '') echo '<p class="ss-desc">' . e($desc) . '</p>';
        echo   '</div>';
        echo '</div>';
    }
}

if (!function_exists('settings_count_filled')) {
    /**
     * Bir grup setting key'inden kaç tanesinin dolu olduğunu sayar.
     * Hub sayfasındaki "12/14 ayar dolu" rozetinin hesabı için.
     */
    function settings_count_filled(array $keys): array {
        $filled = 0;
        foreach ($keys as $k) {
            $v = trim((string)setting($k, ''));
            if ($v !== '' && $v !== '0') $filled++;
        }
        return ['filled' => $filled, 'total' => count($keys)];
    }
}
