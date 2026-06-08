<?php
$page='layout'; $title='Sayfa Düzeni';
require_once __DIR__ . '/core/auth.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check(isset($_POST['csrf'])?$_POST['csrf']:null)) {
    $st = db()->prepare('INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $keys = array(
        'home_featured_count'      => max(0, min(48, (int)$_POST['home_featured_count'])),
        'home_blog_count'          => max(0, min(12, (int)$_POST['home_blog_count'])),
        'home_secondary_count'     => max(0, min(24, (int)$_POST['home_secondary_count'])),
        'home_secondary_kicker'    => trim($_POST['home_secondary_kicker']),
        'home_secondary_title'     => trim($_POST['home_secondary_title']),
        'home_show_categories'     => !empty($_POST['home_show_categories']) ? '1' : '0',
        'home_show_features'       => !empty($_POST['home_show_features']) ? '1' : '0',
        'home_show_newsletter'     => !empty($_POST['home_show_newsletter']) ? '1' : '0',
        'home_featured_title'      => trim($_POST['home_featured_title']),
        'home_featured_kicker'     => trim($_POST['home_featured_kicker']),
        'home_blog_title'          => trim($_POST['home_blog_title']),
        'home_blog_kicker'         => trim($_POST['home_blog_kicker']),
        'home_show_most_faved'     => !empty($_POST['home_show_most_faved']) ? '1' : '0',
        'home_most_faved_count'    => max(0, min(24, (int)$_POST['home_most_faved_count'])),
        'home_most_faved_kicker'   => trim($_POST['home_most_faved_kicker']),
        'home_most_faved_title'    => trim($_POST['home_most_faved_title']),
    );
    foreach ($keys as $k=>$v) $st->execute(array($k, (string)$v));
    flash_set('ok','Sayfa düzeni güncellendi.');
    redirect('layout.php');
}

require_once __DIR__ . '/core/header.php';
?>
<div class="panel">
  <p class="muted" style="margin-bottom:18px">Anasayfada gösterilen blokları, sayıları ve başlıkları buradan yönetebilirsiniz.</p>
  <form method="post" style="display:grid;gap:18px;max-width:760px">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="row-2">
      <div class="field"><label>Öne Çıkan Ürün Sayısı</label><input type="number" name="home_featured_count" min="0" max="48" value="<?= e(setting('home_featured_count','12')) ?>"></div>
      <div class="field"><label>Blog Yazısı Sayısı</label><input type="number" name="home_blog_count" min="0" max="12" value="<?= e(setting('home_blog_count','4')) ?>"></div>
    </div>

    <div class="row-2">
      <div class="field"><label>Ürünler Bölümü Üst Etiketi</label><input name="home_featured_kicker" value="<?= e(setting('home_featured_kicker','Öne Çıkan Ürünler')) ?>"></div>
      <div class="field"><label>Ürünler Bölümü Başlığı</label><input name="home_featured_title" value="<?= e(setting('home_featured_title','Seçkin Koleksiyon')) ?>"></div>
    </div>

    <div class="row-2">
      <div class="field"><label>Blog Bölümü Üst Etiketi</label><input name="home_blog_kicker" value="<?= e(setting('home_blog_kicker','Blog')) ?>"></div>
      <div class="field"><label>Blog Bölümü Başlığı</label><input name="home_blog_title" value="<?= e(setting('home_blog_title','Son Yazılar')) ?>"></div>
    </div>

    <div class="divider"></div>
    <h3 style="font-size:18px">Blog Altı Ürün Bloğu</h3>

    <div class="row-2">
      <div class="field"><label>Ürün Sayısı</label><input type="number" name="home_secondary_count" min="0" max="24" value="<?= e(setting('home_secondary_count','4')) ?>"></div>
      <div class="field"><label>Üst Etiket</label><input name="home_secondary_kicker" value="<?= e(setting('home_secondary_kicker','Mağazadan')) ?>"></div>
    </div>
    <div class="field"><label>Başlık</label><input name="home_secondary_title" value="<?= e(setting('home_secondary_title','Beğenebileceğiniz Ürünler')) ?>"></div>

    <div class="divider"></div>
    <h3 style="font-size:18px">En Çok Favoriye Eklenenler Bölümü</h3>

    <div class="row-2">
      <div class="field"><label>Ürün Sayısı</label><input type="number" name="home_most_faved_count" min="0" max="24" value="<?= e(setting('home_most_faved_count','8')) ?>"></div>
      <div class="field"><label>Üst Etiket</label><input name="home_most_faved_kicker" value="<?= e(setting('home_most_faved_kicker','Favoriler')) ?>"></div>
    </div>
    <div class="field"><label>Başlık</label><input name="home_most_faved_title" value="<?= e(setting('home_most_faved_title','En Çok Favoriye Eklenenler')) ?>"></div>

    <div style="display:flex;flex-direction:column;gap:10px;margin-top:8px">
      <label><input type="checkbox" name="home_show_categories" value="1" <?= setting('home_show_categories','1')==='1'?'checked':'' ?>> Kategori şeridini göster</label>
      <label><input type="checkbox" name="home_show_features"   value="1" <?= setting('home_show_features','1')==='1'?'checked':'' ?>> "Söz Verdiklerimiz" kutucuklarını göster</label>
      <label><input type="checkbox" name="home_show_newsletter" value="1" <?= setting('home_show_newsletter','1')==='1'?'checked':'' ?>> Bülten kutusunu göster</label>
      <label><input type="checkbox" name="home_show_most_faved" value="1" <?= setting('home_show_most_faved','1')==='1'?'checked':'' ?>> "En Çok Favoriye Eklenenler" bölümünü göster</label>
    </div>

    <div><button class="btn btn-primary">Kaydet</button></div>
  </form>
</div>
<?php require_once __DIR__ . '/core/footer.php'; ?>
