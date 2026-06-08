<?php
/**
 * Veritabanı Migration Runner — admin panelinden SQL dosyası çalıştırma.
 *
 * Davranış:
 *  - sql/ klasöründeki tüm .sql dosyalarını listeler
 *  - migrations tablosunda hangisinin ne zaman çalıştığı kaydedilir
 *  - Tek dosya veya "tüm bekleyenler" toplu çalıştırma
 *  - Yeniden çalıştırma (idempotent migration'lar için)
 *  - Önizleme (dosya içeriği modal)
 *
 * Güvenlik:
 *  - Sadece admin (auth.php)
 *  - CSRF token zorunlu
 *  - Path traversal koruması: basename() + realpath() kontrolü
 *  - Sadece .sql uzantısı kabul
 *  - migrations tablosu otomatik oluşturulur (bootstrap)
 */
$page = 'migrations'; $title = 'Veritabanı Migration';
require_once __DIR__ . '/../core/auth.php';

$SQL_DIR = realpath(__DIR__ . '/../../sql');
if (!$SQL_DIR || !is_dir($SQL_DIR)) {
    flash_set('err', 'sql/ klasörü bulunamadı.');
    redirect(SITE_URL . '/admin_panel/dashboard.php');
}

/* ─── migrations tablosu yoksa oluştur (bootstrap) ──────────────────── */
try {
    db()->exec(
        "CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            file_hash VARCHAR(64) NULL,
            statements_count INT NOT NULL DEFAULT 0,
            status ENUM('success','failure') NOT NULL,
            error_message TEXT NULL,
            executed_by INT UNSIGNED NULL,
            executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_filename (filename),
            KEY idx_executed (executed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
} catch (\Throwable $e) {
    flash_set('err', 'migrations tablosu oluşturulamadı: ' . $e->getMessage());
    require_once __DIR__ . '/../core/header.php';
    echo '<div class="panel"><h3>Hata</h3><p>' . e($e->getMessage()) . '</p></div>';
    require_once __DIR__ . '/../core/footer.php';
    exit;
}

/* ─── SQL dosyasını güvenli şekilde çöz ────────────────────────────── */
function safe_resolve_sql(string $filename, string $baseDir): ?string {
    $base = basename($filename); // path traversal koruması
    if (!preg_match('/^[A-Za-z0-9_\-.]+\.sql$/', $base)) return null;
    $full = realpath($baseDir . '/' . $base);
    if (!$full) return null;
    // Hala sql/ klasörünün altında mı?
    if (strncmp($full, $baseDir . DIRECTORY_SEPARATOR, strlen($baseDir) + 1) !== 0) return null;
    return $full;
}

/* ─── Dosyayı güvenlik açısından analiz et ─────────────────────────
 * Otomatik tespit: idempotent mi (tekrar çalıştırılırsa hata vermez mi)?
 *
 * Risk düzeyleri:
 *   SAFE     → Sadece IF NOT EXISTS / IF EXISTS / ALTER TABLE ADD COLUMN IF kullanır
 *   WARN     → INSERT içerir (duplicate risk) ya da CREATE TABLE plain
 *   DANGER   → DROP TABLE / TRUNCATE / DROP DATABASE içerir (veri kaybı)
 */
function analyze_sql_safety(string $content): array {
    $issues = [];
    $level = 'safe';

    // Veri kaybı riski
    if (preg_match('/\b(DROP\s+TABLE|TRUNCATE\s+TABLE|TRUNCATE\b|DROP\s+DATABASE)\b/i', $content, $m)) {
        $issues[] = 'Veri kaybı: ' . strtoupper($m[1]);
        $level = 'danger';
    }

    // Plain CREATE TABLE (IF NOT EXISTS olmadan)
    if (preg_match_all('/CREATE\s+TABLE(?!\s+IF\s+NOT\s+EXISTS)/i', $content, $m) && count($m[0]) > 0) {
        $issues[] = count($m[0]) . ' adet CREATE TABLE (IF NOT EXISTS olmadan)';
        if ($level !== 'danger') $level = 'warn';
    }

    // Plain INSERT (IGNORE veya ON DUPLICATE KEY olmadan)
    if (preg_match_all('/INSERT\s+INTO(?!\s+IGNORE)/i', $content, $m)) {
        $hasInserts = count($m[0]);
        // ON DUPLICATE KEY UPDATE eşleştirmesi sayısı kadar düş
        $safeInserts = preg_match_all('/ON\s+DUPLICATE\s+KEY\s+UPDATE/i', $content, $dm) ? count($dm[0]) : 0;
        $riskyInserts = max(0, $hasInserts - $safeInserts);
        if ($riskyInserts > 0) {
            $issues[] = $riskyInserts . ' adet INSERT (IGNORE/ON DUPLICATE olmadan) → mevcut verilerle çakışabilir';
            if ($level === 'safe') $level = 'warn';
        }
    }

    // Plain ALTER TABLE ADD COLUMN (IF NOT EXISTS olmadan, MySQL 8.0.29+)
    if (preg_match_all('/ALTER\s+TABLE\s+\w+\s+ADD\s+COLUMN(?!\s+IF\s+NOT\s+EXISTS)/i', $content, $m)) {
        // information_schema sorgusu ile koşullandırılmış mı? (idempotent pattern)
        $hasGuard = preg_match('/information_schema\.columns/i', $content);
        if (!$hasGuard && count($m[0]) > 0) {
            $issues[] = count($m[0]) . ' adet ALTER TABLE ADD COLUMN (kolon zaten varsa hata verir)';
            if ($level === 'safe') $level = 'warn';
        }
    }

    return ['level' => $level, 'issues' => $issues];
}

/* ─── SQL içeriğini statement'lara böl (string-aware) ─────────────────
 *
 * String literal'lar (tek tırnak / çift tırnak) içindeki `;`, `--`, `#`, `/*`
 * karakterlerini DOĞRU şekilde tanır → INSERT'lerdeki HTML/JSON/uzun metin
 * içeriği bölmez. Önceki naif splitter `&amp;`, `&nbsp;` gibi HTML entity'lerini
 * statement sonu sanıyordu.
 *
 * SQL escape kuralları:
 *   - Tek tırnak içinde: \' veya '' = literal apostrof
 *   - Çift tırnak içinde: \" veya "" = literal double-quote
 *   - Backtick içinde (identifier): hiçbir şey escape edilmez, sadece ` bekler
 */
function split_sql(string $content): array {
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") $content = substr($content, 3);

    $statements = [];
    $current = '';
    $len = strlen($content);
    $i = 0;
    $inSingle  = false;  // 'string'
    $inDouble  = false;  // "string"
    $inBacktick= false;  // `identifier`
    $inLineCmt = false;  // -- veya # ile satır sonuna kadar
    $inBlockCmt= false;  // /* ... */

    while ($i < $len) {
        $c = $content[$i];
        $next = $i + 1 < $len ? $content[$i + 1] : '';

        // --- Yorum içindeyken sadece kapanışı ara ---
        if ($inBlockCmt) {
            if ($c === '*' && $next === '/') { $inBlockCmt = false; $i += 2; continue; }
            $i++; continue;
        }
        if ($inLineCmt) {
            if ($c === "\n") { $inLineCmt = false; $current .= "\n"; }
            $i++; continue;
        }

        // --- String içindeyken yalnız kapanışı ve escape'i takip et ---
        if ($inSingle) {
            if ($c === '\\' && $next !== '') {       // \\, \', \n vb. escape
                $current .= $c . $next; $i += 2; continue;
            }
            if ($c === "'" && $next === "'") {        // SQL doubled-quote = literal '
                $current .= "''"; $i += 2; continue;
            }
            if ($c === "'") { $inSingle = false; }
            $current .= $c; $i++; continue;
        }
        if ($inDouble) {
            if ($c === '\\' && $next !== '') { $current .= $c . $next; $i += 2; continue; }
            if ($c === '"' && $next === '"') { $current .= '""'; $i += 2; continue; }
            if ($c === '"') { $inDouble = false; }
            $current .= $c; $i++; continue;
        }
        if ($inBacktick) {
            if ($c === '`') { $inBacktick = false; }
            $current .= $c; $i++; continue;
        }

        // --- Normal kod akışı: yorum/string açılışı + ; ayırıcısı ---
        if ($c === '-' && $next === '-') { $inLineCmt = true; $i += 2; continue; }
        if ($c === '#')                  { $inLineCmt = true; $i++;    continue; }
        if ($c === '/' && $next === '*') { $inBlockCmt = true; $i += 2; continue; }

        if ($c === "'") { $inSingle  = true; $current .= $c; $i++; continue; }
        if ($c === '"') { $inDouble  = true; $current .= $c; $i++; continue; }
        if ($c === '`') { $inBacktick= true; $current .= $c; $i++; continue; }

        if ($c === ';') {
            $s = trim($current);
            if ($s !== '') $statements[] = $s;
            $current = '';
            $i++; continue;
        }

        $current .= $c; $i++;
    }

    // Son statement (terminator olmayabilir)
    $s = trim($current);
    if ($s !== '') $statements[] = $s;

    return $statements;
}

/* ─── Tek bir migration dosyasını çalıştır ─────────────────────────── */
function run_migration(string $fullPath, ?int $adminId = null): array {
    $content = @file_get_contents($fullPath);
    if ($content === false) return ['ok' => false, 'count' => 0, 'error' => 'Dosya okunamadı'];
    $hash = md5($content);
    $statements = split_sql($content);

    $count = 0;
    $error = null;
    try {
        foreach ($statements as $stmt) {
            // query() + closeCursor(): bazı idempotent statement'lar sonuç kümesi döndürür
            // (ör. zaten varsa çalışan 'SELECT "...already exists"'). Sonuç tüketilmezse
            // sonraki statement "2014 unbuffered queries active" hatası verir. closeCursor()
            // bağlantıyı serbest bırakır → güvenli şekilde devam edilir.
            $res = db()->query($stmt);
            if ($res instanceof \PDOStatement) {
                $res->closeCursor();
            }
            $count++;
        }
        $ok = true;
    } catch (\Throwable $e) {
        $ok = false;
        $error = $e->getMessage() . ' — Statement #' . ($count + 1);
    }

    // Log
    try {
        $log = db()->prepare(
            "INSERT INTO migrations (filename, file_hash, statements_count, status, error_message, executed_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $log->execute([
            basename($fullPath), $hash, $count,
            $ok ? 'success' : 'failure',
            $error, $adminId,
        ]);
    } catch (\Throwable $e) { /* log başarısız olursa sessiz geç */ }

    return ['ok' => $ok, 'count' => $count, 'error' => $error, 'total' => count($statements)];
}

/* ─── POST handler ───────────────────────────────────────────────── */
$resultLog = []; // Bu request'te çalıştırılanların özeti

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    $adminId = (int)($GLOBALS['_admin_id'] ?? 0) ?: null;
    // fallback for admin id
    if (!$adminId) {
        $u = current_user();
        $adminId = $u ? (int)$u['id'] : null;
    }

    if ($action === 'run_one') {
        $full = safe_resolve_sql($_POST['filename'] ?? '', $SQL_DIR);
        if (!$full) {
            flash_set('err', 'Geçersiz dosya.');
        } else {
            $r = run_migration($full, $adminId);
            $resultLog[] = ['file' => basename($full)] + $r;
            flash_set($r['ok'] ? 'ok' : 'err',
                $r['ok'] ? basename($full) . ': ' . $r['count'] . '/' . $r['total'] . ' statement başarılı.'
                         : basename($full) . ': HATA — ' . $r['error']);
        }
    }

    if ($action === 'run_pending') {
        $files = $_POST['pending'] ?? [];
        if (!is_array($files)) $files = [];
        $totalOk = 0; $totalFail = 0;
        foreach ($files as $fn) {
            $full = safe_resolve_sql($fn, $SQL_DIR);
            if (!$full) continue;
            $r = run_migration($full, $adminId);
            $resultLog[] = ['file' => basename($full)] + $r;
            if ($r['ok']) $totalOk++; else $totalFail++;
        }
        flash_set($totalFail === 0 ? 'ok' : 'err',
            "Toplu çalıştırma tamamlandı: {$totalOk} başarılı, {$totalFail} hata.");
    }
}

