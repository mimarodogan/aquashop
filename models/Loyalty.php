<?php
/**
 * Sadakat (Loyalty) Modeli — puan kazan/harca/expire/raporla.
 *
 * Davranışlar:
 *   - Sipariş "delivered" olduğunda otomatik earn (loyalty_award_for_order)
 *   - Sipariş iptal/iade'de earn geri alınır (loyalty_revoke_for_order)
 *   - Checkout'ta puan kullanıldıysa redeem kaydı
 *   - 12 aydan eski earn'ler cron ile expire
 *
 * Settings:
 *   loyalty_enabled       (default '0')
 *   loyalty_earn_rate     (kaç ₺'ye 1 puan, default '1' = 1₺=1puan)
 *   loyalty_redeem_rate   (1 puan kaç ₺, default '0.10' = 10 puan = 1₺)
 *   loyalty_min_redeem    (min kullanılabilir puan, default '100')
 *   loyalty_expire_months (kaç ay sonra expire, default '12')
 */

if (!function_exists('loyalty_enabled')) {
    function loyalty_enabled(): bool {
        return setting('loyalty_enabled', '0') === '1';
    }
}

if (!function_exists('loyalty_earn_rate')) {
    function loyalty_earn_rate(): float {
        $r = (float)setting('loyalty_earn_rate', '1');
        return $r > 0 ? $r : 1.0;
    }
}

if (!function_exists('loyalty_redeem_rate')) {
    function loyalty_redeem_rate(): float {
        $r = (float)setting('loyalty_redeem_rate', '0.10');
        return $r > 0 ? $r : 0.10;
    }
}

if (!function_exists('loyalty_min_redeem')) {
    function loyalty_min_redeem(): int {
        return max(1, (int)setting('loyalty_min_redeem', '100'));
    }
}

if (!function_exists('loyalty_expire_months')) {
    function loyalty_expire_months(): int {
        return max(1, (int)setting('loyalty_expire_months', '12'));
    }
}

if (!function_exists('loyalty_balance')) {
    function loyalty_balance(int $userId): int {
        if ($userId <= 0) return 0;
        try {
            $st = db()->prepare('SELECT points FROM loyalty_points WHERE user_id = ?');
            $st->execute([$userId]);
            return (int)($st->fetchColumn() ?: 0);
        } catch (\Throwable $e) { return 0; }
    }
}

if (!function_exists('loyalty_value_of')) {
    /** N puanın TL karşılığı */
    function loyalty_value_of(int $points): float {
        return round($points * loyalty_redeem_rate(), 2);
    }
}

if (!function_exists('loyalty_points_for_amount')) {
    /** X TL harcamaya kaç puan kazandırır */
    function loyalty_points_for_amount(float $amount): int {
        $rate = loyalty_earn_rate();
        return (int)floor($amount / $rate);
    }
}

if (!function_exists('loyalty_history')) {
    function loyalty_history(int $userId, int $limit = 50): array {
        if ($userId <= 0) return [];
        try {
            $st = db()->prepare(
                "SELECT type, points, order_id, note, expires_at, created_at
                 FROM loyalty_transactions
                 WHERE user_id = ?
                 ORDER BY created_at DESC
                 LIMIT $limit"
            );
            $st->execute([$userId]);
            return $st->fetchAll();
        } catch (\Throwable $e) { return []; }
    }
}

/* ────────────────────────────────────────────────────────────────── */

