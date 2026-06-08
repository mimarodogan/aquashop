<?php
/**
 * Toplu Ürün/Fiyat Güncelleme — CSV upload.
 *
 * Akış:
 *   Step 1: CSV yükle → temp'e kaydet → dry-run preview (DB'ye yazılmaz)
 *   Step 2: "Onayla" → güncellemeleri commit, sonuç raporu
 *
 * Desteklenen alanlar:
 *   sku (zorunlu, eşleşme anahtarı), price, old_price, stock, is_active, brand
 *
 * Format:
 *   - CSV, virgül veya noktalı virgül ayraç (otomatik tespit)
 *   - UTF-8 (Excel'den "CSV UTF-8" olarak kaydedin)
 *   - 1. satır başlık (sku,price,stock,...)
 *   - Eksik kolonlar atlanır (sadece doldurulan alan güncellenir)
 *
 * Sınırlar: 5MB maks dosya, 5000 satır maks.
 */
$page = 'products_bulk'; $title = 'Toplu Ürün/Fiyat Güncelle';
require_once __DIR__ . '/../core/auth.php';

$ALLOWED_COLS = ['sku','price','old_price','stock','is_active','brand'];
$MAX_ROWS = 5000;

/* ─── CSV parse helper ─────────────────────────────────────────────── */
function parse_csv_file(string $path): array {
    $content = file_get_contents($path);
    if ($content === false) return ['error' => 'Dosya okunamadı'];
    // BOM temizle
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") $content = substr($content, 3);
    // CRLF/CR → LF
    $content = str_replace(["\r\n","\r"], "\n", $content);
    // Ayraç otomatik tespit: ilk satırda ; varsa ; aksi halde ,
    $firstLine = strtok($content, "\n");
    $delim = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

    $lines = explode("\n", $content);
    $rows = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $rows[] = str_getcsv($line, $delim);
    }
    if (count($rows) < 2) return ['error' => 'En az başlık + 1 veri satırı olmalı'];
    return ['rows' => $rows, 'delim' => $delim];
}

