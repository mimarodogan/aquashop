<?php
// Bootstrap üzerinden yüklenir; bağımsız çalıştırılmaz.
if (!defined('MEDIA_DIR'))     define('MEDIA_DIR',     APP_ROOT . '/uploads');
if (!defined('MEDIA_URL'))     define('MEDIA_URL',     '/uploads');
if (!defined('MEDIA_MAX_W'))   define('MEDIA_MAX_W',   1280);  // PageSpeed önerisi: mobil için 1280px yeterli
if (!defined('MEDIA_QUALITY')) define('MEDIA_QUALITY', 82);
if (!defined('MEDIA_TRASH_DAYS')) define('MEDIA_TRASH_DAYS', 30);

function media_ensure_dir($dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
}

/**
 * $file: tek $_FILES alanı.
 * $opts (opsiyonel):
 *   - max_width: int → bu boyutun üstündeki görseller bu genişliğe küçültülür (default: MEDIA_MAX_W = 1280)
 *   - quality:   int → WebP kalitesi 1-100 arası (default: MEDIA_QUALITY = 82)
 *   - max_height: int → opsiyonel, hem genişlik hem yükseklik için ayrı sınır
 *
 * Kategori, avatar gibi küçük thumbnail kullanımları için:
 *   media_upload_from_files($_FILES['image_file'], ['max_width' => 480, 'quality' => 80])
 *
 * Başarılıysa array döner: ['ok'=>true,'id'=>..,'filename'=>..,'path'=>..,'url'=>..]
 * Hata: ['ok'=>false,'error'=>'..']
 */
function media_upload_from_files($file, $opts = array()) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return array('ok'=>false,'error'=>'Yükleme hatası (kod '.($file['error'] ?? '?').').');
    }
    $tmp = $file['tmp_name'];
    if (!is_uploaded_file($tmp) && !is_file($tmp)) return array('ok'=>false,'error'=>'Geçersiz dosya.');

    $info = @getimagesize($tmp);
    if (!$info) return array('ok'=>false,'error'=>'Dosya geçerli bir görsel değil.');
    list($w,$h,$type) = $info;

    // Opts parse — geriye dönük uyumluluk için default'lar mevcut sabitler
    $maxW    = isset($opts['max_width'])  ? max(16, (int)$opts['max_width'])  : MEDIA_MAX_W;
    $maxH    = isset($opts['max_height']) ? max(16, (int)$opts['max_height']) : 0; // 0 = sınırsız
    $quality = isset($opts['quality'])    ? max(40, min(95, (int)$opts['quality'])) : MEDIA_QUALITY;

    $sub = date('Y/m');
    $dir = MEDIA_DIR . '/' . $sub;
    media_ensure_dir($dir);

    $base = pathinfo(isset($file['name'])?$file['name']:'image', PATHINFO_FILENAME);
    $base = preg_replace('~[^a-z0-9\-]+~i','-', strtr($base, array('ç'=>'c','ğ'=>'g','ı'=>'i','ö'=>'o','ş'=>'s','ü'=>'u','Ç'=>'c','Ğ'=>'g','İ'=>'i','Ö'=>'o','Ş'=>'s','Ü'=>'u')));
    $base = trim(strtolower($base), '-'); if ($base === '') $base = 'gorsel';
    $base = substr($base, 0, 60);
    $name = $base . '-' . substr(md5(uniqid('', true)), 0, 8) . '.webp';
    $dest = $dir . '/' . $name;
    $relPath = MEDIA_URL . '/' . $sub . '/' . $name;

    // GD ile yükle, gerekirse küçült, webp olarak yaz
    if (!function_exists('imagecreatefromjpeg') || !function_exists('imagewebp')) {
        return array('ok'=>false,'error'=>'GD/WebP desteği bulunmuyor (PHP eklentisi gerekli).');
    }

    switch ($type) {
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($tmp); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($tmp);  break;
        case IMAGETYPE_GIF:  $img = @imagecreatefromgif($tmp);  break;
        case IMAGETYPE_WEBP: $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false; break;
        case IMAGETYPE_BMP:  $img = function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($tmp) : false; break;
        default: $img = false;
    }
    if (!$img) return array('ok'=>false,'error'=>'Görsel açılamadı (format desteklenmiyor olabilir).');

    // Boyutlandırma — hem max_width hem max_height kontrolü (oran korunur)
    $scaleW = ($w > $maxW) ? ($maxW / $w) : 1.0;
    $scaleH = ($maxH > 0 && $h > $maxH) ? ($maxH / $h) : 1.0;
    $scale  = min($scaleW, $scaleH);

    if ($scale < 1.0) {
        $newW = max(1, (int)round($w * $scale));
        $newH = max(1, (int)round($h * $scale));
        $resized = imagecreatetruecolor($newW, $newH);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $img, 0,0,0,0, $newW,$newH, $w,$h);
        imagedestroy($img);
        $img = $resized;
        $w = $newW; $h = $newH;
    }

    if (!@imagewebp($img, $dest, $quality)) {
        imagedestroy($img);
        return array('ok'=>false,'error'=>'WebP yazılamadı.');
    }
    imagedestroy($img);
    @chmod($dest, 0644);

    $size = filesize($dest);
    $st = db()->prepare('INSERT INTO media (filename,original_name,path,size,width,height,mime,uploaded_by) VALUES (?,?,?,?,?,?,?,?)');
    $uid = null;
    if (function_exists('current_user')) {
        $u = current_user();
        if ($u) $uid = (int)$u['id'];
    }
    $st->execute(array($name, isset($file['name'])?$file['name']:null, $relPath, $size, $w, $h, 'image/webp', $uid));
    $id = (int)db()->lastInsertId();

    return array('ok'=>true,'id'=>$id,'filename'=>$name,'path'=>$relPath,'url'=>$relPath,'width'=>$w,'height'=>$h,'size'=>$size);
}

