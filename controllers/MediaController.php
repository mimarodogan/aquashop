<?php
require_once __DIR__ . '/../core/bootstrap.php';

// Sadece adminler yükleyebilir
$u = current_user();
if (!$u || $u['role'] !== 'admin') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error'=>'Yetki yok.'));
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_FILES['file'])) {
    echo json_encode(array('error'=>'Dosya yok.'));
    exit;
}
$res = media_upload_from_files($_FILES['file']);
if (!$res['ok']) {
    http_response_code(400);
    echo json_encode(array('error'=>$res['error']));
    exit;
}
echo json_encode(array('location'=>$res['path'],'id'=>$res['id']));
