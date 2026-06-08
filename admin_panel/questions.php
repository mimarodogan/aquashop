<?php
$page  = 'questions';
$title = 'Ürün Soruları';
require_once __DIR__ . '/core/auth.php';

/* ── POST actions ──────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $a  = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($a === 'approve') {
        db()->prepare('UPDATE product_questions SET is_approved=1 WHERE id=?')->execute([$id]);
        flash_set('ok', 'Soru onaylandı.');
    }
    if ($a === 'unapprove') {
        db()->prepare('UPDATE product_questions SET is_approved=0 WHERE id=?')->execute([$id]);
        flash_set('ok', 'Soru yayından kaldırıldı.');
    }
    if ($a === 'delete') {
        db()->prepare('DELETE FROM product_questions WHERE id=?')->execute([$id]);
        flash_set('ok', 'Soru silindi.');
    }
    if ($a === 'answer') {
        $answer = trim($_POST['answer'] ?? '');
        if ($answer) {
            db()->prepare(
                'UPDATE product_questions SET answer=?, answered_by=?, answered_at=NOW(), is_approved=1 WHERE id=?'
            )->execute([$answer, $ADMIN['id'], $id]);
            flash_set('ok', 'Cevap kaydedildi, soru yayına alındı.');
        } else {
            flash_set('err', 'Cevap boş olamaz.');
        }
    }

    $back = 'questions.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : '');
    redirect($back);
}

/* ── Query ─────────────────────────────────────────────────────── */
$filter = $_GET['filter'] ?? 'pending';
$where  = match ($filter) {
    'approved'   => 'q.is_approved=1',
    'unanswered' => 'q.is_approved=1 AND q.answer IS NULL',
    'all'        => '1=1',
    default      => 'q.is_approved=0',   // pending
};

$rows = db()->query(
    "SELECT q.*, p.name AS product_name, p.slug AS product_slug
       FROM product_questions q
       LEFT JOIN products p ON p.id = q.product_id
      WHERE $where
      ORDER BY q.created_at DESC
      LIMIT 300"
)->fetchAll();

$counts = db()->query(
    "SELECT
       SUM(is_approved=0)                              AS pending,
       SUM(is_approved=1)                              AS approved,
       SUM(is_approved=1 AND answer IS NULL)           AS unanswered,
       COUNT(*)                                        AS total
     FROM product_questions"
)->fetch();

require_once __DIR__ . '/core/header.php';
?>

<div class="kpis">
  <div class="kpi"><div class="lbl">Onay Bekleyen</div><div class="val <?= $counts['pending']>0?'val-warn':'' ?>"><?= (int)$counts['pending'] ?></div></div>
  <div class="kpi"><div class="lbl">Yanıtsız</div><div class="val <?= $counts['unanswered']>0?'val-warn':'' ?>"><?= (int)$counts['unanswered'] ?></div></div>
  <div class="kpi"><div class="lbl">Yayında</div><div class="val"><?= (int)$counts['approved'] ?></div></div>
  <div class="kpi"><div class="lbl">Toplam</div><div class="val"><?= (int)$counts['total'] ?></div></div>
</div>

