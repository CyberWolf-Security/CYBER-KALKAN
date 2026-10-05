<?php
/* AJAN CONFIG DAGITIMI — ajanlar merkezi config ceker */
require __DIR__ . '/ortak.php';
header('Content-Type: application/json; charset=utf-8');
$ayar = kalkan_oku("ayarlar", []);
$token = $_GET["token"] ?? $_SERVER["HTTP_X_KALKAN_TOKEN"] ?? "";
if ($token !== ($ayar["api_token"] ?? "")) {
    http_response_code(403); exit('{"durum":"hata","mesaj":"token gecersiz"}');
}
$cfg = kalkan_oku("ajan_config", []);
echo json_encode($cfg, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
