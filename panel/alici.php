<?php
/* ALICI — ajanlardan veri kabul eder (headless, panel oturumu gerekmez, token ile) */
require __DIR__ . '/ortak.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Powered-By: CYBER KALKAN');

$ayar = kalkan_oku('ayarlar', []);
$beklenen = $ayar['ajan_token'] ?? 'cyber-kalkan-2026';
$gelen = $_SERVER['HTTP_X_KALKAN_TOKEN'] ?? ($_GET['token'] ?? '');

// GET -> saglik kontrolu
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['durum' => 'aktif', 'mesaj' => 'CYBER KALKAN alici hazir',
        'surum' => '1.0', 'token_gerekli' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!hash_equals((string)$beklenen, (string)$gelen)) {
    http_response_code(403);
    echo json_encode(['hata' => 'yetkisiz token']);
    exit;
}

$govde = file_get_contents('php://input');
$v = json_decode($govde, true);
if (!is_array($v)) { http_response_code(400); echo json_encode(['hata' => 'gecersiz JSON']); exit; }

$makine = preg_replace('/[^A-Za-z0-9_.-]/', '', $v['makine'] ?? 'bilinmeyen');
$olaylar = is_array($v['olaylar'] ?? null) ? $v['olaylar'] : [];
$bilgi = is_array($v['bilgi'] ?? null) ? $v['bilgi'] : [];

// 1) ajan durum dosyasi
kalkan_yaz("ajan_$makine", [
    'makine' => $makine, 'ip' => $bilgi['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '?'),
    'os' => $bilgi['os'] ?? '', 'kernel' => $bilgi['kernel'] ?? '',
    'cpu' => $bilgi['cpu'] ?? '', 'ram_mb' => $bilgi['ram_mb'] ?? '',
    'disk' => $v['disk_kullanim'] ?? ($bilgi['disk'] ?? ''), 'yuk' => $v['yuk'] ?? '',
    'uptime' => $bilgi['uptime'] ?? '', 'son_gorulme' => $v['zaman'] ?? date('d.m.Y H:i:s'),
    'olay_sayisi' => count($olaylar), 'durum' => 'AKTIF',
]);

// 2) olaylari merkeze ekle
if ($olaylar) {
    $o = kalkan_oku('olaylar', ['olaylar' => [], 'toplam' => 0]);
    $yeni = [];
    foreach ($olaylar as $x) {
        $yeni[] = ['zaman' => $v['zaman'] ?? date('d.m.Y H:i:s'), 'ip' => $x['ip'] ?? '',
                   'kural' => $x['kural'] ?? 0, 'seviye' => $x['seviye'] ?? 'ORTA',
                   'aciklama' => '[' . $makine . '] ' . ($x['aciklama'] ?? ''),
                   'kaynak' => 'ajan', 'mitre' => ''];
    }
    $o['olaylar'] = array_slice(array_merge($yeni, $o['olaylar'] ?? []), 0, 500);
    $o['toplam'] = ($o['toplam'] ?? 0) + count($yeni);
    kalkan_yaz('olaylar', $o);
}

echo json_encode(['durum' => 'kabul', 'makine' => $makine, 'olay_kaydedildi' => count($olaylar),
    'zaman' => date('d.m.Y H:i:s')], JSON_UNESCAPED_UNICODE);
