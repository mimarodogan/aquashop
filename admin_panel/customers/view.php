<?php
$page='customers'; $title='Müşteri Detayı';
require_once __DIR__ . '/../core/auth.php';

$id = (int)($_GET['id'] ?? 0);
$u  = db()->prepare('SELECT * FROM users WHERE id = ?');
$u->execute([$id]);
$user = $u->fetch();
if (!$user) {
    http_response_code(404);
    $title = 'Müşteri Bulunamadı';
    require_once __DIR__ . '/../core/header.php';
    echo '<div class="panel"><h3>Müşteri bulunamadı</h3><p class="muted">ID: ' . (int)$id . '</p><a class="btn btn-secondary" href="list.php">← Müşteri Listesi</a></div>';
    require_once __DIR__ . '/../core/footer.php';
    exit;
}

$title = $user['name'] ?: $user['email'];

/* ─── Sipariş istatistikleri ─────────────────────────────────────────── */
$paidWhere = "status IN ('paid','shipped','delivered')";
$stats = db()->prepare(
    "SELECT COUNT(*) AS total_orders,
            COALESCE(SUM(total),0) AS lifetime_value,
            COALESCE(AVG(total),0) AS avg_order,
            MIN(created_at) AS first_order_at,
            MAX(created_at) AS last_order_at,
            SUM(CASE WHEN $paidWhere THEN 1 ELSE 0 END) AS paid_orders,
            SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled_orders
     FROM orders WHERE user_id = ?"
);
$stats->execute([$id]);
$s = $stats->fetch() ?: [];

/* ─── Tüm siparişler ───────────────────────────────────────────────── */
$ordersSt = db()->prepare(
    "SELECT id, total, status, payment_method, payment_status, created_at, coupon_code, discount_amount
     FROM orders WHERE user_id = ? ORDER BY created_at DESC"
);
$ordersSt->execute([$id]);
$orders = $ordersSt->fetchAll();

/* ─── Yorumlar ─────────────────────────────────────────────────────── */
$reviewsSt = db()->prepare(
    "SELECT pr.*, p.name AS product_name, p.slug AS product_slug
     FROM product_reviews pr
     LEFT JOIN products p ON p.id = pr.product_id
     WHERE pr.user_id = ?
     ORDER BY pr.created_at DESC LIMIT 20"
);
try { $reviewsSt->execute([$id]); $reviews = $reviewsSt->fetchAll(); }
catch (\Throwable $e) { $reviews = []; }

/* ─── Favoriler ────────────────────────────────────────────────────── */
$favsSt = db()->prepare(
    "SELECT p.id, p.name, p.slug, p.image, p.price
     FROM favorites f
     JOIN products p ON p.id = f.product_id
     WHERE f.user_id = ?
     ORDER BY f.created_at DESC LIMIT 12"
);
try { $favsSt->execute([$id]); $favs = $favsSt->fetchAll(); }
catch (\Throwable $e) { $favs = []; }

/* ─── Adresler ─────────────────────────────────────────────────────── */
$addrSt = db()->prepare('SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC');
try { $addrSt->execute([$id]); $addresses = $addrSt->fetchAll(); }
catch (\Throwable $e) { $addresses = []; }

/* ─── Kupon kullanım geçmişi ───────────────────────────────────────── */
$couponsSt = db()->prepare(
    "SELECT cr.*, c.code, c.type, c.amount
     FROM coupon_redemptions cr
     LEFT JOIN coupons c ON c.id = cr.coupon_id
     WHERE cr.user_id = ? ORDER BY cr.created_at DESC LIMIT 10"
);
try { $couponsSt->execute([$id]); $couponUses = $couponsSt->fetchAll(); }
catch (\Throwable $e) { $couponUses = []; }

/* ─── İletişim mesajları (varsa) ───────────────────────────────────── */
$messagesSt = db()->prepare("SELECT * FROM contact_messages WHERE email = ? ORDER BY created_at DESC LIMIT 5");
try { $messagesSt->execute([$user['email']]); $messages = $messagesSt->fetchAll(); }
catch (\Throwable $e) { $messages = []; }

