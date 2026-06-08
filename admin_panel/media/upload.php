<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../../includes/media.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($_FILES['file'])) { echo json_encode(array('error'=>'no file')); exit; }
$res = media_upload_from_files($_FILES['file']);
if (!$res['ok']) { http_response_code(400); echo json_encode(array('error'=>$res['error'])); exit; }
// TinyMCE: { "location": "..." }
echo json_encode(array('location'=>$res['path'],'id'=>$res['id']));
