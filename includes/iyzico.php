<?php
/**
 * iyzico ödeme yardımcıları.
 * Ayar değerleri admin panel → Ayarlar → Ödeme'den okunur.
 */

require_once __DIR__ . '/functions.php';

if (!function_exists('iyzico_options')) {
function iyzico_options(): array {
    $env = setting('iyz_env', 'sandbox') === 'live' ? 'live' : 'sandbox';
    $base = $env === 'live'
        ? 'https://api.iyzipay.com'
        : 'https://sandbox-api.iyzipay.com';
    return [
        'env'        => $env,
        'apiKey'     => trim((string)setting('iyz_api_key', '')),
        'secretKey'  => trim((string)setting('iyz_secret_key', '')),
        'baseUrl'    => $base,
        'maxInstallment' => max(1, min(12, (int)setting('iyz_max_installment', '6'))),
        'enabled'    => setting('iyz_enabled', '0') === '1',
    ];
}}

if (!function_exists('iyzico_sdk_loaded')) {
function iyzico_sdk_loaded(): bool {
    static $loaded = null;
    if ($loaded !== null) return $loaded;
    $bootstrap = __DIR__ . '/../vendor/iyzipay-php/IyzipayBootstrap.php';
    if (!file_exists($bootstrap)) return $loaded = false;
    require_once $bootstrap;
    \IyzipayBootstrap::init();
    $loaded = class_exists('\\Iyzipay\\Options');
    return $loaded;
}}

if (!function_exists('iyzico_options_obj')) {
function iyzico_options_obj() {
    if (!iyzico_sdk_loaded()) return null;
    $cfg = iyzico_options();
    if ($cfg['apiKey'] === '' || $cfg['secretKey'] === '') return null;
    $opts = new \Iyzipay\Options();
    $opts->setApiKey($cfg['apiKey']);
    $opts->setSecretKey($cfg['secretKey']);
    $opts->setBaseUrl($cfg['baseUrl']);
    return $opts;
}}

/**
 * Sepet ve müşteri bilgisinden Checkout Form ödeme isteği oluşturur.
 * Başarılıysa ['ok'=>true,'token'=>..,'paymentPageUrl'=>..,'checkoutFormContent'=>..] döner.
 */
