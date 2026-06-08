<?php
require_once __DIR__ . '/../includes/functions.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$st = db()->prepare('SELECT * FROM pages WHERE slug=? AND is_published=1');
$st->execute(array($slug));
$pg = $st->fetch();

if (!$pg) {
    http_response_code(404);
    $title = 'Sayfa bulunamadı';
    include __DIR__ . '/../includes/header.php';
    echo '<section class="container" style="padding:120px 0"><h1>Sayfa bulunamadı</h1><p class="muted" style="margin-top:14px">Aradığınız sayfa mevcut değil.</p></section>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$title = $pg['title'];
$page  = $pg['slug'];

require_once __DIR__ . '/../components/json-ld.php';
$extraSchemas = array();

/* ── SSS sayfası: DB'den Q/A çek, FAQPage schema üret ──────── */
$isSss   = in_array($slug, ['sss', 'sikca-sorulan-sorular', 'faq'], true);
$siteFaqs = [];
if ($isSss) {
    try {
        $siteFaqs = db()->query(
            'SELECT question, answer FROM site_faqs WHERE is_active=1 ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
    } catch (\Throwable $e) { $siteFaqs = []; }

    if ($siteFaqs) {
        /* FAQPage JSON-LD — DB'den üret (hem temiz hem güncel) */
        $faqEntities = [];
        foreach ($siteFaqs as $f) {
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
    } else {
        /* DB boşsa HTML içeriğinden parse et (geriye dönük uyum) */
        $faq = jsonld_faq_from_html($pg['content']);
        if ($faq) $extraSchemas[] = $faq;
    }
} else {
    /* Diğer CMS sayfaları: h3+p yapısından schema çıkarmayı dene */
    $faq = jsonld_faq_from_html($pg['content']);
    if ($faq) $extraSchemas[] = $faq;
}

/* Breadcrumb schema */
$base = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off' ? 'https':'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
$extraSchemas[] = jsonld_breadcrumb(array(
    array('name'=>'Anasayfa','url'=>$base . url('home')),
    array('name'=>$pg['title'],'url'=>$base . url('page', array('slug'=>$pg['slug']))),
));

include __DIR__ . '/../includes/header.php';
?>
<section class="page-header">
  <div class="container">
    <span class="kicker">Kurumsal</span>
    <h1 style="margin-top:10px"><?= e($pg['title']) ?></h1>
    <div class="breadcrumb"><a href="<?= url('home') ?>">Anasayfa</a><span>/</span><?= e($pg['title']) ?></div>
  </div>
</section>

<section>
  <div class="container" style="max-width:860px">

    <?php if ($isSss && $siteFaqs): ?>
    <?php /* ── SSS: DB'den accordion ── */ ?>
    <div class="panel">
      <p class="muted" style="margin:0 0 20px;font-size:14px">
        <?= count($siteFaqs) ?> soru · Bir soruya tıklayarak cevabı görüntüleyebilirsiniz.
      </p>
      <div class="pd-faqs" style="max-width:none">
        <?php foreach ($siteFaqs as $i => $f): ?>
          <details class="pd-faq" <?= $i === 0 ? 'open' : '' ?>>
            <summary><?= e($f['question']) ?></summary>
            <div class="pd-faq-a"><?= nl2br(e($f['answer'])) ?></div>
          </details>
        <?php endforeach; ?>
      </div>
    </div>

    <?php elseif ($isSss && !$siteFaqs): ?>
    <?php /* SSS tablosu boş veya tablo yok → içerik editörüne düş */ ?>
    <div class="panel cms-content">
      <?php if (trim($pg['content'])): ?>
        <?= /* K-3: sanitize_html admin HTML'sini XSS-temizler */ embed_videos(sanitize_html($pg['content'])) ?>
      <?php else: ?>
        <p class="muted">Henüz soru eklenmemiş. Admin paneli → İçerik &amp; SEO → SSS Yönetimi bölümünden ekleyebilirsiniz.</p>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <?php /* Standart CMS sayfası */ ?>
    <div class="panel cms-content">
      <?= embed_videos($pg['content']) ?>
    </div>
    <?php endif; ?>

  </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
