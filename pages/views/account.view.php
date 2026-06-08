<?php
/**
 * Hesabım görünümü — sol menülü, sekmeli ("kumanda paneli") düzen.
 * core/controllers/account.php tarafından sağlanan değişkenleri kullanır.
 */
include __DIR__ . "/../../includes/header.php";

$__loyalty   = function_exists('loyalty_enabled') && loyalty_enabled();
$__savedOn   = function_exists('saved_items_enabled') && saved_items_enabled();
$__savedItems = $__savedOn ? saved_items_list((int)$user['id']) : [];
// Adres düzenleme/eklemeden gelindiyse doğrudan "Adreslerim" sekmesini aç
$__initialTab = (isset($_GET['edit_address'])) ? 'adresler' : '';
$__avatar = mb_strtoupper(mb_substr(trim($user['first_name'] ?: $user['name']), 0, 1));
?>
<section class="page-header"><div class="container"><span class="kicker">Hesap</span><h1 style="margin-top:10px">Merhaba, <?= e($user['name']) ?></h1></div></section>

<?php if ($err) toast_now('error', $err); ?>

<section><div class="container">
  <div class="acc-layout">

    <!-- ───────── SOL MENÜ ───────── -->
    <aside class="acc-nav">
      <div class="acc-user">
        <div class="acc-avatar"><?= e($__avatar ?: 'U') ?></div>
        <div style="min-width:0">
          <strong><?= e($user['name']) ?></strong>
          <span><?= e($user['email']) ?></span>
          <?php if ($__loyalty && !empty($user['loyalty_tier'])):
            $__tierLabels = ['new'=>'Yeni Üye','loyal'=>'Sadık Müşteri','vip'=>'VIP'];
            $__tierColors = ['new'=>'var(--muted-text)','loyal'=>'var(--leaf)','vip'=>'var(--gold)'];
            $__tier = $__tierLabels[$user['loyalty_tier']] ?? 'Üye';
            $__tcl  = $__tierColors[$user['loyalty_tier']] ?? 'var(--muted-text)'; ?>
            <span class="acc-tier" style="color:<?= $__tcl ?>"><?= $user['loyalty_tier']==='vip'?'★ ':'' ?><?= e($__tier) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <nav>
        <button type="button" class="acc-nav-item" data-tab="profil"><span class="i">👤</span> Profilim</button>
        <button type="button" class="acc-nav-item" data-tab="siparisler"><span class="i">📦</span> Siparişlerim <span class="acc-badge"><?= count($orders) ?></span></button>
        <button type="button" class="acc-nav-item" data-tab="adresler"><span class="i">📍</span> Adreslerim <span class="acc-badge"><?= count($addresses) ?></span></button>
        <?php if ($__loyalty): ?>
          <button type="button" class="acc-nav-item" data-tab="puanlar"><span class="i">🏆</span> Puanlarım</button>
        <?php endif; ?>
        <?php if ($__savedItems): ?>
          <button type="button" class="acc-nav-item" data-tab="sonraal"><span class="i">📋</span> Sonra Al <span class="acc-badge"><?= count($__savedItems) ?></span></button>
        <?php endif; ?>
        <button type="button" class="acc-nav-item" data-tab="email"><span class="i">✉️</span> E-posta Değiştir</button>
        <button type="button" class="acc-nav-item" data-tab="sifre"><span class="i">🔒</span> Şifre Değiştir</button>
        <a class="acc-nav-item" href="<?= url('favorites') ?>"><span class="i">❤️</span> Favorilerim <span class="acc-badge"><?= fav_count() ?></span></a>
        <a class="acc-nav-item acc-nav-logout" href="<?= url('logout') ?>"><span class="i">↪</span> Çıkış Yap</a>
      </nav>
    </aside>

    <!-- ───────── İÇERİK ───────── -->
    <div class="acc-content">

      <!-- PROFİL -->
      <section class="acc-panel" id="tab-profil">
        <div class="panel">
          <h3 style="margin-bottom:18px">Profil Bilgileri</h3>
          <form method="post" style="display:grid;gap:14px">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="profile">
            <div class="row-2">
              <div class="field"><label>Ad</label><input name="first_name" value="<?= e($user['first_name']) ?>"></div>
              <div class="field"><label>Soyad</label><input name="last_name" value="<?= e($user['last_name']) ?>"></div>
            </div>
            <div class="row-2">
              <div class="field"><label>Telefon</label><input name="phone" value="<?= e($user['phone']) ?>"></div>
              <div class="field">
                <label>Doğum Tarihi <?php if (!empty($user['birth_date'])): ?><span style="color:var(--muted-text);font-weight:400;font-size:11px">(kilitli)</span><?php endif; ?></label>
                <?php if (!empty($user['birth_date'])): ?>
                  <input type="date" value="<?= e($user['birth_date']) ?>" readonly disabled style="background:var(--cream);color:var(--muted-text);cursor:not-allowed">
                  <small class="muted" style="font-size:12px;color:var(--muted-text)">🔒 Doğum tarihi güvenlik nedeniyle bir kez girildikten sonra değiştirilemez. Yanlış girdiyseniz <a href="<?= url('contact') ?>" style="color:var(--leaf);text-decoration:underline">iletişime geçin</a>.</small>
                <?php else: ?>
                  <input name="birth_date" type="date" value="" max="<?= date('Y-m-d') ?>">
                  <small class="muted" style="font-size:12px">Bir kez girdikten sonra değiştirilemez — doğum günü kuponu için gereklidir.</small>
                <?php endif; ?>
              </div>
            </div>
            <div style="display:flex;gap:24px;flex-wrap:wrap">
              <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-size:14px"><input type="checkbox" name="email_consent" value="1" <?= $user['email_consent']?'checked':'' ?> style="width:18px;height:18px;min-height:0;flex:0 0 auto;margin:0;accent-color:var(--leaf)"> E-posta izni</label>
              <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-size:14px"><input type="checkbox" name="sms_consent" value="1" <?= $user['sms_consent']?'checked':'' ?> style="width:18px;height:18px;min-height:0;flex:0 0 auto;margin:0;accent-color:var(--leaf)"> SMS izni</label>
            </div>
            <div><button class="btn btn-primary">Kaydet</button></div>
          </form>
          <p class="muted" style="font-size:12px;margin-top:14px">📍 Teslimat adreslerinizi <strong>Adreslerim</strong> bölümünden yönetebilirsiniz.</p>
        </div>
      </section>

      <!-- SİPARİŞLER -->
      <section class="acc-panel" id="tab-siparisler">
        <div class="panel">
          <h3 style="margin-bottom:18px">Siparişlerim</h3>
          <?php if (!$orders): ?>
            <p class="muted">Henüz siparişiniz bulunmuyor.</p>
          <?php else:
            $timeline = [
              'pending'   => ['label'=>'Sipariş Alındı', 'icon'=>'📋'],
              'paid'      => ['label'=>'Onaylandı',     'icon'=>'✓'],
              'shipped'   => ['label'=>'Kargoda',       'icon'=>'🚚'],
              'delivered' => ['label'=>'Teslim Edildi', 'icon'=>'📦'],
            ];
            $order_indexes = ['pending'=>0,'paid'=>1,'shipped'=>2,'delivered'=>3,'cancelled'=>-1];
          ?>
            <?php foreach ($orders as $o): $tu = tracking_url($o['tracking_carrier'], $o['tracking_number']); $cur = $order_indexes[$o['status']] ?? 0; $cancelled = $o['status']==='cancelled'; ?>
              <div style="padding:18px;border:1px solid var(--gold-border);border-radius:var(--radius);margin-bottom:14px;background:var(--olive-2)">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:14px">
                  <div>
                    <strong style="color:var(--ink);font-size:16px">Sipariş #<?= (int)$o['id'] ?></strong>
                    <div style="color:var(--muted-text);font-size:13px;margin-top:2px"><?= e(date('d.m.Y H:i', strtotime($o['created_at']))) ?></div>
                  </div>
                  <div style="text-align:right">
                    <div style="font-weight:600;color:var(--ink);font-size:18px"><?= money($o['total']) ?></div>
                    <span class="status <?= e($o['status']) ?>" style="margin-top:4px;display:inline-block"><?= e(status_label($o['status'])) ?></span>
                  </div>
                </div>
                <?php if (!$cancelled): ?>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0;margin:18px 0 12px;position:relative">
                  <?php $idx = 0; foreach ($timeline as $key => $st): $done = $idx <= $cur; $isCur = $idx === $cur; ?>
                    <div style="display:flex;flex-direction:column;align-items:center;position:relative;z-index:2">
                      <div style="width:36px;height:36px;border-radius:50%;background:<?= $done?'var(--gold)':'var(--cream)' ?>;color:<?= $done?'var(--on-dark)':'var(--muted-text)' ?>;display:grid;place-items:center;font-size:16px;border:2px solid <?= $done?'var(--gold)':'var(--gold-border)' ?>;<?= $isCur?'box-shadow:0 0 0 4px rgba(107,122,47,.18)':'' ?>"><?= $st['icon'] ?></div>
                      <div style="font-size:11px;letter-spacing:.06em;color:<?= $done?'var(--ink)':'var(--muted-text)' ?>;margin-top:6px;text-align:center;font-weight:<?= $isCur?'600':'500' ?>"><?= e($st['label']) ?></div>
                    </div>
                  <?php $idx++; endforeach; ?>
                  <div style="position:absolute;top:18px;left:14%;right:14%;height:2px;background:var(--gold-border);z-index:1"></div>
                  <?php if ($cur > 0): $progressPct = ($cur / 3) * 72; ?>
                    <div style="position:absolute;top:18px;left:14%;width:<?= $progressPct ?>%;height:2px;background:var(--gold);z-index:1"></div>
                  <?php endif; ?>
                </div>
                <?php else: ?>
                  <div style="padding:14px;background:#FBEFEF;border-radius:var(--radius);color:#7A1F1F;font-size:13px;margin-bottom:12px">⊗ Sipariş iptal edildi<?= !empty($o['cancellation_reason'])?' — '.e($o['cancellation_reason']):'' ?></div>
                <?php endif; ?>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
                  <a class="btn btn-secondary btn-sm" href="<?= e(url('order', ['id'=>$o['id']])) ?>">Detay</a>
                  <?php if ($tu): ?><a class="btn btn-secondary btn-sm" href="<?= e($tu) ?>" target="_blank">Kargo Takip</a><?php endif; ?>
                  <?php if (in_array($o['status'], ['paid','shipped','delivered']) && (strtotime($o['created_at']) > strtotime('-30 days'))): ?>
                    <a class="btn btn-secondary btn-sm" href="<?= e(url('return', ['order'=>$o['id']])) ?>" style="color:#9A2A2A;border-color:#C99090">İade Talep Et</a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <!-- ADRESLER -->
      <section class="acc-panel" id="tab-adresler">
        <div class="panel" id="adresler">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
            <h3 style="margin:0">Adreslerim</h3>
            <a class="btn btn-secondary btn-sm" href="?edit_address=new#adresler">+ Yeni Adres</a>
          </div>

          <?php if ($editAddress || isset($_GET['edit_address'])): ?>
            <form method="post" style="display:grid;gap:14px;margin-bottom:18px;padding:18px;border:1px solid var(--gold-border);border-radius:var(--radius);background:var(--cream)">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="<?= $editAddress ? 'address_update' : 'address_add' ?>">
              <?php if ($editAddress): ?><input type="hidden" name="address_id" value="<?= (int)$editAddress['id'] ?>"><?php endif; ?>
              <div class="row-2">
                <div class="field"><label>Adres Etiketi</label><input name="label" value="<?= e($editAddress['label'] ?? 'Ev') ?>" placeholder="Ev / İş / Annem"></div>
                <div class="field"><label>Ad Soyad <span class="req" aria-hidden="true">*</span></label><input name="full_name" value="<?= e($editAddress['full_name'] ?? $user['name']) ?>" required></div>
              </div>
              <div class="row-2">
                <div class="field"><label>Telefon <span class="req" aria-hidden="true">*</span></label><input name="phone" value="<?= e($editAddress['phone'] ?? $user['phone'] ?? '') ?>" required></div>
                <div class="field"><label>Şehir <span class="req" aria-hidden="true">*</span></label><input name="city" value="<?= e($editAddress['city'] ?? '') ?>" required></div>
              </div>
              <div class="row-2">
                <div class="field"><label>İlçe</label><input name="district" value="<?= e($editAddress['district'] ?? '') ?>"></div>
                <div class="field"><label>Posta Kodu</label><input name="zip" value="<?= e($editAddress['zip'] ?? '') ?>"></div>
              </div>
              <div class="field"><label>Adres <span class="req" aria-hidden="true">*</span></label><textarea name="address_line" rows="2" required><?= e($editAddress['address'] ?? '') ?></textarea></div>
              <label style="display:flex;gap:10px;align-items:center;font-size:14px;text-transform:none;letter-spacing:0"><input type="checkbox" name="is_default" value="1" <?= !empty($editAddress['is_default']) ? 'checked' : '' ?> style="width:18px;height:18px;min-height:0;flex:0 0 auto;margin:0;accent-color:var(--leaf)"> Varsayılan adres yap</label>
              <div class="btn-row">
                <button class="btn btn-primary"><?= $editAddress ? 'Güncelle' : 'Adresi Kaydet' ?></button>
                <a class="btn btn-secondary" href="<?= url('account') ?>#adresler">Vazgeç</a>
              </div>
            </form>
          <?php endif; ?>

          <?php if (!$addresses): ?>
            <p class="muted">Henüz kayıtlı adresiniz yok. Yeni adres ekleyerek checkout'ta hızlıca seçebilirsiniz.</p>
          <?php else: ?>
            <div style="display:grid;gap:12px">
              <?php foreach ($addresses as $a): ?>
                <div style="padding:16px;border:1px solid var(--gold-border);border-radius:var(--radius);<?= $a['is_default']?'background:rgba(107,122,47,.06)':'' ?>">
                  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
                    <div>
                      <div style="font-weight:600;color:var(--ink);margin-bottom:4px">
                        <?= e($a['label']) ?>
                        <?php if ($a['is_default']): ?><span class="chip" style="background:var(--gold);color:var(--on-dark);border:none;margin-left:6px">Varsayılan</span><?php endif; ?>
                      </div>
                      <div style="font-size:14px;color:var(--champagne);line-height:1.55"><?= e($a['full_name']) ?> · <?= e($a['phone']) ?></div>
                      <div style="font-size:13px;color:var(--muted-text);margin-top:6px;line-height:1.5"><?= nl2br(e($a['address'])) ?><br><?= e($a['city']) ?><?= $a['district']?' / '.e($a['district']):'' ?><?= $a['zip']?' · '.e($a['zip']):'' ?></div>
                    </div>
                    <div class="actions">
                      <a class="btn btn-secondary btn-sm" href="?edit_address=<?= (int)$a['id'] ?>#adresler">Düzenle</a>
                      <?php if (!$a['is_default']): ?>
                        <form method="post" style="display:inline">
                          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                          <input type="hidden" name="action" value="address_set_default">
                          <input type="hidden" name="address_id" value="<?= (int)$a['id'] ?>">
                          <button class="btn btn-secondary btn-sm">Varsayılan Yap</button>
                        </form>
                      <?php endif; ?>
                      <form method="post" style="display:inline" onsubmit="return confirm('Bu adres silinsin mi?')">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="address_delete">
                        <input type="hidden" name="address_id" value="<?= (int)$a['id'] ?>">
                        <button class="btn btn-danger btn-sm">Sil</button>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <?php if ($__loyalty):
        $__bal = loyalty_balance((int)$user['id']);
        $__history = loyalty_history((int)$user['id'], 8);
        $__cashValue = loyalty_value_of($__bal);
      ?>
      <!-- PUANLAR -->
      <section class="acc-panel" id="tab-puanlar">
        <div class="panel">
          <h3 style="margin-bottom:14px">🏆 Puanlarım</h3>
          <div style="text-align:center;padding:24px;background:linear-gradient(135deg,rgba(201,162,75,.08),rgba(107,122,47,.05));border-radius:8px;margin-bottom:14px">
            <p style="margin:0;font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted-text)">Mevcut Bakiye</p>
            <p style="margin:8px 0 0;font-size:42px;color:var(--gold);font-family:Georgia,serif;font-weight:600;line-height:1"><?= $__bal ?> <small style="font-size:16px;color:var(--muted-text);font-weight:400">puan</small></p>
            <p style="margin:6px 0 0;font-size:14px;color:var(--leaf)">≈ <?= money($__cashValue) ?> indirim</p>
          </div>
          <?php if ($__history):
            $__typeLabels = ['earn'=>'Kazandın','redeem'=>'Kullandın','expire'=>'Süresi doldu','adjust'=>'Düzeltme','refund'=>'İade'];
            $__typeColors = ['earn'=>'var(--leaf)','refund'=>'var(--leaf)','adjust'=>'var(--leaf)','redeem'=>'#9A6020','expire'=>'#9A2A2A'];
          ?>
            <p class="muted" style="font-size:11px;letter-spacing:.18em;text-transform:uppercase;margin-bottom:8px">Son Hareketler</p>
            <div style="display:flex;flex-direction:column;gap:4px;font-size:13px">
              <?php foreach ($__history as $h): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--gold-border)">
                  <span style="font-size:12px"><strong style="color:<?= $__typeColors[$h['type']] ?? 'var(--ink)' ?>"><?= e($__typeLabels[$h['type']] ?? $h['type']) ?></strong><br><small class="muted" style="font-size:10px"><?= e(substr($h['created_at'],0,10)) ?></small></span>
                  <span style="font-weight:600;color:<?= ($h['points']>=0) ? 'var(--leaf)' : '#9A2A2A' ?>"><?= $h['points']>=0?'+':'' ?><?= (int)$h['points'] ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <p class="muted" style="font-size:12px;margin-top:14px;line-height:1.5;text-align:center">Her <?= money(loyalty_earn_rate()) ?> harcamada <strong style="color:var(--leaf)">1 puan</strong> kazanırsın. Min. kullanım: <?= loyalty_min_redeem() ?> puan.</p>
        </div>
      </section>
      <?php endif; ?>

      <?php if ($__savedItems): ?>
      <!-- SONRA AL -->
      <section class="acc-panel" id="tab-sonraal">
        <div class="panel">
          <h3 style="margin-bottom:18px">📋 Sonra Al Listem (<?= count($__savedItems) ?>)</h3>
          <div style="display:grid;gap:12px">
            <?php foreach ($__savedItems as $si): ?>
              <div style="display:flex;gap:14px;align-items:center;padding:12px;border:1px solid var(--gold-border);border-radius:var(--radius);background:var(--cream)">
                <?php if (!empty($si['image'])): ?>
                  <img loading="lazy" src="<?= e($si['image']) ?>" alt="" style="width:60px;height:60px;object-fit:cover;border-radius:6px;background:#fff">
                <?php endif; ?>
                <div style="flex:1;min-width:0">
                  <a href="<?= e(url('product', ['slug' => $si['slug']])) ?>" style="color:var(--ink);font-weight:600;text-decoration:none;font-size:14px"><?= e($si['name']) ?></a>
                  <p style="margin:3px 0 0;font-size:13px;color:var(--leaf);font-weight:600"><?= money($si['price']) ?> · <?= (int)$si['qty'] ?> adet</p>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px">
                  <?php if ((int)$si['stock'] > 0 && (int)$si['is_active'] === 1): ?>
                    <form method="post" action="<?= SITE_URL ?>/saved-items.php" style="margin:0">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="move_to_cart">
                      <input type="hidden" name="product_id" value="<?= (int)$si['product_id'] ?>">
                      <input type="hidden" name="variant_id" value="<?= (int)($si['variant_id'] ?? 0) ?>">
                      <button class="btn btn-primary btn-sm" type="submit" style="font-size:11px">Sepete Taşı</button>
                    </form>
                  <?php else: ?>
                    <span class="status cancelled" style="font-size:9px">Stokta yok</span>
                  <?php endif; ?>
                  <form method="post" action="<?= SITE_URL ?>/saved-items.php" style="margin:0">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="product_id" value="<?= (int)$si['product_id'] ?>">
                    <input type="hidden" name="variant_id" value="<?= (int)($si['variant_id'] ?? 0) ?>">
                    <button class="btn btn-secondary btn-sm" type="submit" style="font-size:11px" onclick="return confirm('Listeden çıkarılsın mı?')">Çıkar</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
      <?php endif; ?>

      <!-- E-POSTA -->
      <section class="acc-panel" id="tab-email">
        <div class="panel">
          <h3 style="margin-bottom:18px">E-posta Değiştir</h3>
          <form method="post" style="display:grid;gap:14px;max-width:520px">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="email">
            <div class="field"><label>Yeni E-posta</label><input name="email" type="email" value="<?= e($user['email']) ?>" required></div>
            <div class="field"><label>Mevcut Şifre (doğrulama)</label><input name="current_password" type="password" required></div>
            <div><button class="btn btn-primary">E-postayı Güncelle</button></div>
          </form>
        </div>
      </section>

      <!-- ŞİFRE -->
      <section class="acc-panel" id="tab-sifre">
        <div class="panel">
          <h3 style="margin-bottom:18px">Şifre Değiştir</h3>
          <form method="post" style="display:grid;gap:14px;max-width:520px">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="password">
            <div class="field"><label>Mevcut Şifre</label><input name="current_password" type="password" required></div>
            <div class="row-2">
              <div class="field"><label>Yeni Şifre (en az 6)</label><input name="new_password" type="password" minlength="6" required></div>
              <div class="field"><label>Yeni Şifre (tekrar)</label><input name="repeat_password" type="password" minlength="6" required></div>
            </div>
            <div><button class="btn btn-primary">Şifreyi Değiştir</button></div>
          </form>
        </div>
      </section>

    </div>
  </div>
