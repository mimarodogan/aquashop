<?php
/**
 * URL yönlendirme katmanı.
 * router.php başında redirect_check() çağrılır; eşleşme varsa header gönderir + exit.
 */
require_once __DIR__ . '/functions.php';

if (!function_exists('redirect_rules_load')) {
function redirect_rules_load(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $cache = db()->query("SELECT * FROM redirects WHERE enabled=1 ORDER BY match_type='exact' DESC, id ASC")->fetchAll();
    } catch (\Throwable $e) {
        $cache = [];
    }
    return $cache;
}}

if (!function_exists('redirect_check')) {
function redirect_check(?string $path = null): void {
    $path = $path ?? ($_SERVER['REQUEST_URI'] ?? '/');
    // Query string'i ayır
    $qpos = strpos($path, '?');
    $qstr = $qpos !== false ? substr($path, $qpos) : '';
    $cleanPath = $qpos !== false ? substr($path, 0, $qpos) : $path;
    $cleanPath = '/' . ltrim($cleanPath, '/');

    foreach (redirect_rules_load() as $r) {
        $target = null;
        $src = (string)$r['source'];
        switch ($r['match_type']) {
            case 'exact':
                if ($cleanPath === $src || rtrim($cleanPath, '/') === rtrim($src, '/')) {
                    $target = $r['target'];
                }
                break;
            case 'prefix':
                $srcN = rtrim($src, '/') . '/';
                $pathN = rtrim($cleanPath, '/') . '/';
                if (strpos($pathN, $srcN) === 0) {
                    $tail = substr($cleanPath, strlen(rtrim($src,'/')));
                    $target = rtrim($r['target'], '/') . $tail;
                }
                break;
            case 'regex':
                if (@preg_match('~' . str_replace('~','\\~',$src) . '~', $cleanPath, $m)) {
                    $t = $r['target'];
                    foreach ($m as $i => $g) {
                        $t = str_replace('$' . $i, $g, $t);
                    }
                    $target = $t;
                }
                break;
        }
        if ($target !== null) {
            // Hit count async-ish (basit increment)
            try {
                db()->prepare('UPDATE redirects SET hit_count=hit_count+1, last_hit_at=NOW() WHERE id=?')->execute([(int)$r['id']]);
            } catch (\Throwable $e) {}
            // Query string'i koru (target'ta yoksa)
            if ($qstr && strpos($target, '?') === false) $target .= $qstr;
            $code = (int)$r['status_code'] ?: 301;
            header('Location: ' . $target, true, $code);
            exit;
        }
    }
}}

