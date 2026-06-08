<?php
/**
 * AI Danışman — yüzen sohbet widget'ı (her sayfada, footer'dan include edilir).
 * WhatsApp balonunun yerini alır; "İnsana bağlan" linkiyle WhatsApp handoff korunur.
 *
 * JS gerekli verileri kök elemanın data-* attribute'larından okur (global yok).
 */
require_once __DIR__ . '/../core/AIAssistant.php';

$asstName = ai_assistant_display_name();
$greeting = ai_assistant_greeting();
$waUrl    = ai_assistant_whatsapp_url();
$endpoint = rtrim(SITE_URL, '/') . '/ajax/assistant.php';

// Başlangıç soru çipleri (genel — kategori-bağımsız)
$chips = ['Bana ürün öner', 'Neler satıyorsunuz?', 'Kullanım/bakım ipucu ver'];
?>
<div id="ai-assistant" class="ai-asst"
     data-endpoint="<?= e($endpoint) ?>"
     data-csrf="<?= e(csrf_token()) ?>"
     data-name="<?= e($asstName) ?>"
     <?= $waUrl ? 'data-wa="' . e($waUrl) . '"' : '' ?>>

  <!-- Yüzen buton -->
  <button type="button" class="ai-asst-fab" data-ai-toggle aria-expanded="false"
          aria-controls="ai-asst-panel" aria-label="<?= e($asstName) ?> ile sohbet et">
    <svg class="ai-asst-fab-ic" width="26" height="26" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
      <circle cx="9" cy="12" r="1" fill="currentColor" stroke="none"/>
      <circle cx="12.5" cy="12" r="1" fill="currentColor" stroke="none"/>
      <circle cx="16" cy="12" r="1" fill="currentColor" stroke="none"/>
    </svg>
    <span class="ai-asst-fab-label">Yardım</span>
  </button>

  <!-- Sohbet penceresi -->
  <div class="ai-asst-panel" id="ai-asst-panel" role="dialog" aria-modal="false"
       aria-label="<?= e($asstName) ?>" hidden>
    <div class="ai-asst-head">
      <span class="ai-asst-avatar" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <rect x="4" y="8" width="16" height="11" rx="3"/><path d="M12 8V4"/><circle cx="12" cy="3" r="1.4" fill="currentColor" stroke="none"/>
          <circle cx="9" cy="13.5" r="1.1" fill="currentColor" stroke="none"/><circle cx="15" cy="13.5" r="1.1" fill="currentColor" stroke="none"/>
        </svg>
      </span>
      <div class="ai-asst-head-txt">
        <strong class="ai-asst-title"><?= e($asstName) ?></strong>
        <span class="ai-asst-status">Çevrimiçi · genelde birkaç saniyede yanıtlar</span>
      </div>
      <button type="button" class="ai-asst-close" data-ai-close aria-label="Kapat">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>

    <div class="ai-asst-body" data-ai-body>
      <div class="ai-msg ai-msg-bot">
        <div class="ai-bubble"><?= e($greeting) ?></div>
      </div>
      <div class="ai-chips" data-ai-chips>
        <?php foreach ($chips as $c): ?>
          <button type="button" class="ai-chip" data-ai-chip><?= e($c) ?></button>
        <?php endforeach; ?>
      </div>
    </div>

    <form class="ai-asst-input" data-ai-form autocomplete="off">
      <input type="text" class="ai-asst-text" data-ai-text maxlength="1000"
             placeholder="Mesajınızı yazın…" aria-label="Mesajınız">
      <button type="submit" class="ai-asst-send" data-ai-send aria-label="Gönder">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
      </button>
    </form>

    <?php if ($waUrl): ?>
      <a class="ai-asst-handoff" href="<?= e($waUrl) ?>" target="_blank" rel="noopener">
        Bir insana mı bağlanmak istersiniz? <strong>WhatsApp'tan yazın →</strong>
      </a>
    <?php endif; ?>

    <p class="ai-asst-disclaimer">Yapay zeka önerileridir; fiyat ve stok sepette teyit edilir.</p>
  </div>
</div>
