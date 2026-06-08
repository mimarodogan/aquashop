<?php
/**
 * Ürün soru gönderme handler'ı.
 * Hem misafir (isim+email) hem de giriş yapmış kullanıcılar kullanabilir.
 * Admin onayı gerektirir (is_approved=0).
 *
 * Spam koruması:
 *  1. CSRF token
 *  2. Honeypot alanı
 *  3. Zaman kontrolü (min 5 saniye)
 *  4. Rate limit: giriş yapmış → DB, misafir → session
 *  5. Duplicate check: aynı kullanıcı aynı ürüne 24 saatte 1 soru
 */
require_once __DIR__ . '/includes/functions.php';

// Y-10 GÜVENLİK: open redirect — same-host whitelist
$ref = safe_back_url($_POST['back'] ?? '', '');
if ($ref === '') $ref = safe_back_url($_SERVER['HTTP_REFERER'] ?? '', url('home'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {

    /* ── KATMAN 1: Honeypot ────────────────────────────────────── */
    if (!empty($_POST['q_url'])) {
        redirect($ref . '#sorular');
        exit;
    }

    /* ── KATMAN 2: Zaman kontrolü ─────────────────────────────── */
    $ttParts = explode('|', $_POST['q_tt'] ?? '', 2);
    $ttTs    = (int)($ttParts[0] ?? 0);
    $ttSig   = $ttParts[1] ?? '';
    $ttKey   = session_id() . 'q';
    $ttValid = ($ttSig === hash_hmac('sha256', 'q:' . $ttTs, $ttKey))
            && (time() - $ttTs >= 5)
            && (time() - $ttTs <= 7200);
    if (!$ttValid) {
        flash_set('err', 'Formu yenileyip tekrar deneyin.');
        redirect($ref . '#sorular');
        exit;
    }

    /* ── Girdi toplama ─────────────────────────────────────────── */
    $pid      = (int)($_POST['product_id'] ?? 0);
    $question = trim($_POST['question'] ?? '');
    $u        = current_user();

    if ($u) {
        $askerName  = $u['name'];
        $askerEmail = $u['email'] ?? null;
        $userId     = (int)$u['id'];
    } else {
        $askerName  = trim($_POST['asker_name']  ?? '');
        $askerEmail = trim($_POST['asker_email'] ?? '');
        $userId     = null;
    }

    if ($pid <= 0) {
        flash_set('err', 'Geçersiz ürün.');
    } elseif (mb_strlen($question) < 10) {
        flash_set('err', 'Sorunuz en az 10 karakter olmalı.');
    } elseif (!$askerName) {
        flash_set('err', 'Lütfen adınızı girin.');
    } elseif ($askerEmail && !filter_var($askerEmail, FILTER_VALIDATE_EMAIL)) {
        flash_set('err', 'E-posta adresi geçerli değil.');
    } else {

        /* ── KATMAN 3: Rate limit ──────────────────────────────── */
        $rateLimited = false;
        if ($userId) {
            // Giriş yapmış: DB'den son 1 saatte kaç soru gönderilmiş?
            $rlSt = db()->prepare(
                "SELECT COUNT(*) FROM product_questions
                  WHERE user_id=? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );
            $rlSt->execute([$userId]);
            if ((int)$rlSt->fetchColumn() >= 5) {
                $rateLimited = true;
            }
        } else {
            // Misafir: session tabanlı sayaç
            $now = time();
            if (!isset($_SESSION['q_rl_hour']) || ($now - (int)$_SESSION['q_rl_hour']) > 3600) {
                $_SESSION['q_rl_count'] = 0;
                $_SESSION['q_rl_hour']  = $now;
            }
            $_SESSION['q_rl_count'] = (int)($_SESSION['q_rl_count'] ?? 0);
            if ($_SESSION['q_rl_count'] >= 3) {
                $rateLimited = true;
            }
        }

        if ($rateLimited) {
            flash_set('err', 'Saatte çok fazla soru gönderdiniz. Lütfen bekleyin.');
            redirect($ref . '#sorular');
            exit;
        }

        /* ── KATMAN 4: Duplicate check (giriş yapmış, 24 saat) ── */
        if ($userId) {
            $dupSt = db()->prepare(
                "SELECT COUNT(*) FROM product_questions
                  WHERE user_id=? AND product_id=? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            $dupSt->execute([$userId, $pid]);
            if ((int)$dupSt->fetchColumn() > 0) {
                flash_set('err', 'Bu ürüne son 24 saat içinde zaten soru sordunuz. Sorunuzu lütfen bekleyin.');
                redirect($ref . '#sorular');
                exit;
            }
        }

        /* ── DB kaydı ─────────────────────────────────────────── */
        db()->prepare(
            'INSERT INTO product_questions (product_id, user_id, asker_name, asker_email, question, is_approved, created_at)
             VALUES (?,?,?,?,?,0,NOW())'
        )->execute([$pid, $userId, $askerName, $askerEmail ?: null, $question]);

        // Misafir sayacını artır
        if (!$userId) {
            $_SESSION['q_rl_count'] = (int)($_SESSION['q_rl_count'] ?? 0) + 1;
        }

        flash_set('success', 'Sorunuz alındı. Onay sonrasında yayında görünecek.');
    }
}

redirect($ref . '#sorular');
