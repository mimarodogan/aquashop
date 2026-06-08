<?php
/**
 * WordPress WXR (XML export) → bizim products tablosuna aktarıcı.
 * Streaming XMLReader kullanır — 24 MB+ dosyalarda RAM patlamaz.
 *
 * Kullanım:
 *   wp_importer_parse($xmlPath)  → ['products'=>[...], 'attachments'=>[...]]
 *   wp_importer_apply($parsed)   → DB'ye yazar, görsel indirme kuyruğu kurar
 *   wp_importer_download_batch($limit=50) → kuyruktaki görselleri indirir
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/media.php';

/**
 * Gutenberg/WP editor artığı data-* niteliklerini, gereksiz boşluk ve boş <p>'leri temizler.
 * - HTML yapısını korur (başlık, paragraf, liste, link)
 * - data-start, data-end, data-tour vb. tüm data-* niteliklerini siler
 * - Boş <p></p> ve <p>&nbsp;</p> bloklarını siler
 * - Çoklu &nbsp; ve fazla boşlukları normalize eder
 */
if (!function_exists('wp_clean_html')) {
function wp_clean_html(string $html): string {
    if ($html === '') return $html;
    // 1) data-* nitelikleri (tek/çift tırnaklı veya tırnaksız)
    $html = preg_replace('/\s+data-[a-z0-9_-]+\s*=\s*"[^"]*"/i', '', $html);
    $html = preg_replace("/\\s+data-[a-z0-9_-]+\\s*=\\s*'[^']*'/i", '', $html);
    $html = preg_replace('/\s+data-[a-z0-9_-]+\s*=\s*[^"\'\s>]+/i', '', $html);
    // 2) Boş paragraflar
    $html = preg_replace('~<p[^>]*>\s*(?:&nbsp;|\s)*\s*</p>~i', '', $html);
    // 3) Çoklu &nbsp;
    $html = preg_replace('/(&nbsp;)+/i', ' ', $html);
    // 4) Çoklu boşluk
    $html = preg_replace('/[ \t]{2,}/', ' ', $html);
    // 5) Çoklu boş satır
    $html = preg_replace("/\n\s*\n\s*\n+/", "\n\n", $html);
    return trim($html);
}}

/**
 * short_desc gibi düz metin alanlar için tüm HTML'i siler.
 */
if (!function_exists('wp_strip_html')) {
function wp_strip_html(string $html): string {
    if ($html === '') return $html;
    $text = strip_tags(str_replace(['<br>','<br/>','<br />','</p>','</li>'], "\n", $html));
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/\n\s*\n\s*\n+/", "\n\n", $text);
    $text = preg_replace('/[ \t]{2,}/', ' ', $text);
    return trim($text);
}}