</div></section>

<style>
.acc-layout{display:grid;grid-template-columns:260px 1fr;gap:32px;align-items:start}
/* Form satırları: iki kutu ÜSTTEN hizalı — bir alanın altında not/uyarı olsa bile diğeri kaymaz */
.acc-content .row-2{align-items:start}
.acc-content .field input,.acc-content .field select{min-height:48px;box-sizing:border-box}
/* Menü ve içerik kartı HER ZAMAN aynı üst hizada dursun (sticky kaldırıldı: kaydırınca ayrışmaz, birlikte kayar) */
.acc-nav{background:var(--olive-2);border:1px solid var(--gold-border);border-radius:var(--radius);padding:16px;display:flex;flex-direction:column;gap:4px}
.acc-user{display:flex;gap:12px;align-items:center;padding-bottom:14px;margin-bottom:8px;border-bottom:1px solid var(--gold-border)}
.acc-avatar{width:46px;height:46px;border-radius:50%;background:var(--gold);color:var(--on-dark);display:grid;place-items:center;font-size:20px;font-weight:600;font-family:Georgia,serif;flex:0 0 auto}
.acc-user strong{display:block;color:var(--ink);font-size:15px;line-height:1.3}
.acc-user span{display:block;color:var(--muted-text);font-size:12px;word-break:break-all;line-height:1.3}
.acc-tier{display:inline-block;margin-top:4px;font-size:11px;font-weight:600;letter-spacing:.06em}
.acc-nav-item{display:flex;align-items:center;gap:10px;width:100%;text-align:left;background:none;border:none;color:var(--champagne);font-size:14px;font-family:inherit;padding:11px 13px;border-radius:8px;cursor:pointer;transition:background .15s,color .15s;text-decoration:none;line-height:1.2}
.acc-nav-item .i{width:20px;text-align:center;flex:0 0 auto}
.acc-nav-item:hover{background:rgba(107,122,47,.08);color:var(--ink)}
.acc-nav-item.active{background:var(--gold);color:var(--on-dark);font-weight:600}
.acc-badge{margin-left:auto;background:rgba(0,0,0,.10);color:inherit;padding:1px 9px;border-radius:10px;font-size:12px;font-weight:600}
.acc-nav-item.active .acc-badge{background:rgba(255,255,255,.28)}
.acc-nav-logout{margin-top:6px;padding-top:13px;border-top:1px solid var(--gold-border);border-radius:0;color:#B5524F}
.acc-nav-logout:hover{background:rgba(154,42,42,.08);color:#9A2A2A}
.acc-panel{display:none}
.acc-panel.active{display:block;animation:accFade .2s ease}
@keyframes accFade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
@media(max-width:860px){
  .acc-layout{grid-template-columns:1fr;gap:18px}
  .acc-nav{position:static;top:auto}
}
</style>
<script>
(function () {
  var nav = document.querySelector('.acc-nav');
  if (!nav) return;
  var items  = Array.prototype.slice.call(nav.querySelectorAll('.acc-nav-item[data-tab]'));
  var panels = Array.prototype.slice.call(document.querySelectorAll('.acc-panel'));
  function show(tab) {
    var found = false;
    panels.forEach(function (p) { var on = p.id === 'tab-' + tab; p.classList.toggle('active', on); if (on) found = true; });
    items.forEach(function (i) { i.classList.toggle('active', i.dataset.tab === tab); });
    if (found) { try { history.replaceState(null, '', '#' + tab); } catch (e) {} }
    return found;
  }
  items.forEach(function (i) {
    i.addEventListener('click', function () { show(i.dataset.tab); window.scrollTo({ top: 0, behavior: 'smooth' }); });
  });
  // Başlangıç sekmesi: PHP yönlendirmesi (adres düzenleme) > URL hash > profil
  var forced = <?= json_encode($__initialTab) ?>;
  var fromHash = (location.hash || '').replace('#', '');
  var initial = forced || fromHash || 'profil';
  if (!show(initial)) show('profil');
})();
</script>

<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/auth.css">
<?php include __DIR__ . "/../../includes/footer.php"; ?>
