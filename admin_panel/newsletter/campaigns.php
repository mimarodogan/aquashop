<?php
$page='newsletter_campaigns'; $title='Bülten Kampanyaları';
require_once __DIR__ . '/../core/auth.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? null)) {
    $a = $_POST['action'] ?? '';
    if ($a==='create') {
        $sub = trim($_POST['subject'] ?? '');
        $bod = $_POST['body'] ?? '';
        if ($sub==='' || trim(strip_tags($bod))==='') { flash_set('err','Konu ve içerik zorunlu.'); redirect('campaigns.php?action=new'); }
        $st = db()->prepare('INSERT INTO newsletter_campaigns (subject,body,created_by) VALUES (?,?,?)');
        $st->execute(array($sub,$bod,(int)$ADMIN['id']));
        flash_set('ok','Kampanya taslak olarak kaydedildi.');
        redirect('campaigns.php?id=' . db()->lastInsertId());
    }
    if ($a==='delete') {
        db()->prepare('DELETE FROM newsletter_campaigns WHERE id=?')->execute(array((int)$_POST['id']));
        flash_set('ok','Silindi.');
        redirect('campaigns.php');
    }
    if ($a==='send') {
        $cid = (int)$_POST['id'];
        $st = db()->prepare('SELECT * FROM newsletter_campaigns WHERE id=?'); $st->execute(array($cid));
        $c = $st->fetch();
        if (!$c) { flash_set('err','Kampanya bulunamadı.'); redirect('campaigns.php'); }
        if ($c['status']==='sent') { flash_set('err','Bu kampanya zaten gönderildi.'); redirect('campaigns.php?id='.$cid); }

        db()->prepare("UPDATE newsletter_campaigns SET status='sending' WHERE id=?")->execute(array($cid));

        $subs = db()->query('SELECT email,token FROM newsletter_subscribers WHERE is_active=1')->fetchAll();
        $from = setting('contact_email','noreply@'.($_SERVER['HTTP_HOST']??'example.com'));
        $siteName = setting('site_name','E-Ticaret');
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: $siteName <$from>\r\n";
        $headers .= "Reply-To: $from\r\n";

        $sent=0; $failed=0;
        foreach ($subs as $s) {
            $unsubUrl = 'https://' . ($_SERVER['HTTP_HOST']??'') . '/unsubscribe.php?email=' . urlencode($s['email']) . '&token=' . urlencode($s['token']);
            $html = '<div style="font-family:Inter,Arial,sans-serif;background:#f7f5ee;padding:24px">'
                  . '<div style="max-width:620px;margin:0 auto;background:#1F2A1C;border:1px solid #8C6F2A;border-radius:10px;overflow:hidden;color:#E5C97A">'
                  . '<div style="padding:24px 28px;border-bottom:1px solid #8C6F2A;font-family:\'Playfair Display\',serif;font-size:24px;color:#C9A24B">' . htmlspecialchars($siteName) . '</div>'
                  . '<div style="padding:28px;line-height:1.7;color:#E5C97A">' . $c['body'] . '</div>'
                  . '<div style="padding:18px 28px;border-top:1px solid #8C6F2A;font-size:11px;color:#8C6F2A;text-align:center">Bu e-postayı, ' . htmlspecialchars($siteName) . ' bültenine kayıtlı olduğunuz için aldınız.<br><a href="' . htmlspecialchars($unsubUrl) . '" style="color:#C9A24B">Abonelikten çıkmak için tıklayın</a></div>'
                  . '</div></div>';
            $ok = @mail($s['email'], '=?UTF-8?B?'.base64_encode($c['subject']).'?=', $html, $headers, '-f' . $from);
            if ($ok) $sent++; else $failed++;
            // Sunucu rate-limit'ine takılmamak için kısa bekleme
            if (($sent+$failed) % 25 === 0) usleep(200000);
        }
        db()->prepare("UPDATE newsletter_campaigns SET status='sent', sent_count=?, failed_count=?, total_recipients=?, sent_at=NOW() WHERE id=?")
            ->execute(array($sent, $failed, count($subs), $cid));
        flash_set('ok',"Gönderildi: $sent başarılı, $failed başarısız.");
        redirect('campaigns.php?id='.$cid);
    }
}

