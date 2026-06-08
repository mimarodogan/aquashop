<?php
/**
 * Ürün değerlendirme gönderme handler'ı.
 * Faz 7.E: foto yükleme (max 5, max 5MB, jpg/png/webp) — JSON olarak media sütununa kaydedilir.
 *
 * Spam koruması katmanları:
 *  1. CSRF token doğrulama (zaten vardı)
 *  2. Honeypot alanı — botlar doldurur, insanlar görmez
 *  3. Zaman kontrolü — form en az 5 saniye açık kalmadan gönderilirse bot
 *  4. Duplicate check — aynı kullanıcı aynı ürüne zaten yorum yapmış
 *  5. Rate limit — aynı kullanıcı saatte max 3 yorum
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/reviews.php';

// Y-10 GÜVENLİK: open redirect — same-host whitelist
$ref = safe_back_url($_POST['back'] ?? '', '');
if ($ref === '') $ref = safe_back_url($_SERVER['HTTP_REFERER'] ?? '', url('home'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {

    /* ── KATMAN 1: Honeypot ────────────────────────────────────── */
    // 'rv_url' alanı form üzerinde gizlidir; botu doldurursa sessizce bitir.
    if (!empty($_POST['rv_url'])) {
        redirect($ref);
        exit;
    }

    /* ── KATMAN 2: Zaman kontrolü ─────────────────────────────── */
    // rv_tt = "timestamp|hmac" biçiminde; en az 5 saniye geçmeli.
    $ttParts = explode('|', $_POST['rv_tt'] ?? '', 2);
    $ttTs    = (int)($ttParts[0] ?? 0);
    $ttSig   = $ttParts[1] ?? '';
    $ttKey   = session_id() . 'rv';
    $ttValid = ($ttSig === hash_hmac('sha256', 'rv:' . $ttTs, $ttKey))
            && (time() - $ttTs >= 5)
            && (time() - $ttTs <= 7200); // 2 saaten eski form kabul edilmez
    if (!$ttValid) {
        flash_set('err', 'Formu yenileyip tekrar deneyin.');
        redirect($ref . '#yorumlar');
        exit;
    }

    /* ── Giriş kontrolü ───────────────────────────────────────── */
    $u = current_user();
    if (!$u) {
        flash_set('err', 'Değerlendirme yapmak için giriş yapmanız gerekiyor.');
        redirect($ref);
        exit;
    }

    $pid    = (int)($_POST['product_id'] ?? 0);
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 0)));
    $title  = trim($_POST['title'] ?? '');
    $body   = trim($_POST['body'] ?? '');

    if ($pid <= 0 || $rating < 1) {
        flash_set('err', 'Lütfen 1-5 arası bir yıldız sayısı seçin.');
    } elseif (!$body || mb_strlen($body) < 10) {
        flash_set('err', 'Yorum metni en az 10 karakter olmalı.');
    } else {

        /* ── KATMAN 3: Duplicate check ─────────────────────────── */
        $dupSt = db()->prepare(
            'SELECT COUNT(*) FROM product_reviews WHERE user_id=? AND product_id=?'
        );
        $dupSt->execute([$u['id'], $pid]);
        if ((int)$dupSt->fetchColumn() > 0) {
            flash_set('err', 'Bu ürüne zaten yorum yaptınız.');
            redirect($ref . '#yorumlar');
            exit;
        }

        /* ── KATMAN 4: Rate limit (saatte max 3 yorum) ─────────── */
        $rlSt = db()->prepare(
            "SELECT COUNT(*) FROM product_reviews
              WHERE user_id=? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $rlSt->execute([$u['id']]);
        if ((int)$rlSt->fetchColumn() >= 3) {
            flash_set('err', 'Saatte en fazla 3 yorum gönderebilirsiniz. Lütfen bekleyin.');
            redirect($ref . '#yorumlar');
            exit;
        }

        /* ── Foto yükleme ─────────────────────────────────────── */
        $mediaJson = null;
        if (!empty($_FILES['review_photos']['name'][0])) {
            $uploaded  = [];
            $uploadDir = APP_ROOT . '/uploads/reviews/' . date('Y/m');
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            // GIF yasak — sadece durağan görseller
            $allowed   = ['image/jpeg', 'image/png', 'image/webp'];
            $maxSize   = 5 * 1024 * 1024; // 5 MB
            $maxPhotos = 5;
            $errors    = [];

            $files = $_FILES['review_photos'];
            $count = min(count($files['name']), $maxPhotos);

            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                if ($files['size'][$i] > $maxSize) {
                    $errors[] = $files['name'][$i] . ' çok büyük (max 5 MB).';
                    continue;
                }

                $tmpPath = $files['tmp_name'][$i];
                if (!is_uploaded_file($tmpPath)) continue;

                // MIME doğrulama — finfo magic bytes ile
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
                if (!in_array($mime, $allowed, true)) {
                    $errors[] = $files['name'][$i] . ' desteklenmeyen format (JPG/PNG/WEBP).';
                    continue;
                }

                // Güvenli rastgele dosya adı
                $ext   = match($mime) {
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                    default      => 'jpg',
                };
                $fname = 'rv_' . time() . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest  = $uploadDir . '/' . $fname;

                // GD ile yeniden yaz (polyglot payload'ı siler, EXIF temizler)
                if (_review_resize_write($tmpPath, $dest, $mime, 800)) {
                    $uploaded[] = '/uploads/reviews/' . date('Y/m') . '/' . $fname;
                }
            }

            if ($errors) {
                flash_set('err', implode(' ', $errors));
            }
            if ($uploaded) {
                $mediaJson = json_encode($uploaded);
            }
        }

        /* ── DB kaydı ─────────────────────────────────────────── */
        $verified = reviews_verified_buyer($u['id'] ?? null, $u['email'], $pid) ? 1 : 0;

        db()->prepare(
            'INSERT INTO product_reviews
               (product_id, user_id, author_name, author_email, rating, title, body, is_approved, is_verified_buyer, media)
             VALUES (?,?,?,?,?,?,?,0,?,?)'
        )->execute([$pid, $u['id'], $u['name'], $u['email'] ?: null, $rating, $title ?: null, $body, $verified, $mediaJson]);

        flash_set('success', 'Yorumunuz alındı. Onay sonrası yayında görünecek. Teşekkürler!');
    }
}

