<?php
/**
 * Tek seferlik kullanılan minify scripti.
 * `assets/css/` altındaki tüm `.css` dosyalarını yerinde minify eder,
 * orijinalleri `.src.css` olarak yedekler. İdempotent: yedek varsa onu kullanır.
 *
 * Çalıştır: `php sql/minify_css.php`
 */

function aggressiveMinCss(string $css): string {
    // 1) Yorumları sil (/*! ... */ önemli yorumları koru)
    $css = preg_replace('~/\*(?!!)[\s\S]*?\*/~', '', $css);
    // 2) CR/LF/TAB → boşluk
    $css = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $css);
    // 3) Çoklu boşluk → tek
    $css = preg_replace('/ {2,}/', ' ', $css);
    // 4) Operatör çevresinde boşlukları sil
    $css = preg_replace('/\s*([{};:,>+~()])\s*/', '$1', $css);
    // 5) Son ; ile } arasını sil
    $css = str_replace(';}', '}', $css);
    // 6) Leading sıfır (0.5 → .5)
    $css = preg_replace('/(?<=[\s:(,])0+\.([0-9])/', '.$1', $css);
    // 7) Hex renk #ffffff → #fff
    $css = preg_replace_callback(
        '/#([0-9a-fA-F])\1([0-9a-fA-F])\2([0-9a-fA-F])\3\b/',
        function ($m) { return '#' . $m[1] . $m[2] . $m[3]; },
        $css
    );
    // 8) 0px / 0em / 0rem / 0vh / 0vw / 0% → 0
    $css = preg_replace('/(?<![a-zA-Z0-9])0(px|em|rem|vh|vw|%)\b/', '0', $css);
    return trim($css);
}

function findCss(string $d, array &$files): void {
    foreach (scandir($d) as $e) {
        if ($e[0] === '.') continue;
        $p = $d . '/' . $e;
        if (is_dir($p)) {
            findCss($p, $files);
        } elseif (preg_match('/\.css$/', $e) && !preg_match('/\.(min|src)\.css$/', $e)) {
            $files[] = $p;
        }
    }
}

$dir = __DIR__ . '/../assets/css';
$files = [];
findCss($dir, $files);

$totalOrig = 0;
$totalNew  = 0;

foreach ($files as $src) {
    $srcBak = preg_replace('/\.css$/', '.src.css', $src);

    // İdempotent: yedek varsa daima ondan üret
    if (!is_file($srcBak)) {
        copy($src, $srcBak);
    } else {
        copy($srcBak, $src);
    }

    $origSize = filesize($src);
    $css      = file_get_contents($src);
    $min      = aggressiveMinCss($css);
    file_put_contents($src, $min);
    $newSize = filesize($src);

    $totalOrig += $origSize;
    $totalNew  += $newSize;
    printf("  %-50s %5d → %5d (%5.1f%%)\n",
        basename($src), $origSize, $newSize, (1 - $newSize/$origSize) * 100);

    // Eski .min.css dosyalarını temizle (artık ana .css zaten minified)
    $old = preg_replace('/\.css$/', '.min.css', $src);
    if (is_file($old)) unlink($old);
}

echo PHP_EOL;
echo "TOPLAM: $totalOrig → $totalNew bytes ("
   . round((1 - $totalNew/$totalOrig) * 100, 1) . "% tasarruf)" . PHP_EOL;
echo "Yedekler: assets/css/*.src.css olarak duruyor — geri yüklemek için bunları .css'e geri yaz." . PHP_EOL;