/**
 * Mevcut bir görseli yerinde optimize eder (max boyut + kalite).
 * Orijinali $backupExt suffix ile yedekler (örn: '.orig480.webp').
 *
 * Return: ['ok'=>true,'before'=>bytes,'after'=>bytes,'w'=>...,'h'=>...]
 *         ['ok'=>false,'reason'=>'skip|notfound|error','msg'=>'...']
 */
function media_resize_path($absPath, $maxW = 480, $maxH = 480, $quality = 80, $backupExt = '.orig.webp') {
    if (!is_file($absPath)) return array('ok'=>false,'reason'=>'notfound','msg'=>'Dosya yok');

    $info = @getimagesize($absPath);
    if (!$info) return array('ok'=>false,'reason'=>'error','msg'=>'Okunamadı');
    list($w,$h) = $info;

    if ($w <= $maxW && $h <= $maxH) {
        return array('ok'=>false,'reason'=>'skip','msg'=>'Zaten küçük','w'=>$w,'h'=>$h);
    }

    $scaleW = ($w > $maxW) ? ($maxW / $w) : 1.0;
    $scaleH = ($h > $maxH) ? ($maxH / $h) : 1.0;
    $scale  = min($scaleW, $scaleH);
    $newW = max(1, (int)round($w * $scale));
    $newH = max(1, (int)round($h * $scale));

    if (!function_exists('imagecreatefromwebp') || !function_exists('imagewebp')) {
        return array('ok'=>false,'reason'=>'error','msg'=>'GD WebP desteği yok');
    }

    $before = filesize($absPath);

    // Yedek (ilk seferden sonra atlanır)
    $backup = preg_replace('/\.webp$/i', $backupExt, $absPath);
    if ($backup === $absPath) $backup .= '.bak'; // .webp değilse
    if (!file_exists($backup)) copy($absPath, $backup);

    $img = @imagecreatefromwebp($absPath);
    if (!$img) {
        // WebP değilse, mime'a göre dene
        switch ((int)$info[2]) {
            case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($absPath); break;
            case IMAGETYPE_PNG:  $img = @imagecreatefrompng($absPath);  break;
            case IMAGETYPE_GIF:  $img = @imagecreatefromgif($absPath);  break;
        }
    }
    if (!$img) return array('ok'=>false,'reason'=>'error','msg'=>'Görsel açılamadı');

    $dst = imagecreatetruecolor($newW, $newH);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $img, 0,0,0,0, $newW,$newH, $w,$h);
    $ok = @imagewebp($dst, $absPath, max(40, min(95, (int)$quality)));
    imagedestroy($img);
    imagedestroy($dst);

    if (!$ok) return array('ok'=>false,'reason'=>'error','msg'=>'Yazılamadı');

    $after = filesize($absPath);
    return array('ok'=>true,'before'=>$before,'after'=>$after,'w'=>$newW,'h'=>$newH);
}