if (!function_exists('wp_importer_parse')) {
function wp_importer_parse(string $xmlPath): array {
    if (!file_exists($xmlPath)) {
        throw new \RuntimeException('XML dosyası bulunamadı: ' . $xmlPath);
    }
    $r = new \XMLReader();
    if (!$r->open($xmlPath)) throw new \RuntimeException('XML açılamadı.');

    $products = [];
    $attachments = []; // attachment_id => url
    $categories = []; // slug => name
    $tags = []; // slug => name

    while ($r->read()) {
        if ($r->nodeType !== \XMLReader::ELEMENT || $r->name !== 'item') continue;
        $itemXml = $r->readOuterXML();
        if (!$itemXml) continue;
        $item = @simplexml_load_string($itemXml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$item) continue;
        $wp = $item->children('http://wordpress.org/export/1.2/');
        $type = (string)$wp->post_type;

        if ($type === 'attachment') {
            $aid = (int)$wp->post_id;
            $url = (string)$wp->attachment_url;
            if ($aid && $url) $attachments[$aid] = $url;
            continue;
        }

        if ($type !== 'product') continue;

        $status = (string)$wp->status;
        if ($status !== 'publish') continue;

        $content = $item->children('http://purl.org/rss/1.0/modules/content/');
        $excerpt = $item->children('http://wordpress.org/export/1.2/excerpt/');

        $p = [
            'wp_id'       => (int)$wp->post_id,
            'name'        => trim((string)$item->title),
            'slug'        => trim((string)$wp->post_name),
            'description' => wp_clean_html(trim((string)$content->encoded)),
            'short_desc'  => wp_strip_html(trim((string)$excerpt->encoded)),
            'created_at'  => (string)$wp->post_date_gmt ?: date('Y-m-d H:i:s'),
            'is_featured' => 0,
            'category_slug' => null,
            'category_name' => null,
            'tag_slugs'   => [],
            'sku'         => '',
            'price'       => 0,
            'old_price'   => null,
            'thumbnail_id'=> null,
            'gallery_ids' => [],
        ];

        // Kategoriler ve etiketler
        foreach ($item->category as $cat) {
            $domain = (string)$cat['domain'];
            $nicename = (string)$cat['nicename'];
            $name = trim((string)$cat);
            if ($domain === 'product_cat' && $nicename) {
                // İlk kategori ana kategori olarak
                if (!$p['category_slug']) {
                    $p['category_slug'] = $nicename;
                    $p['category_name'] = $name ?: $nicename;
                }
                if (!isset($categories[$nicename])) $categories[$nicename] = $name ?: $nicename;
            } elseif ($domain === 'product_tag' && $nicename) {
                $p['tag_slugs'][] = $nicename;
                $tags[$nicename] = $name ?: $nicename;
            } elseif ($domain === 'product_visibility' && $nicename === 'featured') {
                $p['is_featured'] = 1;
            }
        }

        // postmeta — _sku, _regular_price, _sale_price, _thumbnail_id, _product_image_gallery
        foreach ($wp->postmeta as $meta) {
            $key = (string)$meta->meta_key;
            $val = (string)$meta->meta_value;
            switch ($key) {
                case '_sku':              $p['sku'] = $val; break;
                case '_regular_price':    if ($val !== '') $p['old_price'] = (float)$val; break;
                case '_sale_price':       if ($val !== '') $p['price']     = (float)$val; break;
                case '_price':            if ($p['price'] == 0 && $val !== '') $p['price'] = (float)$val; break;
                case '_thumbnail_id':     if ($val !== '') $p['thumbnail_id'] = (int)$val; break;
                case '_product_image_gallery':
                    if ($val !== '') {
                        foreach (explode(',', $val) as $gid) {
                            $gid = (int)trim($gid);
                            if ($gid) $p['gallery_ids'][] = $gid;
                        }
                    }
                    break;
            }
        }

        // Sale price yoksa regular_price → price; old_price = null
        if ($p['price'] == 0 && $p['old_price'] !== null) {
            $p['price'] = $p['old_price'];
            $p['old_price'] = null;
        }

        $products[] = $p;
    }
    $r->close();

    // Galeri istatistikleri (önizleme için)
    $productsWithGallery = 0;
    $totalGalleryImages  = 0;
    $productsWithoutThumb = 0;
    foreach ($products as $p) {
        if ($p['gallery_ids'])    { $productsWithGallery++; $totalGalleryImages += count($p['gallery_ids']); }
        if (!$p['thumbnail_id'])  { $productsWithoutThumb++; }
    }

    return [
        'products'              => $products,
        'attachments'           => $attachments,
        'categories'            => $categories,
        'tags'                  => $tags,
        'products_with_gallery' => $productsWithGallery,
        'total_gallery_images'  => $totalGalleryImages,
        'products_without_thumb'=> $productsWithoutThumb,
    ];
}}

/**
 * Parsed veriyi DB'ye yazar.
 * - Kategorileri eşleştirir/oluşturur
 * - Ürünleri ekler (slug benzersizliği korunur)
 * - Görsel indirme kuyruğunu wp_import_queue tablosuna kaydeder
 *
 * Ön koşul: çağıran tarafta mevcut ürünler temizlenmeli (eğer istenirse).
 */
