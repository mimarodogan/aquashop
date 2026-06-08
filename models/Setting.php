<?php
function setting($key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = array();
        try {
            foreach (db()->query('SELECT setting_key, setting_value FROM settings') as $r) {
                $cache[$r['setting_key']] = $r['setting_value'];
            }
        } catch (Exception $e) {}
    }
    return isset($cache[$key]) ? $cache[$key] : $default;
}