/**
 * Bir DB tablosundaki tüm görsel URL'lerini tarayıp $maxW × $maxH üstündekileri küçültür.
 * $dryRun=true → sadece raporlama, dosyaya dokunmaz.
 *
 * Return: ['scanned'=>N,'oversized'=>N,'resized'=>N,'skipped'=>N,'notFound'=>N,'before'=>bytes,'after'=>bytes,'items'=>[...]]
 */
function media_optimize_table($table, $col, $maxW, $maxH, $quality, $dryRun = true, $backupExt = '.orig.webp') {
    $root = APP_ROOT;
    // Güvenlik: yalnızca bilinen tablo+sütun çiftleri
    // (table => [allowed_columns])
    $whitelist = array(
        'categories'     => array('image'),
        'blog_authors'   => array('avatar'),
        'blog_posts'     => array('cover_image'),
        'pages'          => array('cover_image'),
        'seo_settings'   => array('og_image'),
        'products'       => array('image'),
        'product_images' => array('path'),
    );
    if (!isset($whitelist[$table]) || !in_array($col, $whitelist[$table], true)) {
        return array('error'=>'Bilinmeyen tablo/sütun: '.$table.'.'.$col);
    }

    $stats = array('scanned'=>0,'oversized'=>0,'resized'=>0,'skipped'=>0,'notFound'=>0,'before'=>0,'after'=>0,'items'=>array());

    try {
        $rows = db()->query("SELECT $col AS img FROM $table WHERE $col IS NOT NULL AND $col != ''")->fetchAll();
    } catch (Exception $e) {
        return array('error'=>$e->getMessage());
    }

    foreach ($rows as $row) {
        $stats['scanned']++;
        $rel = ltrim((string)$row['img'], '/');
        $abs = $root . '/' . $rel;

        if (!is_file($abs)) { $stats['notFound']++; continue; }

        $info = @getimagesize($abs);
        if (!$info) { $stats['skipped']++; continue; }
        list($w,$h) = $info;

        if ($w <= $maxW && $h <= $maxH) { $stats['skipped']++; continue; }

        $stats['oversized']++;
        $stats['before'] += filesize($abs);

        if ($dryRun) {
            $stats['items'][] = array('url'=>$rel,'w'=>$w,'h'=>$h);
            continue;
        }

        $r = media_resize_path($abs, $maxW, $maxH, $quality, $backupExt);
        if ($r['ok']) {
            $stats['resized']++;
            $stats['after']  += $r['after'];
            $stats['items'][] = array('url'=>$rel,'from'=>"{$w}x{$h}",'to'=>$r['w'].'x'.$r['h']);
        } else {
            $stats['skipped']++;
        }
    }

    return $stats;
}

/**
 * Bir görselin "yanına" boyutlandırılmış bir variant dosyası oluşturur.
 * Orijinal dosyaya dokunmaz — `foo.webp` yanında `foo-mobile.webp` üretir.
 *
 * Phase 1 LCP optimizasyonu için: hero banner'ın mobil ekrana özel daha küçük
 * sürümü <picture> ile servis edilir → mobilde 3× daha hızlı paint → LCP iyileşir.
 *
 * Return: ['ok'=>true,'path'=>'...rel url','size'=>bytes,'w'=>...,'h'=>...]
 *         ['ok'=>false,'reason'=>'skip|exists|notfound|error','msg'=>'...']
 */