<div class="panel">
  <div class="toolbar">
    <div class="btn-row">
      <?php
        $tabs = [
            'pending'    => ['Bekleyen',   (int)$counts['pending']],
            'unanswered' => ['Yanıtsız',   (int)$counts['unanswered']],
            'approved'   => ['Yayında',    (int)$counts['approved']],
            'all'        => ['Tümü',       (int)$counts['total']],
        ];
        foreach ($tabs as $k => [$lbl, $cnt]):
          $active = $filter === $k ? 'background:var(--cream);border-color:var(--ink)' : '';
      ?>
        <a class="btn btn-secondary btn-sm" href="?filter=<?= $k ?>" style="<?= $active ?>">
          <?= $lbl ?> (<?= $cnt ?>)
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!$rows): ?>
    <p class="muted" style="padding:24px 0">Bu filtreye uyan soru yok.</p>
  <?php else: ?>
    <div style="display:grid;gap:16px;margin-top:8px">
      <?php foreach ($rows as $q): ?>
        <article style="border:1px solid var(--border);border-radius:var(--radius);padding:20px;background:var(--bg)">

          <!-- Ürün + Meta -->
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:12px;flex-wrap:wrap">
            <div>
              <?php if ($q['product_slug']): ?>
                <a href="../urun/<?= e($q['product_slug']) ?>" target="_blank" style="font-weight:600;color:var(--ink);font-size:14px"><?= e($q['product_name']) ?></a>
              <?php else: ?>
                <span class="muted" style="font-size:14px">(silinmiş ürün)</span>
              <?php endif; ?>
              <span style="color:var(--muted-text);font-size:12px;margin-left:10px"><?= e(date('d.m.Y H:i', strtotime($q['created_at']))) ?></span>
            </div>
            <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
              <?php if (!$q['is_approved']): ?>
                <span style="font-size:11px;background:#fff3cd;color:#856404;padding:3px 10px;border-radius:999px;border:1px solid #ffc107">⏳ Onay Bekliyor</span>
              <?php elseif (!$q['answer']): ?>
                <span style="font-size:11px;background:#d1ecf1;color:#0c5460;padding:3px 10px;border-radius:999px;border:1px solid #bee5eb">💬 Yanıt Bekleniyor</span>
              <?php else: ?>
                <span style="font-size:11px;background:#d4edda;color:#155724;padding:3px 10px;border-radius:999px;border:1px solid #c3e6cb">✓ Yanıtlandı</span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Soru -->
          <div style="margin-bottom:12px">
            <div style="font-size:12px;color:var(--muted-text);margin-bottom:4px">
              ❓ <strong><?= e($q['asker_name']) ?></strong>
              <?php if ($q['asker_email']): ?>
                — <a href="mailto:<?= e($q['asker_email']) ?>" style="color:var(--muted-text)"><?= e($q['asker_email']) ?></a>
              <?php endif; ?>
            </div>
            <p style="margin:0;font-size:15px;color:var(--ink)"><?= nl2br(e($q['question'])) ?></p>
          </div>

          <!-- Mevcut cevap -->
          <?php if ($q['answer']): ?>
            <div style="background:rgba(74,90,42,.07);border-left:3px solid var(--leaf);padding:10px 14px;border-radius:0 6px 6px 0;margin-bottom:12px">
              <div style="font-size:11px;color:var(--muted-text);margin-bottom:4px">💡 Admin Yanıtı · <?= $q['answered_at'] ? e(date('d.m.Y', strtotime($q['answered_at']))) : '' ?></div>
              <p style="margin:0;font-size:14px;color:var(--ink)"><?= nl2br(e($q['answer'])) ?></p>
            </div>
          <?php endif; ?>

          <!-- Cevap formu -->
          <details style="margin-bottom:12px" <?= (!$q['answer'] && $q['is_approved']) ? 'open' : '' ?>>
            <summary style="cursor:pointer;font-size:13px;color:var(--leaf);font-weight:600;user-select:none">
              <?= $q['answer'] ? '✏️ Yanıtı Düzenle' : '💬 Yanıt Yaz' ?>
            </summary>
            <form method="post" style="margin-top:12px;display:grid;gap:10px">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="answer">
              <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
              <textarea name="answer" rows="3" placeholder="Soruya yanıtınızı yazın…" style="width:100%;resize:vertical"><?= e($q['answer'] ?? '') ?></textarea>
              <div>
                <button class="btn btn-primary btn-sm">Kaydet &amp; Yayına Al</button>
              </div>
            </form>
          </details>

          <!-- Eylemler -->
          <div class="btn-row">
            <?php if (!$q['is_approved']): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
                <button class="btn btn-primary btn-sm">✓ Onayla</button>
              </form>
            <?php else: ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="unapprove">
                <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
                <button class="btn btn-secondary btn-sm">Yayından Kaldır</button>
              </form>
            <?php endif; ?>

            <form method="post" style="display:inline" onsubmit="return confirm('Soruyu kalıcı olarak sil?')">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
              <button class="btn btn-secondary btn-sm" style="color:#c0392b">🗑 Sil</button>
            </form>
          </div>

        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/core/footer.php'; ?>