/* ─── POST: dosya yükle veya commit ─────────────────────────────────── */
$flash = null;
$preview = null;
$tempPath = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {

    /* ── Step 1: dosya yükleme ────────────────────────────────────── */
    if (!empty($_FILES['csv']['name'])) {
        $f = $_FILES['csv'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $flash = ['err', 'Dosya yükleme hatası: kod ' . $f['error']];
        } elseif ($f['size'] > 5 * 1024 * 1024) {
            $flash = ['err', 'Dosya 5MB\'tan büyük olamaz'];
        } else {
            // Temp'e taşı
            $tempFile = sys_get_temp_dir() . '/bulk_' . bin2hex(random_bytes(8)) . '.csv';
            if (!move_uploaded_file($f['tmp_name'], $tempFile)) {
                $flash = ['err', 'Geçici dosyaya kaydedilemedi'];
            } else {
                $parsed = parse_csv_file($tempFile);
                if (!empty($parsed['error'])) {
                    @unlink($tempFile);
                    $flash = ['err', $parsed['error']];
                } else {
                    // İlk satır: başlık
                    $headers = array_map(static fn($h) => strtolower(trim($h)), $parsed['rows'][0]);
                    if (!in_array('sku', $headers, true)) {
                        @unlink($tempFile);
                        $flash = ['err', 'CSV\'de "sku" kolonu bulunamadı — bu kolon zorunlu (eşleşme anahtarı)'];
                    } else {
                        $dataRows = array_slice($parsed['rows'], 1, $MAX_ROWS);
                        // Dry-run preview için DB ile karşılaştır
                        $preview = ['headers' => $headers, 'rows' => [], 'matched' => 0, 'unmatched' => 0, 'invalid' => 0, 'temp' => basename($tempFile)];
                        $skuIdx = array_search('sku', $headers);

                        // Toplu sku lookup
                        $skus = [];
                        foreach ($dataRows as $r) {
                            $sku = trim($r[$skuIdx] ?? '');
                            if ($sku !== '') $skus[] = $sku;
                        }
                        $skus = array_values(array_unique($skus));
                        $existing = [];
                        if ($skus) {
                            $in = implode(',', array_fill(0, count($skus), '?'));
                            $st = db()->prepare("SELECT id, sku, name, price, stock FROM products WHERE sku IN ($in)");
                            $st->execute($skus);
                            foreach ($st->fetchAll() as $row) $existing[$row['sku']] = $row;
                        }

                        foreach ($dataRows as $i => $r) {
                            $rowAssoc = [];
                            foreach ($headers as $hi => $h) {
                                if (in_array($h, $ALLOWED_COLS, true)) {
                                    $rowAssoc[$h] = trim($r[$hi] ?? '');
                                }
                            }
                            $sku = $rowAssoc['sku'] ?? '';
                            if ($sku === '') {
                                $preview['invalid']++;
                                $rowAssoc['_status'] = 'invalid'; $rowAssoc['_reason'] = 'SKU boş';
                            } elseif (!isset($existing[$sku])) {
                                $preview['unmatched']++;
                                $rowAssoc['_status'] = 'unmatched'; $rowAssoc['_reason'] = 'Bu SKU\'da ürün yok';
                            } else {
                                $preview['matched']++;
                                $rowAssoc['_status'] = 'matched';
                                $rowAssoc['_current_name']  = $existing[$sku]['name'];
                                $rowAssoc['_current_price'] = $existing[$sku]['price'];
                                $rowAssoc['_current_stock'] = $existing[$sku]['stock'];
                                $rowAssoc['_product_id']    = $existing[$sku]['id'];
                            }
                            $preview['rows'][] = $rowAssoc;
                            if ($i >= 100) break; // İlk 100 satır preview'da göster
                        }
                        $preview['total_rows'] = count($dataRows);
                        // Temp dosya yolu — commit'te kullanılacak
                        $tempPath = $tempFile;
                        $_SESSION['bulk_update_temp'] = $tempFile;
                        $_SESSION['bulk_update_headers'] = $headers;
                    }
                }
            }
        }
    }

    /* ── Step 2: commit ────────────────────────────────────────────── */
    elseif (($_POST['action'] ?? '') === 'commit') {
        $tempFile = $_SESSION['bulk_update_temp'] ?? '';
        $headers  = $_SESSION['bulk_update_headers'] ?? [];
        if (!$tempFile || !is_file($tempFile)) {
            $flash = ['err', 'Geçici dosya bulunamadı — lütfen tekrar yükleyin'];
        } else {
            $parsed = parse_csv_file($tempFile);
            $dataRows = array_slice($parsed['rows'], 1, $MAX_ROWS);
            $skuIdx = array_search('sku', $headers);

            $updated = 0; $skipped = 0; $failed = 0;
            $pdo = db();
            $pdo->beginTransaction();
            try {
                foreach ($dataRows as $r) {
                    $sku = trim($r[$skuIdx] ?? '');
                    if ($sku === '') { $skipped++; continue; }

                    // Hangi alanları güncelleyelim
                    $sets = []; $args = [];
                    foreach ($headers as $hi => $h) {
                        if (!in_array($h, $ALLOWED_COLS, true) || $h === 'sku') continue;
                        $val = trim($r[$hi] ?? '');
                        if ($val === '') continue; // boş = atla (silme değil)
                        if ($h === 'price' || $h === 'old_price') {
                            $val = (float)str_replace([',',' '], ['.',''], $val);
                            if ($val < 0) continue;
                        } elseif ($h === 'stock') {
                            $val = (int)$val;
                        } elseif ($h === 'is_active') {
                            $val = in_array(strtolower($val), ['1','true','aktif','evet','yes'], true) ? 1 : 0;
                        }
                        $sets[] = "$h = ?";
                        $args[] = $val;
                    }
                    if (!$sets) { $skipped++; continue; }
                    $args[] = $sku;
                    $sql = 'UPDATE products SET ' . implode(', ', $sets) . ' WHERE sku = ?';
                    try {
                        $aff = $pdo->prepare($sql);
                        $aff->execute($args);
                        if ($aff->rowCount() > 0) $updated++;
                        else $skipped++;
                    } catch (\Throwable $e) {
                        $failed++;
                        error_log('[bulk-update] sku=' . $sku . ' err=' . $e->getMessage());
                    }
                }
                $pdo->commit();
                @unlink($tempFile);
                unset($_SESSION['bulk_update_temp'], $_SESSION['bulk_update_headers']);
                $flash = ['ok', "Tamamlandı: {$updated} güncellendi, {$skipped} atlandı, {$failed} hata."];
            } catch (\Throwable $e) {
                $pdo->rollBack();
                $flash = ['err', 'Toplu güncelleme başarısız: ' . $e->getMessage()];
            }
        }
    }

    /* ── İptal ─────────────────────────────────────────────────────── */
    elseif (($_POST['action'] ?? '') === 'cancel') {
        if (!empty($_SESSION['bulk_update_temp']) && is_file($_SESSION['bulk_update_temp'])) {
            @unlink($_SESSION['bulk_update_temp']);
        }
        unset($_SESSION['bulk_update_temp'], $_SESSION['bulk_update_headers']);
        $flash = ['ok', 'Yükleme iptal edildi'];
    }

    if ($flash && !$preview) {
        flash_set($flash[0], $flash[1]);
        redirect('bulk-update.php');
    }
}

require_once __DIR__ . '/../core/header.php';
?>

