<?php
/**
 * JSON-LD Schema generator.
 * Sayfa türüne göre uygun yapılandırılmış veri üretir.
 *
 * Kullanım:
 *   $jsonld = ['type' => 'Product', 'data' => [...]];
 *   include 'components/json-ld.php';
 */
function jsonld_emit($schemas) {
    if (!is_array($schemas)) return;
    // K-4 GÜVENLİK: JSON_HEX_TAG/AMP/APOS/QUOT → '</script>', '&', "'", '"' karakterlerini \u00XX'e çevirir
    // → DB'den gelen $name/title/body alanına yerleştirilen '</script><script>...</script>' payload'u
    //   JSON-LD bloğunu kıramaz, ikinci <script> çalışmaz.
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
           | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    foreach ($schemas as $s) {
        if ($s) echo '<script type="application/ld+json">' . json_encode($s, $flags) . '</script>' . "\n";
    }
}

function jsonld_organization() {
    $name = trim((string)(setting('site_name') ?? '')) ?: SITE_NAME_FALLBACK;
    $email = setting('contact_email');
    $phone = setting('contact_phone');
    $address = setting('contact_address');
    $base = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off' ? 'https':'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    return array_filter(array(
        '@context' => 'https://schema.org',
        '@type'    => 'Store',
        'name'     => $name,
        'url'      => $base . '/',
        'email'    => $email,
        'telephone'=> $phone,
        'address'  => $address ? array('@type'=>'PostalAddress','streetAddress'=>$address) : null,
        'sameAs'   => array_values(array_filter(array(
            trim((string)setting('social_instagram','')),
            trim((string)setting('social_facebook','')),
            trim((string)setting('social_twitter','')),
            trim((string)setting('social_youtube','')),
            trim((string)setting('social_linkedin','')),
            trim((string)setting('social_tiktok','')),
        ))),
    ));
}

function jsonld_breadcrumb($crumbs) {
    if (!$crumbs) return null;
    $items = array();
    $i = 1;
    foreach ($crumbs as $c) {
        $items[] = array(
            '@type'    => 'ListItem',
            'position' => $i++,
            'name'     => $c['name'],
            'item'     => $c['url'],
        );
    }
    return array(
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $items,
    );
}

function jsonld_product($p) {
    if (!$p) return null;
    $base = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off' ? 'https':'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    $img  = !empty($p['image']) ? (strpos($p['image'],'http')===0 ? $p['image'] : $base.$p['image']) : null;
    $url  = url('product', array('slug'=>$p['slug']));

    // Yorum/puan bilgisi (varsa)
    $agg = null;
    if (function_exists('comment_avg_rating')) {
        $r = comment_avg_rating($p['id']);
        if (!empty($r['cnt'])) {
            $agg = array(
                '@type'      => 'AggregateRating',
                'ratingValue'=> (string)$r['avg'],
                'reviewCount'=> (int)$r['cnt'],
            );
        }
    }

    $siteName = trim((string)(setting('site_name') ?? '')) ?: (defined('SITE_NAME_FALLBACK') ? SITE_NAME_FALLBACK : '');

    return array_filter(array(
        '@context'    => 'https://schema.org',
        '@type'       => 'Product',
        'name'        => $p['name'],
        'description' => (($p['short_desc'] ?? '') !== '') ? $p['short_desc'] : ((($p['description'] ?? '') !== '') ? mb_substr(strip_tags((string)$p['description']), 0, 500) : null),
        'sku'         => $p['sku'] ?? null,
        'image'       => $img,
        'category'    => $p['cat_name'] ?? null,
        'url'         => $url,
        'brand'       => !empty($p['brand']) ? array('@type'=>'Brand','name'=>$p['brand']) : null,
        'offers'      => array(
            '@type'        => 'Offer',
            'priceCurrency'=> 'TRY',
            'price'        => number_format((float)$p['price'], 2, '.', ''),
            'availability' => ((int)$p['stock'] > 0) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'url'          => $url,
            'seller'       => $siteName ? array('@type'=>'Organization','name'=>$siteName) : null,
            'shippingDetails' => array(
                '@type'               => 'OfferShippingDetails',
                'shippingRate'        => array('@type'=>'MonetaryAmount','currency'=>'TRY','value'=>'0'),
                'shippingDestination' => array('@type'=>'DefinedRegion','addressCountry'=>'TR'),
                'deliveryTime'        => array(
                    '@type'       => 'ShippingDeliveryTime',
                    'handlingTime'=> array('@type'=>'QuantitativeValue','minValue'=>0,'maxValue'=>1,'unitCode'=>'DAY'),
                    'transitTime' => array('@type'=>'QuantitativeValue','minValue'=>1,'maxValue'=>3,'unitCode'=>'DAY'),
                ),
            ),
            'hasMerchantReturnPolicy' => array(
                '@type'                => 'MerchantReturnPolicy',
                'applicableCountry'    => 'TR',
                'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
                'merchantReturnDays'   => 14,
                'returnMethod'         => 'https://schema.org/ReturnByMail',
                'returnFees'           => 'https://schema.org/FreeReturn',
            ),
        ),
        'aggregateRating' => $agg,
    ));
}

