<?php
$page='messages'; $title='Mesajlar';
require_once __DIR__ . '/core/auth.php';
if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? null)) {
    if (($_POST['action']??'')==='delete') db()->prepare('DELETE FROM contact_messages WHERE id=?')->execute([(int)$_POST['id']]);
    if (($_POST['action']??'')==='read')   db()->prepare('UPDATE contact_messages SET is_read=1 WHERE id=?')->execute([(int)$_POST['id']]);
    redirect('messages.php');
}
$rows=db()->query('SELECT * FROM contact_messages ORDER BY created_at DESC')->fetchAll();
require_once __DIR__ . '/core/header.php';
?>
<div class="panel">
  <table>
    <thead><tr><th>Tarih</th><th>Ad</th><th>E-posta</th><th>Konu</th><th>Mesaj</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $m): ?>
      <tr style="<?= $m['is_read']?'opacity:.6':'' ?>">
        <td class="muted" style="font-size:12px"><?= e($m['created_at']) ?></td>
        <td><?= e($m['name']) ?></td><td><?= e($m['email']) ?></td>
        <td><?= e($m['subject'] ?: '-') ?></td>
        <td style="max-width:400px"><?= e($m['message']) ?></td>
        <td>
          <?php if (!$m['is_read']): ?>
            <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="read"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-secondary btn-sm">Okundu</button></form>
          <?php endif; ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Silinsin mi?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-secondary btn-sm">Sil</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/core/footer.php'; ?>
