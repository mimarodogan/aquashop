<?php
// Mobil menü paneli — components/header.php tarafından dahil edilir.
// Beklenen değişkenler: $page (aktif sayfa anahtarı)
$page = $page ?? '';
?>
<div class="mobile-menu" id="mobileMenu" role="dialog" aria-label="Mobil menü">
  <div class="mm-overlay" data-close="mobile-menu" aria-hidden="true"></div>
  <aside class="mm-panel">
    <div class="mm-head">
      <span class="logo"><?= e(trim((string)(setting('site_name') ?? '')) ?: SITE_NAME_FALLBACK) ?></span>
      <button type="button" class="mm-close" aria-label="Kapat" data-close="mobile-menu">×</button>
    </div>
    <nav class="mm-links">
      <a href="<?= url('products') ?>"        class="<?= $page==='products'?'active':'' ?>">Ürünler</a>
      <a href="<?= url('categories_list') ?>" class="<?= $page==='categories_list'?'active':'' ?>">Kategoriler</a>
      <a href="<?= url('blog') ?>"            class="<?= $page==='blog'?'active':'' ?>">Blog</a>
      <a href="<?= url('about') ?>"           class="<?= $page==='about'?'active':'' ?>">Hakkımızda</a>
      <a href="<?= url('contact') ?>"         class="<?= $page==='contact'?'active':'' ?>">İletişim</a>
    </nav>
    <div class="mm-divider"></div>
    <nav class="mm-links">
      <?php if (current_user()): ?>
        <a href="<?= url('account') ?>">Hesabım</a>
        <a href="<?= url('favorites') ?>">Favorilerim</a>
        <a href="<?= url('cart') ?>">Sepetim<?php if (cart_count()>0): ?> · <?= cart_count() ?><?php endif; ?></a>
        <?php if (current_user()['role']==='admin'): ?>
          <a href="<?= url('admin') ?>">Yönetim Paneli</a>
        <?php endif; ?>
        <a href="<?= url('logout') ?>">Çıkış Yap</a>
      <?php else: ?>
        <a href="<?= url('login') ?>">Giriş Yap</a>
        <a href="<?= url('register') ?>">Üye Ol</a>
        <a href="<?= url('cart') ?>">Sepetim<?php if (cart_count()>0): ?> · <?= cart_count() ?><?php endif; ?></a>
      <?php endif; ?>
    </nav>
    <div class="mm-divider"></div>
    <div class="mm-foot">
      <p class="muted" style="font-size:12px;letter-spacing:.18em;text-transform:uppercase;margin-bottom:10px">İletişim</p>
      <p style="font-size:13px"><?= e(setting('contact_phone','-')) ?></p>
      <p style="font-size:13px"><?= e(setting('contact_email','-')) ?></p>
    </div>
  </aside>
</div>
