<?php
/**
 * AI Ürün İçeriği — AJAX endpoint
 * POST: csrf, name, brand, category, sku
 * Response: JSON { ok, short_desc, description, faqs } | { error }
 */

// Bootstrap hata handler'ı AJAX için JSON döndürecek şekilde override et
// (bootstrap'ı dahil etmeden önce tanımlayabiliriz ama bootstrap override eder,
//  bu yüzden bootstrap sonrası tekrar set ediyoruz)
require_once __DIR__ . '/../../core/bootstrap.php';

// Bootstrap'ın HTML hata handler'ını AJAX için JSON ile ezip geçiyoruz
set_exception_handler(function (\Throwable $e): void {
    error_log('[AI-AJAX ERROR] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'Sunucu hatası: ' . $e->getMessage()]);
    exit;
});

header('Content-Type: application/json; charset=utf-8');

// ── Yetki kontrolü (current_user + admin rolü) ────────────────────────────
$u = current_user();
if (!$u || $u['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Yetkisiz erişim. Lütfen tekrar giriş yapın.']);
    exit;
}

// ── CSRF ────────────────────────────────────────────────────────────────────
if (!csrf_check($_POST['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['error' => 'Geçersiz istek (CSRF).']);
    exit;
}

// ── API anahtarı ─────────────────────────────────────────────────────────────
$apiKey = trim(setting('anthropic_api_key', ''));
if (!$apiKey) {
    echo json_encode(['error' => 'Anthropic API anahtarı ayarlanmamış. Ayarlar → Yapay Zeka bölümünden ekleyin.']);
    exit;
}

// ── Giriş parametreleri ───────────────────────────────────────────────────────
$name     = trim($_POST['name']     ?? '');
$brand    = trim($_POST['brand']    ?? '');
$category = trim($_POST['category'] ?? '');
$sku      = trim($_POST['sku']      ?? '');

if (!$name) {
    echo json_encode(['error' => 'Ürün adı zorunludur.']);
    exit;
}

// ── Prompt ───────────────────────────────────────────────────────────────────
$productInfo = "Ürün Adı: $name";
if ($brand)    $productInfo .= "\nMarka: $brand";
if ($category) $productInfo .= "\nKategori: $category";
if ($sku)      $productInfo .= "\nSKU: $sku";

$systemPrompt = 'Sen 20 yıllık deneyimli bir akvaryum uzmanı ve içerik yazarısın. '
    . 'Akvaryum ürünleri için Türkçe, SEO dostu, bilgilendirici ve doğal dil kullanan içerikler yazıyorsun. '
    . 'Teknik bilgiyi sade ve anlaşılır anlat. Gerçekçi ol, abartma. '
    . 'Detaylı açıklamada SEO için H2/H3 başlıklar kullan: ## H2 Başlık, ### H3 Başlık. '
    . 'Paragraflar arasında boş satır bırak. **kalın** ve *italik* kullanabilirsin. HTML tag yazma, sadece Markdown.';

$userPrompt = "Aşağıdaki akvaryum ürünü için içerik üret:\n\n$productInfo\n\n"
    . "Tam olarak şu JSON formatında yanıt ver (başka hiçbir şey ekleme, ```json``` sarmalama yapma):\n"
    . '{"short_desc":"1-2 cümle, max 160 karakter, HTML/Markdown YOK, düz metin SEO meta açıklaması",'
    . '"description":"Markdown formatında detaylı açıklama. ## ile H2, ### ile H3 başlık kullan. En az 2 H2 başlık olsun. Paragraflar arası boş satır. **kalın** kullanabilirsin.",'
    . '"faqs":[{"q":"Soru 1?","a":"Cevap 1"},{"q":"Soru 2?","a":"Cevap 2"},{"q":"Soru 3?","a":"Cevap 3"},'
    . '{"q":"Soru 4?","a":"Cevap 4"},{"q":"Soru 5?","a":"Cevap 5"},{"q":"Soru 6?","a":"Cevap 6"}]}';

// ── Claude API çağrısı ────────────────────────────────────────────────────────
$payload = json_encode([
    'model'      => 'claude-sonnet-4-5',
    'max_tokens' => 2048,
    'system'     => $systemPrompt,
    'messages'   => [['role' => 'user', 'content' => $userPrompt]],
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ],
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['error' => 'Bağlantı hatası: ' . $curlError]);
    exit;
}
if ($httpCode !== 200) {
    $errData = json_decode($response, true);
    $msg = $errData['error']['message'] ?? ('API hatası: HTTP ' . $httpCode);
    echo json_encode(['error' => $msg]);
    exit;
}

// ── Yanıtı parse et ──────────────────────────────────────────────────────────
$apiData = json_decode($response, true);
$text    = $apiData['content'][0]['text'] ?? '';

// Claude bazen ```json ... ``` ile döndürür, temizle
$text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
$text = preg_replace('/\s*```$/', '', $text);
$text = trim($text);

// JSON parse
$result = json_decode($text, true);

if (!$result || empty($result['short_desc'])) {
    echo json_encode([
        'error' => 'AI yanıtı beklenmedik formatta geldi. Tekrar deneyin.',
        'raw'   => mb_substr($text, 0, 300),
    ]);
    exit;
}

echo json_encode([
    'ok'          => true,
    'short_desc'  => trim($result['short_desc']  ?? ''),
    'description' => trim($result['description'] ?? ''),
    'faqs'        => array_values(array_filter(
        $result['faqs'] ?? [],
        fn($f) => !empty($f['q']) && !empty($f['a'])
    )),
]);