redirect($ref . '#yorumlar');

/* ─────────────────────────────────────────────────────────────── */

/**
 * Görseli GD ile yeniden boyutlandır ve hedefe yaz.
 * Ham dosyayı asla kaydetme — GD yeniden yazması polyglot saldırılarına ve
 * gömülü zararlı verilere (EXIF vb.) karşı en etkili sanitizasyon yöntemidir.
 *
 * @return bool Başarıyla yazıldıysa true
 */
function _review_resize_write(string $src, string $dest, string $mime, int $maxW): bool {
    if (!function_exists('imagecreatefromjpeg')) return false;

    $imgSrc = match($mime) {
        'image/jpeg' => @imagecreatefromjpeg($src),
        'image/png'  => @imagecreatefrompng($src),
        'image/webp' => @imagecreatefromwebp($src),
        default      => null,
    };
    if (!$imgSrc) return false;

    $w = imagesx($imgSrc);
    $h = imagesy($imgSrc);

    // Boyut sınırı: en fazla $maxW px genişlik
    if ($w > $maxW) {
        $scale = $maxW / $w;
        $nw    = (int)round($w * $scale);
        $nh    = (int)round($h * $scale);
    } else {
        $nw = $w;
        $nh = $h;
    }

    $dst = imagecreatetruecolor($nw, $nh);

    // PNG/WebP saydamlığını koru
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $tr = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $tr);
    }

    imagecopyresampled($dst, $imgSrc, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $ok = match($mime) {
        'image/jpeg' => imagejpeg($dst, $dest, 85),
        'image/png'  => imagepng($dst, $dest, 8),
        'image/webp' => imagewebp($dst, $dest, 85),
        default      => false,
    };

    imagedestroy($imgSrc);
    imagedestroy($dst);
    return (bool)$ok;
}