$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

require_once __DIR__ . '/../core/header.php';

if ($action === 'new'):
?>
  <div class="panel">
    <h3>Yeni Kampanya</h3>
    <form method="post" style="display:grid;gap:14px">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="field">
        <label>Konu (E-posta başlığı)</label>
        <div style="display:flex;gap:8px;align-items:center">
          <input name="subject" id="campaign-subject" required placeholder="Örn: %20 Yaz Kampanyası Başladı" style="flex:1">
          <?php if (trim((string)setting('anthropic_api_key',''))!==''): ?>
            <button type="button" class="btn btn-secondary btn-sm" id="ai-subject-btn" title="Claude AI'dan 5 konu öner" style="white-space:nowrap">✨ AI Öner</button>
          <?php endif; ?>
        </div>
        <div id="ai-subject-suggestions" style="display:none;margin-top:10px;padding:14px;background:var(--cream);border:1px solid var(--gold-border);border-radius:8px"></div>
      </div>
      <div class="field"><label>İçerik</label><textarea id="body" name="body" rows="14" required></textarea></div>
      <div style="display:flex;gap:10px">
        <button class="btn btn-primary">Taslak Olarak Kaydet</button>
        <a class="btn btn-secondary" href="campaigns.php">Vazgeç</a>
      </div>
    </form>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
  <script defer src="<?= SITE_URL ?>/assets/js/admin/tinymce-init-simple.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/admin/tinymce-init-simple.js') ?: time() ?>"></script>
<?php elseif ($id):
    $st = db()->prepare('SELECT * FROM newsletter_campaigns WHERE id=?'); $st->execute(array($id));
    $c = $st->fetch();
    if (!$c) { echo '<div class="panel"><p class="muted">Kampanya bulunamadı.</p></div>'; require_once __DIR__ . '/../core/footer.php'; exit; }
    $activeCount = (int)db()->query('SELECT COUNT(*) FROM newsletter_subscribers WHERE is_active=1')->fetchColumn();
?>
  <div class="panel">
    <div class="toolbar">
      <h3 style="margin:0"><?= e($c['subject']) ?></h3>
      <a class="btn btn-secondary btn-sm" href="campaigns.php">← Liste</a>
    </div>
    <p class="muted" style="font-size:13px;margin-bottom:14px">
      Durum: <span class="status <?= $c['status']==='sent'?'paid':($c['status']==='failed'?'cancelled':'') ?>"><?= e($c['status']) ?></span>
      <?php if ($c['status']==='sent'): ?>
        · <?= (int)$c['sent_count'] ?> başarılı / <?= (int)$c['failed_count'] ?> başarısız · <?= e($c['sent_at']) ?>
      <?php endif; ?>
    </p>
    <div class="panel" style="background:#fff;color:#1A1A1A;border-color:#8C6F2A">
      <div style="font-size:13px;color:#666;margin-bottom:8px">Konu: <strong><?= e($c['subject']) ?></strong></div>
      <hr style="border:none;border-top:1px solid #ddd;margin:8px 0 16px">
      <?= $c['body'] ?>
    </div>
    <div style="display:flex;gap:10px;margin-top:18px">
      <?php if ($c['status']!=='sent'): ?>
        <form method="post" onsubmit="return confirm('<?= $activeCount ?> aktif aboneye gönderilsin mi?')">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="send">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button class="btn btn-primary">📨 <?= $activeCount ?> Aboneye Gönder</button>
        </form>
      <?php endif; ?>
      <form method="post" onsubmit="return confirm('Silinsin mi?')">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <button class="btn btn-secondary">Sil</button>
      </form>
    </div>
  </div>
<?php else:
    $rows = db()->query('SELECT * FROM newsletter_campaigns ORDER BY created_at DESC')->fetchAll();
