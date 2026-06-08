<?php
$page = 'dashboard'; $title = 'Komuta Merkezi';
require_once __DIR__ . '/core/header.php';

/* ════════════════════════════════════════════════════════════════
 * KOMUTA MERKEZİ — Dashboard 2.0
 * 4 katman: Eylem Merkezi · KPI'lar · Activity Feed + System Health · Quick Actions
 * ════════════════════════════════════════════════════════════════ */

/* ─── Yardımcılar ─────────────────────────────────────────────── */
function _pct_delta(float $now, float $prev): array {
    if ($prev <= 0) return ['pct' => null, 'dir' => 'flat'];
    $d = (($now - $prev) / $prev) * 100;
    return ['pct' => $d, 'dir' => $d > 0 ? 'up' : ($d < 0 ? 'down' : 'flat')];
}
function _delta_chip(array $d): string {
    if ($d['pct'] === null) return '<span class="kpi-delta kpi-delta-flat">yeni</span>';
    $arrow = $d['dir'] === 'up' ? '▲' : ($d['dir'] === 'down' ? '▼' : '–');
    return '<span class="kpi-delta kpi-delta-' . $d['dir'] . '">' . $arrow . ' ' . number_format(abs($d['pct']), 1, ',', '.') . '%</span>';
}
function _q_one(string $sql): float {
    try { return (float)db()->query($sql)->fetchColumn(); }
    catch (\Throwable $e) { return 0; }
}
function _q_int(string $sql): int { return (int)_q_one($sql); }
function _q_all(string $sql): array {
    try { return db()->query($sql)->fetchAll(); }
    catch (\Throwable $e) { return []; }
}
function _ago(string $ts): string {
    if (!$ts) return '—';
    $t = strtotime($ts);
    if (!$t) return '—';
    $diff = time() - $t;
    if ($diff < 60)        return 'şimdi';
    if ($diff < 3600)      return floor($diff/60) . ' dk';
    if ($diff < 86400)     return floor($diff/3600) . ' sa';
    if ($diff < 30*86400)  return floor($diff/86400) . ' g';
    return date('d.m.Y', $t);
}

/* ─── Greeting ────────────────────────────────────────────────── */
$hour = (int)date('H');
$greet = $hour < 6 ? '🌙 İyi geceler' : ($hour < 12 ? '🌅 Günaydın' : ($hour < 18 ? '☀️ İyi günler' : '🌆 İyi akşamlar'));
$adminName = $ADMIN['name'] ?? 'Yönetici';

// Türkçe tarih
$days = ['Pazar','Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi'];
$months = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
$today = (int)date('j') . ' ' . $months[(int)date('n') - 1] . ' ' . date('Y') . ', ' . $days[(int)date('w')];

/* ─── Katman 1: Bekleyen İşler (eylem merkezi) ──────────────────── */
$pendingTasks = admin_sidebar_counts();
$abandonedFresh = 0;
try {
    $abandonedFresh = (int)db()->query("SELECT COUNT(*) FROM abandoned_carts WHERE updated_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) AND reminder_step = 0")->fetchColumn();
} catch (\Throwable $e) {}

/* ─── Katman 2: KPI'lar (delta'lı) ───────────────────────────────── */
$paidWhere = "status IN ('paid','shipped','delivered')";

