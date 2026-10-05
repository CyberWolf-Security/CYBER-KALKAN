<?php
/* INDEXER API — SQLite hizli arama */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();
header('Content-Type: application/json; charset=utf-8');
$db = new PDO("sqlite:/opt/siber-kalkan/VERI/kalkan.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$islem = $_GET["islem"] ?? "ara";
if ($islem === "ara") {
    $q = trim($_GET["q"] ?? ""); $sev = $_GET["seviye"] ?? ""; $limit = (int)($_GET["limit"] ?? 50);
    $sql = "SELECT * FROM olaylar WHERE 1=1"; $p = [];
    if ($q) { $sql .= " AND (ip LIKE ? OR ad LIKE ?)"; $p[] = "%$q%"; $p[] = "%$q%"; }
    if ($sev) { $sql .= " AND seviye = ?"; $p[] = $sev; }
    $sql .= " ORDER BY id DESC LIMIT $limit";
    $st = $db->prepare($sql); $st->execute($p);
    exit(json_encode(["durum"=>"ok","kayit"=>$st->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE));
}
if ($islem === "istatistik") {
    $r = [];
    foreach (["olaylar","engel","fim","waf"] as $t) {
        $r[$t] = (int)$db->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    }
    $r["seviye"] = $db->query("SELECT seviye, COUNT(*) c FROM olaylar GROUP BY seviye")->fetchAll(PDO::FETCH_KEY_PAIR);
    $r["top_ip"] = $db->query("SELECT ip, COUNT(*) c FROM olaylar GROUP BY ip ORDER BY c DESC LIMIT 10")->fetchAll(PDO::FETCH_KEY_PAIR);
    exit(json_encode(["durum"=>"ok","istatistik"=>$r], JSON_UNESCAPED_UNICODE));
}
if ($islem === "trend") {
    $r = $db->query("SELECT substr(zaman,1,10) gun, COUNT(*) c FROM olaylar GROUP BY gun ORDER BY gun DESC LIMIT 30")->fetchAll(PDO::FETCH_KEY_PAIR);
    exit(json_encode(["durum"=>"ok","trend"=>$r], JSON_UNESCAPED_UNICODE));
}
echo json_encode(["durum"=>"ok"]);