?>
  <div class="panel">
    <div class="toolbar">
      <p class="muted">Toplu duyuru / kampanya / indirim e-postaları.</p>
      <a class="btn btn-primary btn-sm" href="?action=new">+ Yeni Kampanya</a>
    </div>
    <table>
      <thead><tr><th>Konu</th><th>Durum</th><th>Alıcı</th><th>Başarılı</th><th>Tarih</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $c): ?>
        <tr>
          <td><strong style="color:var(--champagne)"><?= e($c['subject']) ?></strong></td>
          <td><span class="status <?= $c['status']==='sent'?'paid':($c['status']==='failed'?'cancelled':'') ?>"><?= e($c['status']) ?></span></td>
          <td><?= (int)$c['total_recipients'] ?></td>
          <td><?= (int)$c['sent_count'] ?>/<?= (int)$c['total_recipients'] ?></td>
          <td class="muted" style="font-size:12px"><?= e($c['sent_at'] ?? $c['created_at']) ?></td>
          <td><a class="btn btn-secondary btn-sm" href="?id=<?= (int)$c['id'] ?>">Aç</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted">Henüz kampanya yok.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<script>
(function(){
  var btn = document.getElementById('ai-subject-btn');
  if (!btn) return;
  var bodyEl = document.getElementById('body');
  var subjEl = document.getElementById('campaign-subject');
  var box    = document.getElementById('ai-subject-suggestions');
  var csrf   = document.querySelector('input[name=csrf]').value;

  btn.addEventListener('click', function () {
    var content = (bodyEl.value || '').trim();
    if (!content) { alert('Önce email içeriğini yazın, sonra AI önerisi alın.'); bodyEl.focus(); return; }
    btn.disabled = true; btn.textContent = '⏳ Düşünüyor...';
    box.style.display = 'block';
    box.innerHTML = '<p style="margin:0;color:var(--muted-text);font-size:13px">Claude AI 5 konu satırı öneriyor...</p>';
    var fd = new FormData();
    fd.append('csrf', csrf);
    fd.append('body', content);
    fd.append('tone', 'warm');
    fetch('<?= SITE_URL ?>/admin_panel/ajax/ai-subject.php', { method:'POST', body:fd, credentials:'same-origin' })
      .then(function(r){ return r.json().catch(function(){ return null; }); })
      .then(function(data){
        btn.disabled = false; btn.textContent = '✨ AI Öner';
        if (!data || !data.ok) {
          box.innerHTML = '<p style="margin:0;color:#9A2A2A;font-size:13px">Hata: ' + ((data && data.error) || 'Bilinmeyen hata') + '</p>';
          return;
        }
        var html = '<p style="margin:0 0 10px;font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:var(--muted-text);font-weight:600">Öneriler — tıkla &amp; kullan</p><div style="display:flex;flex-direction:column;gap:6px">';
        data.suggestions.forEach(function(s){
          html += '<button type="button" data-subject="' + s.replace(/"/g,'&quot;') + '" style="text-align:left;padding:10px 12px;border:1px solid var(--gold-border);background:#fff;border-radius:6px;cursor:pointer;font-size:13px;color:var(--ink);font-family:inherit;transition:all .15s">' + s.replace(/</g,'&lt;') + ' <small style="color:var(--muted-text);float:right">' + s.length + ' karakter</small></button>';
        });
        html += '</div>';
        box.innerHTML = html;
        box.querySelectorAll('button[data-subject]').forEach(function(b){
          b.addEventListener('mouseenter', function(){ b.style.borderColor='var(--gold)'; b.style.background='var(--cream)'; });
          b.addEventListener('mouseleave', function(){ b.style.borderColor='var(--gold-border)'; b.style.background='#fff'; });
          b.addEventListener('click', function(){
            subjEl.value = b.getAttribute('data-subject');
            box.style.display = 'none';
            subjEl.focus();
          });
        });
      })
      .catch(function(){
        btn.disabled = false; btn.textContent = '✨ AI Öner';
        box.innerHTML = '<p style="margin:0;color:#9A2A2A;font-size:13px">Bağlantı hatası.</p>';
      });
  });
})();
</script>
<?php require_once __DIR__ . '/../core/footer.php'; ?>
