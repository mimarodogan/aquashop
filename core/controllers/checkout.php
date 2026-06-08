<?php
/**
 * Ödeme sayfası — controller.
 * Form gönderildiğinde sipariş oluşturur, iyzico veya havale ile ödemeye yönlendirir.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/iyzico.php';
require_once __DIR__ . '/../../includes/pricing.php';

$page = 'checkout'; $title = 'Ödeme';
$items = cart_items();
if (!$items) { redirect(url('cart')); }
$user = current_user();
$err = null;
$iyzCfg = iyzico_options();
$pr = cart_pricing();

// Kullanıcının kayıtlı adresleri (varsa)
$savedAddresses = [];
if ($user) {
    try {
        $st = db()->prepare('SELECT * FROM user_addresses WHERE user_id=? ORDER BY is_default DESC, id DESC');
        $st->execute([$user['id']]);
        $savedAddresses = $st->fetchAll();
    } catch (\Throwable $e) {}
}

// Hangi ödeme yöntemleri sunulacak
$methods = [];
if ($iyzCfg['enabled']) $methods['kart']   = 'Kredi / Banka Kartı (Güvenli Ödeme)';
$methods['havale'] = 'Havale / EFT';

// İframe modu için: form gönderildi mı, içerik hazır mı?
$checkoutHtml = null;

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? null)) {
    $name=trim($_POST['name']??''); $email=trim($_POST['email']??''); $phone=trim($_POST['phone']??'');
    $addr=trim($_POST['address']??''); $city=trim($_POST['city']??''); $note=trim($_POST['note']??'');
    $pay = $_POST['pay'] ?? 'havale';
    // Hediye paketi (Faz 6.D)
    $giftWrap     = (setting('gift_wrap_enabled','0') === '1' && !empty($_POST['gift_wrap'])) ? 1 : 0;
    $giftWrapNote = $giftWrap ? trim(substr((string)($_POST['gift_wrap_note'] ?? ''), 0, 255)) : '';
    $giftWrapPrice= $giftWrap ? (float)setting('gift_wrap_price','25') : 0;
    if ($giftWrap) {
        // Grand total'a ekle (pricing'i bypass'lamamak için manuel)
        $pr['grand_total'] = (float)$pr['grand_total'] + $giftWrapPrice;
    }
    // Fatura tipi
    $invoiceType = ($_POST['invoice_type'] ?? 'individual') === 'company' ? 'company' : 'individual';
    $invCompany = trim($_POST['invoice_company'] ?? '');
    $invTaxNo   = trim($_POST['invoice_tax_no'] ?? '');
    $invTaxOff  = trim($_POST['invoice_tax_office'] ?? '');
    if (!array_key_exists($pay, $methods)) $pay = 'havale';

    $consents = !empty($_POST['agree_preinfo']) && !empty($_POST['agree_contract']) && !empty($_POST['agree_kvkk']);
    if (!$name || !$email || !$phone || !$addr || !$city) {
        $err='Lütfen tüm zorunlu alanları doldurun.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err='Geçerli bir e-posta girin.';
    } elseif (!$consents) {
        $err='Devam etmek için yasal onayların hepsini işaretlemelisiniz (Ön bilgilendirme, Mesafeli satış sözleşmesi, KVKK).';
    } elseif ($invoiceType==='company' && (!$invCompany || !$invTaxNo || !$invTaxOff)) {
        $err='Şirket faturası için ünvan, vergi numarası ve vergi dairesi zorunludur.';
    } else {
        // Stok altyapısını transaction DIŞINDA hazırla (DDL transaction'ı implicit commit etmesin)
        require_once __DIR__ . '/../../includes/stock.php';
        stock_ensure_table();
        try { db()->exec("ALTER TABLE order_items ADD COLUMN IF NOT EXISTS stock_applied_at DATETIME DEFAULT NULL"); } catch (\Throwable $e) {}
        // Süresi dolmuş (terk edilmiş) kart siparişlerinin tuttuğu stoğu önce serbest bırak
        if (function_exists('orders_cancel_stale_card')) orders_cancel_stale_card(30);

        try {
            $pdo = db(); $pdo->beginTransaction();

            // Sipariş oluştur — payment_status: havale → pending, kart → pending (ödeme bitince paid)
            $st = $pdo->prepare("INSERT INTO orders
                (user_id,full_name,email,phone,address,city,total,status,payment_method,payment_status,note,
                 invoice_type,invoice_company,invoice_tax_no,invoice_tax_office,
                 subtotal,vat_amount,shipping_amount,coupon_code,discount_amount,
                 gift_wrap_price,gift_wrap_note)
                VALUES (?,?,?,?,?,?,?, 'pending', ?, 'pending', ?, ?,?,?,?, ?,?,?,?,?, ?,?)");
            $st->execute([
                $user['id']??null,$name,$email,$phone,$addr,$city,$pr['grand_total'],$pay,$note,
                $invoiceType, $invCompany ?: null, $invTaxNo ?: null, $invTaxOff ?: null,
                $pr['subtotal'], $pr['vat'], $pr['shipping'],
                $pr['coupon_code'], $pr['discount'],
                $giftWrapPrice, $giftWrapNote ?: null
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $stI = $pdo->prepare("INSERT INTO order_items (order_id,product_id,variation_id,variation_label,product_name,qty,price) VALUES (?,?,?,?,?,?,?)");
            foreach ($items as $it) $stI->execute([
                $orderId,$it['id'],
                $it['variant_id'] ?? null,
                $it['variant_label'] ?? null,
                $it['name'],$it['qty'],$it['price']
            ]);

            // STOK REZERVASYONU — atomik & race-safe. Ödeme penceresi boyunca stok bu siparişe
            // kilitlenir; başkası son ürünü alamaz. Yetersizse rollback + kullanıcıya net hata.
            $rsv = stock_reserve_for_order($orderId);
            if (!$rsv['ok']) { throw new \RuntimeException($rsv['error']); }

            $pdo->commit();

            // Giriş yapmış müşteri için: profildeki eksik bilgileri tamamla ve yeni adresi
            // adres defterine kaydet (bir dahaki siparişte tekrar yazmasın). Hatalar siparişi etkilemez.
            if ($user) {
                try {
                    // Profilde telefon/adres boşsa, siparişte girileni doldur (mevcutu ezme)
                    if (empty($user['phone']) && $phone !== '') {
                        db()->prepare('UPDATE users SET phone=? WHERE id=? AND (phone IS NULL OR phone="")')->execute([$phone, $user['id']]);
                    }
                    if (empty($user['address']) && $addr !== '') {
                        db()->prepare('UPDATE users SET address=? WHERE id=? AND (address IS NULL OR address="")')->execute([$addr, $user['id']]);
                    }
                } catch (\Throwable $e) {}

                // Adres defterine kaydet — kullanıcı kutuyu işaretlediyse ve aynı adres henüz kayıtlı değilse
                if (!empty($_POST['save_address'])) {
                    try {
                        $aLabel = trim((string)($_POST['address_label'] ?? '')) ?: 'Ev';
                        $aZip   = trim((string)($_POST['zip'] ?? '')) ?: null;
                        $dup = db()->prepare('SELECT id FROM user_addresses WHERE user_id=? AND address=? AND city=? LIMIT 1');
                        $dup->execute([$user['id'], $addr, $city]);
                        if (!$dup->fetch()) {
                            // İlk adresse varsayılan yap
                            $cntSt = db()->prepare('SELECT COUNT(*) FROM user_addresses WHERE user_id=?');
                            $cntSt->execute([$user['id']]);
                            $isDef = ((int)$cntSt->fetchColumn() === 0) ? 1 : 0;
                            db()->prepare('INSERT INTO user_addresses (user_id,label,full_name,phone,address,city,zip,is_default) VALUES (?,?,?,?,?,?,?,?)')
                                ->execute([$user['id'], $aLabel, $name, $phone, $addr, $city, $aZip, $isDef]);
                        }
                    } catch (\Throwable $e) {}
                }
            }

            // Sadakat puanı kullanıldıysa düş ve siparişe işle (sipariş anında — stok rezervasyonu gibi).
            // Ödeme başarısız/iptal olursa loyalty_revoke_for_order ile geri verilir.
            if ($user && !empty($pr['loyalty_points'])) {
                require_once __DIR__ . '/../../models/Loyalty.php';
                @loyalty_redeem((int)$user['id'], (int)$pr['loyalty_points'], $orderId);
                unset($_SESSION['cart_points']);
            }

            if ($pay === 'kart') {
                // iyzico Checkout Form başlat — toplam = ürün+KDV+kargo
                $orderRow = ['total'=>$pr['grand_total']];
                $billing = [
                    'name'=>$name, 'email'=>$email, 'phone'=>$phone,
                    'address'=>$addr, 'city'=>$city, 'zip'=>$_POST['zip'] ?? '',
                    'identity'=>preg_replace('/\D+/','', $_POST['identity'] ?? ''),
                    'user_id'=>$user['id'] ?? null,
                ];
                $r = iyzico_init_checkout($orderId, $orderRow, $items, $billing);
                if (!$r['ok']) {
                    $err = 'Ödeme başlatılamadı: ' . $r['error'];
                    // Ödeme hiç başlayamadı → rezerve stoğu HEMEN geri aç ve siparişi iptal et.
                    // Aksi halde kullanıcı tekrar denediğinde kendi başarısız siparişi stoğu kilitli tutar.
                    try {
                        stock_revert_order($orderId, 'payment_init_failed', true);
                        require_once __DIR__ . '/../../models/Loyalty.php';
                        @loyalty_revoke_for_order($orderId); // kullanılan puanı geri ver
                        db()->prepare("UPDATE orders SET status='cancelled', payment_status='failed' WHERE id=?")->execute([$orderId]);
                    } catch (\Throwable $e) {}
                } else {
                    // Sepet ödeme bitince callback'te temizlenecek
                    $_SESSION['pending_order_id'] = $orderId;
                    // En güvenilir yol: iyzico hosted sayfasına yönlendir (CSP/embed sorunu yaşanmaz)
                    if (!empty($r['paymentPageUrl'])) {
                        header('Location: ' . $r['paymentPageUrl']);
                        exit;
                    }
                    // Yedek: ana sayfada embed dene
                    $checkoutHtml = $r['checkoutFormContent'];
                }
            } else {
                // Havale yolu: sipariş alındı, manuel onay bekliyor.
                // (Stok yukarıda stock_reserve_for_order ile zaten rezerve edildi — burada tekrar düşülmez.)
                // Kupon kullanımını işle
                if (!empty($pr['coupon_id']) && $pr['discount'] > 0) {
                    require_once __DIR__ . '/../../includes/coupons.php';
                    coupon_redeem((int)$pr['coupon_id'], $orderId, (float)$pr['discount'], $user['id'] ?? null);
                    unset($_SESSION['cart_coupon']);
                }
                // Sipariş onay maili (havale bilgileri içerir)
                require_once __DIR__ . '/../../includes/order_mailer.php';
                @order_send_confirmation($orderId);

                cart_clear();
                cart_abandon_clear(); // terk edilmiş sepet kaydını sil
                unset($_SESSION['quick_checkout']);
                // GA4 purchase event'i order.php'de tek seferlik basılsın diye işaret bırak
                $_SESSION['ga_pending_purchase'] = $orderId;
                flash_set('success','Siparişiniz alındı. Sipariş No: #' . $orderId . ' — Havale bilgileri e-posta ile gönderildi.');
                redirect(url('order', ['id'=>$orderId]));
            }
        } catch (\PDOException $e) {
            // O-2 GÜVENLİK: ham PDO mesajı (tablo/sütun/kısıt adı) kullanıcıya gitmesin
            if (db()->inTransaction()) db()->rollBack();
            error_log('[checkout] PDOException: ' . $e->getMessage());
            $err = 'Sipariş oluşturulamadı. Lütfen daha sonra tekrar deneyin.';
        } catch (\RuntimeException $stockErr) {
            // Stok yetersiz (rezervasyon başarısız) — kullanıcıya net mesaj
            if (db()->inTransaction()) db()->rollBack();
            $err = $stockErr->getMessage();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            error_log('[checkout] Throwable: ' . $e->getMessage());
            $err = 'Sipariş oluşturulamadı. Lütfen daha sonra tekrar deneyin.';
        }
    }
}

