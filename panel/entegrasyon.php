<?php
/* ENTEGRASYON API — VirusTotal/MISP/Slack sorgulari */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();
header('Content-Type: application/json; charset=utf-8');
$ayar = kalkan_oku("ayarlar", []);
$islem = $_GET["islem"] ?? "liste";
if ($islem === "vt_sorgu") {
    $ip = $_GET["ip"] ?? "";
    $vt = $ayar["vt_api_key"] ?? "";
    if (!$vt) { exit(json_encode(["durum"=>"hata","mesaj"=>"VT API anahtari yok"])); }
    $ch = curl_init("https://www.virustotal.com/api/v3/ip_addresses/".urlencode($ip));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>["x-apikey: $vt"], CURLOPT_TIMEOUT=>10]);
    $r = curl_exec($ch); $kod = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($kod === 200) {
        $d = json_decode($r, true);
        $st = $d["data"]["attributes"]["last_analysis_stats"] ?? [];
        exit(json_encode(["durum"=>"ok","ip"=>$ip,"kotu"=>$st["malicious"]??0,"supheli"=>$st["suspicious"]??0], JSON_UNESCAPED_UNICODE));
    }
    exit(json_encode(["durum"=>"hata","kod"=>$kod], JSON_UNESCAPED_UNICODE));
}
if ($islem === "slack") {
    $wh = $ayar["slack_webhook"] ?? "";
    if (!$wh) { exit(json_encode(["durum"=>"hata","mesaj"=>"webhook yok"])); }
    $d = json_encode(["text" => $_GET["mesaj"] ?? "CYBER KALKAN test"]);
    $ch = curl_init($wh);
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$d, CURLOPT_HTTPHEADER=>["Content-Type: application/json"], CURLOPT_TIMEOUT=>8]);
    curl_exec($ch); $k = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    exit(json_encode(["durum"=>$k===200?"ok":"hata","kod"=>$k]));
}
echo json_encode(kalkan_oku("entegrasyonlar", []), JSON_UNESCAPED_UNICODE);
