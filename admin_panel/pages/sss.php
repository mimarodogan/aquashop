<?php
$page = 'sss_faq'; $title = 'SSS Yönetimi';
require_once __DIR__ . '/../core/auth.php';

/* ── POST: kaydet ────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {

    $qs = array_values(array_filter((array)($_POST['faq_q'] ?? []), 'strlen'));
    $as = array_values((array)($_POST['faq_a'] ?? []));

    /* Tümünü sil, yeniden ekle (sıralama korunur) */
    db()->exec('DELETE FROM site_faqs');

    if ($qs) {
        $ins = db()->prepare(
            'INSERT INTO site_faqs (question, answer, sort_order, is_active) VALUES (?,?,?,1)'
        );
        foreach ($qs as $i => $q) {
            $q = trim($q);
            $a = trim($as[$i] ?? '');
            if ($q === '' || $a === '') continue;
            $ins->execute([$q, $a, $i]);
        }
    }

    flash_set('ok', 'SSS kaydedildi.');
    redirect('sss.php');
}

/* ── Mevcut kayıtları çek ───────────────────────────────────── */
$faqs = [];
try {
    $faqs = db()->query(
        'SELECT * FROM site_faqs ORDER BY sort_order ASC, id ASC'
    )->fetchAll();
} catch (\Throwable $e) {
    /* Tablo yoksa sessizce boş bırak; migration hatırlatıcısı aşağıda gösterilir */
}

$tableExists = (bool)$faqs !== false;
try {
    db()->query('SELECT 1 FROM site_faqs LIMIT 1');
    $tableExists = true;
} catch (\Throwable $e) {
    $tableExists = false;
}

require_once __DIR__ . '/../core/header.php';
?>

<?php if (!$tableExists): ?>
<div class="panel" style="border-left:4px solid #e67e22;padding:18px">
  <strong>⚠️ site_faqs tablosu bulunamadı.</strong>
  <p class="muted" style="margin-top:6px">Lütfen önce <code>sql/migrate_site_faqs.sql</code> dosyasını phpMyAdmin → SQL sekmesinden çalıştırın.</p>
</div>
<?php else: ?>

<div class="panel">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;flex-wrap:wrap;gap:10px">
    <div>
      <h2 style="margin:0 0 4px">Sıkça Sorulan Sorular</h2>
      <p class="muted" style="margin:0;font-size:13px">
        Sorular ön yüzde <strong>/sayfa/sss</strong> adresinde akordiyon olarak görünür ve
        <strong>Google FAQPage şeması</strong> olarak yayınlanır (SEO katkısı).
        Sıralamayı değiştirmek için satırları sürükleyebilirsiniz.
      </p>
    </div>
    <a href="<?= SITE_URL ?>/sayfa/sss" target="_blank" class="btn btn-secondary btn-sm">🔗 Sayfayı Görüntüle</a>
  </div>
</div>

<form method="post">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

  <div id="faq-list" style="display:flex;flex-direction:column;gap:14px;margin-bottom:20px">

    <?php if ($faqs): foreach ($faqs as $f): ?>
    <div class="faq-row panel" style="padding:16px;cursor:grab;position:relative"
         draggable="true">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
        <span style="color:var(--muted-text);cursor:grab;font-size:18px;line-height:1" title="Sürükle">⠿</span>
        <span style="font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:var(--muted-text);font-weight:600">SORU</span>
        <button type="button" class="btn btn-sm faq-del"
                style="margin-left:auto;color:#f5a3a3;border-color:#f5a3a3;padding:3px 10px">Sil</button>
      </div>
      <input type="text" name="faq_q[]" value="<?= e($f['question']) ?>"
             placeholder="Soruyu yazın…" required
             style="margin-bottom:10px">
      <span style="font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:var(--muted-text);font-weight:600">CEVAP</span>
      <textarea name="faq_a[]" rows="3"
                placeholder="Cevabı yazın…" required
                style="margin-top:6px"><?= e($f['answer']) ?></textarea>
    </div>
    <?php endforeach; endif; ?>

  </div><!-- #faq-list -->

  <!-- Yeni soru şablonu -->
  <template id="faq-tpl">
    <div class="faq-row panel" style="padding:16px;cursor:grab;position:relative"
         draggable="true">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
        <span style="color:var(--muted-text);cursor:grab;font-size:18px;line-height:1">⠿</span>
        <span style="font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:var(--muted-text);font-weight:600">SORU</span>
        <button type="button" class="btn btn-sm faq-del"
                style="margin-left:auto;color:#f5a3a3;border-color:#f5a3a3;padding:3px 10px">Sil</button>
      </div>
      <input type="text" name="faq_q[]" placeholder="Soruyu yazın…" required
             style="margin-bottom:10px">
      <span style="font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:var(--muted-text);font-weight:600">CEVAP</span>
      <textarea name="faq_a[]" rows="3"
                placeholder="Cevabı yazın…" required
                style="margin-top:6px"></textarea>
    </div>
  </template>

  <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
    <button type="button" id="faq-add" class="btn btn-secondary">+ Yeni Soru Ekle</button>
    <button type="submit" class="btn btn-primary">💾 Kaydet</button>
    <span class="muted" style="font-size:13px" id="faq-count">
      <?= count($faqs) ?> soru kayıtlı
    </span>
  </div>
