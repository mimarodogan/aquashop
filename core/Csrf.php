<?php
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16));
    }
    return $_SESSION['csrf'];
}
function csrf_check($t) {
    return is_string($t) && hash_equals(isset($_SESSION['csrf']) ? $_SESSION['csrf'] : '', $t);
}

/**
 * Token'ı yeni bir değerle değiştir. (Y-4)
 * Kritik aksiyonlardan SONRA çağırın: login, register, logout, şifre değişikliği, ödeme.
 * Token sızıntısı/XSS pencere süresini azaltır.
 */
function csrf_rotate() {
    $_SESSION['csrf'] = bin2hex(function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16));
    return $_SESSION['csrf'];
}
