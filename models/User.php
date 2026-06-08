<?php
function user_find($id) {
    $st = db()->prepare("SELECT * FROM users WHERE id=?");
    $st->execute(array((int)$id));
    return $st->fetch();
}
function user_find_by_email($email) {
    $st = db()->prepare("SELECT * FROM users WHERE email=?");
    $st->execute(array($email));
    return $st->fetch();
}
function user_create($data) {
    $st = db()->prepare("INSERT INTO users (name,first_name,last_name,email,password,phone,birth_date,email_consent,sms_consent,role) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $st->execute(array(
        $data['name'], $data['first_name'] ?? null, $data['last_name'] ?? null,
        $data['email'], password_hash($data['password'], PASSWORD_DEFAULT),
        $data['phone'] ?? null, $data['birth_date'] ?? null,
        !empty($data['email_consent']) ? 1 : 0, !empty($data['sms_consent']) ? 1 : 0,
        $data['role'] ?? 'customer'
    ));
    return (int)db()->lastInsertId();
}
function user_update_password($id, $newPlain) {
    db()->prepare("UPDATE users SET password=? WHERE id=?")
        ->execute(array(password_hash($newPlain, PASSWORD_DEFAULT), (int)$id));
}
function user_verify($plain, $hash) {
    return password_verify($plain, $hash);
}