</form>

<?php endif; ?>

<?php require_once __DIR__ . '/../core/footer.php'; ?>

<script>
(function () {
  'use strict';

  var list = document.getElementById('faq-list');
  var addBtn = document.getElementById('faq-add');
  var tpl  = document.getElementById('faq-tpl');
  var countEl = document.getElementById('faq-count');

  function updateCount() {
    if (!countEl || !list) return;
    var n = list.querySelectorAll('.faq-row').length;
    countEl.textContent = n + ' soru kayıtlı';
  }

  /* Yeni soru ekle */
  if (addBtn && tpl && list) {
    addBtn.addEventListener('click', function () {
      var clone = tpl.content.cloneNode(true);
      list.appendChild(clone);
      var rows = list.querySelectorAll('.faq-row');
      var newRow = rows[rows.length - 1];
      newRow.querySelector('input[name="faq_q[]"]').focus();
      bindDel(newRow);
      bindDrag(newRow);
      updateCount();
    });
  }

  /* Sil */
  function bindDel(row) {
    var btn = row.querySelector('.faq-del');
    if (btn) {
      btn.addEventListener('click', function () {
        if (confirm('Bu soru silinsin mi?')) {
          row.remove();
          updateCount();
        }
      });
    }
  }

  /* Mevcut satırlara sil bağla */
  if (list) {
    list.querySelectorAll('.faq-row').forEach(bindDel);
  }

  /* ── Sürükle-bırak sıralama ─────────────────────────────── */
  var dragSrc = null;

  function bindDrag(row) {
    row.addEventListener('dragstart', function (e) {
      dragSrc = row;
      e.dataTransfer.effectAllowed = 'move';
      row.style.opacity = '0.5';
    });
    row.addEventListener('dragend', function () {
      row.style.opacity = '';
      if (list) list.querySelectorAll('.faq-row').forEach(function (r) {
        r.classList.remove('drag-over');
      });
    });
    row.addEventListener('dragover', function (e) {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
    });
    row.addEventListener('dragenter', function () {
      row.classList.add('drag-over');
    });
    row.addEventListener('dragleave', function () {
      row.classList.remove('drag-over');
    });
    row.addEventListener('drop', function (e) {
      e.preventDefault();
      if (dragSrc && dragSrc !== row) {
        var rows = Array.from(list.querySelectorAll('.faq-row'));
        var srcIdx = rows.indexOf(dragSrc);
        var tgtIdx = rows.indexOf(row);
        if (srcIdx < tgtIdx) {
          list.insertBefore(dragSrc, row.nextSibling);
        } else {
          list.insertBefore(dragSrc, row);
        }
      }
      row.classList.remove('drag-over');
    });
  }

  if (list) {
    list.querySelectorAll('.faq-row').forEach(bindDrag);
  }

})();
</script>
<style>
.faq-row.drag-over { outline: 2px dashed var(--gold); }
</style>
