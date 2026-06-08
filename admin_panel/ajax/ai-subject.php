<?php
/**
 * AI Email Subject Önerici — Anthropic Claude ile newsletter konu satırı önerir.
 * POST: csrf, body (mail HTML/text), tone (default "warm")
 * Yanıt: JSON { ok, suggestions: [string × 5] }
 */
require_once __DIR__ . '/../core/auth.php';

header('Content-Type: application/json; charset=utf-8');
function jout(array $d, int $c = 200): void { http_response_code($c); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jout(['ok'=>false,'error'=>'method'], 405);
if (!csrf_check($_POST['csrf'] ?? null)) jout(['ok'=>false,'error'=>'csrf'], 403);

$apiKey = trim((string)setting('anthropic_api_key', ''));
if ($apiKey === '') {
    jout(['ok'=>false,'error'=>'Anthropic API anahtarı tanımlı değil. Ayarlar → Entegrasyonlar bölümünden ekleyin.'], 422);
}

$body = trim((string)($_POST['body'] ?? ''));
$tone = trim((string)($_POST['tone'] ?? 'warm'));
if ($body === '') jout(['ok'=>false,'error'=>'Email içeriği boş.'], 422);

// Çok uzun body'yi kısalt (token tasarrufu)
$bodyShort = mb_substr(strip_tags($body), 0, 1500);

$toneInstructions = [
    'warm'      => 'sıcak, samimi, dostane',
    'urgent'    => 'aciliyet hissi veren, dikkat çekici (FOMO)',
    'curious'   => 'merak uyandıran, soru tarzı',
    'discount'  => 'indirim/fırsat vurgulu, sayı içeren',
    'minimal'   => 'kısa, sade, doğrudan',
];
$toneText = $toneInstructions[$tone] ?? $toneInstructions['warm'];

$siteName = setting('site_name', 'Mağaza');

$prompt = <<<TXT
Sen Türkçe email pazarlama uzmanısın. Aşağıdaki e-bülten içeriği için 5 farklı konu satırı (subject line) öner.

ZORLU KURALLAR:
- Hepsi Türkçe
- Her biri maksimum 60 karakter (mobil görünüm için)
- Stil: $toneText
- Emoji kullan ama abartma (1 tane yeterli)
- Spam tetikleyici kelime KULLANMA ("BEDAVA", "TIKLA ŞİMDİ", "KAÇIRMA" gibi)
- Her satır farklı bir açıdan yaklaşsın
- Sadece konu satırlarını yaz, açıklama yok, numara veya bullet yok, her satıra 1 öneri

MAĞAZA: $siteName

EMAIL İÇERİĞİ:
$bodyShort

5 KONU SATIRI ÖNERİSİ:
TXT;

// Anthropic API çağrısı
$payload = [
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 500,
    'messages'   => [['role' => 'user', 'content' => $prompt]],
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 5,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr = curl_error($ch);
curl_close($ch);

if ($cerr) jout(['ok'=>false,'error'=>'API bağlantı hatası: ' . $cerr], 502);
if ($code !== 200) {
    $errInfo = json_decode((string)$resp, true);
    $msg = $errInfo['error']['message'] ?? ('HTTP ' . $code);
    jout(['ok'=>false,'error'=>'Claude API hatası: ' . $msg], $code);
}

$data = json_decode((string)$resp, true);
$text = $data['content'][0]['text'] ?? '';
if ($text === '') jout(['ok'=>false,'error'=>'Boş yanıt'], 500);

// Satırlara böl, temizle
$lines = preg_split('/\r?\n/', trim($text));
$suggestions = [];
foreach ($lines as $line) {
    $line = trim($line);
    // Başındaki numara/madde işaretlerini temizle
    $line = preg_replace('/^[\d\-•\*\.\)]+\s*/u', '', $line);
    $line = trim($line);
    if ($line !== '' && mb_strlen($line) <= 120) {
        $suggestions[] = $line;
    }
}
$suggestions = array_slice($suggestions, 0, 5);

if (!$suggestions) jout(['ok'=>false,'error'=>'Yanıt parse edilemedi.'], 500);

jout(['ok'=>true, 'suggestions'=>$suggestions]);
