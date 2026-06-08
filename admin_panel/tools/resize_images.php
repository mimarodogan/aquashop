<?php
/**
 * Web tabanlı toplu resim küçültme aracı.
 *
 * Paylaşımlı hosting'de terminal yokken kullanılır:
 *   /admin_panel/tools/resize_images.php
 *
 * Akış:
 *  1) Açıldığında uploads/ taranır, > MAX_LONG_EDGE olan resimler listelenir
 *  2) "Başlat" → AJAX ile 3'erli batch'ler hâlinde küçültür
 *  3) İlerleme çubuğu, tasarruf raporu gösterir
 *
 * Güvenlik: admin login + CSRF zorunlu.
 */
$page  = 'tools_resize';
$title = 'Resim Optimizasyonu';
require_once __DIR__ . '/../core/auth.php';

const RZ_MAX_LONG = 1280;
const RZ_QUALITY  = 82;
const RZ_BATCH    = 3;

/** uploads/ altındaki tüm webp'leri toplar (.orig.webp hariç) */
function rz_find_webp(string $d, array &$files): void {
    if (!is_dir($d)) return;
    foreach (scandir($d) as $e) {
        if ($e[0] === '.') continue;
        $p = $d . '/' . $e;
        if (is_dir($p)) rz_find_webp($p, $files);
        elseif (preg_match('/\.webp$/i', $e) && !preg_match('/\.orig\.webp$/i', $e)) {
            $files[] = $p;
        }
    }
}

/** Tek bir dosyayı küçültür. Başarılı: ['ok'=>true,'before'=>x,'after'=>y]. Hata/atlandı: ['ok'=>false,'reason'=>...] */
function rz_resize_one(string $src): array {
    $info = @getimagesize($src);
    if (!$info) return ['ok' => false, 'reason' => 'okunamadi'];
    [$w, $h] = $info;
    $longest = max($w, $h);
    if ($longest <= RZ_MAX_LONG) return ['ok' => false, 'reason' => 'zaten_kucuk'];
    if (!function_exists('imagecreatefromwebp') || !function_exists('imagewebp')) {
        return ['ok' => false, 'reason' => 'gd_webp_yok'];
    }
    $scale = RZ_MAX_LONG / $longest;
    $newW  = (int) round($w * $scale);
    $newH  = (int) round($h * $scale);
    $before = filesize($src);

    // Yedek
    $backup = preg_replace('/\.webp$/i', '.orig.webp', $src);
    if (!file_exists($backup)) @copy($src, $backup);

    $img = @imagecreatefromwebp($src);
    if (!$img) return ['ok' => false, 'reason' => 'webp_acilmadi'];
    $resized = imagecreatetruecolor($newW, $newH);
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
    $ok = @imagewebp($resized, $src, RZ_QUALITY);
    imagedestroy($img);
    imagedestroy($resized);
    if (!$ok) return ['ok' => false, 'reason' => 'yazilamadi'];

    return ['ok' => true, 'before' => $before, 'after' => filesize($src), 'old' => "{$w}x{$h}", 'new' => "{$newW}x{$newH}"];
}

// =========================================================================
// AJAX: batch işleyici
// =========================================================================
if (($_GET['ajax'] ?? '') === 'batch') {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? null)) {
        echo json_encode(['ok' => false, 'error' => 'CSRF']);
        exit;
    }
    @set_time_limit(60);
    @ini_set('memory_limit', '256M');

    $offset = (int) ($_POST['offset'] ?? 0);
    $files  = [];
    rz_find_webp(APP_ROOT . '/uploads', $files);
    sort($files);

    $totalLarge = 0;
    foreach ($files as $f) {
        $info = @getimagesize($f);
        if ($info && max($info[0], $info[1]) > RZ_MAX_LONG) $totalLarge++;
    }

    // Sadece büyük olanları işle
    $largeFiles = [];
    foreach ($files as $f) {
        $info = @getimagesize($f);
        if ($info && max($info[0], $info[1]) > RZ_MAX_LONG) $largeFiles[] = $f;
    }

    $slice = array_slice($largeFiles, $offset, RZ_BATCH);
    $done = 0; $saved = 0; $errors = []; $details = [];
    foreach ($slice as $f) {
        $r = rz_resize_one($f);
        if (!empty($r['ok'])) {
            $done++;
            $saved += ($r['before'] - $r['after']);
            $details[] = basename($f) . ' ' . $r['old'] . '→' . $r['new']
                       . ' (' . round($r['before']/1024) . 'KB→' . round($r['after']/1024) . 'KB)';
        } else {
            $errors[] = basename($f) . ': ' . ($r['reason'] ?? '?');
        }
    }

    $newOffset = $offset + count($slice);
    $remaining = max(0, count($largeFiles) - $newOffset);

    echo json_encode([
        'ok'         => true,
        'done'       => $done,
        'saved'      => $saved,
        'remaining'  => $remaining,
        'total'      => count($largeFiles),
        'next_offset'=> $newOffset,
        'details'    => $details,
        'errors'     => $errors,
    ]);
    exit;
}

// =========================================================================
// Normal görünüm: dry-run rapor + başlat butonu
// =========================================================================
$files = [];
rz_find_webp(APP_ROOT . '/uploads', $files);
sort($files);

