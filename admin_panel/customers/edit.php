<?php
$page='customers';
require_once __DIR__ . '/../core/auth.php';

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
$st = db()->prepare('SELECT * FROM users WHERE id=?'); $st->execute(array($id));
$u = $st->fetch();
if (!$u) { flash_set('err','Kullanıcı bulunamadı.'); redirect('list.php'); }
$title = 'Kullanıcı: ' . $u['name'];

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check(isset($_POST['csrf'])?$_POST['csrf']:null)) {
    $action = isset($_POST['action']) ? $_POST['action'] : 'profile';

    if ($action === 'profile') {
        $first = trim(isset($_POST['first_name'])?$_POST['first_name']:'');
        $last  = trim(isset($_POST['last_name'])?$_POST['last_name']:'');
        $name  = trim($first.' '.$last);
        if ($name === '') $name = trim(isset($_POST['name'])?$_POST['name']:$u['name']);
        $email = trim(isset($_POST['email'])?$_POST['email']:'');
        $phone = trim(isset($_POST['phone'])?$_POST['phone']:'');
        $addr  = trim(isset($_POST['address'])?$_POST['address']:'');
        $birth = trim(isset($_POST['birth_date'])?$_POST['birth_date']:'') ?: null;
        $role  = (isset($_POST['role']) && $_POST['role']==='admin') ? 'admin' : 'customer';
        $emailC= !empty($_POST['email_consent'])?1:0;
        $smsC  = !empty($_POST['sms_consent'])?1:0;

        // Son admini düşürmeyi engelle
        if ($u['role']==='admin' && $role==='customer') {
            $cnt = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
            if ($cnt <= 1) { flash_set('err','En az bir admin kalmalı; rol değiştirilemedi.'); redirect('edit.php?id='.$id); }
        }

        // O-13 AUDIT LOG: rol değişikliğini sunucu log'una yaz (kim, kimi, ne zaman, hangi rolden hangi role)
        if ($u['role'] !== $role) {
            $actor = current_user();
            error_log(sprintf(
                '[audit] role_change actor_id=%d (%s) target_user_id=%d (%s) from=%s to=%s at=%s',
                (int)($actor['id'] ?? 0),
                $actor['email'] ?? '?',
                $id,
                $u['email'] ?? '?',
                $u['role'],
                $role,
                date('c')
            ));
        }
        try {
            $st = db()->prepare('UPDATE users SET name=?,first_name=?,last_name=?,email=?,phone=?,address=?,birth_date=?,role=?,email_consent=?,sms_consent=? WHERE id=?');
            $st->execute(array($name,$first,$last,$email,$phone,$addr,$birth,$role,$emailC,$smsC,$id));
            flash_set('ok','Kullanıcı bilgileri güncellendi.');
        } catch (PDOException $e) {
            // O-2: PDO mesaj sızıntısı kapalı
            error_log('[customer edit] PDOException: ' . $e->getMessage());
            flash_set('err', (isset($e->errorInfo[1]) && $e->errorInfo[1]==1062) ? 'Bu e-posta başka bir kullanıcıda kayıtlı.' : 'Güncelleme başarısız.');
        }
        redirect('edit.php?id='.$id);
    }

    if ($action === 'password') {
        $pw = isset($_POST['password']) ? $_POST['password'] : '';
        if (strlen($pw) < 6) { flash_set('err','Şifre en az 6 karakter olmalı.'); }
        else {
            db()->prepare('UPDATE users SET password=? WHERE id=?')
                ->execute(array(password_hash($pw, PASSWORD_DEFAULT), $id));
            flash_set('ok','Şifre güncellendi.');
        }
        redirect('edit.php?id='.$id);
    }

    if ($action === 'delete') {
        if ($u['role']==='admin') {
            $cnt = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
            if ($cnt <= 1) { flash_set('err','Tek admin silinemez.'); redirect('edit.php?id='.$id); }
        }
        db()->prepare('DELETE FROM users WHERE id=?')->execute(array($id));
        flash_set('ok','Kullanıcı silindi.');
        redirect('list.php');
    }
}