$revToday = _q_one("SELECT COALESCE(SUM(total),0) FROM orders WHERE $paidWhere AND DATE(created_at) = CURDATE()");
$revYday  = _q_one("SELECT COALESCE(SUM(total),0) FROM orders WHERE $paidWhere AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
$rev7     = _q_one("SELECT COALESCE(SUM(total),0) FROM orders WHERE $paidWhere AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$revPrev7 = _q_one("SELECT COALESCE(SUM(total),0) FROM orders WHERE $paidWhere AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY)");
$ord7     = _q_int("SELECT COUNT(*) FROM orders WHERE $paidWhere AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$aov7     = $ord7 > 0 ? $rev7 / $ord7 : 0;

// Kanal bazlı ciro (source kolonu POS satışlarıyla ekleniyor — yoksa sessizce 0)
$revTodayPos = 0; $revTodayWeb = 0;
$rev7Pos     = 0; $rev7Web     = 0;
try {
    $revTodayPos = (float)db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE $paidWhere AND source='pos' AND DATE(created_at) = CURDATE()")->fetchColumn();
    $revTodayWeb = (float)db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE $paidWhere AND (source='web' OR source IS NULL) AND DATE(created_at) = CURDATE()")->fetchColumn();
    $rev7Pos     = (float)db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE $paidWhere AND source='pos' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $rev7Web     = (float)db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE $paidWhere AND (source='web' OR source IS NULL) AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
} catch (\Throwable $e) {}

// Aktif ziyaretçi (son 5 dk)
$activeNow = 0;
try { $activeNow = (int)db()->query("SELECT COUNT(DISTINCT session_id) FROM page_views WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn(); }
catch (\Throwable $e) {}

// Stok durumu: tükenen (stok=0) ve düşük stok (1..eşik) AYRI sayılır
$lowStock = 0;
$outStock = 0;
try {
    // Tükenen ürünler — eşikten BAĞIMSIZ, her zaman kritik (satışa kapalı)
    $outStock = (int) db()->query("SELECT COUNT(*) FROM products WHERE is_active = 1 AND has_variations = 0 AND stock <= 0")->fetchColumn();
    // Düşük stok — eşik altı ama henüz tükenmemiş
    $thr = (int)setting('low_stock_threshold', '5');
    if ($thr > 0) {
        $st = db()->prepare("SELECT COUNT(*) FROM products WHERE is_active = 1 AND has_variations = 0 AND stock > 0 AND stock <= ?");
        $st->execute([$thr]);
        $lowStock = (int)$st->fetchColumn();
    }
} catch (\Throwable $e) {}

// Dönüşüm oranı (7g)
$conv7 = null;
try {
    $sessions7 = (int)db()->query("SELECT COUNT(DISTINCT session_id) FROM page_views WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    if ($sessions7 > 0) $conv7 = ($ord7 / $sessions7) * 100;
} catch (\Throwable $e) {}

$dRevToday = _pct_delta($revToday, $revYday);
$dRev7     = _pct_delta($rev7, $revPrev7);

/* ─── Katman 3-A: Activity Feed (UNION orders + reviews + messages + newsletter + comments) ── */
$activity = [];
try {
    $st = db()->query("
        SELECT 'order' AS t, id, full_name AS who, total AS amount, status AS extra, created_at FROM orders ORDER BY id DESC LIMIT 8
    ");
    foreach ($st->fetchAll() as $r) $activity[] = $r + ['_ts' => strtotime($r['created_at'])];
} catch (\Throwable $e) {}
try {
    $st = db()->query("
        SELECT 'review' AS t, pr.id, pr.author_name AS who, NULL AS amount, p.name AS extra, pr.created_at
        FROM product_reviews pr LEFT JOIN products p ON p.id = pr.product_id
        ORDER BY pr.id DESC LIMIT 5
    ");
    foreach ($st->fetchAll() as $r) $activity[] = $r + ['_ts' => strtotime($r['created_at'])];
} catch (\Throwable $e) {}
try {
    $st = db()->query("SELECT 'message' AS t, id, name AS who, NULL AS amount, subject AS extra, created_at FROM contact_messages ORDER BY id DESC LIMIT 5");
    foreach ($st->fetchAll() as $r) $activity[] = $r + ['_ts' => strtotime($r['created_at'])];
} catch (\Throwable $e) {}
try {
    $st = db()->query("SELECT 'newsletter' AS t, id, email AS who, NULL AS amount, source AS extra, subscribed_at AS created_at FROM newsletter_subscribers WHERE is_active = 1 ORDER BY id DESC LIMIT 5");
    foreach ($st->fetchAll() as $r) $activity[] = $r + ['_ts' => strtotime($r['created_at'])];
} catch (\Throwable $e) {}
try {
    $st = db()->query("SELECT 'comment' AS t, c.id, COALESCE(u.name, '(Üye)') AS who, NULL AS amount, c.target_type AS extra, c.created_at FROM comments c LEFT JOIN users u ON u.id = c.user_id ORDER BY c.id DESC LIMIT 5");
    foreach ($st->fetchAll() as $r) $activity[] = $r + ['_ts' => strtotime($r['created_at'])];
} catch (\Throwable $e) {}

// Zamanına göre sırala
usort($activity, fn($a, $b) => $b['_ts'] <=> $a['_ts']);
$activity = array_slice($activity, 0, 12);

/* ─── Katman 3-B: System Health ─────────────────────────────────── */
// Cron son çalışma — migrations ile aynı yerden okuyamayız; mail_log veya sms_log baz alalım
$smsToday      = _q_int("SELECT COUNT(*) FROM sms_log WHERE DATE(sent_at) = CURDATE()");
$smsFailToday  = _q_int("SELECT COUNT(*) FROM sms_log WHERE DATE(sent_at) = CURDATE() AND status = 'failure'");
$abandonedNotified24h = _q_int("SELECT COUNT(*) FROM abandoned_carts WHERE last_reminder_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$lastAbandonedRun = '';
try { $lastAbandonedRun = (string)db()->query("SELECT MAX(last_reminder_at) FROM abandoned_carts WHERE last_reminder_at IS NOT NULL")->fetchColumn(); }
catch (\Throwable $e) {}

// Düşük stok cron — settings'te low_stock_notified array
$lowStockLastNotify = '';
try {
    $lsn = setting('low_stock_notified', '');
    if ($lsn) {
        $arr = json_decode($lsn, true);
        if (is_array($arr) && $arr) {
            $maxTs = max($arr);
            $lowStockLastNotify = date('Y-m-d H:i:s', (int)$maxTs);
        }
    }
} catch (\Throwable $e) {}

// Sadakat aktif mi?
$loyaltyActive = setting('loyalty_enabled', '0') === '1';
$loyaltyMembers = 0;
try { $loyaltyMembers = (int)db()->query("SELECT COUNT(*) FROM loyalty_points WHERE points > 0")->fetchColumn(); }
catch (\Throwable $e) {}

// Analitik aktif mi?
$analyticsActive = setting('analytics_enabled', '0') === '1';
$pageViewsToday  = _q_int("SELECT COUNT(*) FROM page_views WHERE DATE(viewed_at) = CURDATE()");

// Genel sayılar (eski KPI'lar — alt blok için)
$totalOrders   = _q_int("SELECT COUNT(*) FROM orders");
$totalProducts = _q_int("SELECT COUNT(*) FROM products WHERE is_active = 1");
$totalCustomers= _q_int("SELECT COUNT(*) FROM users WHERE role = 'customer'");

$AP = SITE_URL . '/admin_panel';
?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- KATMAN 1: Eylem Merkezi (selamlama + bekleyen işler)        -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="cm-greeting">
  <h1><?= $greet ?>, <?= e($adminName) ?></h1>
  <p class="cm-date"><?= e($today) ?></p>

  <?php
  $hasPending = $pendingTasks['pending_orders'] > 0
             || ($pendingTasks['paid_orders'] ?? 0) > 0
             || $pendingTasks['pending_reviews'] > 0
             || $pendingTasks['pending_comments'] > 0
             || $pendingTasks['unread_messages'] > 0
             || $abandonedFresh > 0
             || $lowStock > 0
             || $outStock > 0;
  ?>

  <?php if ($hasPending): ?>
    <p class="cm-intro">Bugün öncelikli olarak şu işler bekliyor:</p>
    <div class="action-grid">
      <?php if (($pendingTasks['paid_orders'] ?? 0) > 0): ?>
        <a class="action-card" href="<?= $AP ?>/orders/list.php?status=paid" style="border-color:var(--leaf);background:linear-gradient(135deg,rgba(107,122,47,.12),rgba(201,162,75,.05))">
          <span class="ac-num" style="color:var(--leaf)"><?= (int)$pendingTasks['paid_orders'] ?></span>
          <span class="ac-lbl">💚 Ödenmiş Sipariş<br><small style="font-weight:400;color:var(--muted-text)">kargoya hazırla</small></span>
        </a>
      <?php endif; ?>
      <?php if ($pendingTasks['pending_orders'] > 0): ?>
        <a class="action-card <?= $pendingTasks['pending_orders'] > 5 ? 'critical' : 'urgent' ?>" href="<?= $AP ?>/orders/list.php?status=pending">
          <span class="ac-num"><?= (int)$pendingTasks['pending_orders'] ?></span>
          <span class="ac-lbl">Bekleyen Sipariş<br><small style="font-weight:400;color:var(--muted-text)">acil işle</small></span>
        </a>
      <?php endif; ?>
      <?php if ($pendingTasks['pending_reviews'] > 0): ?>
        <a class="action-card urgent" href="<?= $AP ?>/reviews.php?filter=pending">
          <span class="ac-num"><?= (int)$pendingTasks['pending_reviews'] ?></span>
          <span class="ac-lbl">Onay Bekleyen Yorum</span>
        </a>
      <?php endif; ?>
      <?php if ($pendingTasks['unread_messages'] > 0): ?>
        <a class="action-card" href="<?= $AP ?>/messages.php">
          <span class="ac-num"><?= (int)$pendingTasks['unread_messages'] ?></span>
          <span class="ac-lbl">Okunmamış Mesaj</span>
        </a>
      <?php endif; ?>
      <?php if ($pendingTasks['pending_comments'] > 0): ?>
        <a class="action-card" href="<?= $AP ?>/comments.php?filter=pending">
          <span class="ac-num"><?= (int)$pendingTasks['pending_comments'] ?></span>
          <span class="ac-lbl">Blog Yorumu</span>
        </a>
      <?php endif; ?>
      <?php if ($abandonedFresh > 0): ?>
        <a class="action-card" href="<?= $AP ?>/abandoned-carts.php">
          <span class="ac-num"><?= (int)$abandonedFresh ?></span>
          <span class="ac-lbl">Terk Sepet (24sa)<br><small style="font-weight:400;color:var(--muted-text)">hatırlatma bekliyor</small></span>
        </a>
      <?php endif; ?>
      <?php if ($outStock > 0): ?>
        <a class="action-card critical" href="<?= $AP ?>/products/stock.php?stock_filter=out">
          <span class="ac-num"><?= (int)$outStock ?></span>
          <span class="ac-lbl">Tükenen Ürün<br><small style="font-weight:400;color:var(--muted-text)">stok yok — satışa kapalı</small></span>
        </a>
      <?php endif; ?>
      <?php if ($lowStock > 0): ?>
        <a class="action-card critical" href="<?= $AP ?>/products/stock.php?stock_filter=low">
          <span class="ac-num"><?= (int)$lowStock ?></span>
          <span class="ac-lbl">Düşük Stok Ürün</span>
        </a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <p class="cm-intro" style="color:var(--success-text)">✓ Tüm acil işler hallolmuş. Harika gidiyor.</p>
  <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- KATMAN 2: Canlı KPI'lar (delta'lı)                          -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="kpis">
  <a class="kpi-link" href="<?= $AP ?>/reports/sales.php?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>">
    <div class="kpi">
      <div class="lbl">Bugün Ciro</div>
      <div class="val"><?= money($revToday) ?><?= _delta_chip($dRevToday) ?></div>
      <?php if ($revTodayPos > 0): ?>
      <div class="hint">🏪 <?= money($revTodayPos) ?> &nbsp;·&nbsp; 🌐 <?= money($revTodayWeb) ?></div>
      <?php else: ?>
      <div class="hint">Dün: <?= money($revYday) ?></div>
      <?php endif; ?>
    </div>
  </a>
  <a class="kpi-link" href="<?= $AP ?>/reports/sales.php?from=<?= date('Y-m-d', strtotime('-7 days')) ?>&to=<?= date('Y-m-d') ?>">
    <div class="kpi">
      <div class="lbl">7 Günlük Ciro</div>
      <div class="val"><?= money($rev7) ?><?= _delta_chip($dRev7) ?></div>
      <?php if ($rev7Pos > 0): ?>
      <div class="hint">🏪 <?= money($rev7Pos) ?> &nbsp;·&nbsp; 🌐 <?= money($rev7Web) ?></div>
      <?php else: ?>
      <div class="hint"><?= $ord7 ?> sipariş · AOV <?= money($aov7) ?></div>
      <?php endif; ?>
    </div>
  </a>
  <div class="kpi">
    <div class="lbl">Dönüşüm Oranı (7g)</div>
    <div class="val"><?= $conv7 !== null ? number_format($conv7, 2, ',', '.') . '%' : '<span style="font-size:14px;color:var(--muted-text)">veri toplanıyor</span>' ?></div>
    <div class="hint"><?= $activeNow ?> kişi şu an aktif</div>
  </div>
  <a class="kpi-link" href="<?= $AP ?>/products/stock.php">
    <div class="kpi">
      <div class="lbl">Stok Durumu</div>
      <div class="val" style="color:<?= ($lowStock + $outStock) > 0 ? 'var(--danger-text)' : 'var(--ink)' ?>"><?= $lowStock + $outStock ?></div>
      <div class="hint"><?= ($lowStock + $outStock) > 0 ? ((int)$outStock . ' tükendi · ' . (int)$lowStock . ' düşük') : 'her şey yolunda' ?></div>
    </div>
  </a>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- KATMAN 3: Activity Feed + System Health                     -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="cm-two-col">

  <!-- 3-A: Activity Feed -->
  <div class="cm-section">
    <div class="cm-section-head">
      <h3>🔔 Son Aktiviteler</h3>
      <a class="cm-section-link" href="<?= $AP ?>/orders/list.php">Tüm siparişler →</a>
    </div>
    <?php if ($activity): ?>
      <div class="activity-feed">
        <?php foreach ($activity as $a):
          $type = $a['t'];
          $icoCls = 't-order'; $emoji = '🔔'; $titleHtml = ''; $link = '#';
          switch ($type) {
            case 'order':
              $icoCls = 't-order'; $emoji = '🛒';
              $link = $AP . '/orders/view.php?id=' . (int)$a['id'];
              $titleHtml = 'Sipariş <a href="' . $link . '">#' . (int)$a['id'] . '</a> — <strong>' . e($a['who']) . '</strong> · <span class="muted">' . e(status_label($a['extra'])) . '</span>';
              break;
            case 'review':
              $icoCls = 't-review'; $emoji = '⭐';
              $link = $AP . '/reviews.php';
              $titleHtml = 'Ürün yorumu — <strong>' . e($a['who']) . '</strong> · <span class="muted">' . e($a['extra'] ?? '(silinmiş ürün)') . '</span>';
              break;
            case 'message':
              $icoCls = 't-message'; $emoji = '✉️';
              $link = $AP . '/messages.php';
              $titleHtml = 'Yeni mesaj — <strong>' . e($a['who']) . '</strong> · <span class="muted">' . e(mb_substr($a['extra'] ?? '(konu yok)', 0, 50)) . '</span>';
              break;
            case 'comment':
              $icoCls = 't-coupon'; $emoji = '💬';
              $link = $AP . '/comments.php';
              $titleHtml = 'Blog yorumu — <strong>' . e($a['who']) . '</strong>';
              break;
            case 'newsletter':
              $icoCls = 't-news'; $emoji = '📧';
              $link = $AP . '/newsletter/subscribers.php';
              $titleHtml = 'Yeni e-bülten aboneliği — <strong>' . e($a['who']) . '</strong>' . ($a['extra'] ? ' · <span class="muted">' . e($a['extra']) . '</span>' : '');
              break;
          }
        ?>
          <div class="activity-item">
            <div class="activity-ico <?= $icoCls ?>"><?= $emoji ?></div>
            <div class="activity-body">
              <p class="activity-title"><?= $titleHtml ?></p>
              <p class="activity-meta"><?= e(_ago($a['created_at'])) ?> önce · <?= e(substr($a['created_at'], 0, 16)) ?></p>
            </div>
            <?php if ($a['amount']): ?>
              <span class="activity-amount"><?= money($a['amount']) ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="muted" style="text-align:center;padding:24px 0">Henüz aktivite yok.</p>
    <?php endif; ?>
  </div>

  <!-- 3-B: System Health -->
  <div class="cm-section">
    <h3 style="margin-bottom:14px">🩺 Sistem Sağlığı</h3>

    <p class="health-section-title">📨 Bildirimler (Bugün)</p>
    <div class="health-list">
      <div class="health-row">
        <span class="health-dot <?= $smsToday > 0 ? ($smsFailToday > 0 ? 'warn' : 'ok') : 'off' ?>"></span>
        <span class="health-label">SMS Gönderim</span>
        <span class="health-meta"><?= $smsToday ?> gönderildi<?php if ($smsFailToday > 0): ?>, <?= $smsFailToday ?> hata<?php endif; ?></span>
      </div>
      <div class="health-row">
        <span class="health-dot <?= $abandonedNotified24h > 0 ? 'ok' : 'off' ?>"></span>
        <span class="health-label">Terk Sepet Cron</span>
        <span class="health-meta">
          <?= $lastAbandonedRun ? 'Son: ' . _ago($lastAbandonedRun) . ' önce' : 'Henüz çalışmadı' ?>
        </span>
      </div>
      <div class="health-row">
        <span class="health-dot <?= $lowStockLastNotify ? 'ok' : 'off' ?>"></span>
        <span class="health-label">Düşük Stok Cron</span>
        <span class="health-meta">
          <?= $lowStockLastNotify ? 'Son: ' . _ago($lowStockLastNotify) . ' önce' : 'Henüz çalışmadı' ?>
        </span>
      </div>
    </div>

    <p class="health-section-title">⚙ Entegrasyonlar</p>
    <div class="health-list">
      <div class="health-row">
        <span class="health-dot <?= $analyticsActive ? 'ok' : 'off' ?>"></span>
        <span class="health-label">Analitik</span>
        <span class="health-meta"><?= $analyticsActive ? $pageViewsToday . ' sayfa görüntüleme bugün' : 'Pasif' ?></span>
      </div>
      <div class="health-row">
        <span class="health-dot <?= setting('iyz_enabled','0')==='1' ? 'ok' : 'off' ?>"></span>
        <span class="health-label">iyzico Ödeme</span>
        <span class="health-meta"><?= setting('iyz_enabled','0')==='1' ? (setting('iyz_env')==='live' ? 'Canlı' : 'Sandbox') : 'Pasif' ?></span>
      </div>
      <div class="health-row">
        <span class="health-dot <?= setting('sms_enabled','0')==='1' ? 'ok' : 'off' ?>"></span>
        <span class="health-label">SMS Sağlayıcı</span>
        <span class="health-meta"><?= setting('sms_enabled','0')==='1' ? ucfirst((string)setting('sms_provider','-')) : 'Pasif' ?></span>
      </div>
      <div class="health-row">
        <span class="health-dot <?= setting('smtp_host','') ? 'ok' : 'off' ?>"></span>
        <span class="health-label">SMTP (E-posta)</span>
        <span class="health-meta"><?= setting('smtp_host','') ? e(setting('smtp_host')) : 'Yapılandırılmadı' ?></span>
      </div>
      <div class="health-row">
        <span class="health-dot <?= $loyaltyActive ? 'ok' : 'off' ?>"></span>
        <span class="health-label">Sadakat Programı</span>
        <span class="health-meta"><?= $loyaltyActive ? $loyaltyMembers . ' aktif üye' : 'Pasif' ?></span>
      </div>
    </div>

    <p class="health-section-title">📊 Genel Sayılar</p>
    <div class="health-list">
      <div class="health-row">
        <span class="health-dot ok"></span>
        <span class="health-label">Toplam Sipariş</span>
        <span class="health-meta"><?= number_format($totalOrders, 0, ',', '.') ?></span>
      </div>
      <div class="health-row">
        <span class="health-dot ok"></span>
        <span class="health-label">Aktif Ürün</span>
        <span class="health-meta"><?= number_format($totalProducts, 0, ',', '.') ?></span>
      </div>
      <div class="health-row">
        <span class="health-dot ok"></span>
        <span class="health-label">Kayıtlı Müşteri</span>
        <span class="health-meta"><?= number_format($totalCustomers, 0, ',', '.') ?></span>
      </div>
    </div>
  </div>

</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- KATMAN 4: Hızlı Eylemler                                    -->
<!-- ═══════════════════════════════════════════════════════════ -->
<h3 style="font-family:'Playfair Display',serif;font-size:18px;color:var(--ink);margin:0 0 14px;font-weight:500">⚡ Hızlı Eylemler</h3>
<div class="cm-quick-grid">
  <a class="cm-quick-item" href="<?= $AP ?>/products/edit.php">
    <span class="qi-ico">➕</span>Yeni Ürün
  </a>
  <a class="cm-quick-item" href="<?= $AP ?>/coupons.php">
    <span class="qi-ico">🎁</span>Yeni Kupon
  </a>
  <a class="cm-quick-item" href="<?= $AP ?>/banners.php">
    <span class="qi-ico">🖼</span>Banner
  </a>
  <a class="cm-quick-item" href="<?= $AP ?>/blog/posts.php">
    <span class="qi-ico">📝</span>Blog Yazısı
  </a>
  <a class="cm-quick-item" href="<?= $AP ?>/newsletter/campaigns.php">
    <span class="qi-ico">📧</span>Newsletter
  </a>
  <a class="cm-quick-item" href="<?= $AP ?>/pos.php">
    <span class="qi-ico">🛍</span>POS
  </a>
  <a class="cm-quick-item" href="<?= $AP ?>/reports/sales.php">
    <span class="qi-ico">📈</span>Raporlar
  </a>
  <a class="cm-quick-item" href="<?= $AP ?>/settings/index.php">
    <span class="qi-ico">⚙</span>Ayarlar
  </a>
</div>

<?php require_once __DIR__ . '/core/footer.php'; ?>
