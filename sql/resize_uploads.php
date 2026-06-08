<?php
/**
 * Tek seferlik resim optimizasyonu — uploads/ altındaki büyük WebP'leri max 1280px'e küçültür.
 *
 * Kullanım:
 *   php sql/resize_uploads.php           # rapor (dry run)
 *   php sql/resize_uploads.php --apply   # uygula
 *
 * Mantık:
 *  - Sadece her iki kenarı da > 1280 olan resimleri küçültür.
 *  - Uzun kenar 1280'a indirgenir, en boy oranı korunur.
 *  - WebP kalitesi 82 (PageSpeed önerisi).
 *  - Orijinaller `.orig.webp` olarak yedeklenir (ilk seferden sonra atlanır).
 *  - Gerektiğinde `.orig.webp` üzerinden geri yüklenebilir.
 *
 * Hedef: PageSpeed "Resim yayınlamayı kolaylaştırın — 656 KiB tasarruf" uyarısı temizlenir.
 */

$apply = in_array('--apply', $argv ?? [], true);
$root  = dirname(__DIR__);
$dir   = $root . '/uploads';

if (!extension_loaded('gd')) {
    fwrite(STDERR, "GD eklentisi gerekli (PHP). cPanel'de PHP Selector → eklentilerden 'gd' aktif edin.\n");
    exit(1);
}
if (!function_exists('imagewebp')) {
    fwrite(STDERR, "GD WebP desteği yok. PHP 7.0+ ve --with-webp ile derlenmiş GD gerekli.\n");
    exit(1);
}

$MAX_LONG_EDGE = 1280;
$WEBP_QUALITY  = 82;

function findWebp(string $d, array &$files): void {
    foreach (scandir($d) as $e) {
        if ($e[0] === '.') continue;
        $p = $d . '/' . $e;
        if (is_dir($p)) {
            findWebp($p, $files);
        } elseif (preg_match('/\.webp$/i', $e) && !preg_match('/\.orig\.webp$/i', $e)) {
            $files[] = $p;
        }
    }
}

$files = [];
if (is_dir($dir)) findWebp($dir, $files);

if (empty($files)) {
    echo "uploads/ altında WebP bulunamadı.\n";
    exit(0);
}

$totalBefore = 0;
$totalAfter  = 0;
$resized     = 0;
$skipped     = 0;

foreach ($files as $src) {
    $rel = str_replace($root . '/', '', $src);
    $info = @getimagesize($src);
    if (!$info) {
        echo "  [skip] $rel (okunamadı)\n";
        $skipped++;
        continue;
    }
    [$w, $h] = $info;
    $longest = max($w, $h);

    if ($longest <= $MAX_LONG_EDGE) {
        $skipped++;
        continue; // Zaten yeterince küçük
    }

    // Yeni boyutları hesapla
    $scale  = $MAX_LONG_EDGE / $longest;
    $newW   = (int) round($w * $scale);
    $newH   = (int) round($h * $scale);
    $before = filesize($src);
    $totalBefore += $before;

    if (!$apply) {
        printf("  [dry] %-60s %4dx%-4d → %4dx%-4d  (%d KB)\n",
            basename($rel), $w, $h, $newW, $newH, round($before / 1024));
        continue;
    }

    // Yedek (sadece ilk seferde)
    $backup = preg_replace('/\.webp$/i', '.orig.webp', $src);
    if (!file_exists($backup)) {
        copy($src, $backup);
    }

    $img = @imagecreatefromwebp($src);
    if (!$img) {
        echo "  [hata] $rel — WebP açılamadı\n";
        $skipped++;
        continue;
    }
    $resized_img = imagecreatetruecolor($newW, $newH);
    imagealphablending($resized_img, false);
    imagesavealpha($resized_img, true);
    imagecopyresampled($resized_img, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
    imagewebp($resized_img, $src, $WEBP_QUALITY);
    imagedestroy($img);
    imagedestroy($resized_img);

    $after = filesize($src);
    $totalAfter += $after;
    $resized++;

    printf("  [ok]  %-60s %4dx%-4d → %4dx%-4d  %4d KB → %4d KB (%-3d%%)\n",
        basename($rel), $w, $h, $newW, $newH,
        round($before / 1024), round($after / 1024),
        100 - round($after / $before * 100));
}

echo PHP_EOL;
if ($apply) {
    echo "RESİZE: $resized adet | ATLANMIŞ: $skipped adet" . PHP_EOL;
    if ($totalBefore > 0) {
        echo "Toplam: " . round($totalBefore / 1024) . " KB → "
           . round($totalAfter / 1024) . " KB (" . round((1 - $totalAfter / $totalBefore) * 100) . "% tasarruf)" . PHP_EOL;
    }
    echo "Orijinaller .orig.webp olarak yedeklendi. Geri yüklemek için bu dosyaları .webp olarak adlandırın.\n";
} else {
    echo "DRY RUN: Yukarıdaki dosyalar küçültülecek. Uygulamak için:\n  php sql/resize_uploads.php --apply\n";
    echo "(GD desteği yoksa veya çok büyük site için cPanel CLI üzerinden çalıştırın.)\n";
}