$bigFiles    = [];
$totalSize   = 0;
$totalBig    = 0;
foreach ($files as $f) {
    $info = @getimagesize($f);
    if (!$info) continue;
    [$w, $h] = $info;
    $totalSize += filesize($f);
    if (max($w, $h) > RZ_MAX_LONG) {
        $bigFiles[] = ['path' => $f, 'w' => $w, 'h' => $h, 'size' => filesize($f)];
        $totalBig  += filesize($f);
    }
}

$gdOk = function_exists('imagecreatefromwebp') && function_exists('imagewebp');

require_once __DIR__ . '/../core/header.php';
?>
<div class="panel">
  <h3>Resim Optimizasyonu</h3>
  <p class="muted" style="font-size:13px;margin-bottom:18px">
    <code>uploads/</code> klasöründeki büyük WebP'leri max <strong><?= RZ_MAX_LONG ?>px</strong> uzun kenara küçültür.
    Orijinaller <code>.orig.webp</code> olarak yedeklenir. PageSpeed "Resim yayınlamayı kolaylaştırın" uyarısını temizler.
  </p>

  <?php if (!$gdOk): ?>
    <div class="alert alert-err">⚠️ GD WebP desteği yok. cPanel → PHP Selector'dan <code>gd</code> eklentisini etkinleştirin.</div>
  <?php endif; ?>

  <div class="kpis" style="margin-bottom:24px">
    <div class="kpi"><div class="lbl">Toplam Resim</div><div class="val"><?= count($files) ?></div></div>
    <div class="kpi"><div class="lbl">Toplam Boyut</div><div class="val"><?= round($totalSize / 1024 / 1024, 1) ?> MB</div></div>
    <div class="kpi"><div class="lbl">Küçültülecek</div><div class="val" style="color:<?= count($bigFiles) > 0 ? '#E0A800' : 'var(--leaf)' ?>"><?= count($bigFiles) ?></div></div>
    <div class="kpi"><div class="lbl">Etkilenen Boyut</div><div class="val"><?= round($totalBig / 1024 / 1024, 1) ?> MB</div></div>
  </div>

  <?php if ($bigFiles && $gdOk): ?>
    <div id="rz-progress" style="display:none;margin-bottom:18px">
      <div class="muted" style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
        <span><strong id="rz-count">0</strong> / <?= count($bigFiles) ?> dosya işlendi</span>
        <span id="rz-saved">Tasarruf: 0 KB</span>
      </div>
      <div style="background:var(--cream);border-radius:6px;height:18px;overflow:hidden">
        <div id="rz-bar" style="background:var(--leaf);height:100%;width:0%;transition:width .2s;border-radius:6px"></div>
      </div>
      <div id="rz-log" style="margin-top:14px;max-height:280px;overflow:auto;font-family:monospace;font-size:11px;background:var(--cream);padding:12px;border-radius:6px;color:var(--ink-soft);line-height:1.5"></div>
    </div>

    <button type="button" id="rz-start" class="btn btn-primary"
            data-csrf="<?= e(csrf_token()) ?>"
            data-total="<?= count($bigFiles) ?>">
      🪄 <?= count($bigFiles) ?> Resmi Optimize Et
    </button>
    <p class="muted" style="font-size:12px;margin-top:12px">
      İşlem sırasında bu sayfayı kapatma. <?= count($bigFiles) ?> dosya tahminen
      <strong>~<?= ceil(count($bigFiles) / RZ_BATCH * 4) ?> saniye</strong> sürer (paylaşımlı hosting hızına göre).
    </p>

    <details style="margin-top:18px">
      <summary style="cursor:pointer;font-size:13px;color:var(--muted-text)">
        İşlenecek dosya listesi (<?= count($bigFiles) ?> dosya)
      </summary>
      <table style="margin-top:12px;font-size:12px;width:100%">
        <thead><tr><th>Dosya</th><th>Mevcut Boyut</th><th>Mevcut Boyut</th></tr></thead>
        <tbody>
          <?php foreach (array_slice($bigFiles, 0, 30) as $b): ?>
            <tr>
              <td style="font-family:monospace;font-size:11px"><?= e(str_replace(APP_ROOT . '/', '', $b['path'])) ?></td>
              <td><?= $b['w'] ?>×<?= $b['h'] ?></td>
              <td><?= round($b['size'] / 1024) ?> KB</td>
            </tr>
          <?php endforeach; ?>
          <?php if (count($bigFiles) > 30): ?>
            <tr><td colspan="3" class="muted" style="text-align:center;font-style:italic">...ve <?= count($bigFiles) - 30 ?> dosya daha</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </details>
  <?php elseif (!$bigFiles): ?>
    <div class="alert alert-success" style="background:rgba(107,122,47,.08);color:var(--leaf);border:1px solid var(--leaf);padding:14px 18px;border-radius:6px">
      ✅ Tüm resimler zaten optimize edilmiş. Küçültülmesi gereken büyük resim yok.
    </div>
  <?php endif; ?>
</div>

<script defer src="<?= SITE_URL ?>/assets/js/admin/resize-images.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/admin/resize-images.js') ?: time() ?>"></script>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
