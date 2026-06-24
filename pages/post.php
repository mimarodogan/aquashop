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
    echo '<section class="aq-container" style="padding:120px 0"><h1>Yazı bulunamadı</h1></section>';
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

$randomProducts = db()->query("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 ORDER BY RAND() LIMIT 4")->fetchAll();

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

$__pubTs   = strtotime($post['published_at'] ?? $post['created_at']);
$__siteN   = trim((string)setting('site_name', 'AquaShop'));
?>
<section class="aq-all-categories-page aq-blog-detail-page">
  <div class="aq-container">

    <div class="aq-all-categories-head aq-blog-detail-head">
      <nav class="aq-breadcrumb" aria-label="Sayfa yolu">
        <a href="<?= url('home') ?>">Ana Sayfa</a>
        <i class="bi bi-chevron-right"></i>
        <a href="<?= url('blog') ?>">Blog</a>
        <i class="bi bi-chevron-right"></i>
        <span><?= e($post['title']) ?></span>
      </nav>
      <div class="aq-blog-detail-head-card">
        <?php if (!empty($post['cat_name'])): ?><span><?= e($post['cat_name']) ?></span><?php endif; ?>
        <h1><?= e($post['title']) ?></h1>
        <?php if (!empty($post['excerpt'])): ?><p><?= e($post['excerpt']) ?></p><?php endif; ?>
        <div class="aq-blog-detail-meta" style="margin-top:14px;color:var(--aq-soft-text);font-size:12px;font-weight:650;letter-spacing:.02em">
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
        </div>
      </div>
    </div>

    <article class="aq-blog-detail-layout">
      <div class="aq-blog-detail-main">
        <?php if (!empty($post['cover_image'])): ?>
        <div class="aq-blog-detail-image">
          <img loading="eager" fetchpriority="high" width="1200" height="675"
               src="<?= e($post['cover_image']) ?>" alt="<?= e($post['title']) ?>">
        </div>
        <?php endif; ?>

        <div class="aq-blog-detail-content cms-content">
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

        <?php if ($postFaqs): ?>
        <div class="aq-blog-detail-content" style="margin-top:22px">
          <h2 style="margin-top:0">Sık Sorulan Sorular</h2>
          <p style="margin:-6px 0 18px;color:var(--aq-muted);font-size:13px">Bu yazıyla ilgili en çok merak edilen sorular</p>
          <div class="pd-faqs">
            <?php foreach ($postFaqs as $i => $f): ?>
              <details class="pd-faq" <?= $i === 0 ? 'open' : '' ?>>
                <summary><?= e($f['question']) ?></summary>
                <div class="pd-faq-a"><?= nl2br(e($f['answer'])) ?></div>
              </details>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($blogAuthor): ?>
        <div class="aq-blog-detail-content" style="margin-top:22px">
          <p style="margin:0 0 16px;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--aq-soft-text);font-weight:800">Yazar Hakkında</p>
          <div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap">
            <div style="flex-shrink:0">
              <?php if (!empty($blogAuthor['avatar'])): ?>
                <img loading="lazy" src="<?= e($blogAuthor['avatar']) ?>" alt="<?= e($blogAuthor['name']) ?>"
                     style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid var(--aq-border)">
              <?php else: ?>
                <div style="width:72px;height:72px;border-radius:50%;background:var(--aq-blue-light);border:2px solid var(--aq-border);display:grid;place-items:center;font-size:24px">👤</div>
              <?php endif; ?>
            </div>
            <div style="flex:1;min-width:0">
              <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px">
                <strong style="color:var(--aq-dark);font-size:17px"><?= e($blogAuthor['name']) ?></strong>
                <?php if (!empty($blogAuthor['title'])): ?>
                  <span style="color:var(--aq-muted);font-size:13px">· <?= e($blogAuthor['title']) ?></span>
                <?php endif; ?>
                <?php if (!empty($blogAuthor['instagram'])): ?>
                  <a href="https://instagram.com/<?= e($blogAuthor['instagram']) ?>" target="_blank" rel="noopener"
                     style="color:var(--aq-muted);font-size:12px;text-decoration:none" title="Instagram">📸 @<?= e($blogAuthor['instagram']) ?></a>
                <?php endif; ?>
                <?php if (!empty($blogAuthor['twitter'])): ?>
                  <a href="https://twitter.com/<?= e($blogAuthor['twitter']) ?>" target="_blank" rel="noopener"
                     style="color:var(--aq-muted);font-size:12px;text-decoration:none" title="Twitter/X">𝕏 @<?= e($blogAuthor['twitter']) ?></a>
                <?php endif; ?>
                <?php if (!empty($blogAuthor['website'])): ?>
                  <a href="<?= e($blogAuthor['website']) ?>" target="_blank" rel="noopener"
                     style="color:var(--aq-blue);font-size:12px" title="Web Sitesi">🌐 Site</a>
                <?php endif; ?>
              </div>
              <?php if (!empty($blogAuthor['bio'])): ?>
                <p style="color:var(--aq-muted);font-size:14px;line-height:1.7;margin:0"><?= nl2br(e($blogAuthor['bio'])) ?></p>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <?php $comments = comment_list('blog', $post['id']); ?>
        <div class="aq-blog-detail-content" style="margin-top:22px">
          <h2 style="margin-top:0"><?= count($comments) ?> Yorum</h2>

          <?php if (!$comments): ?>
            <p style="color:var(--aq-muted);margin:6px 0 20px">İlk yorumu yapan siz olun.</p>
          <?php else: ?>
            <div style="display:grid;gap:12px;margin:14px 0 22px">
              <?php foreach ($comments as $c): ?>
                <div style="padding:16px 18px;border:1px solid var(--aq-border);border-radius:var(--aq-radius);background:var(--aq-section)">
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                    <strong style="color:var(--aq-dark)"><?= e($c['author_name']) ?></strong>
                    <span style="color:var(--aq-soft-text);font-size:12px"><?= e(date('d.m.Y H:i', strtotime($c['created_at']))) ?></span>
                  </div>
                  <p style="color:var(--aq-text);margin:0"><?= nl2br(e($c['body'])) ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if (current_user()): ?>
            <h3 style="font-size:17px;margin:0 0 12px;color:var(--aq-dark)">Yorum Yap</h3>
            <form method="post" action="comment-add.php" style="display:grid;gap:12px">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="type" value="blog">
              <input type="hidden" name="target_id" value="<?= (int)$post['id'] ?>">
              <input type="hidden" name="back" value="<?= e(url('blog_post', ['slug'=>$post['slug']])) ?>">
              <div class="field"><label>Yorumunuz</label><textarea name="body" rows="4" required></textarea></div>
              <div><button class="btn btn-primary">Gönder</button></div>
            </form>
          <?php else: ?>
            <p style="margin:6px 0 0;color:var(--aq-muted)">Yorum yapmak için <a href="<?= url('login') ?>" style="color:var(--aq-blue);font-weight:700">giriş yapın</a> veya <a href="<?= url('register') ?>" style="color:var(--aq-blue);font-weight:700">üye olun</a>.</p>
          <?php endif; ?>
        </div>
      </div>

      <aside class="aq-blog-detail-sidebar">
        <div class="aq-blog-side-card">
          <strong><?= e($__siteN) ?> Blog</strong>
          <span>Akvaryum hobiniz için rehber içerikleri inceleyin.</span>
          <a href="<?= url('blog') ?>">Tüm Blog Yazıları <i class="bi bi-arrow-right"></i></a>
        </div>
        <?php if ($related): ?>
        <div class="aq-blog-side-card aq-blog-side-related">
          <strong>Benzer İçerikler</strong>
          <div class="aq-blog-side-list">
            <?php foreach ($related as $r): ?>
              <a href="<?= e(url('blog_post', ['slug'=>$r['slug']])) ?>">
                <span>
                  <?php if (!empty($r['cover_image'])): ?>
                    <img loading="lazy" decoding="async" src="<?= e($r['cover_image']) ?>" alt="<?= e($r['title']) ?>">
                  <?php endif; ?>
                </span>
                <em><?= e($r['title']) ?></em>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </aside>
    </article>

    <?php if ($related): ?>
    <div class="aq-blog-related-mobile">
      <div class="aq-blog-related-head">
        <span>Benzer İçerikler</span>
        <h2>Diğer Blog Yazıları</h2>
      </div>
      <div class="aq-blog-list-grid">
        <?php foreach ($related as $r): ?>
          <article class="aq-blog-list-card">
            <a class="aq-blog-list-image" href="<?= e(url('blog_post', ['slug'=>$r['slug']])) ?>">
              <?php if (!empty($r['cover_image'])): ?>
                <img loading="lazy" decoding="async" width="600" height="400" src="<?= e($r['cover_image']) ?>" alt="<?= e($r['title']) ?>">
              <?php else: ?>
                <span class="aq-ph"><?= e(mb_substr($r['title'],0,1)) ?></span>
              <?php endif; ?>
            </a>
            <div class="aq-blog-list-content">
              <span><?= e($r['cat_name'] ?? 'Blog') ?> · <?= e(date('d.m.Y', strtotime($r['published_at'] ?? $r['created_at']))) ?></span>
              <h2><a href="<?= e(url('blog_post', ['slug'=>$r['slug']])) ?>"><?= e($r['title']) ?></a></h2>
              <?php if (!empty($r['excerpt'])): ?><p><?= e(mb_substr(strip_tags($r['excerpt']), 0, 150)) ?><?= mb_strlen(strip_tags($r['excerpt'])) > 150 ? '…' : '' ?></p><?php endif; ?>
              <a class="aq-blog-list-link" href="<?= e(url('blog_post', ['slug'=>$r['slug']])) ?>">Devamını Oku <i class="bi bi-arrow-right"></i></a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</section>

<?php if ($randomProducts): ?>
<section class="aq-blog-detail-page" style="padding-top:0 !important">
  <div class="aq-container">
    <div class="aq-blog-related-head">
      <span>Mağazadan</span>
      <h2>Beğenebileceğiniz Ürünler</h2>
    </div>
    <div class="aq-product-grid aq-grid-4">
      <?php $favIds = fav_ids(); $cardBack = url('blog_post', ['slug'=>$post['slug']]); foreach ($randomProducts as $p): ?>
        <?php include __DIR__ . '/../components/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
