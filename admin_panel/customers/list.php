<?php
$page='customers'; $title='Müşteriler';
require_once __DIR__ . '/../core/auth.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$role = isset($_GET['role']) ? $_GET['role'] : '';

$where = array(); $args = array();
if ($q !== '')   { $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)'; $args[]="%$q%"; $args[]="%$q%"; $args[]="%$q%"; }
if ($role!=='')  { $where[] = 'u.role = ?'; $args[]=$role; }
$sql = "SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id=u.id) AS orders_cnt FROM users u";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY u.created_at DESC';
$st = db()->prepare($sql); $st->execute($args); $rows = $st->fetchAll();

require_once __DIR__ . '/../core/header.php';
?>
<div class="panel">
  <div class="toolbar">
    <form class="search" method="get" style="display:flex;gap:8px;flex:1;max-width:480px">
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Ad, e-posta veya telefon ara…">
      <select name="role" onchange="this.form.submit()" style="padding:10px 14px;background:transparent;border:1px solid var(--gold-border);border-radius:5px;color:var(--champagne)">
        <option value="">Tüm roller</option>
        <option value="customer" <?= $role==='customer'?'selected':'' ?>>Müşteri</option>
        <option value="admin"    <?= $role==='admin'?'selected':'' ?>>Yönetici</option>
      </select>
    </form>
  </div>
  <table>
    <thead><tr><th>ID</th><th>Ad</th><th>E-posta</th><th>Telefon</th><th>Rol</th><th>Sipariş</th><th>Kayıt</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $u): ?>
        <tr>
          <td>#<?= (int)$u['id'] ?></td>
          <td><strong style="color:var(--champagne)"><?= e($u['name']) ?></strong></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($u['phone'] ?? '-') ?></td>
          <td><span class="status <?= $u['role']==='admin'?'paid':'' ?>"><?= e(role_label($u['role'])) ?></span></td>
          <td><?= (int)$u['orders_cnt'] ?></td>
          <td class="muted" style="font-size:12px"><?= e($u['created_at']) ?></td>
          <td style="white-space:nowrap"><a class="btn btn-secondary btn-sm" href="view.php?id=<?= (int)$u['id'] ?>">Detay</a> <a class="btn btn-secondary btn-sm" href="edit.php?id=<?= (int)$u['id'] ?>">Düzenle</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="8" class="muted">Sonuç yok.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../core/footer.php'; ?>