<?php if (!$preview): ?>
  <div class="panel" style="max-width:720px">
    <h3 style="margin-bottom:14px">Toplu Ürün / Fiyat / Stok Güncelle</h3>
    <p class="muted" style="margin-bottom:18px">
      CSV dosyası yükleyerek mevcut ürünlerin <strong>fiyat, eski fiyat, stok, aktiflik, marka</strong> alanlarını toplu güncelleyin.
      Eşleşme <code>sku</code> alanı üzerinden yapılır.
    </p>
    <ul style="margin:0 0 18px 18px;font-size:13px;line-height:1.7;color:var(--muted-text)">
      <li>Format: <strong>UTF-8 CSV</strong> (Excel'de "CSV UTF-8" olarak kaydedin)</li>
      <li>Ayraç: virgül (<code>,</code>) veya noktalı virgül (<code>;</code>) — otomatik tespit</li>
      <li>İlk satır başlık olmalı; izinli kolonlar: <code>sku, price, old_price, stock, is_active, brand</code></li>
      <li>Boş bırakılan hücreler atlanır (mevcut değer korunur)</li>
      <li>Yeni ürün <strong>eklemez</strong> — sadece var olanları günceller</li>
      <li>Maks. 5 MB, maks. 5000 satır</li>
    </ul>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="field">
        <label>CSV Dosyası</label>
        <input type="file" name="csv" accept=".csv,text/csv" required>
      </div>
      <div style="margin-top:14px;display:flex;gap:10px">
        <button class="btn btn-primary" type="submit">Yükle &amp; Önizle</button>
        <a class="btn btn-secondary" href="stock.php?export=1">📥 Mevcut Stok CSV Şablonunu İndir</a>
      </div>
    </form>
  </div>

<?php else: ?>
  <!-- Preview ekranı -->
  <div class="panel" style="margin-bottom:14px">
    <h3 style="margin-bottom:10px">Önizleme — Toplam <?= (int)$preview['total_rows'] ?> satır</h3>
    <div style="display:flex;gap:14px;flex-wrap:wrap;font-size:13px">
      <span style="color:#9fce7d">✓ Eşleşen: <strong><?= (int)$preview['matched'] ?></strong></span>
      <span style="color:#e4a3a3">✗ Eşleşmeyen SKU: <strong><?= (int)$preview['unmatched'] ?></strong></span>
      <span style="color:#e4a3a3">⚠ Geçersiz: <strong><?= (int)$preview['invalid'] ?></strong></span>
    </div>
    <?php if ($preview['matched'] === 0): ?>
      <p style="color:#e4a3a3;margin-top:14px"><strong>Uyarı:</strong> Hiçbir SKU eşleşmedi. CSV'deki SKU değerlerini kontrol edin.</p>
    <?php endif; ?>
  </div>

  <div class="panel" style="margin-bottom:14px;padding:0;overflow:auto">
    <table style="font-size:12px">
      <thead><tr>
        <th>Durum</th>
        <th>SKU</th>
        <th>Mevcut Ürün</th>
        <?php foreach ($preview['headers'] as $h):
          if (!in_array($h, $ALLOWED_COLS, true) || $h === 'sku') continue;
        ?>
          <th><?= e($h) ?></th>
        <?php endforeach; ?>
        <th>Sebep</th>
      </tr></thead>
      <tbody>
      <?php foreach ($preview['rows'] as $r): ?>
        <tr>
          <td>
            <?php if ($r['_status']==='matched'): ?><span style="color:#9fce7d">✓</span>
            <?php elseif ($r['_status']==='unmatched'): ?><span style="color:#e4a3a3">✗</span>
            <?php else: ?><span style="color:#e4a3a3">⚠</span>
            <?php endif; ?>
          </td>
          <td><code><?= e($r['sku'] ?? '') ?></code></td>
          <td><?= e($r['_current_name'] ?? '—') ?></td>
          <?php foreach ($preview['headers'] as $h):
            if (!in_array($h, $ALLOWED_COLS, true) || $h === 'sku') continue;
            $newVal = $r[$h] ?? '';
            $currentKey = '_current_' . $h;
            $oldVal = $r[$currentKey] ?? null;
          ?>
            <td>
              <?= e($newVal) ?>
              <?php if ($oldVal !== null && (string)$oldVal !== (string)$newVal && $newVal !== ''): ?>
                <small style="color:var(--muted-text);text-decoration:line-through"><?= e($oldVal) ?></small>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
          <td style="color:var(--muted-text);font-size:11px"><?= e($r['_reason'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ($preview['total_rows'] > 100): ?>
      <p class="muted" style="padding:12px;font-size:12px">İlk 100 satır gösteriliyor (toplam <?= (int)$preview['total_rows'] ?>).</p>
    <?php endif; ?>
  </div>

  <div style="display:flex;gap:10px">
    <form method="post" style="display:inline">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="commit">
      <button class="btn btn-primary" type="submit" onclick="return confirm('<?= (int)$preview['matched'] ?> ürün güncellenecek. Devam edilsin mi?')">✓ <?= (int)$preview['matched'] ?> Ürünü Güncelle</button>
    </form>
    <form method="post" style="display:inline">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="cancel">
      <button class="btn btn-secondary" type="submit">İptal</button>
    </form>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