$st = db()->prepare('SELECT COUNT(*) FROM orders WHERE user_id=? OR email=?'); $st->execute(array($id, $u['email']));
$ordCnt = (int)$st->fetchColumn();

require_once __DIR__ . '/../core/header.php';
?>
<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px">
  <div>
    <div class="panel">
      <h3>Profil Bilgileri</h3>
      <form method="post" style="display:grid;gap:14px">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="profile">
        <div class="row-2">
          <div class="field"><label>Ad</label><input name="first_name" value="<?= e($u['first_name']) ?>"></div>
          <div class="field"><label>Soyad</label><input name="last_name" value="<?= e($u['last_name']) ?>"></div>
        </div>
        <div class="field"><label>Görünen Ad (boşsa Ad Soyad)</label><input name="name" value="<?= e($u['name']) ?>"></div>
        <div class="row-2">
          <div class="field"><label>E-posta</label><input name="email" type="email" value="<?= e($u['email']) ?>" required></div>
          <div class="field"><label>Telefon</label><input name="phone" value="<?= e($u['phone']) ?>"></div>
        </div>
        <div class="row-2">
          <div class="field"><label>Doğum Tarihi</label><input name="birth_date" type="date" value="<?= e($u['birth_date']) ?>"></div>
          <div class="field"><label>Rol</label>
            <select name="role">
              <option value="customer" <?= $u['role']==='customer'?'selected':'' ?>>Müşteri</option>
              <option value="admin"    <?= $u['role']==='admin'?'selected':'' ?>>Yönetici</option>
            </select>
          </div>
        </div>
        <div class="field"><label>Adres</label><textarea name="address"><?= e($u['address']) ?></textarea></div>
        <div style="display:flex;gap:24px">
          <label><input type="checkbox" name="email_consent" value="1" <?= $u['email_consent']?'checked':'' ?>> E-posta izni</label>
          <label><input type="checkbox" name="sms_consent" value="1" <?= $u['sms_consent']?'checked':'' ?>> SMS izni</label>
        </div>
        <div><button class="btn btn-primary">Kaydet</button></div>
      </form>
    </div>

    <div class="panel">
      <h3>Şifreyi Sıfırla</h3>
      <form method="post" style="display:grid;gap:14px;max-width:420px">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="password">
        <div class="field"><label>Yeni Şifre (en az 6 karakter)</label><input name="password" type="password" minlength="6" required></div>
        <div><button class="btn btn-primary">Şifreyi Güncelle</button></div>
      </form>
    </div>
  </div>

  <aside>
    <div class="panel">
      <h3>Hesap</h3>
      <p class="muted" style="font-size:12px">ID</p><p style="margin-bottom:10px">#<?= (int)$u['id'] ?></p>
      <p class="muted" style="font-size:12px">Rol</p><p style="margin-bottom:10px"><span class="status <?= $u['role']==='admin'?'paid':'' ?>"><?= e(role_label($u['role'])) ?></span></p>
      <p class="muted" style="font-size:12px">Sipariş Sayısı</p><p style="margin-bottom:10px"><?= $ordCnt ?></p>
      <p class="muted" style="font-size:12px">Kayıt</p><p><?= e($u['created_at']) ?></p>
      <div class="divider"></div>
      <a class="btn btn-secondary btn-sm" href="list.php">← Listeye Dön</a>
    </div>
    <div class="panel">
      <h3>Tehlikeli Bölge</h3>
      <form method="post" onsubmit="return confirm('Kullanıcı kalıcı olarak silinsin mi?')">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="delete">
        <button class="btn btn-secondary btn-sm" style="color:#f5a3a3;border-color:#f5a3a3">Kullanıcıyı Sil</button>
      </form>
    </div>
  </aside>
</div>
<?php require_once __DIR__ . '/../core/footer.php'; ?>