/* ─── Önizleme (AJAX-benzeri inline) ───────────────────────────────── */
$previewContent = null;
$previewName = null;
if (!empty($_GET['preview'])) {
    $full = safe_resolve_sql($_GET['preview'], $SQL_DIR);
    if ($full) {
        $previewContent = file_get_contents($full);
        $previewName = basename($full);
    }
}

/* ─── Dosya listesi + durum ─────────────────────────────────────────── */
$files = glob($SQL_DIR . '/*.sql');
sort($files);

// Her dosyanın son çalıştırma kaydı
$execMap = [];
try {
    $st = db()->query(
        "SELECT m1.* FROM migrations m1
         INNER JOIN (SELECT filename, MAX(id) AS max_id FROM migrations GROUP BY filename) m2
           ON m1.id = m2.max_id"
    );
    foreach ($st->fetchAll() as $r) $execMap[$r['filename']] = $r;
} catch (\Throwable $e) {}

// Her dosya için güvenlik analizi (cache'le)
$safetyMap = [];
foreach ($files as $f) {
    $bn = basename($f);
    $c = @file_get_contents($f);
    $safetyMap[$bn] = $c !== false ? analyze_sql_safety($c) : ['level' => 'warn', 'issues' => ['Dosya okunamadı']];
}

// Bekleyen dosyalar — sadece SAFE olanlar batch için seçilir
$pendingSafeFiles = [];
$pendingRiskyFiles = [];
foreach ($files as $f) {
    $bn = basename($f);
    $exec = $execMap[$bn] ?? null;
    if (!$exec || $exec['status'] !== 'success') {
        if (($safetyMap[$bn]['level'] ?? 'warn') === 'safe') {
            $pendingSafeFiles[] = $bn;
        } else {
            $pendingRiskyFiles[] = $bn;
        }
    }
}

