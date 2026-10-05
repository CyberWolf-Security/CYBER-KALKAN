<?php
/* AJAN UPGRADE — merkezden surum kontrolu + indirme */
require __DIR__ . '/ortak.php';
header('Content-Type: application/json; charset=utf-8');
$ayar = kalkan_oku("ayarlar", []);
$token = $_GET["token"] ?? $_SERVER["HTTP_X_KALKAN_TOKEN"] ?? "";
if ($token !== ($ayar["api_token"] ?? "")) { http_response_code(403); exit('{"durum":"hata"}'); }
$ajan_surum = $_GET["surum"] ?? "0.0";
$merkez_surum = "1.0";
$guncelle = version_compare($merkez_surum, $ajan_surum, ">");
echo json_encode([
    "durum" => "aktif",
    "ajan_surum" => $ajan_surum,
    "merkez_surum" => $merkez_surum,
    "guncelleme_var" => $guncelle,
    "indirme" => $guncelle ? "/dosyalar/ajan-kur.sh" : null
], JSON_UNESCAPED_UNICODE);
