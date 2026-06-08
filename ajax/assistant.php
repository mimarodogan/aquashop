<?php
/**
 * AI Danışman — AJAX endpoint.
 * POST: csrf, message, history(JSON)
 * Yanıt: { ok, reply, products[], model } | { error, handoff? }
 *
 * Güvenlik: CSRF + hız sınırı (oturum/IP) + girdi sınırlama.
 * Loglama: KVKK uyumlu (ham IP yok, ip_hash). Log/rate-limit tablosu yoksa
 * sohbet yine çalışır (fail-open) — yalnızca sınır uygulanmaz.
 */
require_once __DIR__ . '/../core/bootstrap.php';

// AJAX için JSON hata yakalayıcı
set_exception_handler(function (\Throwable $e): void {
    error_log('[assistant-ajax] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'Beklenmeyen bir hata oluştu.'], JSON_UNESCAPED_UNICODE);
    exit;
});

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

require_once __DIR__ . '/../core/AIAssistant.php';

// ── Açık mı? ────────────────────────────────────────────────────────────────
if (!ai_assistant_enabled()) {
    http_response_code(403);
    echo json_encode(['error' => 'Danışman şu anda kapalı.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Sadece POST ───────────────────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Yöntem desteklenmiyor.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
if (!csrf_check($_POST['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['error' => 'Oturum doğrulaması başarısız. Sayfayı yenileyin.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Girdi ───────────────────────────────────────────────────────────────────
$message = trim((string)($_POST['message'] ?? ''));
$message = mb_substr($message, 0, 1000, 'UTF-8');
if ($message === '') {
    echo json_encode(['error' => 'Mesaj boş olamaz.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Sohbet geçmişi (client'tan JSON) — son 8 turla sınırla, her içerik kısaltılır
$history = [];
$rawHist = $_POST['history'] ?? '';
if (is_string($rawHist) && $rawHist !== '') {
    $decoded = json_decode($rawHist, true);
    if (is_array($decoded)) {
        foreach (array_slice($decoded, -8) as $h) {
            if (!is_array($h)) continue;
            $role = ($h['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $cont = mb_substr(trim((string)($h['content'] ?? '')), 0, 2000, 'UTF-8');
            if ($cont !== '') $history[] = ['role' => $role, 'content' => $cont];
        }
    }
}

// ── Kimlik / pseudonim ────────────────────────────────────────────────────────
$u         = function_exists('current_user') ? current_user() : null;
$userId    = $u['id'] ?? null;
$sessionId = substr((string)session_id(), 0, 64);
$ip        = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$ipHash    = hash('sha256', $ip . '|aqua-ai-pepper-2026'); // ham IP saklanmaz

// ── Hız sınırı (fail-open: tablo yoksa geç) ───────────────────────────────────
$RATE_MAX = 15; $RATE_WINDOW_MIN = 5;
try {
    $rl = db()->prepare(
        "SELECT COUNT(*) FROM ai_chat_log
         WHERE role='user' AND created_at > (NOW() - INTERVAL {$RATE_WINDOW_MIN} MINUTE)
           AND (session_id = ? OR ip_hash = ?)"
    );
    $rl->execute([$sessionId, $ipHash]);
    if ((int)$rl->fetchColumn() >= $RATE_MAX) {
        $waUrl = ai_assistant_whatsapp_url();
        echo json_encode([
            'error'   => 'Çok fazla mesaj gönderdiniz, lütfen birkaç dakika sonra tekrar deneyin.',
            'handoff' => $waUrl ?: null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (\Throwable $e) {
    // tablo henüz yok → sınır uygulanmaz (sohbet çalışmaya devam eder)
}

// ── Kullanıcı mesajını logla ──────────────────────────────────────────────────
ai_assistant_log($sessionId, $userId, 'user', $message, null, $ipHash);

// ── Claude ile sohbet ─────────────────────────────────────────────────────────
$res = ai_assistant_chat($history, $message);

if (empty($res['ok'])) {
    $waUrl = ai_assistant_whatsapp_url();
    echo json_encode([
        'error'   => $res['error'] ?: 'Yanıt alınamadı.',
        'handoff' => $waUrl ?: null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Asistan cevabını logla ──────────────────────────────────────────────────
ai_assistant_log($sessionId, $userId, 'assistant', $res['reply'], $res['model'] ?? null, $ipHash);

echo json_encode([
    'ok'       => true,
    'reply'    => $res['reply'],
    'products' => $res['products'],
    'articles' => $res['articles'] ?? [],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
