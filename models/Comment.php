<?php
function comment_list($type, $targetId, $onlyApproved = true) {
    $sql = "SELECT c.*, u.name AS author_name FROM comments c JOIN users u ON u.id=c.user_id
            WHERE c.target_type=? AND c.target_id=?";
    if ($onlyApproved) $sql .= ' AND c.is_approved=1';
    $sql .= ' ORDER BY c.created_at DESC';
    $st = db()->prepare($sql);
    $st->execute(array($type, (int)$targetId));
    return $st->fetchAll();
}
function comment_add($type, $targetId, $body, $rating = null) {
    $u = current_user(); if (!$u) return false;
    $body = trim($body); if ($body === '') return false;
    $r = ($rating !== null && $rating !== '') ? max(1, min(5, (int)$rating)) : null;
    $st = db()->prepare('INSERT INTO comments (target_type,target_id,user_id,body,rating) VALUES (?,?,?,?,?)');
    $st->execute(array($type, (int)$targetId, (int)$u['id'], $body, $r));
    return true;
}
function comment_avg_rating($targetId) {
    $st = db()->prepare("SELECT ROUND(AVG(rating),1) AS avg, COUNT(*) AS cnt FROM comments WHERE target_type='product' AND target_id=? AND is_approved=1 AND rating IS NOT NULL");
    $st->execute(array((int)$targetId));
    return $st->fetch();
}