if (!function_exists('loyalty_apply_delta')) {
    /**
     * Bakiyeyi delta kadar değiştir (pozitif veya negatif) ve transaction kaydet.
     * Atomik (transaction içinde). points_lifetime sadece earn'lerde artar.
     */
    function loyalty_apply_delta(int $userId, int $delta, string $type, ?int $orderId = null, ?string $note = null, ?string $expiresAt = null): bool {
        if ($userId <= 0 || $delta === 0) return false;
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Bakiye satırı yoksa oluştur
            $pdo->prepare('INSERT IGNORE INTO loyalty_points (user_id, points) VALUES (?, 0)')->execute([$userId]);

            // Sadece earn ve adjust pozitif → lifetime artırır
            $isEarn = in_array($type, ['earn','refund','adjust'], true) && $delta > 0;

            $pdo->prepare(
                'UPDATE loyalty_points
                 SET points = GREATEST(0, points + ?),
                     points_lifetime = points_lifetime + ?
                 WHERE user_id = ?'
            )->execute([$delta, $isEarn ? $delta : 0, $userId]);

            // İşlem kaydı
            $pdo->prepare(
                'INSERT INTO loyalty_transactions (user_id, type, points, order_id, note, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$userId, $type, $delta, $orderId, $note, $expiresAt]);

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[loyalty] apply_delta failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('loyalty_award_for_order')) {
    /**
     * Sipariş teslim olduğunda çağrılır. Önceden ödüllendirilmişse no-op.
     * Yalnız harcanmış net tutar üzerinden puan verir (puan kullanılarak indirilen tutar hariç).
     */
    function loyalty_award_for_order(int $orderId): bool {
        if (!loyalty_enabled()) return false;
        $st = db()->prepare(
            "SELECT id, user_id, total, loyalty_points_used, loyalty_points_value, loyalty_points_earned
             FROM orders WHERE id = ?"
        );
        $st->execute([$orderId]);
        $o = $st->fetch();
        if (!$o || !$o['user_id']) return false;
        if ((int)$o['loyalty_points_earned'] > 0) return false; // zaten verildi

        // order.total zaten puan indirimi düşülmüş NET tutardır (checkout grand_total) →
        // doğrudan nakit harcama olarak kullanılır (puan değeri ikinci kez düşülmez).
        $cashSpent = max(0, (float)$o['total']);
        $earn = loyalty_points_for_amount($cashSpent);
        if ($earn <= 0) return false;

        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . loyalty_expire_months() . ' months'));
        $note = 'Sipariş #' . $orderId . ' (₺' . number_format($cashSpent, 2, ',', '.') . ')';

        if (loyalty_apply_delta((int)$o['user_id'], $earn, 'earn', $orderId, $note, $expiresAt)) {
            db()->prepare('UPDATE orders SET loyalty_points_earned = ? WHERE id = ?')->execute([$earn, $orderId]);
            return true;
        }
        return false;
    }
}

if (!function_exists('loyalty_revoke_for_order')) {
    /**
     * Sipariş iptal/iade'de earn'i geri al.
     */
    function loyalty_revoke_for_order(int $orderId): bool {
        if (!loyalty_enabled()) return false;
        $st = db()->prepare("SELECT user_id, loyalty_points_earned, loyalty_points_used FROM orders WHERE id = ?");
        $st->execute([$orderId]);
        $o = $st->fetch();
        if (!$o || !$o['user_id']) return false;
        $ok = true;
        if ((int)$o['loyalty_points_earned'] > 0) {
            $ok = loyalty_apply_delta((int)$o['user_id'], -(int)$o['loyalty_points_earned'], 'adjust', $orderId, 'Sipariş iptali — earn iadesi') && $ok;
            db()->prepare('UPDATE orders SET loyalty_points_earned = 0 WHERE id = ?')->execute([$orderId]);
        }
        if ((int)$o['loyalty_points_used'] > 0) {
            // Kullanılan puanları geri ver
            $ok = loyalty_apply_delta((int)$o['user_id'], (int)$o['loyalty_points_used'], 'refund', $orderId, 'Sipariş iptali — kullanılan puan iadesi') && $ok;
            db()->prepare('UPDATE orders SET loyalty_points_used = 0, loyalty_points_value = 0 WHERE id = ?')->execute([$orderId]);
        }
        return $ok;
    }
}

if (!function_exists('loyalty_redeem')) {
    /**
     * Checkout sırasında puan kullan. orderId siparişe işlenir, points düşülür.
     */
    function loyalty_redeem(int $userId, int $points, int $orderId): bool {
        if (!loyalty_enabled() || $points <= 0) return false;
        if ($points < loyalty_min_redeem()) return false;
        $bal = loyalty_balance($userId);
        if ($bal < $points) return false;
        $value = loyalty_value_of($points);
        if (loyalty_apply_delta($userId, -$points, 'redeem', $orderId, 'Sipariş #' . $orderId . ' indirimi')) {
            db()->prepare(
                'UPDATE orders SET loyalty_points_used = ?, loyalty_points_value = ? WHERE id = ?'
            )->execute([$points, $value, $orderId]);
            return true;
        }
        return false;
    }
}