function jsonld_article($post) {
    if (!$post) return null;
    $base = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off' ? 'https':'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    return array_filter(array(
        '@context'      => 'https://schema.org',
        '@type'         => 'Article',
        'headline'      => $post['title'],
        'description'   => $post['excerpt'] ?? null,
        'image'         => !empty($post['cover_image']) ? (strpos($post['cover_image'],'http')===0 ? $post['cover_image'] : $base.$post['cover_image']) : null,
        'datePublished' => $post['published_at'] ?? $post['created_at'],
        'dateModified'  => $post['updated_at'] ?? null,
        'author'        => !empty($post['author_name']) ? array('@type'=>'Person','name'=>$post['author_name']) : null,
        'mainEntityOfPage' => $base . url('blog_post', array('slug'=>$post['slug'])),
    ));
}

function jsonld_reviews($comments, $itemName, $itemType = 'Product') {
    $items = array();
    foreach ($comments as $c) {
        $rev = array(
            '@context' => 'https://schema.org',
            '@type'    => 'Review',
            'author'   => array('@type'=>'Person','name'=>$c['author_name']),
            'reviewBody'    => $c['body'],
            'datePublished' => $c['created_at'],
            'itemReviewed'  => array('@type'=>$itemType,'name'=>$itemName),
        );
        if (!empty($c['rating'])) {
            $rev['reviewRating'] = array(
                '@type'      => 'Rating',
                'ratingValue'=> (string)$c['rating'],
                'bestRating' => '5',
                'worstRating'=> '1',
            );
        }
        $items[] = $rev;
    }
    return $items;
}

/**
 * HTML içeriğinden FAQ Q/A çiftleri çıkar.
 * h3 (soru) + sonraki p (cevap) örüntüsünü tarar.
 */
function jsonld_faq_from_html($html) {
    if (!$html) return null;
    $faqs = array();
    if (preg_match_all('/<h3[^>]*>(.*?)<\/h3>\s*<p[^>]*>(.*?)<\/p>/is', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $row) {
            $q = trim(strip_tags($row[1]));
            $a = trim(strip_tags($row[2]));
            if ($q !== '' && $a !== '') {
                $faqs[] = array(
                    '@type'          => 'Question',
                    'name'           => $q,
                    'acceptedAnswer' => array('@type'=>'Answer','text'=>$a),
                );
            }
        }
    }
    if (!$faqs) return null;
    return array(
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $faqs,
    );
}

function jsonld_website() {
    $name = trim((string)(setting('site_name') ?? '')) ?: SITE_NAME_FALLBACK;
    $base = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off' ? 'https':'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    return array(
        '@context' => 'https://schema.org',
        '@type'    => 'WebSite',
        'name'     => $name,
        'url'      => $base . '/',
        'potentialAction' => array(
            '@type'      => 'SearchAction',
            'target'     => $base . '/urun?q={search_term_string}',
            'query-input'=> 'required name=search_term_string',
        ),
    );
}