/* ─── Sadakat (Loyalty) bakiyesi + son işlemler ────────────────────── */
$loyaltyBalance = function_exists('loyalty_balance') ? loyalty_balance($id) : 0;
$loyaltyValue   = function_exists('loyalty_value_of') ? loyalty_value_of($loyaltyBalance) : 0;
$loyaltyHistory = function_exists('loyalty_history') ? loyalty_history($id, 10) : [];

/* ─── Bu müşterinin telefonuna gönderilen SMS'ler ──────────────────── */
$smsLog = [];
if (!empty($user['phone'])) {
    $normalized = preg_replace('/\D+/', '', $user['phone']);
    // 5XX → 905XX dönüşümü (sms_normalize_phone ile aynı mantık)
    if (strlen($normalized) === 10 && $normalized[0] === '5') $normalized = '90' . $normalized;
    elseif (strlen($normalized) === 11 && substr($normalized, 0, 2) === '05') $normalized = '90' . substr($normalized, 1);
    try {
        $smsSt = db()->prepare("SELECT * FROM sms_log WHERE recipient = ? ORDER BY sent_at DESC LIMIT 10");
        $smsSt->execute([$normalized]);
        $smsLog = $smsSt->fetchAll();
    } catch (\Throwable $e) {}
}

/* ─── Bu müşteri için otomatik üretilmiş kuponlar (CARTBACK/WELCOME/BDAY) ─ */
$autoCoupons = [];
try {
    $acSt = db()->prepare(
        "SELECT id, code, type, amount, starts_at, ends_at, enabled, usage_count, notes
         FROM coupons
         WHERE notes LIKE ? OR notes LIKE ?
         ORDER BY id DESC LIMIT 10"
    );
    $acSt->execute(['%' . $user['email'] . '%', '%user #' . $id . '%']);
    $autoCoupons = $acSt->fetchAll();
} catch (\Throwable $e) {}

require_once __DIR__ . '/../core/header.php';

$AP = SITE_URL . '/admin_panel';
?>

