<?php
require_once __DIR__ . '/../includes/functions.php';
$page = 'post';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$st = db()->prepare("SELECT p.*, c.name AS cat_name, c.slug AS cat_slug, u.name AS author_name
                     FROM blog_posts p
                     LEFT JOIN blog_categories c ON c.id = p.category_id
                     LEFT JOIN users u ON u.id = p.author_id
                     WHERE p.slug = ? AND p.is_published = 1");
$st->execute(array($slug));
$post = $st->fetch();

if (!$post) {
    http_response_code(404);
    $title = 'Yazı bulunamadı';
    include __DIR__ . '/../includes/header.php';
    echo '<section class="container" style="padding:120px 0"><h1>Yazı bulunamadı</h1></section>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}
$title = $post['title'];

// Görüntülenme +1
try { db()->prepare('UPDATE blog_posts SET views = views + 1 WHERE id=?')->execute(array($post['id'])); } catch (Exception $e) {}

// Blog yazar profili
$blogAuthor = null;
try {
    if (!empty($post['blog_author_id'])) {
        $stA = db()->prepare('SELECT * FROM blog_authors WHERE id=? AND is_active=1');
        $stA->execute([(int)$post['blog_author_id']]);
        $blogAuthor = $stA->fetch() ?: null;
    }
} catch (\Throwable $e) {}

$related = db()->prepare("SELECT * FROM blog_posts WHERE is_published=1 AND id<>? AND category_id<=>? ORDER BY COALESCE(published_at,created_at) DESC LIMIT 3");
$related->execute(array($post['id'], $post['category_id']));
$related = $related->fetchAll();

$randomProducts = db()->query("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 ORDER BY RAND() LIMIT 3")->fetchAll();

// Yazıya özel SSS
$postFaqs = [];
try {
    $stf = db()->prepare('SELECT question, answer FROM blog_post_faqs WHERE post_id = ? ORDER BY sort_order, id');
    $stf->execute([$post['id']]);
    $postFaqs = $stf->fetchAll();
} catch (Exception $e) {
    // blog_post_faqs tablosu henüz yoksa görmezden gel
}

require_once __DIR__ . '/../components/json-ld.php';
$base = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off' ? 'https':'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
$extraSchemas = array(
    jsonld_article($post),
    jsonld_breadcrumb(array(
        array('name'=>'Anasayfa','url'=>$base . url('home')),
        array('name'=>'Blog','url'=>$base . url('blog')),
        array('name'=>$post['title'],'url'=>$base . url('blog_post', array('slug'=>$post['slug']))),
    )),
);

// FAQPage JSON-LD — mevcutsa ekle
if ($postFaqs) {
    $faqEntities = [];
    foreach ($postFaqs as $f) {
        $faqEntities[] = [
            '@type'          => 'Question',
            'name'           => $f['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']],
        ];
    }
    $extraSchemas[] = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $faqEntities,
    ];
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-header">
  <div class="container">
    <span class="kicker"><?= e($post['cat_name'] ?? 'Yazı') ?></span>
    <h1 style="margin-top:10px"><?= e($post['title']) ?></h1>
    <div class="breadcrumb">
      <a href="<?= url('home') ?>">Anasayfa</a><span>/</span>
      <a href="<?= url('blog') ?>">Blog</a><span>/</span>
      <?= e($post['title']) ?>
    </div>
    <p class="muted" style="margin-top:18px;font-size:13px;letter-spacing:.18em;text-transform:uppercase">
      <?php $__pubTs = strtotime($post['published_at'] ?? $post['created_at']); ?>
      <time datetime="<?= e(date('Y-m-d', $__pubTs)) ?>"><?= e(date('d.m.Y', $__pubTs)) ?></time>
      <?php
        // Güncellenme tarihi yayın tarihinden ≥1 gün sonraysa göster (aynı gün edit'leri dışla)
        if (!empty($post['updated_at'])
            && !empty($post['published_at'])
            && strtotime($post['updated_at']) > strtotime($post['published_at']) + 86400):
          $__updTs = strtotime($post['updated_at']);
      ?>
        · Güncelleme: <time datetime="<?= e(date('Y-m-d', $__updTs)) ?>"><?= e(date('d.m.Y', $__updTs)) ?></time>
      <?php endif; ?>
      <?php if (!empty($post['author_name'])): ?> · <?= e($post['author_name']) ?><?php endif; ?>
      · <?= (int)$post['views'] ?> görüntülenme
    </p>
  </div>
</section>

<section>
  <div class="container" style="max-width:860px">
    <?php if (!empty($post['cover_image'])): ?>
      <img loading="lazy" decoding="async" width="1200" height="675" src="<?= e($post['cover_image']) ?>" alt="<?= e($post['title']) ?>" style="width:100%;height:auto;border-radius:10px;border:1px solid var(--gold-border);margin-bottom:32px">
    <?php endif; ?>
    <div class="panel cms-content">
      <?php /* SEO: gövde içeriğindeki H1'ler H2'ye indirilir — sayfada tek H1 (yazı başlığı) kalsın. */ ?>
      <?php /* K-2 GÜVENLİK: sanitize_html() admin'in girdiği HTML'i XSS-temizler (script, iframe, on* handler, javascript: vb. sökülür). */ ?>
      <?= preg_replace('~<(/?)h1\b~i', '<$1h2', embed_videos(sanitize_html($post["content"]))) ?>
    </div>

    <?php
    /* Sosyal paylaşım butonları — blog post için */
    $shareUrl   = $base . url('blog_post', ['slug' => $post['slug']]);
    $shareTitle = $post['title'];
    $shareDesc  = $post['excerpt'] ?? '';
    $shareImage = $post['cover_image'] ?? '';
    include __DIR__ . '/../components/social-share.php';
    ?>
  </div>
</section>

<?php if ($postFaqs): ?>
<section style="padding-top:0;padding-bottom:56px">
  <div class="container" style="max-width:860px">
    <div class="panel">
      <h2 style="font-size:22px;margin-bottom:6px;color:var(--ink)">Sık Sorulan Sorular</h2>
      <p class="muted" style="font-size:13px;margin-bottom:20px">Bu yazıyla ilgili en çok merak edilen sorular</p>
      <div class="pd-faqs">
        <?php foreach ($postFaqs as $i => $f): ?>
          <details class="pd-faq" <?= $i === 0 ? 'open' : '' ?>>
            <summary><?= e($f['question']) ?></summary>
            <div class="pd-faq-a"><?= nl2br(e($f['answer'])) ?></div>
          </details>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($blogAuthor): ?>
<section style="padding-top:0;padding-bottom:48px">
  <div class="container" style="max-width:860px">
    <div class="panel" style="padding:24px">
      <p class="muted" style="font-size:11px;letter-spacing:.18em;text-transform:uppercase;margin:0 0 16px">Yazar Hakkında</p>
      <div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap">
        <!-- Avatar -->
        <div style="flex-shrink:0">
          <?php if (!empty($blogAuthor['avatar'])): ?>
            <img loading="lazy" src="<?= e($blogAuthor['avatar']) ?>" alt="<?= e($blogAuthor['name']) ?>"
                 style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid var(--gold-border)">
          <?php else: ?>
            <div style="width:72px;height:72px;border-radius:50%;background:var(--olive-2);border:2px solid var(--gold-border);display:grid;place-items:center;font-size:24px">👤</div>
          <?php endif; ?>
        </div>
        <!-- Bilgi -->
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px">
            <strong style="color:var(--champagne);font-size:17px"><?= e($blogAuthor['name']) ?></strong>
            <?php if (!empty($blogAuthor['title'])): ?>
              <span class="muted" style="font-size:13px">· <?= e($blogAuthor['title']) ?></span>
            <?php endif; ?>
            <!-- Sosyal linkler -->
            <?php if (!empty($blogAuthor['instagram'])): ?>
              <a href="https://instagram.com/<?= e($blogAuthor['instagram']) ?>" target="_blank" rel="noopener"
                 style="color:var(--muted-text);font-size:12px;text-decoration:none" title="Instagram">📸 @<?= e($blogAuthor['instagram']) ?></a>
            <?php endif; ?>
            <?php if (!empty($blogAuthor['twitter'])): ?>
              <a href="https://twitter.com/<?= e($blogAuthor['twitter']) ?>" target="_blank" rel="noopener"
                 style="color:var(--muted-text);font-size:12px;text-decoration:none" title="Twitter/X">𝕏 @<?= e($blogAuthor['twitter']) ?></a>
            <?php endif; ?>
            <?php if (!empty($blogAuthor['website'])): ?>
              <a href="<?= e($blogAuthor['website']) ?>" target="_blank" rel="noopener"
                 style="color:var(--gold);font-size:12px" title="Web Sitesi">🌐 Site</a>
            <?php endif; ?>
          </div>
          <?php if (!empty($blogAuthor['bio'])): ?>
            <p style="color:var(--muted-text);font-size:14px;line-height:1.7;margin:0"><?= nl2br(e($blogAuthor['bio'])) ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php $comments = comment_list('blog', $post['id']); ?>
<section>
  <div class="container" style="max-width:860px">
    <div class="section-head">
      <span class="kicker">Yorumlar</span>
      <h2><?= count($comments) ?> Yorum</h2>
    </div>

    <?php if (!$comments): ?>
      <p class="muted center" style="margin-bottom:24px">İlk yorumu yapan siz olun.</p>
    <?php else: ?>
      <div style="display:grid;gap:14px;margin-bottom:24px">
        <?php foreach ($comments as $c): ?>
          <div class="panel" style="padding:18px 22px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
              <strong style="color:var(--champagne)"><?= e($c['author_name']) ?></strong>
              <span class="muted" style="font-size:12px"><?= e(date('d.m.Y H:i', strtotime($c['created_at']))) ?></span>
            </div>
            <p style="color:var(--champagne)"><?= nl2br(e($c['body'])) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (current_user()): ?>
      <div class="panel">
        <h3 style="font-size:18px;margin-bottom:14px">Yorum Yap</h3>
        <form method="post" action="comment-add.php" style="display:grid;gap:14px">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="type" value="blog">
          <input type="hidden" name="target_id" value="<?= (int)$post['id'] ?>">
          <input type="hidden" name="back" value="<?= e(url('blog_post', ['slug'=>$post['slug']])) ?>">
          <div class="field"><label>Yorumunuz</label><textarea name="body" rows="4" required></textarea></div>
          <div><button class="btn btn-primary">Gönder</button></div>
        </form>
      </div>
    <?php else: ?>
      <div class="panel center">
        <p>Yorum yapmak için <a href="<?= url('login') ?>" style="color:var(--gold)">giriş yapın</a> veya <a href="<?= url('register') ?>" style="color:var(--gold)">üye olun</a>.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($related): ?>
<section>
  <div class="container">
    <div class="section-head">
      <span class="kicker">Devamı</span>
      <h2>Benzer Yazılar</h2>
    </div>
    <div class="grid grid-3">
      <?php foreach ($related as $r): ?>
        <a class="card" href="<?= e(url('blog_post', ['slug'=>$r['slug']])) ?>">
          <div class="card-img">
            <?php if (!empty($r['cover_image'])): ?>
              <img loading="lazy" decoding="async" width="600" height="660" src="<?= e($r['cover_image']) ?>" alt="<?= e($r['title']) ?>" style="width:100%;height:100%;object-fit:cover">
            <?php else: ?>
              <span class="ph"><?= e(mb_substr($r['title'],0,1)) ?></span>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <span class="cat"><?= e(date('d.m.Y', strtotime($r['published_at'] ?? $r['created_at']))) ?></span>
            <h3 style="font-size:18px"><?= e($r['title']) ?></h3>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($randomProducts): ?>
<section>
  <div class="container">
    <div class="section-head">
      <span class="kicker">Mağazadan</span>
      <h2>Beğenebileceğiniz Ürünler</h2>
      <div class="ornament-divider"><span class="line"></span><span class="diamond"></span><span class="line"></span></div>
    </div>
    <div class="grid grid-3">
      <?php $favIds = fav_ids(); foreach ($randomProducts as $p): ?>
        <div class="card" style="position:relative">
          <form method="post" action="favorite-toggle.php" class="fav-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id"   value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="back" value="<?= e(url('blog_post', ['slug'=>$post['slug']])) ?>">
            <button class="fav-btn <?= in_array((int)$p['id'],$favIds)?'active':'' ?>" type="submit" aria-label="Favori"><?= ic('heart', '', 18) ?></button>
          </form>
          <a href="<?= e(url('product', ['slug'=>$p['slug']])) ?>" style="display:flex;flex-direction:column;flex:1">
            <div class="card-img">
              <?php if (!empty($p['old_price'])): ?><span class="badge">İndirim</span><?php endif; ?>
              <?php if (!empty($p['image'])): ?>
                <img loading="lazy" decoding="async" width="600" height="660" src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" style="width:100%;height:100%;object-fit:cover">
              <?php else: ?>
                <span class="ph"><?= e(mb_substr($p['name'],0,1)) ?></span>
              <?php endif; ?>
            </div>
            <div class="card-body">
              <span class="cat"><?= e($p['cat_name'] ?? 'Genel') ?></span>
              <h3><?= e($p['name']) ?></h3>
              <div class="card-foot">
                <span class="price"><?= money($p['price']) ?><?php if (!empty($p['old_price'])): ?><span class="price-old"><?= money($p['old_price']) ?></span><?php endif; ?></span>
                <span class="icon-btn"><?= ic('cart', '', 17) ?></span>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- inline style removed: see assets/css/ -->
<?php include __DIR__ . '/../includes/footer.php'; ?>