function media_make_variant($absPath, $suffix = '-mobile', $maxW = 800, $maxH = 800, $quality = 78) {
    if (!is_file($absPath)) return array('ok'=>false,'reason'=>'notfound','msg'=>'Kaynak yok');

    $variantPath = preg_replace('/(\.[a-z0-9]+)$/i', $suffix . '$1', $absPath);
    if ($variantPath === $absPath || $variantPath === null) {
        return array('ok'=>false,'reason'=>'error','msg'=>'Variant path türetilemedi');
    }

    // Zaten varsa atla — idempotent
    if (file_exists($variantPath)) {
        $info = @getimagesize($variantPath);
        return array('ok'=>true,'reason'=>'exists','path'=>$variantPath,
                     'size'=>filesize($variantPath),
                     'w'=>$info?$info[0]:0,'h'=>$info?$info[1]:0);
    }

    $info = @getimagesize($absPath);
    if (!$info) return array('ok'=>false,'reason'=>'error','msg'=>'Kaynak okunamadı');
    list($w,$h,$type) = $info;

    // Yeni boyutlar (oran korunur)
    $scaleW = ($w > $maxW) ? ($maxW / $w) : 1.0;
    $scaleH = ($h > $maxH) ? ($maxH / $h) : 1.0;
    $scale  = min($scaleW, $scaleH);

    if ($scale >= 1.0) {
        // Kaynak zaten küçük → variant yaratmaya gerek yok, orijinal yeterli
        return array('ok'=>false,'reason'=>'skip','msg'=>'Kaynak zaten küçük');
    }

    $newW = max(1, (int)round($w * $scale));
    $newH = max(1, (int)round($h * $scale));

    if (!function_exists('imagewebp')) {
        return array('ok'=>false,'reason'=>'error','msg'=>'GD WebP desteği yok');
    }

    // Source yükle
    $img = false;
    switch ((int)$type) {
        case IMAGETYPE_WEBP: $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absPath) : false; break;
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($absPath); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($absPath);  break;
        case IMAGETYPE_GIF:  $img = @imagecreatefromgif($absPath);  break;
    }
    if (!$img) return array('ok'=>false,'reason'=>'error','msg'=>'Görsel açılamadı');

    $dst = imagecreatetruecolor($newW, $newH);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $img, 0,0,0,0, $newW,$newH, $w,$h);
    $ok = @imagewebp($dst, $variantPath, max(60, min(95, (int)$quality)));
    imagedestroy($img);
    imagedestroy($dst);

    if (!$ok) return array('ok'=>false,'reason'=>'error','msg'=>'Variant yazılamadı');
    @chmod($variantPath, 0644);

    return array('ok'=>true,'path'=>$variantPath,'size'=>filesize($variantPath),'w'=>$newW,'h'=>$newH);
}

/**
 * `categories` veya `banners` gibi bir tabloda her satırın görselinin
 * `-mobile.webp` variant'ını üretir. Orijinaller dokunulmaz.
 *
 * Return: ['scanned'=>N,'created'=>N,'existed'=>N,'skipped'=>N,'totalSize'=>bytes,'items'=>[...]]
 */
function media_create_mobile_variants($table, $col, $maxW = 800, $maxH = 800, $quality = 78, $suffix = '-mobile') {
    // Güvenlik whitelist
    $allowed = array('banners'=>'image','categories'=>'image','products'=>'image','blog_posts'=>'cover_image','pages'=>'cover_image');
    if (!isset($allowed[$table]) || $allowed[$table] !== $col) {
        return array('error'=>'İzin verilmeyen tablo/sütun: '.$table.'.'.$col);
    }

    $root = APP_ROOT;
    $stats = array('scanned'=>0,'created'=>0,'existed'=>0,'skipped'=>0,'notFound'=>0,'totalSize'=>0,'items'=>array());

    try {
        $rows = db()->query("SELECT $col AS img FROM $table WHERE $col IS NOT NULL AND $col != ''")->fetchAll();
    } catch (Exception $e) {
        return array('error'=>$e->getMessage());
    }

    foreach ($rows as $row) {
        $stats['scanned']++;
        $rel = ltrim((string)$row['img'], '/');
        $abs = $root . '/' . $rel;

        $r = media_make_variant($abs, $suffix, $maxW, $maxH, $quality);

        if (!$r['ok']) {
            if (($r['reason'] ?? '') === 'notfound') $stats['notFound']++;
            elseif (($r['reason'] ?? '') === 'skip') $stats['skipped']++;
            else $stats['skipped']++;
            continue;
        }

        if (($r['reason'] ?? '') === 'exists') {
            $stats['existed']++;
            $stats['totalSize'] += $r['size'];
        } else {
            $stats['created']++;
            $stats['totalSize'] += $r['size'];
            $stats['items'][] = array(
                'src'  => $rel,
                'size' => $r['size'],
                'w'    => $r['w'],
                'h'    => $r['h'],
            );
        }
    }

    return $stats;
}

