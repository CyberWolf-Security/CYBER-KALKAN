<?php
/* API — dis entegrasyon icin JSON uclari (kendi kodumuz) */
require __DIR__ . '/ortak.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Powered-By: CYBER KALKAN');

$yetki = $_GET['token'] ?? ($_SERVER['HTTP_X_KALKAN_TOKEN'] ?? '');
$ayar  = kalkan_oku('ayarlar', []);
$api_token = $ayar['api_token'] ?? '';
if ($api_token !== '' && !hash_equals($api_token, (string)$yetki)) {
    http_response_code(401);
    echo json_encode(['hata'=>'yetkisiz'], JSON_UNESCAPED_UNICODE); exit;
}

$uc = $_GET['uc'] ?? 'durum';

    /* CSV rapor disa aktarma */
    if (($_GET['uc'] ?? '') === 'csv') {
        $tur = $_GET['tur'] ?? 'olaylar';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="kalkan_' . $tur . '_' . date('Ymd_His') . '.csv"');
        echo "\xEF\xBB\xBF"; // BOM (Excel icin)
        $cikti = fopen('php://output', 'w');
        if ($tur === 'olaylar') {
            $d = kalkan_oku('olaylar', ['olaylar' => []]);
            fputcsv($cikti, ['Zaman', 'IP', 'Kural', 'Seviye', 'Aciklama', 'Kaynak', 'MITRE']);
            foreach ($d['olaylar'] ?? [] as $x)
                fputcsv($cikti, [$x['zaman'] ?? '', $x['ip'] ?? '', $x['kural'] ?? '', $x['seviye'] ?? '',
                                 $x['aciklama'] ?? '', $x['kaynak'] ?? '', $x['mitre'] ?? '']);
        } elseif ($tur === 'engel') {
            $d = kalkan_oku('engel', ['liste' => []]);
            fputcsv($cikti, ['IP', 'Puan', 'Zaman', 'Sebep', 'Kaynak']);
            foreach ($d['liste'] ?? [] as $x)
                fputcsv($cikti, [$x['ip'] ?? '', $x['puan'] ?? '', $x['zaman'] ?? '', $x['sebep'] ?? '', $x['kaynak'] ?? '']);
        } elseif ($tur === 'vakalar') {
            $d = kalkan_oku('vakalar', ['vakalar' => []]);
            fputcsv($cikti, ['ID', 'Baslik', 'IP', 'Onem', 'Durum', 'Zaman', 'Not']);
            foreach ($d['vakalar'] ?? [] as $x)
                fputcsv($cikti, [$x['id'] ?? '', $x['baslik'] ?? '', $x['ip'] ?? '', $x['onem'] ?? '',
                                 $x['durum'] ?? '', $x['zaman'] ?? '', $x['not'] ?? '']);
        }
        fclose($cikti);
        exit;
    }

    switch ($uc) {
    case 'durum':
        echo json_encode(kalkan_oku('istatistik', []), JSON_UNESCAPED_UNICODE);
        break;
    case 'engel':
        echo json_encode(kalkan_oku('engel', ['liste'=>[]]), JSON_UNESCAPED_UNICODE);
        break;
    case 'olaylar':
        $o = kalkan_oku('olaylar', ['olaylar'=>[]]);
        echo json_encode(array_slice($o['olaylar'] ?? [], 0, (int)($_GET['limit'] ?? 50)), JSON_UNESCAPED_UNICODE);
        break;
    case 'ajanlar':
        $a = [];
        foreach (glob(KALKAN_VERI . '/ajan_*.json') ?: [] as $d) {
            $j = json_decode((string)@file_get_contents($d), true);
            if (is_array($j)) $a[] = $j;
        }
        echo json_encode(['ajanlar'=>$a], JSON_UNESCAPED_UNICODE);
        break;
    case 'saglik':
        echo json_encode([
            'durum' => 'aktif',
            'surum' => $ayar['surum'] ?? '1.0',
            'marka' => $ayar['marka'] ?? 'CYBERWOLF SECURITY',
            'zaman' => kalkan_simdi(),
        ], JSON_UNESCAPED_UNICODE);
        break;
    default:
        http_response_code(404);
        echo json_encode(['hata'=>'bilinmeyen uc'], JSON_UNESCAPED_UNICODE);
}
