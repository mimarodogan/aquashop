<?php
/**
 * Admin sidebar bildirim sayaçları.
 *
 * Her sayfa yüklenmesinde 4 hızlı COUNT(*) çalıştırır (~5ms toplam).
 * Tablo yoksa veya sorgu başarısız olursa 0 döner — hiçbir admin sayfasını kırmaz.
 */

/**
 * Yarım kalan / başarısız kart ödemelerini otomatik iptal eder.
 *
 * Kart siparişi, ödeme öncesi 'pending' olarak oluşturulur ve müşteri iyzico'ya
 * yönlendirilir. Müşteri ödemeyi tamamlamazsa (vazgeçer, tarayıcıyı kapatır,
 * callback hiç gelmez) sipariş 'pending' kalır ve admin panelinde "Bekleyen
 * Sipariş" olarak takılı görünür. iyzico ödeme oturumu ~30dk'da dolduğundan
 * 1 saatten eski + ödenmemiş kart siparişleri kesinlikle terk edilmiştir.
 *
 * - Havale siparişlerine DOKUNMAZ (onlar gerçekten ödeme bekliyor).
 * - Kart 'pending' siparişlerinde stok düşmediği için stok iadesi gerekmez.
 * - Kolon yoksa / tablo yoksa sessizce geçer (hiçbir admin sayfasını kırmaz).
 */
function orders_cancel_stale_unpaid(int $olderThanMinutes = 30): void {
    // Stok-farkındalıklı sürüm: terk edilen kart siparişini iptal eder VE rezerve edilen
    // stoğu sessizce geri açar (oversell rezervasyonu serbest kalsın).
    require_once __DIR__ . '/../../includes/stock.php';
    if (function_exists('orders_cancel_stale_card')) {
        orders_cancel_stale_card($olderThanMinutes);
        return;
    }
    // Yedek (stock.php yüklenemediyse): yalnızca status güncelle
    $mins  = max(1, $olderThanMinutes);
    $where = "payment_method='kart' AND status='pending'
                AND payment_status IN ('pending','failed')
                AND created_at < DATE_SUB(NOW(), INTERVAL {$mins} MINUTE)";
    try {
        db()->exec("UPDATE orders SET status='cancelled', cancelled_at=NOW(),
                    cancellation_reason='Ödeme tamamlanmadı (otomatik iptal)' WHERE {$where}");
    } catch (\Throwable $e) {
        try { db()->exec("UPDATE orders SET status='cancelled' WHERE {$where}"); }
        catch (\Throwable $e2) { /* tablo yoksa sessizce geç */ }
    }
}

function admin_sidebar_counts(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    // Sayımdan ÖNCE yarım kalan kart ödemelerini temizle → hem rozet hem sipariş listesi
    // tutarlı olur, hem de terk edilmiş ödemenin tuttuğu stok serbest kalır (30 dk sonra).
    orders_cancel_stale_unpaid(30);

    $cache = [
        'pending_orders'    => 0,
        'paid_orders'       => 0,
        'pending_reviews'   => 0,
        'pending_comments'  => 0,
        'pending_questions' => 0,
        'unread_messages'   => 0,
        'product_trash'     => 0,
    ];

    $queries = [
        'pending_orders'    => "SELECT COUNT(*) FROM orders WHERE status='pending'",
        // Ödemesi alınmış ama henüz kargoya verilmemiş siparişler (hazırlanacak)
        'paid_orders'       => "SELECT COUNT(*) FROM orders WHERE status='paid'",
        'pending_reviews'   => "SELECT COUNT(*) FROM product_reviews WHERE is_approved=0",
        'pending_comments'  => "SELECT COUNT(*) FROM comments WHERE is_approved=0",
        'pending_questions' => "SELECT COUNT(*) FROM product_questions WHERE is_approved=0",
        'unread_messages'   => "SELECT COUNT(*) FROM contact_messages WHERE is_read=0",
        'product_trash'     => "SELECT COUNT(*) FROM products WHERE deleted_at IS NOT NULL",
    ];

    foreach ($queries as $key => $sql) {
        try {
            $cache[$key] = (int) db()->query($sql)->fetchColumn();
        } catch (Throwable $e) {
            // Tablo henüz yoksa sessizce 0
        }
    }

    return $cache;
}

/** Bekleyen yorumların toplamı (ürün + blog) — hızlı erişim butonu için */
function admin_pending_reviews_total(): int {
    $c = admin_sidebar_counts();
    return $c['pending_reviews'] + $c['pending_comments'];
}

/** Bekleyen soruların sayısı */
function admin_pending_questions(): int {
    return admin_sidebar_counts()['pending_questions'];
}