function media_soft_delete($id) {
    db()->prepare('UPDATE media SET deleted_at = NOW() WHERE id=? AND deleted_at IS NULL')->execute(array((int)$id));
}
function media_restore($id) {
    db()->prepare('UPDATE media SET deleted_at = NULL WHERE id=?')->execute(array((int)$id));
}
function media_hard_delete($id) {
    $st = db()->prepare('SELECT path FROM media WHERE id=?'); $st->execute(array((int)$id));
    $row = $st->fetch();
    if ($row) {
        $abs = realpath(__DIR__ . '/..' . $row['path']);
        if ($abs && is_file($abs)) @unlink($abs);
        db()->prepare('DELETE FROM media WHERE id=?')->execute(array((int)$id));
    }
}

/* 30 gün geçen çöp kayıtlarını ve dosyalarını sil */
function media_purge_old() {
    $st = db()->prepare('SELECT id FROM media WHERE deleted_at IS NOT NULL AND deleted_at < (NOW() - INTERVAL ' . (int)MEDIA_TRASH_DAYS . ' DAY)');
    $st->execute();
    foreach ($st->fetchAll() as $r) media_hard_delete($r['id']);
}

/* Bir dosyanın hangi içeriklerde kullanıldığını listele */
function media_usage($filename) {
    $like = '%' . $filename . '%';
    $usage = array();
    try {
        $st = db()->prepare("SELECT id,name AS title,'product' AS type FROM products WHERE image LIKE ?");
        $st->execute(array($like));
        foreach ($st->fetchAll() as $r) $usage[] = $r;
    } catch (Exception $e) {}
    try {
        $st = db()->prepare("SELECT id,title,'blog' AS type FROM blog_posts WHERE cover_image LIKE ? OR content LIKE ?");
        $st->execute(array($like,$like));
        foreach ($st->fetchAll() as $r) $usage[] = $r;
    } catch (Exception $e) {}
    try {
        $st = db()->prepare("SELECT id,title,'page' AS type FROM pages WHERE content LIKE ?");
        $st->execute(array($like));
        foreach ($st->fetchAll() as $r) $usage[] = $r;
    } catch (Exception $e) {}
    return $usage;
}

function media_usage_count($filename) {
    return count(media_usage($filename));
}

/* /uploads klasörünü tara, DB'de olmayan görselleri ekle. */
function media_scan() {
    media_ensure_dir(MEDIA_DIR);
    $added = 0;
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(MEDIA_DIR, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $f) {
        if ($f->isDir()) continue;
        $name = $f->getBasename();
        $ext = strtolower($f->getExtension());
        if (!in_array($ext, array('webp','jpg','jpeg','png','gif'))) continue;
        // DB'de varsa atla
        $st = db()->prepare('SELECT id FROM media WHERE filename = ?');
        $st->execute(array($name));
        if ($st->fetch()) continue;
        $abs = $f->getPathname();
        $rel = MEDIA_URL . str_replace(MEDIA_DIR, '', $abs);
        $rel = str_replace('\\','/',$rel);
        $size = (int)$f->getSize();
        $info = @getimagesize($abs);
        $w = $info ? (int)$info[0] : null;
        $h = $info ? (int)$info[1] : null;
        $mime = $info && isset($info['mime']) ? $info['mime'] : 'image/'.$ext;
        $st = db()->prepare('INSERT INTO media (filename,original_name,path,size,width,height,mime) VALUES (?,?,?,?,?,?,?)');
        try { $st->execute(array($name,$name,$rel,$size,$w,$h,$mime)); $added++; } catch (Exception $e) {}
    }
    return $added;
}
