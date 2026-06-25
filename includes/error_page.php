<?php
/**
 * Temaya uygun HTTP hata sayfaları (404 / 403 / 401 / 500 / 503).
 *
 * BAĞIMSIZ: inline CSS, dış bağımlılık yok (DB/asset/CSS bozuk olsa bile render olur).
 * Aqua tasarım diliyle uyumlu (beyaz kart, aqua vurgu, Inter).
 *
 * Kullanım:
 *   require_once .../includes/error_page.php;
 *   aq_render_error(404);              // sayfayı basar + exit
 *   aq_render_error(500, $reqId);      // hata referansı ile
 *   aq_render_error(500, $reqId, $devDetail);  // sadece development'ta görünür
 */
if (!function_exists('aq_render_error')) {
function aq_render_error(int $code = 500, ?string $reqId = null, ?string $devDetail = null): void
{
    $map = [
        400 => ['Geçersiz İstek', 'İsteğiniz işlenemedi. Lütfen tekrar deneyin.', '⚠️', '#e0a800'],
        401 => ['Yetkisiz Erişim', 'Bu sayfayı görüntülemek için giriş yapmanız gerekiyor.', '🔐', '#0798bd'],
        403 => ['Erişim Engellendi', 'Bu sayfayı görüntüleme yetkiniz bulunmuyor.', '⛔', '#e0a800'],
        404 => ['Sayfa Bulunamadı', 'Aradığınız sayfa taşınmış, adı değişmiş veya hiç var olmamış olabilir.', '🔍', '#0798bd'],
        500 => ['Sunucu Hatası', 'Beklenmedik bir hata oluştu. Ekibimize iletildi; en kısa sürede çözeceğiz.', '⚙️', '#e30613'],
        503 => ['Geçici Olarak Hizmet Dışı', 'Sitemiz bakımda veya yoğun. Lütfen birkaç dakika sonra tekrar deneyin.', '🛠️', '#0798bd'],
    ];
    [$title, $msg, $icon, $accent] = $map[$code] ?? $map[500];

    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: text/html; charset=UTF-8');
        // 503'te arama motorlarına "sonra tekrar gel" sinyali
        if ($code === 503) { header('Retry-After: 600'); }
    }

    $esc  = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
    $isDev = defined('APP_ENV') && APP_ENV === 'development';
    ?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= (int)$code ?> · <?= $esc($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800;900&display=swap">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Inter',system-ui,-apple-system,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;
    background:radial-gradient(circle at 50% -10%, rgba(7,152,189,.12), transparent 55%), linear-gradient(180deg,#f7fbfc 0%, #eaf1f5 100%);color:#26313d}
  .wrap{width:100%;max-width:560px;text-align:center}
  .card{background:#fff;border:1px solid #e5edf3;border-radius:28px;padding:46px 38px;box-shadow:0 24px 60px rgba(15,23,42,.08)}
  .ic{width:92px;height:92px;margin:0 auto;border-radius:26px;display:flex;align-items:center;justify-content:center;font-size:40px;
    background:<?= $accent ?>14;border:1px solid <?= $accent ?>26}
  .code{font-size:78px;font-weight:900;line-height:1;letter-spacing:-3px;color:<?= $accent ?>;margin:18px 0 2px}
  h1{font-size:23px;font-weight:800;color:#111827;letter-spacing:-.5px;margin:10px 0 10px}
  p{font-size:14px;line-height:1.7;color:#667386;font-weight:500;max-width:430px;margin:0 auto 26px}
  .btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
  a.b{min-height:46px;padding:0 22px;border-radius:999px;display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:800;text-decoration:none;transition:all .18s ease}
  a.primary{background:#0798bd;color:#fff;box-shadow:0 12px 24px rgba(7,152,189,.22)}
  a.primary:hover{background:#056f8d;transform:translateY(-1px)}
  a.ghost{background:#fff;border:1px solid #dfe8ef;color:#17202b}
  a.ghost:hover{border-color:#0798bd;color:#0798bd}
  .rid{margin-top:24px;font-size:11px;color:#9aa5b2;font-weight:700}
  .rid code{background:#f3f7fa;padding:3px 8px;border-radius:6px;font-family:ui-monospace,'SF Mono',monospace;color:#56657a}
  .dev{margin-top:18px;text-align:left;background:#fff5f5;border:1px solid #f3c7c7;border-radius:12px;padding:12px 14px;font-size:12px;color:#a12b2b;font-family:ui-monospace,monospace;line-height:1.6;word-break:break-word}
</style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="ic"><?= $icon ?></div>
      <div class="code"><?= (int)$code ?></div>
      <h1><?= $esc($title) ?></h1>
      <p><?= $esc($msg) ?></p>
      <div class="btns">
        <a class="b primary" href="<?= $esc($base) ?>/">Ana Sayfaya Dön</a>
        <?php if ($code === 401): ?>
          <a class="b ghost" href="<?= $esc($base) ?>/giris">Giriş Yap</a>
        <?php else: ?>
          <a class="b ghost" href="<?= $esc($base) ?>/iletisim">İletişime Geç</a>
        <?php endif; ?>
      </div>
      <?php if ($isDev && $devDetail): ?>
        <div class="dev"><?= $esc($devDetail) ?></div>
      <?php elseif ($reqId): ?>
        <div class="rid">Hata referansı: <code><?= $esc($reqId) ?></code></div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html><?php
    exit;
}
}
