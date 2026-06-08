<?php
function newsletter_subscribe($email, $source = 'site') {
    $token = bin2hex(random_bytes(16));
    $st = db()->prepare('INSERT INTO newsletter_subscribers (email,token,source) VALUES (?,?,?)
                         ON DUPLICATE KEY UPDATE is_active=1, unsubscribed_at=NULL');
    $st->execute(array($email, $token, $source));
    return $token;
}
function newsletter_unsubscribe($email, $token) {
    $st = db()->prepare('SELECT id FROM newsletter_subscribers WHERE email=? AND token=?');
    $st->execute(array($email, $token));
    if (!$st->fetch()) return false;
    db()->prepare('UPDATE newsletter_subscribers SET is_active=0, unsubscribed_at=NOW() WHERE email=?')->execute(array($email));
    return true;
}
function newsletter_active_subscribers() {
    return db()->query('SELECT email,token FROM newsletter_subscribers WHERE is_active=1')->fetchAll();
}