if (!function_exists('iyzico_init_checkout')) {
function iyzico_init_checkout(int $orderId, array $order, array $items, array $billing): array {
    $opts = iyzico_options_obj();
    if (!$opts) return ['ok'=>false,'error'=>'iyzico SDK veya anahtarlar yapılandırılmamış.'];

    $cfg = iyzico_options();
    $conversationId = 'O-' . $orderId . '-' . bin2hex(random_bytes(4));

    $req = new \Iyzipay\Request\CreateCheckoutFormInitializeRequest();
    $req->setLocale(\Iyzipay\Model\Locale::TR);
    $req->setConversationId($conversationId);
    $req->setPrice(number_format((float)$order['total'], 2, '.', ''));
    $req->setPaidPrice(number_format((float)$order['total'], 2, '.', ''));
    $req->setCurrency(\Iyzipay\Model\Currency::TL);
    $req->setBasketId('B-' . $orderId);
    $req->setPaymentGroup(\Iyzipay\Model\PaymentGroup::PRODUCT);
    // iyzico MUTLAK (absolute) callback URL ister ve site ÇOKLU DOMAIN'de çalışır.
    // Bu yüzden callback HER ZAMAN müşterinin o an bulunduğu domaine kurulur (HTTP_HOST):
    // A domaininden ödeyen A'ya, B domaininden ödeyen B'ye geri döner. İyzico tarafında
    // domain başına HİÇBİR manuel ayar gerekmez — API anahtarları tek hesap için ortaktır,
    // callback ise her istekte dinamik gönderilir. (SITE_URL yalnızca CLI/host yoksa yedek.)
    $cbScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $cbHost   = $_SERVER['HTTP_HOST'] ?? '';
    $cbBase   = $cbHost !== '' ? ($cbScheme . '://' . $cbHost) : rtrim(SITE_URL, '/');
    $req->setCallbackUrl($cbBase . '/odeme-donus');
    $req->setEnabledInstallments(range(2, $cfg['maxInstallment'])); // 1 her zaman, 2..N

    // Alıcı
    $names = preg_split('/\s+/', trim($billing['name'] ?? 'Müşteri'), 2);
    $first = $names[0] ?? 'Misafir';
    $last  = $names[1] ?? 'Müşteri';

    $buyer = new \Iyzipay\Model\Buyer();
    $buyer->setId('U-' . ($billing['user_id'] ?? ('g' . $orderId)));
    $buyer->setName($first);
    $buyer->setSurname($last);
    $buyer->setGsmNumber(preg_replace('/[^\d+]/','', $billing['phone'] ?? ''));
    $buyer->setEmail($billing['email'] ?? 'no-reply@example.com');
    // iyzico identityNumber'ı ZORUNLU sayar. TC opsiyonel olduğundan, boş/geçersiz (11 hane değil)
    // ise geçerli formatta placeholder gönderilir — aksi halde "identityNumber gönderilmesi zorunludur" hatası.
    $identity = preg_replace('/\D+/', '', (string)($billing['identity'] ?? ''));
    if (strlen($identity) !== 11) $identity = '11111111111';
    $buyer->setIdentityNumber($identity);
    $buyer->setRegistrationAddress(($billing['address'] ?? '') . ' ' . ($billing['city'] ?? ''));
    $buyer->setIp($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $buyer->setCity($billing['city'] ?? 'İstanbul');
    $buyer->setCountry('Türkiye');
    $buyer->setZipCode($billing['zip'] ?? '34000');
    $req->setBuyer($buyer);

    $addr = new \Iyzipay\Model\Address();
    $addr->setContactName($billing['name'] ?? 'Müşteri');
    $addr->setCity($billing['city'] ?? 'İstanbul');
    $addr->setCountry('Türkiye');
    $addr->setAddress($billing['address'] ?? '');
    $addr->setZipCode($billing['zip'] ?? '34000');
    $req->setShippingAddress($addr);
    $req->setBillingAddress($addr);

    // Sepet kalemleri
    $bi = [];
    foreach ($items as $it) {
        $bk = new \Iyzipay\Model\BasketItem();
        $bk->setId('P-' . (int)$it['id']);
        $bk->setName(mb_substr((string)$it['name'], 0, 60));
        $bk->setCategory1('Genel');
        $bk->setItemType(\Iyzipay\Model\BasketItemType::PHYSICAL);
        $bk->setPrice(number_format((float)$it['price'] * (int)$it['qty'], 2, '.', ''));
        $bi[] = $bk;
    }
    $req->setBasketItems($bi);

    try {
        $res = \Iyzipay\Model\CheckoutFormInitialize::create($req, $opts);
        $raw = $res->getRawResult();
        if ($res->getStatus() === 'success') {
            // payments tablosuna kayıt
            db()->prepare('INSERT INTO payments (order_id,provider,conversation_id,token,amount,status,raw_response) VALUES (?,?,?,?,?,?,?)')
                ->execute([$orderId, 'iyzico', $conversationId, $res->getToken(), $order['total'], 'initialized', $raw]);
            db()->prepare('UPDATE orders SET iyzico_token=?, iyzico_conversation_id=? WHERE id=?')
                ->execute([$res->getToken(), $conversationId, $orderId]);
            return [
                'ok'=>true,
                'token'=>$res->getToken(),
                'paymentPageUrl'=>$res->getPaymentPageUrl(),
                'checkoutFormContent'=>$res->getCheckoutFormContent(),
                'conversationId'=>$conversationId,
            ];
        }
        return ['ok'=>false,'error'=>$res->getErrorMessage() ?: 'iyzico başlatılamadı.','raw'=>$raw];
    } catch (\Throwable $e) {
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}}

/**
 * Callback'te token ile ödeme sonucunu sorgular.
 */
if (!function_exists('iyzico_retrieve')) {
function iyzico_retrieve(string $token): array {
    $opts = iyzico_options_obj();
    if (!$opts) return ['ok'=>false,'error'=>'iyzico yapılandırılmamış'];
    $req = new \Iyzipay\Request\RetrieveCheckoutFormRequest();
    $req->setLocale(\Iyzipay\Model\Locale::TR);
    $req->setToken($token);
    try {
        $res = \Iyzipay\Model\CheckoutForm::retrieve($req, $opts);
        return ['ok'=>true,'res'=>$res,'raw'=>$res->getRawResult()];
    } catch (\Throwable $e) {
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}}

/**
 * Ödeme işlem ID'si üzerinden iade.
 */
if (!function_exists('iyzico_refund')) {
function iyzico_refund(string $paymentTransactionId, float $amount, string $ip = ''): array {
    $opts = iyzico_options_obj();
    if (!$opts) return ['ok'=>false,'error'=>'iyzico yapılandırılmamış'];
    $req = new \Iyzipay\Request\CreateRefundRequest();
    $req->setLocale(\Iyzipay\Model\Locale::TR);
    $req->setConversationId('R-' . bin2hex(random_bytes(4)));
    $req->setPaymentTransactionId($paymentTransactionId);
    $req->setPrice(number_format($amount, 2, '.', ''));
    $req->setIp($ip ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
    $req->setCurrency(\Iyzipay\Model\Currency::TL);
    try {
        $res = \Iyzipay\Model\Refund::create($req, $opts);
        $ok = $res->getStatus() === 'success';
        return ['ok'=>$ok,'res'=>$res,'raw'=>$res->getRawResult(),'error'=>$ok?null:$res->getErrorMessage()];
    } catch (\Throwable $e) {
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}}
