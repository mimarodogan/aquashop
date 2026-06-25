<?php
/**
 * Google Reviews Component
 * Admin panelinden girilen Google Yorumlarını carousel şeklinde görüntüler.
 */

$reviews_json = setting('google_reviews_json', '');
$reviews = [];
if ($reviews_json) {
    $reviews = json_decode($reviews_json, true);
}

// Varsayılan yorumlar (hiç yorum girilmemişse gösterilecek placeholder'lar)
if (empty($reviews) || !is_array($reviews)) {
    $reviews = [
        [
            'author' => 'Ahmet Yılmaz',
            'rating' => 5,
            'text' => 'Akvaryum hobisine yeni başladım, tüm kurulum malzemelerini buradan aldım. Çok ilgililer, teşekkürler!',
            'time' => '1 hafta önce',
            'avatar' => ''
        ],
        [
            'author' => 'Zeynep Kaya',
            'rating' => 5,
            'text' => 'Tetra dış filtre aldım. Fiyatı piyasaya göre çok uygun ve hızlı kargoladılar. Güvenilir mağaza.',
            'time' => '3 hafta önce',
            'avatar' => ''
        ],
        [
            'author' => 'Murat Demir',
            'rating' => 5,
            'text' => 'Bursa içinden sipariş verdim, aynı gün teslim ettiler. Canlı bitki kalitesi muazzam.',
            'time' => '1 ay önce',
            'avatar' => ''
        ],
        [
            'author' => 'Elif Çelik',
            'rating' => 5,
            'text' => 'Sorularıma sabırla cevap verdiler. Satış sonrası destekleri harika. Kesinlikle tavsiye ederim.',
            'time' => '2 ay önce',
            'avatar' => ''
        ]
    ];
}

$rating = (float)setting('google_reviews_rating', '4.8');
$count = (int)setting('google_reviews_count', '142');
$maps_url = setting('google_reviews_maps_url', 'https://maps.google.com');
$enabled = setting('google_reviews_enabled', '1') === '1';

if (!$enabled) return;
?>
<section class="aq-google-reviews-section" aria-label="Google Müşteri Yorumları">
  <div class="aq-container">
    <div class="aq-reviews-header">
      <div class="aq-section-title-row">
        <div>
          <span>Müşteri Deneyimleri</span>
          <h2>Google Yorumları</h2>
        </div>
        <a href="<?= e($maps_url) ?>" target="_blank" rel="noopener" class="aq-view-all">Yorum Yaz <i class="bi bi-arrow-right"></i></a>
      </div>

      <div class="aq-reviews-badge">
        <img src="<?= SITE_URL ?>/assets/img/google-logo.svg" alt="Google" class="aq-google-logo" onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg'; this.onerror=null;">
        <strong><?= number_format($rating, 1, ',', '.') ?></strong>
        <span>
          <?php for ($i = 1; $i <= 5; $i++): ?><i class="bi bi-star-fill"></i><?php endfor; ?>
        </span>
        <a href="<?= e($maps_url) ?>" target="_blank" rel="noopener"><?= number_format($count, 0, ',', '.') ?> Google yorumu</a>
      </div>
    </div>

    <!-- Carousel Wrapper -->
    <div class="aq-carousel-wrap" data-carousel data-visible-desktop="3" data-visible-tablet="2" data-visible-mobile="1">
      <div class="aq-carousel-controls">
        <button type="button" class="aq-loop-prev aq-carousel-arrow" data-dir="-1" aria-label="Önceki yorumlar" disabled><i class="bi bi-chevron-left"></i></button>
        <button type="button" class="aq-loop-next aq-carousel-arrow" data-dir="1" aria-label="Sonraki yorumlar"><i class="bi bi-chevron-right"></i></button>
      </div>
      <div class="aq-products-viewport">
        <div class="aq-products-track">
          <?php foreach ($reviews as $rev): 
              $revRating = (int)($rev['rating'] ?? 5);
          ?>
            <div class="aq-review-card">
              <div class="aq-review-card-head">
                <div class="aq-review-avatar">
                  <?php if (!empty($rev['avatar'])): ?>
                    <img src="<?= e($rev['avatar']) ?>" alt="<?= e($rev['author']) ?>" loading="lazy">
                  <?php else: ?>
                    <span><?= e(mb_substr($rev['author'] ?? 'M', 0, 1)) ?></span>
                  <?php endif; ?>
                </div>
                <div class="aq-review-meta">
                  <h4><?= e($rev['author'] ?? 'Müşteri') ?></h4>
                  <div class="aq-review-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <span class="star-icon <?= $i <= $revRating ? 'active' : '' ?>">★</span>
                    <?php endfor; ?>
                    <span class="aq-review-time"><?= e($rev['time'] ?? '') ?></span>
                  </div>
                </div>
                <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" alt="Google" class="aq-review-g-icon" width="18" height="18">
              </div>
              <div class="aq-review-card-body">
                <p><?= e($rev['text'] ?? '') ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div>
</section>
