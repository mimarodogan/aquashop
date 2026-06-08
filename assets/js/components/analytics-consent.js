/**
 * Analytics Consent Bridge
 * ────────────────────────
 * Cookie banner'dan gelen 'cookieconsent' event'ini dinler ve aktif tracker'ların
 * consent state'ini günceller (GA4 Consent Mode v2, Meta Pixel, Clarity).
 *
 * Cookie banner: assets/js/components/cookie-banner.js
 *   accept → CustomEvent('cookieconsent', { detail: { value: 'accept' } })
 *   reject → CustomEvent('cookieconsent', { detail: { value: 'reject' } })
 */
(function () {
    'use strict';

    function applyConsent(granted) {
        // 1) Google Consent Mode v2
        if (typeof gtag === 'function') {
            gtag('consent', 'update', {
                'ad_storage':         granted ? 'granted' : 'denied',
                'ad_user_data':       granted ? 'granted' : 'denied',
                'ad_personalization': granted ? 'granted' : 'denied',
                'analytics_storage':  granted ? 'granted' : 'denied'
            });
        }
        // 2) Meta Pixel
        if (typeof fbq === 'function') {
            try { fbq('consent', granted ? 'grant' : 'revoke'); } catch (e) {}
        }
        // 3) Microsoft Clarity
        if (typeof clarity === 'function') {
            try { clarity('consent', !!granted); } catch (e) {}
        }
        // 4) Cache state — dataLayer'a da push et (GTM tag'leri tetiklensin)
        window.__analyticsConsent = granted ? 'all' : 'essential';
        if (window.dataLayer) {
            window.dataLayer.push({
                event: 'consent_update',
                consent_state: granted ? 'all' : 'essential'
            });
        }
    }

    window.addEventListener('cookieconsent', function (e) {
        var v = (e && e.detail && e.detail.value) || '';
        applyConsent(v === 'accept');
    });
})();