if (!function_exists('wp_importer_apply')) {
function wp_importer_apply(array $parsed, ?callable $onProgress = null): array {
    $pdo = db();
    $stats = ['products'=>0, 'categories'=>0, 'queued_images'=>0, 'errors'=>[]];

    // 1) Kategorileri oluştur (yoksa)
    $catMap = []; // slug => id
    $catFind = $pdo->prepare('SELECT id FROM categories WHERE slug=? LIMIT 1');
    $catIns  = $pdo->prepare('INSERT INTO categories (name, slug) VALUES (?, ?)');
    foreach ($parsed['categories'] as $slug => $name) {
        $catFind->execute([$slug]);
        $row = $catFind->fetch();
        if ($row) {
            $catMap[$slug] = (int)$row['id'];
        } else {
            $catIns->execute([$name, $slug]);
            $catMap[$slug] = (int)$pdo->lastInsertId();
            $stats['categories']++;
        }
    }

    // 2) Görsel kuyruğu tablosunu hazırla (yoksa)
    $pdo->exec("CREATE TABLE IF NOT EXISTS wp_import_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kind ENUM('main','gallery') NOT NULL,
        product_id INT NOT NULL,
        wp_attachment_id INT NOT NULL,
        url VARCHAR(500) NOT NULL,
        status ENUM('pending','done','failed') NOT NULL DEFAULT 'pending',
        local_path VARCHAR(500) DEFAULT NULL,
        attempts TINYINT NOT NULL DEFAULT 0,
        error VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 3) Ürünleri ekle
    $prodIns = $pdo->prepare("INSERT INTO products
        (category_id, name, slug, sku, short_desc, description, price, old_price, stock, image, is_active, is_featured, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $slugChk = $pdo->prepare('SELECT id FROM products WHERE slug=? LIMIT 1');
    $qIns = $pdo->prepare('INSERT INTO wp_import_queue (kind, product_id, wp_attachment_id, url) VALUES (?,?,?,?)');

    $atts = $parsed['attachments'];
    $total = count($parsed['products']);
    $i = 0;

    foreach ($parsed['products'] as $p) {
        $i++;
        try {
            // Slug benzersizliği — çakışırsa -2, -3 ekle
            $baseSlug = $p['slug'] ?: 'urun-' . $p['wp_id'];
            $slug = $baseSlug; $n = 1;
            while (true) {
                $slugChk->execute([$slug]);
                if (!$slugChk->fetch()) break;
                $n++; $slug = $baseSlug . '-' . $n;
            }

            $catId = $p['category_slug'] && isset($catMap[$p['category_slug']])
                ? $catMap[$p['category_slug']] : null;

            $prodIns->execute([
                $catId,
                $p['name'],
                $slug,
                $p['sku'],
                $p['short_desc'],
                $p['description'],
                $p['price'],
                $p['old_price'],
                0,             // stock = 0
                '',            // image — önce boş, indirme bitince güncellenir
                1,             // is_active
                $p['is_featured'],
                $p['created_at'],
            ]);
            $newId = (int)$pdo->lastInsertId();
            $stats['products']++;

            // Görsel kuyruğuna ekle
            if ($p['thumbnail_id'] && isset($atts[$p['thumbnail_id']])) {
                $qIns->execute(['main', $newId, $p['thumbnail_id'], $atts[$p['thumbnail_id']]]);
                $stats['queued_images']++;
            }
            foreach ($p['gallery_ids'] as $gid) {
                if (isset($atts[$gid])) {
                    $qIns->execute(['gallery', $newId, $gid, $atts[$gid]]);
                    $stats['queued_images']++;
                }
            }
        } catch (\Throwable $e) {
            $stats['errors'][] = [
                'wp_id' => $p['wp_id'],
                'name'  => $p['name'],
                'msg'   => $e->getMessage(),
            ];
        }

        if ($onProgress && $i % 25 === 0) $onProgress($i, $total);
    }

    if ($onProgress) $onProgress($total, $total);
    return $stats;
}}

/**
 * Kuyruktaki bekleyen görselleri toplu indirir, WebP'ye çevirir, kaydeder.
 * Bir batch işler ve kalan sayıyı döndürür — UI ajax döngüsüyle çağırır.
 *
 * Düzeltmeler:
 *  - Galeri görselleri artık sort_order=0 yerine ürün başına 1,2,3... sırasıyla eklenir
 *  - stats'a kind ayrımı eklendi (done_main, done_gallery)
 */
if (!function_exists('wp_importer_download_batch')) {
function wp_importer_download_batch(int $limit = 25): array {
    $pdo = db();
    $stats = ['done'=>0, 'failed'=>0, 'remaining'=>0, 'done_main'=>0, 'done_gallery'=>0];

    $rows = $pdo->prepare("SELECT * FROM wp_import_queue WHERE status='pending' ORDER BY id ASC LIMIT ?");
    $rows->bindValue(1, $limit, \PDO::PARAM_INT);
    $rows->execute();
    $batch = $rows->fetchAll();

    $upd      = $pdo->prepare("UPDATE wp_import_queue SET status=?, local_path=?, attempts=attempts+1, error=? WHERE id=?");
    $prodMain = $pdo->prepare("UPDATE products SET image=? WHERE id=? AND (image IS NULL OR image='')");
    $galIns   = $pdo->prepare("INSERT INTO product_images (product_id, path, sort_order) VALUES (?,?,?)");

    // Her ürün için mevcut max sort_order'ı önbellekle — aynı batch'te tutarlı sıra için
    $sortCache = [];

    foreach ($batch as $row) {
        try {
            $url = $row['url'];
            $tmp = tempnam(sys_get_temp_dir(), 'wpimg_');
            $fp  = @fopen($tmp, 'wb');
            // Y-7 SSRF koruması: scheme + private IP filtresi
            if (!is_url_safe_for_fetch($url)) {
                @fclose($fp);
                @unlink($tmpPath);
                error_log('[wp_importer] SSRF blocked: ' . substr($url, 0, 200));
                continue;
            }
            $ch  = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE           => $fp,
                CURLOPT_FOLLOWLOCATION => false,                      // Y-7: redirect zinciri = DNS rebinding riski
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,  // Y-7
                CURLOPT_REDIR_PROTOCOLS=> CURLPROTO_HTTP | CURLPROTO_HTTPS,  // Y-7
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_USERAGENT      => 'AquaShop-Importer/1.0',
                CURLOPT_FAILONERROR    => true,
                CURLOPT_SSL_VERIFYPEER => true,                       // O-11: MITM koruması
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $ok   = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = $ok ? null : ('HTTP ' . $code . ' ' . curl_error($ch));
            curl_close($ch);
            fclose($fp);

            if (!$ok || filesize($tmp) === 0) {
                @unlink($tmp);
                $upd->execute(['failed', null, $err ?: 'boş yanıt', $row['id']]);
                $stats['failed']++;
                continue;
            }

            $fakeFile = [
                'tmp_name' => $tmp,
                'name'     => basename(parse_url($url, PHP_URL_PATH) ?: 'image.jpg'),
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tmp),
            ];
            $r = media_upload_from_files($fakeFile);
            @unlink($tmp);

            if (!$r['ok']) {
                $upd->execute(['failed', null, $r['error'] ?? 'medya işleme hatası', $row['id']]);
                $stats['failed']++;
                continue;
            }

            $localPath = $r['path'];
            $upd->execute(['done', $localPath, null, $row['id']]);

            if ($row['kind'] === 'main') {
                $prodMain->execute([$localPath, $row['product_id']]);
                $stats['done_main']++;
            } else {
                // Doğru sort_order: DB'deki max + 1 (önbellekle fazla sorgu önle)
                $pid = (int)$row['product_id'];
                if (!isset($sortCache[$pid])) {
                    $st = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM product_images WHERE product_id=?');
                    $st->execute([$pid]);
                    $sortCache[$pid] = (int)$st->fetchColumn();
                }
                $galIns->execute([$pid, $localPath, $sortCache[$pid]]);
                $sortCache[$pid]++;
                $stats['done_gallery']++;
            }
            $stats['done']++;
        } catch (\Throwable $e) {
            $upd->execute(['failed', null, substr($e->getMessage(), 0, 250), $row['id']]);
            $stats['failed']++;
        }
    }

    $rem = $pdo->query("SELECT COUNT(*) FROM wp_import_queue WHERE status='pending'")->fetchColumn();
    $stats['remaining'] = (int)$rem;
    return $stats;
}}

/**
 * Başarısız görsel indirmelerini yeniden kuyruğa alır.
 * status='failed' → status='pending', attempts=0, error=NULL
 * kind parametresiyle sadece 'main' veya 'gallery' sıfırlanabilir, null ise hepsi.
 */
if (!function_exists('wp_importer_retry_failed')) {
function wp_importer_retry_failed(?string $kind = null): int {
    $pdo = db();
    try {
        if ($kind !== null) {
            $st = $pdo->prepare("UPDATE wp_import_queue SET status='pending', attempts=0, error=NULL WHERE status='failed' AND kind=?");
            $st->execute([$kind]);
        } else {
            $pdo->exec("UPDATE wp_import_queue SET status='pending', attempts=0, error=NULL WHERE status='failed'");
        }
        return (int)$pdo->query("SELECT COUNT(*) FROM wp_import_queue WHERE status='pending'")->fetchColumn();
    } catch (\Throwable $e) {
        return 0;
    }
}}

/**
 * Kuyruk özetini döndürür: pending/done/failed ayrımı ve kind bazlı kırılım.
 */
if (!function_exists('wp_importer_queue_stats')) {
function wp_importer_queue_stats(): array {
    $pdo = db();
    $out = ['pending'=>0, 'done'=>0, 'failed'=>0, 'main_pending'=>0, 'main_done'=>0, 'main_failed'=>0, 'gallery_pending'=>0, 'gallery_done'=>0, 'gallery_failed'=>0];
    try {
        $rows = $pdo->query("SELECT kind, status, COUNT(*) c FROM wp_import_queue GROUP BY kind, status")->fetchAll();
        foreach ($rows as $r) {
            $out[$r['status']] = ($out[$r['status']] ?? 0) + (int)$r['c'];
            $key = $r['kind'] . '_' . $r['status'];
            if (isset($out[$key])) $out[$key] = (int)$r['c'];
        }
    } catch (\Throwable $e) {}
    return $out;
}}

/* ═══════════════════════════════════════════════════════════
   BLOG YAZISI İMPORTER  (post_type='post')
   Ürün importer'ından bağımsız — sadece blog_posts / blog_categories yazar.
   ═══════════════════════════════════════════════════════════ */

/**
 * WordPress WXR'dan blog yazılarını parse eder.
 *
 * Dönen dizi:
 *   posts[]       — her yazı için: title, slug, content, excerpt, published_at,
 *                   category_slug, category_name, thumbnail_id, wp_id
 *   attachments[] — wp_attachment_id => url
 *   categories[]  — slug => name
 */
if (!function_exists('wp_blog_parse')) {
function wp_blog_parse(string $xmlPath): array {
    if (!file_exists($xmlPath)) throw new \RuntimeException('XML bulunamadı: ' . $xmlPath);

    $r = new \XMLReader();
    if (!$r->open($xmlPath)) throw new \RuntimeException('XML açılamadı.');

    $posts       = [];
    $attachments = [];
    $categories  = [];

    while ($r->read()) {
        if ($r->nodeType !== \XMLReader::ELEMENT || $r->name !== 'item') continue;
        $itemXml = $r->readOuterXML();
        if (!$itemXml) continue;
        $item = @simplexml_load_string($itemXml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$item) continue;

        $wp   = $item->children('http://wordpress.org/export/1.2/');
        $type = (string)$wp->post_type;

        // Attachment → URL haritasına ekle
        if ($type === 'attachment') {
            $aid = (int)$wp->post_id;
            $url = (string)$wp->attachment_url;
            if ($aid && $url) $attachments[$aid] = $url;
            continue;
        }

        // Sadece yayınlanmış normal blog yazıları
        if ($type !== 'post') continue;
        if ((string)$wp->status !== 'publish') continue;

        $content = $item->children('http://purl.org/rss/1.0/modules/content/');
        $exc     = $item->children('http://wordpress.org/export/1.2/excerpt/');

        $body    = wp_clean_html(trim((string)$content->encoded));
        $excerpt = wp_strip_html(trim((string)$exc->encoded));
        if (mb_strlen($excerpt) > 490) $excerpt = mb_substr($excerpt, 0, 490) . '…';

        $post = [
            'wp_id'         => (int)$wp->post_id,
            'title'         => trim((string)$item->title),
            'slug'          => trim((string)$wp->post_name),
            'content'       => $body,
            'excerpt'       => $excerpt,
            'published_at'  => (string)$wp->post_date_gmt ?: date('Y-m-d H:i:s'),
            'category_slug' => null,
            'category_name' => null,
            'thumbnail_id'  => null,
        ];

        // Kategori (domain='category', NOT 'product_cat')
        foreach ($item->category as $cat) {
            $domain   = (string)$cat['domain'];
            $nicename = (string)$cat['nicename'];
            $name     = trim((string)$cat);
            if ($domain === 'category' && $nicename) {
                if (!$post['category_slug']) {
                    $post['category_slug'] = $nicename;
                    $post['category_name'] = $name ?: $nicename;
                }
                if (!isset($categories[$nicename])) $categories[$nicename] = $name ?: $nicename;
            }
        }

        // _thumbnail_id postmeta
        foreach ($wp->postmeta as $meta) {
            if ((string)$meta->meta_key === '_thumbnail_id' && (string)$meta->meta_value !== '') {
                $post['thumbnail_id'] = (int)$meta->meta_value;
                break;
            }
        }

        $posts[] = $post;
    }
    $r->close();

    // İstatistik
    $withThumb   = count(array_filter($posts, fn($p) => $p['thumbnail_id']));
    $withoutThumb = count($posts) - $withThumb;

    return [
        'posts'               => $posts,
        'attachments'         => $attachments,
        'categories'          => $categories,
        'posts_with_thumb'    => $withThumb,
        'posts_without_thumb' => $withoutThumb,
    ];
}}

/**
 * Parse edilmiş blog verisini DB'ye yazar.
 *
 * - blog_categories: slug eşleşmesi; yoksa ekle (mevcut kategoriler korunur)
 * - blog_posts: slug çakışırsa -2/-3 ekle (VEYA skipExisting=true ise atla)
 * - wp_blog_import_queue: kapak görseli kuyruğa alınır
 *
 * @param array   $parsed       wp_blog_parse() çıktısı
 * @param bool    $skipExisting true ise aynı slug'lı yazılar atlanır (varsayılan)
 * @param int     $authorId     Yazarlara atanacak users.id (0 = NULL)
 */
if (!function_exists('wp_blog_apply')) {
function wp_blog_apply(array $parsed, bool $skipExisting = true, int $authorId = 0): array {
    $pdo   = db();
    $stats = ['posts'=>0, 'categories'=>0, 'queued_images'=>0, 'skipped'=>0, 'errors'=>[]];

    // Görsel kuyruğu tablosunu garantile
    $pdo->exec("CREATE TABLE IF NOT EXISTS wp_blog_import_queue (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        blog_post_id     INT          NOT NULL,
        wp_attachment_id INT          NOT NULL,
        url              VARCHAR(500) NOT NULL,
        status           ENUM('pending','done','failed') NOT NULL DEFAULT 'pending',
        local_path       VARCHAR(500) DEFAULT NULL,
        attempts         TINYINT      NOT NULL DEFAULT 0,
        error            VARCHAR(255) DEFAULT NULL,
        created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 1) Kategoriler — slug eşleşmesi, yoksa ekle
    $catMap  = [];
    $catFind = $pdo->prepare('SELECT id FROM blog_categories WHERE slug=? LIMIT 1');
    $catIns  = $pdo->prepare('INSERT INTO blog_categories (name, slug) VALUES (?, ?)');
    foreach ($parsed['categories'] as $slug => $name) {
        $catFind->execute([$slug]);
        $row = $catFind->fetch();
        if ($row) {
            $catMap[$slug] = (int)$row['id'];
        } else {
            $catIns->execute([$name, $slug]);
            $catMap[$slug] = (int)$pdo->lastInsertId();
            $stats['categories']++;
        }
    }

    // 2) Yazılar
    $slugChk  = $pdo->prepare('SELECT id FROM blog_posts WHERE slug=? LIMIT 1');
    $postIns  = $pdo->prepare('INSERT INTO blog_posts
        (category_id, author_id, title, slug, excerpt, content, is_published, published_at, created_at)
        VALUES (?,?,?,?,?,?,1,?,?)');
    $qIns = $pdo->prepare('INSERT INTO wp_blog_import_queue (blog_post_id, wp_attachment_id, url) VALUES (?,?,?)');

    $atts  = $parsed['attachments'];
    $total = count($parsed['posts']);
    $i     = 0;

    foreach ($parsed['posts'] as $post) {
        $i++;
        try {
            // Slug benzersizliği
            $baseSlug = $post['slug'] ?: 'yazi-' . $post['wp_id'];
            $slug     = $baseSlug;
            $n        = 1;
            while (true) {
                $slugChk->execute([$slug]);
                $exists = (bool)$slugChk->fetch();
                if (!$exists) break;
                if ($skipExisting) { $stats['skipped']++; break 2; } // yazıyı atla
                $n++; $slug = $baseSlug . '-' . $n;
            }

            $catId    = $post['category_slug'] && isset($catMap[$post['category_slug']])
                        ? $catMap[$post['category_slug']] : null;
            $authorId_ = $authorId > 0 ? $authorId : null;
            $pubAt    = $post['published_at'] ?: date('Y-m-d H:i:s');

            $postIns->execute([
                $catId,
                $authorId_,
                $post['title'],
                $slug,
                $post['excerpt'],
                $post['content'],
                $pubAt,
                $pubAt,
            ]);
            $newId = (int)$pdo->lastInsertId();
            $stats['posts']++;

            // Kapak görseli kuyruğa al
            if ($post['thumbnail_id'] && isset($atts[$post['thumbnail_id']])) {
                $qIns->execute([$newId, $post['thumbnail_id'], $atts[$post['thumbnail_id']]]);
                $stats['queued_images']++;
            }
        } catch (\Throwable $e) {
            $stats['errors'][] = ['wp_id'=>$post['wp_id'], 'title'=>$post['title'], 'msg'=>$e->getMessage()];
        }
    }

    return $stats;
}}

/**
 * Kuyruktaki blog kapak görsellerini indirir, WebP'ye çevirir, blog_posts.cover_image günceller.
 */
if (!function_exists('wp_blog_download_batch')) {
function wp_blog_download_batch(int $limit = 20): array {
    $pdo   = db();
    $stats = ['done'=>0, 'failed'=>0, 'remaining'=>0];

    $rows = $pdo->prepare("SELECT * FROM wp_blog_import_queue WHERE status='pending' ORDER BY id ASC LIMIT ?");
    $rows->bindValue(1, $limit, \PDO::PARAM_INT);
    $rows->execute();
    $batch = $rows->fetchAll();

    $upd      = $pdo->prepare("UPDATE wp_blog_import_queue SET status=?, local_path=?, attempts=attempts+1, error=? WHERE id=?");
    $postUpd  = $pdo->prepare("UPDATE blog_posts SET cover_image=? WHERE id=? AND (cover_image IS NULL OR cover_image='')");

    foreach ($batch as $row) {
        try {
            $url = $row['url'];
            $tmp = tempnam(sys_get_temp_dir(), 'wpblog_');
            $fp  = @fopen($tmp, 'wb');
            // Y-7 SSRF koruması: scheme + private IP filtresi
            if (!is_url_safe_for_fetch($url)) {
                @fclose($fp);
                @unlink($tmpPath);
                error_log('[wp_blog_importer] SSRF blocked: ' . substr($url, 0, 200));
                continue;
            }
            $ch  = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE           => $fp,
                CURLOPT_FOLLOWLOCATION => false,                      // Y-7
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS=> CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_USERAGENT      => 'AquaShop-BlogImporter/1.0',
                CURLOPT_FAILONERROR    => true,
                CURLOPT_SSL_VERIFYPEER => true,                       // O-11
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $ok   = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = $ok ? null : ('HTTP ' . $code . ' ' . curl_error($ch));
            curl_close($ch);
            fclose($fp);

            if (!$ok || filesize($tmp) === 0) {
                @unlink($tmp);
                $upd->execute(['failed', null, $err ?: 'boş yanıt', $row['id']]);
                $stats['failed']++;
                continue;
            }

            $fakeFile = [
                'tmp_name' => $tmp,
                'name'     => basename(parse_url($url, PHP_URL_PATH) ?: 'cover.jpg'),
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tmp),
            ];
            $r = media_upload_from_files($fakeFile);
            @unlink($tmp);

            if (!$r['ok']) {
                $upd->execute(['failed', null, $r['error'] ?? 'medya hatası', $row['id']]);
                $stats['failed']++;
                continue;
            }

            $localPath = $r['path'];
            $upd->execute(['done', $localPath, null, $row['id']]);
            $postUpd->execute([$localPath, $row['blog_post_id']]);
            $stats['done']++;
        } catch (\Throwable $e) {
            $upd->execute(['failed', null, substr($e->getMessage(), 0, 250), $row['id']]);
            $stats['failed']++;
        }
    }

    $rem = $pdo->query("SELECT COUNT(*) FROM wp_blog_import_queue WHERE status='pending'")->fetchColumn();
    $stats['remaining'] = (int)$rem;
    return $stats;
}}

/** Başarısız blog görsellerini yeniden kuyruğa alır. */
if (!function_exists('wp_blog_retry_failed')) {
function wp_blog_retry_failed(): int {
    $pdo = db();
    try {
        $pdo->exec("UPDATE wp_blog_import_queue SET status='pending', attempts=0, error=NULL WHERE status='failed'");
        return (int)$pdo->query("SELECT COUNT(*) FROM wp_blog_import_queue WHERE status='pending'")->fetchColumn();
    } catch (\Throwable $e) { return 0; }
}}

/** Blog görsel kuyruğu özetini döndürür. */
if (!function_exists('wp_blog_queue_stats')) {
function wp_blog_queue_stats(): array {
    $pdo = db();
    $out = ['pending'=>0, 'done'=>0, 'failed'=>0];
    try {
        $rows = $pdo->query("SELECT status, COUNT(*) c FROM wp_blog_import_queue GROUP BY status")->fetchAll();
        foreach ($rows as $r) $out[$r['status']] = (int)$r['c'];
    } catch (\Throwable $e) {}
    return $out;
}}

/** Blog yazılarını ve görsel kuyruğunu temizler (dikkatli kullanın). */
if (!function_exists('wp_blog_truncate')) {
function wp_blog_truncate(): void {
    $pdo = db();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (['blog_post_faqs','blog_posts','wp_blog_import_queue'] as $t) {
        try { $pdo->exec("TRUNCATE TABLE $t"); } catch (\Throwable $e) {}
    }
    // blog_categories'i koru — manuel eklenenler kaybolmasın
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}}

/* ─── Ürün importer (orijinal) ─────────────────────────────── */

/**
 * Mevcut ürünleri ve kategorileri temizler (kullanıcı onayıyla çağrılır).
 * order_items, favorites, cart_items vb. bağlı tablolar varsa CASCADE ile temizlenir.
 */
if (!function_exists('wp_importer_truncate')) {
function wp_importer_truncate(): void {
    $pdo = db();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (['product_images','products','wp_import_queue'] as $t) {
        try { $pdo->exec("TRUNCATE TABLE $t"); } catch (\Throwable $e) {}
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}}