require_once __DIR__ . '/../core/header.php';
?>

<style>
.mig-warn{background:rgba(207,82,82,.08);border:1px solid rgba(207,82,82,.3);border-radius:8px;padding:14px 18px;margin-bottom:18px;color:#9A2A2A;font-size:13px;line-height:1.6}
.mig-warn strong{color:#7A1F1F}
.mig-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:18px;padding:14px 18px;background:var(--olive-2);border-radius:10px;border:1px solid var(--gold-border)}
.mig-status{padding:3px 9px;border-radius:11px;font-size:11px;font-weight:500;display:inline-block;white-space:nowrap}
.mig-pending{background:rgba(160,160,160,.14);color:#a8a090}
.mig-success{background:rgba(107,170,80,.18);color:#9fce7d}
.mig-failure{background:rgba(207,82,82,.16);color:#e4a3a3}
.mig-stale{background:rgba(201,162,75,.18);color:#c8b560}
.mig-preview{background:#1a1a1a;color:#c0c0c0;padding:18px;border-radius:8px;font-family:monospace;font-size:12px;line-height:1.6;max-height:60vh;overflow:auto;white-space:pre-wrap;border:1px solid var(--gold-border)}
.mig-result-card{padding:10px 14px;border-radius:8px;margin-bottom:6px;font-size:13px;font-family:monospace}
.mig-result-ok{background:rgba(107,170,80,.1);border-left:3px solid #9fce7d;color:#7fb05d}
.mig-result-fail{background:rgba(207,82,82,.1);border-left:3px solid #e4a3a3;color:#c47878}
</style>

<div class="mig-warn">
  <strong>⚠ Dikkat:</strong> Migration dosyaları veritabanı şemasını değiştirir.
  Çalıştırmadan önce <strong>veritabanı yedeği</strong> aldığınızdan emin olun (cPanel → phpMyAdmin → Export).
  Migration'larımız <code>IF NOT EXISTS</code> kullandığı için <strong>idempotent</strong>tir — aynı dosyayı tekrar çalıştırmak güvenlidir.
</div>

<?php if ($previewContent !== null): ?>
  <div class="panel" style="margin-bottom:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <h3 style="margin:0">📄 <?= e($previewName) ?></h3>
      <a class="btn btn-secondary btn-sm" href="migrations.php">✕ Kapat</a>
    </div>
    <pre class="mig-preview"><?= e($previewContent) ?></pre>
  </div>
<?php endif; ?>

<?php if ($resultLog): ?>
  <div class="panel" style="margin-bottom:18px">
    <h3 style="margin-bottom:12px">🔍 Bu çalıştırmanın sonucu</h3>
    <?php foreach ($resultLog as $r): ?>
      <div class="mig-result-card <?= $r['ok'] ? 'mig-result-ok' : 'mig-result-fail' ?>">
        <strong><?= $r['ok'] ? '✓' : '✗' ?> <?= e($r['file']) ?></strong> —
        <?= (int)$r['count'] ?>/<?= (int)$r['total'] ?> statement
        <?php if (!empty($r['error'])): ?>
          <br><small>⚠ <?= e($r['error']) ?></small>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="mig-toolbar">
  <strong style="font-size:13px">
    Bekleyen: <?= count($pendingSafeFiles) + count($pendingRiskyFiles) ?> dosya
    <?php if ($pendingRiskyFiles): ?>
      <span style="color:#e4a3a3"> · <?= count($pendingRiskyFiles) ?> tanesi <strong>RİSKLİ</strong> (manuel inceleme gerekir)</span>
    <?php endif; ?>
  </strong>
  <?php if ($pendingSafeFiles): ?>
    <form method="post" style="margin:0">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="run_pending">
      <?php foreach ($pendingSafeFiles as $pf): ?>
        <input type="hidden" name="pending[]" value="<?= e($pf) ?>">
      <?php endforeach; ?>
      <button class="btn btn-primary btn-sm" type="submit"
        onclick="return confirm('<?= count($pendingSafeFiles) ?> GÜVENLİ migration çalıştırılacak (sadece IF NOT EXISTS kullanan idempotent dosyalar). Riskli dosyalar atlanacak. Devam edilsin mi?')">
        🚀 Sadece Güvenli Olanları Çalıştır (<?= count($pendingSafeFiles) ?>)
      </button>
    </form>
  <?php endif; ?>
  <?php if (empty($pendingSafeFiles) && empty($pendingRiskyFiles)): ?>
    <span style="color:#9fce7d;font-size:13px">✓ Tüm migration'lar başarıyla çalıştırılmış.</span>
  <?php endif; ?>
  <?php if ($pendingRiskyFiles): ?>
    <span style="font-size:12px;color:var(--muted-text);margin-left:auto">
      ℹ Riskli dosyalar (kurulum/seed) toplu çalıştırmaya dahil edilmez — tek tek "Çalıştır" düğmesini kullanın.
    </span>
  <?php endif; ?>
</div>

<div class="panel" style="padding:0">
  <table>
    <thead><tr>
      <th>Dosya</th><th>Güvenlik</th><th>Boyut</th><th>Durum</th><th>Son Çalıştırma</th>
      <th>Statement</th><th>İşlem</th>
    </tr></thead>
    <tbody>
    <?php
    $safetyLabels = [
        'safe'   => ['🔒 Güvenli', 'rgba(107,170,80,.18)', '#9fce7d'],
        'warn'   => ['⚠ Riskli', 'rgba(201,162,75,.22)', '#c8b560'],
        'danger' => ['💀 TEHLİKELİ', 'rgba(207,82,82,.2)', '#e4a3a3'],
    ];
    foreach ($files as $f):
      $bn = basename($f);
      $size = filesize($f);
      $sizeKb = round($size / 1024, 1);
      $exec = $execMap[$bn] ?? null;
      $currentHash = md5_file($f);
      $hashChanged = $exec && $exec['file_hash'] && $exec['file_hash'] !== $currentHash;

      if (!$exec) { $statusKey = 'pending'; $statusLabel = 'Bekliyor'; }
      elseif ($exec['status'] === 'failure') { $statusKey = 'failure'; $statusLabel = 'Hata aldı'; }
      elseif ($hashChanged) { $statusKey = 'stale'; $statusLabel = 'Dosya değişti'; }
      else { $statusKey = 'success'; $statusLabel = 'Başarılı'; }

      $safety = $safetyMap[$bn] ?? ['level'=>'warn','issues'=>[]];
      $sl = $safetyLabels[$safety['level']];
      $tooltipText = $safety['issues'] ? implode(" | ", $safety['issues']) : 'Sadece IF NOT EXISTS / IF EXISTS kullanır — tekrar çalıştırmak güvenlidir';
    ?>
      <tr>
        <td>
          <strong style="color:var(--champagne)"><?= e($bn) ?></strong>
          <?php if ($exec && $exec['status']==='failure' && $exec['error_message']): ?>
            <br><small style="color:#e4a3a3;font-family:monospace;font-size:11px">⚠ <?= e(mb_substr($exec['error_message'], 0, 100)) ?></small>
          <?php endif; ?>
          <?php if ($safety['issues']): ?>
            <br><small style="color:<?= $sl[2] ?>;font-size:11px">⚠ <?= e(implode(' · ', array_slice($safety['issues'],0,2))) ?></small>
          <?php endif; ?>
        </td>
        <td><span style="background:<?= $sl[1] ?>;color:<?= $sl[2] ?>;padding:3px 9px;border-radius:11px;font-size:11px;font-weight:500;white-space:nowrap;cursor:help" title="<?= e($tooltipText) ?>"><?= $sl[0] ?></span></td>
        <td style="font-size:12px"><?= $sizeKb ?> KB</td>
        <td><span class="mig-status mig-<?= $statusKey ?>"><?= $statusLabel ?></span></td>
        <td style="font-size:11px;color:var(--muted-text);white-space:nowrap">
          <?= $exec ? e($exec['executed_at']) : '<span class="muted">—</span>' ?>
        </td>
        <td style="text-align:center"><?= $exec ? (int)$exec['statements_count'] : '<span class="muted">—</span>' ?></td>
        <td style="white-space:nowrap">
          <a class="btn btn-secondary btn-sm" href="?preview=<?= urlencode($bn) ?>" title="İçeriği önizle">👁 Önizle</a>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="run_one">
            <input type="hidden" name="filename" value="<?= e($bn) ?>">
            <?php
              // Güvenlik düzeyine göre buton + confirm metni
              if ($safety['level'] === 'danger') {
                  $btnClass = 'btn-secondary'; $btnLabel = '💀 Çalıştır';
                  $confirmMsg = 'UYARI! Bu dosya tehlikeli operasyon içeriyor:\n\n' . implode("\n", $safety['issues']) . "\n\nVERİ KAYBI olabilir. Yedeğiniz var mı? Devam etmek istediğinize emin misiniz?";
              } elseif ($safety['level'] === 'warn') {
                  $btnClass = 'btn-secondary'; $btnLabel = '⚠ Çalıştır';
                  $confirmMsg = "Bu dosya idempotent değil:\n\n" . implode("\n", $safety['issues']) . "\n\nMevcut verileriniz varsa çakışma olabilir (mevcut migration zaten yapılmışsa hata verir; ilk kurulumdaysa sorunsuz çalışır).\n\nDevam edilsin mi?";
              } else {
                  $btnClass = $statusKey === 'success' ? 'btn-secondary' : 'btn-primary';
                  $btnLabel = $statusKey === 'success' ? '↻ Tekrar' : '▶ Çalıştır';
                  $confirmMsg = $statusKey === 'success'
                      ? 'Bu migration zaten başarıyla çalıştırılmış. Tekrar çalıştırmak istediğinize emin misiniz? (Güvenli — IF NOT EXISTS sayesinde zararsız.)'
                      : 'Bu güvenli migration çalıştırılacak. Devam edilsin mi?';
              }
            ?>
            <button class="btn <?= $btnClass ?> btn-sm" type="submit"
              onclick="return confirm(<?= json_encode($confirmMsg, JSON_HEX_QUOT | JSON_HEX_APOS) ?>)"><?= $btnLabel ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="panel" style="margin-top:18px">
  <h3 style="margin-bottom:10px">📜 Son 20 Çalıştırma Geçmişi</h3>
  <?php
    $history = [];
    try {
        $history = db()->query(
            "SELECT m.*, u.name AS admin_name FROM migrations m
             LEFT JOIN users u ON u.id = m.executed_by
             ORDER BY m.id DESC LIMIT 20"
        )->fetchAll();
    } catch (\Throwable $e) {}
  ?>
  <?php if ($history): ?>
    <table>
      <thead><tr><th>Tarih</th><th>Dosya</th><th>Durum</th><th>Statement</th><th>Hata</th><th>Çalıştıran</th></tr></thead>
      <tbody>
      <?php foreach ($history as $h): ?>
        <tr>
          <td style="font-size:12px;white-space:nowrap"><?= e($h['executed_at']) ?></td>
          <td><code style="font-size:11px"><?= e($h['filename']) ?></code></td>
          <td><span class="mig-status mig-<?= $h['status'] ?>"><?= $h['status']==='success'?'✓ Başarılı':'✗ Hata' ?></span></td>
          <td style="text-align:center"><?= (int)$h['statements_count'] ?></td>
          <td style="font-size:11px;color:#e4a3a3;max-width:300px"><?= e(mb_substr($h['error_message'] ?? '', 0, 120)) ?></td>
          <td style="font-size:11px;color:var(--muted-text)"><?= e($h['admin_name'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="muted">Henüz migration çalıştırılmamış.</p>
  <?php endif; ?>
</div>

<p class="muted" style="margin-top:14px;font-size:12px;line-height:1.7">
  <strong>Güvenlik etiketleri (otomatik analiz):</strong><br>
  <span style="background:rgba(107,170,80,.18);color:#9fce7d;padding:2px 8px;border-radius:10px;font-size:11px">🔒 Güvenli</span>
  = idempotent (IF NOT EXISTS, ALTER GUARD vs.) — tekrar çalıştırılması zararsız<br>
  <span style="background:rgba(201,162,75,.22);color:#c8b560;padding:2px 8px;border-radius:10px;font-size:11px">⚠ Riskli</span>
  = plain INSERT veya CREATE TABLE içerir — mevcut DB ile çakışabilir (ilk kurulumda OK)<br>
  <span style="background:rgba(207,82,82,.2);color:#e4a3a3;padding:2px 8px;border-radius:10px;font-size:11px">💀 TEHLİKELİ</span>
  = DROP TABLE / TRUNCATE içerir — VERİ KAYBI riski, sadece sıfırdan kurulumda kullanın<br><br>

  <strong>Durum etiketleri:</strong>
  <span class="mig-status mig-pending">Bekliyor</span> = hiç çalıştırılmadı ·
  <span class="mig-status mig-success">Başarılı</span> = sorunsuz çalıştı ·
  <span class="mig-status mig-failure">Hata aldı</span> = son çalıştırmada hata ·
  <span class="mig-status mig-stale">Dosya değişti</span> = sonradan değişmiş, yeniden çalıştırılması önerilir<br><br>

  <strong>Önemli:</strong> "Sadece Güvenli Olanları Çalıştır" butonu yalnız 🔒 işaretli dosyaları çalıştırır. Riskli/tehlikeli dosyalar batch'e dahil edilmez — tek tek manuel inceleyip çalıştırmanız gerekir.
</p>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
