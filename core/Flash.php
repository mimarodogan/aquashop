<?php
function flash_set($type, $msg) { $_SESSION['flash'][] = array('type'=>$type,'msg'=>$msg); }
function flash_pop() { $f = isset($_SESSION['flash']) ? $_SESSION['flash'] : array(); unset($_SESSION['flash']); return $f; }

/**
 * Toast.js'in okuyacağı JSON bloğunu sayfaya basar.
 * Storefront ve admin header'ları bu fonksiyonu çağırır.
 */
function flash_render() {
    $items = flash_pop();
    if (!$items) return;
    // Tip eşleme: storefront 'success'/'err' + admin 'ok'/'err' → toast tipleri
    $typeMap = array('success'=>'success','ok'=>'success','err'=>'error','error'=>'error','warning'=>'warning','info'=>'info');
    $out = array();
    foreach ($items as $f) {
        $t = isset($typeMap[$f['type']]) ? $typeMap[$f['type']] : 'info';
        $out[] = array('type'=>$t, 'msg'=>$f['msg']);
    }
    echo '<script id="flash-data" type="application/json">' . json_encode($out, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) . '</script>';
}

/**
 * Aynı istekte (redirect olmadan) anında toast göster.
 * Form validasyon hataları gibi senaryolar için kullanılır.
 */
function toast_now($type, $msg) {
    $typeMap = array('success'=>'success','ok'=>'success','err'=>'error','error'=>'error','warning'=>'warning','info'=>'info');
    $t = isset($typeMap[$type]) ? $typeMap[$type] : 'info';
    $payload = json_encode(array(array('type'=>$t,'msg'=>$msg)), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE);
    // Birden fazla çağrılabilir — her biri benzersiz id ile
    static $i = 0; $i++;
    echo '<script type="application/json" data-flash-extra="'.$i.'">' . $payload . '</script>';
    echo '<script>document.addEventListener("DOMContentLoaded",function(){if(window.toast)JSON.parse(document.querySelector(\'[data-flash-extra="'.$i.'"]\').textContent).forEach(function(f){window.toast[f.type]?window.toast[f.type](f.msg):window.toast.show(f.msg,f.type)})});</script>';
}