<style>
.c360-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:18px}
.c360-kpi{padding:14px 16px;background:var(--olive-2);border:1px solid var(--gold-border);border-radius:10px}
.c360-kpi .lbl{font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted-text);margin-bottom:6px}
.c360-kpi .val{font-size:20px;color:var(--gold);font-weight:600;font-family:Georgia,serif;line-height:1.1}
.c360-kpi .hint{font-size:11px;color:var(--muted-text);margin-top:4px}
.c360-grid2{display:grid;grid-template-columns:2fr 1fr;gap:18px;margin-bottom:18px}
@media(max-width:980px){.c360-grid2{grid-template-columns:1fr}}
.c360-section{margin-bottom:18px}
.c360-section h3{font-size:13px;letter-spacing:.18em;text-transform:uppercase;color:var(--gold);margin:0 0 12px;font-family:Georgia,serif}
.fav-row{display:flex;gap:10px;overflow-x:auto;padding:4px 0}
.fav-thumb{flex:0 0 100px;display:flex;flex-direction:column;text-align:center}
.fav-thumb img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:6px;border:1px solid rgba(255,255,255,.06)}
.fav-thumb .nm{font-size:11px;color:var(--muted-text);margin-top:4px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.review-card{padding:10px 14px;border:1px solid rgba(255,255,255,.06);border-radius:8px;margin-bottom:8px}
.review-card .stars{color:#c8b560;letter-spacing:.05em;font-size:13px}
</style>

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:18px">
  <div>
    <h2 style="margin:0 0 6px;font-family:Georgia,serif;color:var(--gold)"><?= e($user['name'] ?: 'İsimsiz') ?></h2>
    <p class="muted" style="font-size:13px;margin:0"><?= e($user['email']) ?> · <?= e($user['phone'] ?? '—') ?> · <?= e(role_label($user['role'])) ?> · Kayıt: <?= e($user['created_at']) ?></p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn btn-secondary btn-sm" href="edit.php?id=<?= (int)$id ?>">Düzenle</a>
    <a class="btn btn-secondary btn-sm" href="list.php">← Listeye Dön</a>
  </div>
</div>

<!-- KPI'lar -->
<div class="c360-grid">
  <div class="c360-kpi"><div class="lbl">LTV (Yaşam Boyu Değer)</div><div class="val"><?= money($s['lifetime_value'] ?? 0) ?></div><div class="hint"><?= (int)($s['paid_orders'] ?? 0) ?> onaylı sipariş</div></div>
  <div class="c360-kpi"><div class="lbl">Toplam Sipariş</div><div class="val"><?= (int)($s['total_orders'] ?? 0) ?></div><div class="hint"><?= (int)($s['cancelled_orders'] ?? 0) ?> iptal</div></div>
  <div class="c360-kpi"><div class="lbl">Ort. Sipariş Tutarı</div><div class="val"><?= money($s['avg_order'] ?? 0) ?></div></div>
  <?php if (function_exists('loyalty_enabled') && loyalty_enabled()): ?>
  <div class="c360-kpi"><div class="lbl">🏆 Puan Bakiyesi</div><div class="val"><?= number_format($loyaltyBalance,0,',','.') ?></div><div class="hint">≈ <?= money($loyaltyValue) ?> indirim</div></div>
  <?php endif; ?>
  <div class="c360-kpi"><div class="lbl">İlk Sipariş</div><div class="val" style="font-size:14px;font-family:'Inter',sans-serif"><?= !empty($s['first_order_at']) ? e(substr($s['first_order_at'],0,10)) : '—' ?></div></div>
  <div class="c360-kpi"><div class="lbl">Son Sipariş</div><div class="val" style="font-size:14px;font-family:'Inter',sans-serif"><?= !empty($s['last_order_at']) ? e(substr($s['last_order_at'],0,10)) : '—' ?></div></div>
</div>

<!-- Siparişler + Adresler -->
<div class="c360-grid2">
  <div class="panel c360-section">
    <h3>Sipariş Geçmişi</h3>
    <?php if ($orders): ?>
      <table>
        <thead><tr><th>No</th><th>Tarih</th><th>Tutar</th><th>Kupon</th><th>Durum</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td>#<?= (int)$o['id'] ?></td>
            <td><?= e($o['created_at']) ?></td>
            <td style="color:var(--gold);font-weight:600"><?= money($o['total']) ?></td>
            <td><?= $o['coupon_code'] ? '<code>' . e($o['coupon_code']) . '</code> (' . money($o['discount_amount']) . ')' : '<span class="muted">—</span>' ?></td>
            <td><span class="status <?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
            <td><a class="btn btn-secondary btn-sm" href="<?= $AP ?>/orders/view.php?id=<?= (int)$o['id'] ?>">Gör</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="muted">Henüz sipariş yok.</p>
    <?php endif; ?>
  </div>

  <div class="panel c360-section">
    <h3>Adresler</h3>
    <?php if ($addresses): foreach ($addresses as $a): ?>
      <div style="padding:10px 12px;border:1px solid rgba(255,255,255,.06);border-radius:8px;margin-bottom:8px">
        <strong style="color:var(--champagne)"><?= e($a['title'] ?? 'Adres') ?></strong>
        <?php if (!empty($a['is_default'])): ?> <span class="status paid" style="font-size:10px">Varsayılan</span><?php endif; ?>
        <p style="margin:6px 0 0;font-size:13px;line-height:1.5"><?= nl2br(e(($a['address'] ?? '') . ' · ' . ($a['city'] ?? ''))) ?></p>
      </div>
    <?php endforeach; else: ?>
      <p class="muted">Adres yok.</p>
    <?php endif; ?>
  </div>
</div>

<!-- Yorumlar + Kupon kullanımları -->
<div class="c360-grid2">
  <div class="panel c360-section">
    <h3>Ürün Yorumları (Son 20)</h3>
    <?php if ($reviews): foreach ($reviews as $r): ?>
      <div class="review-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
          <strong style="color:var(--champagne);font-size:13px"><a href="<?= SITE_URL ?>/urun/<?= e($r['product_slug']) ?>" target="_blank" style="color:inherit"><?= e($r['product_name'] ?? '(Silinmiş ürün)') ?></a></strong>
          <span class="stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></span>
        </div>
        <p style="margin:0;font-size:13px;color:var(--muted-text);line-height:1.4"><?= e(mb_substr($r['body'] ?? '', 0, 200)) ?></p>
        <p style="margin:4px 0 0;font-size:11px;color:var(--muted-text)"><?= e($r['created_at']) ?> · <?= ($r['status'] ?? 'pending') === 'approved' ? 'Yayında' : 'Onay bekliyor' ?></p>
      </div>
    <?php endforeach; else: ?>
      <p class="muted">Henüz yorum yapmamış.</p>
    <?php endif; ?>
  </div>

  <div class="panel c360-section">
    <h3>Kullanılan Kuponlar</h3>
    <?php if ($couponUses): ?>
      <table>
        <tbody>
        <?php foreach ($couponUses as $cu): ?>
          <tr>
            <td><strong style="color:var(--champagne)"><?= e($cu['code'] ?? '(silinmiş)') ?></strong></td>
            <td style="text-align:right;color:#e4a3a3"><?= money($cu['amount']) ?></td>
            <td style="font-size:11px;color:var(--muted-text)"><?= e($cu['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="muted">Kupon kullanmamış.</p>
    <?php endif; ?>
  </div>
</div>

<!-- Favoriler + İletişim mesajları -->
<div class="panel c360-section">
  <h3>Favoriler (<?= count($favs) ?>)</h3>
  <?php if ($favs): ?>
    <div class="fav-row">
      <?php foreach ($favs as $f): ?>
        <a href="<?= SITE_URL ?>/urun/<?= e($f['slug']) ?>" class="fav-thumb" target="_blank" style="color:inherit;text-decoration:none">
          <?php if (!empty($f['image'])): ?>
            <img src="<?= e($f['image']) ?>" alt="" loading="lazy">
          <?php else: ?>
            <div style="width:100%;aspect-ratio:1;background:rgba(255,255,255,.04);border-radius:6px;display:grid;place-items:center;color:var(--gold);font-family:Georgia,serif;font-size:24px"><?= e(mb_substr($f['name'],0,1)) ?></div>
          <?php endif; ?>
          <span class="nm"><?= e($f['name']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="muted">Favori ürün yok.</p>
  <?php endif; ?>
</div>

<!-- Puan işlemleri + SMS log -->
<?php if (function_exists('loyalty_enabled') && loyalty_enabled()): ?>
<div class="c360-grid2">
  <div class="panel c360-section">
    <h3>🏆 Son Puan İşlemleri</h3>
    <?php if ($loyaltyHistory):
      $tlMap = ['earn'=>['Kazandı','#9fce7d'],'redeem'=>['Kullandı','#c8b560'],'expire'=>['Süresi doldu','#e4a3a3'],'adjust'=>['Düzeltme','#a8a090'],'refund'=>['İade','#9fce7d']];
    ?>
      <table>
        <thead><tr><th>Tarih</th><th>Tip</th><th class="num">Puan</th><th>Not</th></tr></thead>
        <tbody>
        <?php foreach ($loyaltyHistory as $lh): $tl = $tlMap[$lh['type']] ?? ['?','#aaa']; ?>
          <tr>
            <td style="font-size:11px;color:var(--muted-text)"><?= e(substr($lh['created_at'],0,16)) ?></td>
            <td><span style="background:rgba(<?= $lh['points']>0?'107,170,80,.14':'207,82,82,.14' ?>);color:<?= $tl[1] ?>;padding:2px 8px;border-radius:10px;font-size:11px"><?= $tl[0] ?></span></td>
            <td class="num" style="color:<?= $lh['points']>0?'#9fce7d':'#e4a3a3' ?>;font-weight:600"><?= $lh['points']>0?'+':'' ?><?= number_format($lh['points'],0,',','.') ?></td>
            <td style="font-size:11px;color:var(--muted-text)"><?= e(mb_substr($lh['note'] ?? '', 0, 50)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p style="margin-top:8px"><a href="<?= $AP ?>/loyalty/transactions.php?q=<?= urlencode($user['email']) ?>" style="font-size:12px;color:var(--leaf)">→ Tüm işlemler</a></p>
    <?php else: ?>
      <p class="muted">Henüz puan işlemi yok.</p>
    <?php endif; ?>
  </div>

  <div class="panel c360-section">
    <h3>📱 Gönderilen SMS'ler (Son 10)</h3>
    <?php if ($smsLog): ?>
      <table>
        <tbody>
        <?php foreach ($smsLog as $sm): ?>
          <tr>
            <td style="font-size:11px;color:var(--muted-text);white-space:nowrap;vertical-align:top"><?= e(substr($sm['sent_at'],0,16)) ?></td>
            <td>
              <span style="font-size:11px;background:rgba(0,0,0,.05);padding:2px 6px;border-radius:3px"><?= e($sm['template'] ?? 'manual') ?></span>
              <?php if ($sm['status']==='success'): ?>
                <span style="color:#9fce7d;font-size:11px">✓</span>
              <?php else: ?>
                <span style="color:#e4a3a3;font-size:11px" title="<?= e($sm['error_message'] ?? '') ?>">✗</span>
              <?php endif; ?>
              <br><span style="font-size:11px;color:var(--muted-text)"><?= e(mb_substr($sm['message'], 0, 60)) ?>…</span>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p style="margin-top:8px"><a href="<?= $AP ?>/notifications/sms-log.php?q=<?= urlencode(preg_replace('/\D+/','',$user['phone'] ?? '')) ?>" style="font-size:12px;color:var(--leaf)">→ Tüm SMS'ler</a></p>
    <?php elseif (empty($user['phone'])): ?>
      <p class="muted">Telefon kayıtlı değil.</p>
    <?php else: ?>
      <p class="muted">Henüz SMS gönderilmemiş.</p>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($autoCoupons): ?>
<div class="panel c360-section">
  <h3>🎁 Bu Müşteri İçin Üretilen Otomatik Kuponlar</h3>
  <table>
    <thead><tr><th>Kupon</th><th>Tip</th><th>Tutar</th><th>Geçerlilik</th><th>Kullanım</th><th>Durum</th><th>Not</th></tr></thead>
    <tbody>
    <?php foreach ($autoCoupons as $ac):
      $typeLabel = ['percent'=>'% indirim','fixed'=>'TL indirim','free_shipping'=>'Kargo bedava'][$ac['type']] ?? $ac['type'];
      $amountLabel = $ac['type']==='percent' ? '%' . rtrim(rtrim($ac['amount'],'0'),'.') : ($ac['type']==='fixed' ? money($ac['amount']) : '—');
      $expired = $ac['ends_at'] && strtotime($ac['ends_at']) < time();
    ?>
      <tr>
        <td><code style="background:rgba(201,162,75,.15);color:var(--gold);padding:3px 8px;border-radius:4px;font-weight:600"><?= e($ac['code']) ?></code></td>
        <td><?= e($typeLabel) ?></td>
        <td style="font-weight:600"><?= $amountLabel ?></td>
        <td style="font-size:12px"><?= e(substr($ac['starts_at'] ?? '',0,10)) ?> → <?= e(substr($ac['ends_at'] ?? '',0,10)) ?></td>
        <td><?= (int)$ac['usage_count'] ?></td>
        <td>
          <?php if (!$ac['enabled']): ?><span class="status cancelled">Pasif</span>
          <?php elseif ($expired): ?><span class="status cancelled">Süresi geçti</span>
          <?php elseif ($ac['usage_count'] > 0): ?><span class="status paid">Kullanıldı</span>
          <?php else: ?><span class="status pending">Bekliyor</span>
          <?php endif; ?>
        </td>
        <td style="font-size:11px;color:var(--muted-text)"><?= e($ac['notes'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php if ($messages): ?>
<div class="panel c360-section">
  <h3>İletişim Mesajları (Son 5)</h3>
  <?php foreach ($messages as $m): ?>
    <div style="padding:10px 12px;border:1px solid rgba(255,255,255,.06);border-radius:8px;margin-bottom:8px">
      <div style="display:flex;justify-content:space-between"><strong style="color:var(--champagne)"><?= e($m['name'] ?? '') ?></strong><span style="font-size:11px;color:var(--muted-text)"><?= e($m['created_at']) ?></span></div>
      <p style="margin:4px 0 0;font-size:13px;line-height:1.5;color:var(--muted-text)"><?= nl2br(e(mb_substr($m['message'] ?? '', 0, 300))) ?></p>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
