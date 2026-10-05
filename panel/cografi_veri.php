<?php
declare(strict_types=1);
/* COGRAFI VERI — canli JSON (harita otomatik yenileme icin) */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();
header('Content-Type: application/json; charset=utf-8');

$cg = kalkan_oku('cografya', []);
/* ★ DUZELTME: modul 'ip_bilgi' yazar ({"1.2.3.4": {"ulke":"CN",...}}); eski kod 'ip_ulke'
   ariyordu → API hep BOS donuyordu → harita isaretcileri gorunmuyordu. Iki bicim de desteklenir. */
$k = $cg['ip_ulke'] ?? [];
if ((!is_array($k) || !$k) && !empty($cg['ip_bilgi']) && is_array($cg['ip_bilgi'])) {
    $k = [];
    foreach ($cg['ip_bilgi'] as $ip => $b) {
        $u = is_array($b) ? (string)($b['ulke'] ?? '') : (string)$b;
        if ($u !== '' && $u !== '?') $k[$ip] = $u;
    }
}
if (!is_array($k)) $k = [];
$cnt = [];
foreach ($k as $ip => $ulke) {
    $kod = is_array($ulke) ? (string)($ulke['kod'] ?? 'XX') : (string)$ulke;
    $cnt[$kod] = ($cnt[$kod] ?? 0) + 1;
}
arsort($cnt);

/* ulke koordinatlari */
$koord = [
 'US'=>[39.8,-98.6],'CN'=>[35.9,104.2],'RU'=>[61.5,105.3],'DE'=>[51.2,10.4],'NL'=>[52.1,5.3],
 'FR'=>[46.2,2.2],'GB'=>[55.4,-3.4],'IE'=>[53.4,-8.2],'SG'=>[1.35,103.8],'VN'=>[14.1,108.3],
 'IN'=>[20.6,79.0],'BR'=>[-14.2,-51.9],'IR'=>[32.4,53.7],'TR'=>[38.9,35.2],'UA'=>[48.4,31.2],
 'PL'=>[51.9,19.1],'RO'=>[45.9,25.0],'BG'=>[42.7,25.5],'ID'=>[-0.8,113.9],'KR'=>[35.9,127.8],
 'JP'=>[36.2,138.3],'HK'=>[22.3,114.2],'TW'=>[23.7,121.0],'TH'=>[15.9,101.0],'MY'=>[4.2,101.9],
 'SE'=>[60.1,18.6],'NO'=>[60.5,8.5],'FI'=>[61.9,25.7],'DK'=>[56.3,9.5],'BE'=>[50.5,4.5],
 'CH'=>[46.8,8.2],'AT'=>[47.5,14.6],'IT'=>[41.9,12.6],'ES'=>[40.5,-3.7],'PT'=>[39.4,-8.2],
 'GR'=>[39.1,21.8],'CZ'=>[49.8,15.5],'HU'=>[47.2,19.5],'CA'=>[56.1,-106.3],'MX'=>[23.6,-102.6],
 'AR'=>[-38.4,-63.6],'CL'=>[-35.7,-71.5],'CO'=>[4.6,-74.3],'ZA'=>[-30.6,22.9],'EG'=>[26.8,30.8],
 'NG'=>[9.1,8.7],'KE'=>[0.0,37.9],'SA'=>[23.9,45.1],'AE'=>[23.4,53.8],'IL'=>[31.0,34.9],
 'PK'=>[30.4,69.3],'BD'=>[23.7,90.4],'PH'=>[12.9,121.8],'AU'=>[-25.3,133.8],'NZ'=>[-40.9,174.9],
 'LV'=>[56.9,24.6],'LT'=>[55.2,23.9],'EE'=>[58.6,25.0],'MD'=>[47.4,28.4],'KZ'=>[48.0,66.9],
 'VN2'=>[14.1,108.3],'SC'=>[-4.7,55.5],'PA'=>[8.5,-80.8],'CR'=>[9.7,-83.8],'PE'=>[-9.2,-75.0],
 'VE'=>[6.4,-66.6],'EC'=>[-1.8,-78.2],'UY'=>[-32.5,-55.8],'PY'=>[-23.4,-58.4],'BO'=>[-16.3,-63.6],
];
$harita = [];
foreach (array_slice($cnt, 0, 40, true) as $kod => $sayi) {
    if ($kod === 'XX' || !isset($koord[$kod])) continue;
    $harita[] = ['kod'=>$kod, 'lat'=>$koord[$kod][0], 'lon'=>$koord[$kod][1], 'sayi'=>$sayi];
}
echo json_encode([
    'zaman'  => date('H:i:s'),
    'toplam' => array_sum($cnt),
    'ulke'   => count($cnt),
    'veri'   => $harita,
], JSON_UNESCAPED_UNICODE);