<?php
/**
 * AI Danışman — Araç (tool) katmanı.
 *
 * Claude'a verilen YALNIZCA-OKUMA araçlarının gerçek implementasyonu.
 * Kritik güvenlik kuralı: Buradaki hiçbir fonksiyon yazma/güncelleme yapmaz,
 * admin verisine / başka müşterinin verisine / kupon-fiyat değiştirmeye erişmez.
 * Sadece herkese açık, satışa uygun ürün/kategori bilgisini döndürür.
 *
 * Linkler url() ile üretilir → AJAX isteği aynı domain'den geldiği için
 * çoklu-domain kurulumlarda bile doğru domain'e işaret eder (link uydurulamaz).
 */
require_once __DIR__ . '/../includes/functions.php';

if (!function_exists('assistant_tool_definitions')) {
    /**
     * Anthropic Messages API'sine gönderilecek araç tanımları (JSON schema).
     */
    function assistant_tool_definitions(): array {
        return [
            [
                'name'        => 'search_products',
                'description' => 'Mağazadaki ürünleri adı, markası, kategorisi veya açıklamasına göre arar. '
                    . 'Müşteri bir ürün önerisi istediğinde veya belirli bir ürünü/markayı/kullanım amacını sorduğunda KULLAN. '
                    . 'Canlı fiyat, stok durumu ve ürün linkini döndürür. Yalnızca buradan dönen ürünleri öner.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'query' => [
                            'type'        => 'string',
                            'description' => 'Aranacak anahtar kelime(ler). Örn: "beta yemi", "5 litre akvaryum", "su şartlandırıcı". Kısa ve öz tut.',
                        ],
                        'category' => [
                            'type'        => 'string',
                            'description' => 'İsteğe bağlı kategori adı ile daraltma. Örn: "Yem", "Akvaryum". Emin değilsen boş bırak.',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name'        => 'list_categories',
                'description' => 'Mağazadaki ürün kategorilerinin listesini döndürür. '
                    . 'Müşteri "neler satıyorsunuz", "hangi kategoriler var" gibi sorduğunda veya doğru arama terimini bulmak için kullan.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
            [
                'name'        => 'search_blog',
                'description' => 'Mağazanın blog/rehber yazılarında arar (kurulum, bakım, ipuçları, "nasıl yapılır" içerikleri). '
                    . 'Müşteriye konuyu açıklayan faydalı bir rehber önermek veya ürün önerisini destekleyen bilgi yazısı bulmak için kullan.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'query' => [
                            'type'        => 'string',
                            'description' => 'Aranacak konu. Örn: "akvaryum kurulumu", "su değişimi", "beta bakımı".',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
        ];
    }
}

if (!function_exists('assistant_run_tool')) {
    /**
     * Bir tool_use bloğunu çalıştırır.
     * Döndürür: ['products'=>[...kart], 'articles'=>[...blog kart], 'content'=>'...model için JSON metin']
     */
    function assistant_run_tool(string $name, array $input): array {
        switch ($name) {
            case 'search_products':
                $q   = trim((string)($input['query'] ?? ''));
                $cat = trim((string)($input['category'] ?? ''));
                return assistant_search_products($q, $cat !== '' ? $cat : null);
            case 'list_categories':
                return assistant_list_categories();
            case 'search_blog':
                return assistant_search_blog(trim((string)($input['query'] ?? '')));
            default:
                return ['products' => [], 'articles' => [], 'content' => json_encode(['error' => 'Bilinmeyen araç.'], JSON_UNESCAPED_UNICODE)];
        }
    }
}

if (!function_exists('assistant_search_products')) {
    /**
     * Ürün araması (canlı). is_active=1 ve silinmemiş ürünler.
     */
    function assistant_search_products(string $query, ?string $category = null, int $limit = 8): array {
        $query = mb_substr(trim($query), 0, 80);
        if ($query === '') {
            return ['products' => [], 'content' => json_encode(['products' => [], 'note' => 'Arama terimi boş.'], JSON_UNESCAPED_UNICODE)];
        }
        $limit = max(1, min(12, $limit));
        $like  = '%' . $query . '%';

        $sql = "SELECT p.id, p.name, p.slug, p.brand, p.short_desc, p.description, p.price, p.old_price,
                       p.price_on_request, p.stock, p.image, p.has_variations,
                       c.name AS category_name
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.is_active = 1 AND p.deleted_at IS NULL
                  AND (p.name LIKE ? OR p.brand LIKE ? OR p.short_desc LIKE ?
                       OR p.description LIKE ? OR c.name LIKE ?)";
        $params = [$like, $like, $like, $like, $like];

        if ($category !== null && $category !== '') {
            $sql .= " AND c.name LIKE ?";
            $params[] = '%' . mb_substr($category, 0, 60) . '%';
        }
        // stoktakiler ve öne çıkanlar önce; LIMIT int cast ile gömülü (PDO emulate=off uyumu)
        $sql .= " ORDER BY (p.stock > 0) DESC, p.is_featured DESC, p.id DESC LIMIT " . (int)$limit;

        $rich = [];   // widget kartları için zengin set
        $lite = [];   // model için kompakt set
        try {
            $st = db()->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll();
        } catch (\Throwable $e) {
            error_log('[assistant] search_products failed: ' . $e->getMessage());
            $rows = [];
        }

        $lowStock = max(1, (int)setting('low_stock_threshold', '5'));

        foreach ($rows as $p) {
            $priceVal      = (float)($p['price'] ?? 0);
            $onRequest     = !empty($p['price_on_request']) && $priceVal <= 0;
            $hasVariations = !empty($p['has_variations']);
            $stock         = (int)($p['stock'] ?? 0);

            // Stok durumu (varyasyonlu ürünlerde ürün-seviyesi stok güvenilmez → "seçenekler mevcut")
            if ($onRequest) {
                $inStock   = true;  // mağaza/iletişim üzerinden satılabilir
                $stockNote = 'Online satışa kapalı (iletişim)';
            } elseif ($hasVariations) {
                $inStock   = true;
                $stockNote = 'Seçenekler mevcut';
            } elseif ($stock <= 0) {
                $inStock   = false;
                $stockNote = 'Tükendi';
            } elseif ($stock <= $lowStock) {
                $inStock   = true;
                $stockNote = 'Son ' . $stock . ' adet';
            } else {
                $inStock   = true;
                $stockNote = 'Stokta';
            }

            $priceLabel = $onRequest ? 'İletişime Geçin' : money($priceVal);
            $url        = url('product', ['slug' => $p['slug']]);
            // Modele uzun açıklamayı (description) ver — "100 litreye kadar uygun" gibi
            // teknik uygunluk bilgisi burada olur, doğru eşleştirme için kritik.
            $longDesc   = trim((string)($p['description'] ?? ''));
            $shortDesc  = trim((string)($p['short_desc'] ?? ''));
            $descModel  = assistant_plain_excerpt($longDesc !== '' ? $longDesc : $shortDesc, 460);

            // Widget kartı (zengin)
            $rich[] = [
                'id'          => (int)$p['id'],
                'name'        => $p['name'],
                'brand'       => $p['brand'] ?: null,
                'price'       => $priceLabel,
                'old_price'   => (!$onRequest && !empty($p['old_price']) && (float)$p['old_price'] > $priceVal) ? money($p['old_price']) : null,
                'image'       => $p['image'] ?: null,
                'url'         => $url,
                'in_stock'    => $inStock,
                'stock_note'  => $stockNote,
                'online_sale' => !$onRequest,
            ];

            // Model bağlamı (kompakt — token tasarrufu).
            // NOT: 'url' bilinçli olarak YOK — link/kart işini arayüz yapar, model
            // metne ham link yazmasın (aksi halde "[/urun/x](/urun/x)" gibi görünür).
            $lite[] = [
                'name'        => $p['name'],
                'brand'       => $p['brand'] ?: null,
                'category'    => $p['category_name'] ?: null,
                'price'       => $priceLabel,
                'stock'       => $stockNote,
                'online_sale' => !$onRequest,
                'desc'        => $descModel,
            ];
        }

        $content = json_encode([
            'products' => $lite,
            'note'     => empty($lite) ? 'Bu aramayla eşleşen ürün bulunamadı.' : null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return ['products' => $rich, 'articles' => [], 'content' => $content];
    }
}

if (!function_exists('assistant_list_categories')) {
    /**
     * Aktif kategori listesi (ürünü olan kategoriler önce).
     */
    function assistant_list_categories(int $limit = 40): array {
        $limit = max(1, min(80, $limit));
        try {
            $st = db()->query(
                "SELECT c.name, c.slug, COUNT(p.id) AS cnt
                 FROM categories c
                 LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1 AND p.deleted_at IS NULL
                 GROUP BY c.id, c.name, c.slug
                 ORDER BY cnt DESC, c.name ASC
                 LIMIT " . (int)$limit
            );
            $rows = $st->fetchAll();
        } catch (\Throwable $e) {
            error_log('[assistant] list_categories failed: ' . $e->getMessage());
            $rows = [];
        }

        $cats = [];
        foreach ($rows as $c) {
            $cats[] = [
                'name'  => $c['name'],
                'url'   => url('category', ['slug' => $c['slug']]),
                'count' => (int)$c['cnt'],
            ];
        }
        return [
            'products' => [],
            'articles' => [],
            'content'  => json_encode(['categories' => $cats], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }
}

if (!function_exists('assistant_plain_excerpt')) {
    /**
     * Markdown/HTML açıklamayı düz metne çevirir (token tasarrufu + temiz bağlam).
     * Tire ve sayıları KORUR ("100-200 litre", "50 litre" gibi teknik bilgi bozulmasın).
     */
    function assistant_plain_excerpt(string $text, int $max = 460): string {
        $t = (string)$text;
        $t = preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $t); // [metin](url) → metin
        $t = strip_tags($t);                                      // olası HTML
        $t = preg_replace('/[*_`#>]+/u', ' ', $t);                // md işaretleri (tire HARİÇ)
        $t = preg_replace('/\s+/u', ' ', $t);                     // boşlukları sıkıştır
        $t = trim($t);
        if (mb_strlen($t, 'UTF-8') > $max) {
            $t = rtrim(mb_substr($t, 0, $max, 'UTF-8')) . '…';
        }
        return $t;
    }
}

if (!function_exists('assistant_search_blog')) {
    /**
     * Blog/rehber yazısı araması (canlı). Yayınlanmış yazılar.
     */
    function assistant_search_blog(string $query, int $limit = 4): array {
        $query = mb_substr(trim($query), 0, 80);
        if ($query === '') {
            return ['products' => [], 'articles' => [], 'content' => json_encode(['articles' => [], 'note' => 'Arama terimi boş.'], JSON_UNESCAPED_UNICODE)];
        }
        $limit = max(1, min(6, $limit));
        $like  = '%' . $query . '%';

        try {
            $st = db()->prepare(
                "SELECT title, slug, excerpt
                 FROM blog_posts
                 WHERE is_published = 1
                   AND (title LIKE ? OR excerpt LIKE ? OR content LIKE ?)
                 ORDER BY published_at DESC
                 LIMIT " . (int)$limit
            );
            $st->execute([$like, $like, $like]);
            $rows = $st->fetchAll();
        } catch (\Throwable $e) {
            error_log('[assistant] search_blog failed: ' . $e->getMessage());
            $rows = [];
        }

        $rich = []; $lite = [];
        foreach ($rows as $b) {
            $excerpt = assistant_plain_excerpt((string)($b['excerpt'] ?? ''), 200);
            $url     = url('blog_post', ['slug' => $b['slug']]);
            $rich[] = ['title' => $b['title'], 'excerpt' => $excerpt, 'url' => $url];
            $lite[] = ['title' => $b['title'], 'excerpt' => $excerpt];
        }

        $content = json_encode([
            'articles' => $lite,
            'note'     => empty($lite) ? 'Bu konuyla eşleşen blog yazısı bulunamadı.' : null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return ['products' => [], 'articles' => $rich, 'content' => $content];
    }
}
