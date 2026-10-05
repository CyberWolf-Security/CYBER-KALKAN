<?php
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();
$ayar = kalkan_oku("ayarlar", []);
$engel = kalkan_oku("engel", ["liste"=>[]]);
$olay = kalkan_oku("olaylar", ["olaylar"=>[],"toplam"=>0]);
$kural = kalkan_oku("kurallar", ["kurallar"=>[]]);
$comp = kalkan_oku("compliance_mapping", ["kapsam"=>[]]);
$fm = kalkan_oku("fim", []);
?><!doctype html><html lang="tr"><head><meta charset="utf-8">
<title>CYBER KALKAN Rapor</title>
<link rel="stylesheet" href="assets/panel.css?v=1.7">
<link rel="stylesheet" href="assets/hero.css?v=2">
<style>
body{font-family:Arial;margin:30px;color:#222}h1{color:#0b6}
h2{border-bottom:2px solid #0b6;padding:5px 0;margin-top:25px}
table{border-collapse:collapse;width:100%;margin:10px 0}
th,td{border:1px solid #ccc;padding:8px;text-align:left}
th{background:#0b6;color:#fff}.kpi{display:flex;gap:20px;margin:20px 0}
.kpi div{background:#f0f8f5;padding:15px;border-left:4px solid #0b6;flex:1}
/* ★ yazdirirken ust bant / hero / buton gizlensin (temiz cikti) */
@media print{
  .no-print,.ust,nav,.cy-hero,.canli-serit,.altbilgi,footer{display:none !important}
  body{margin:10px}
}
</style></head><body>
<?= kalkan_ustbilgi('rapor.php') ?>
<main class="sarici">
<?php kalkan_baslik('📄', 'Güvenlik Raporu', 'engel · olay · kural · uyumluluk özeti — yazdırılabilir'); ?>
<div class="no-print" style="margin:14px 0"><button onclick="window.print()">🖨️ PDF olarak kaydet</button></div>
<h1>🐺 CYBER KALKAN — Güvenlik Raporu</h1>
<p style="color:#9fc0dc">Marka: <?= htmlspecialchars($ayar["marka"] ?? "CYBERWOLF SECURITY") ?> · Tarih: <?= date("d.m.Y H:i") ?></p>
<div class="kpi">
<div><b style="font-size:24px"><?= count($engel["liste"]) ?></b><br>Engellenen IP</div>
<div><b style="font-size:24px"><?= $olay["toplam"] ?></b><br>Toplam Olay</div>
<div><b style="font-size:24px"><?= count($kural["kurallar"]) ?></b><br>Aktif Kural</div>
<div><b style="font-size:24px"><?= count($fm["degisimler"] ?? []) ?></b><br>FIM Değişiklik</div>
</div>
<h2>Uyumluluk Durumu</h2><table><tr><th>Standart</th><th>Kapsam</th><th>Skor</th></tr>
<?php foreach ($comp["kapsam"] as $k=>$v): ?>
<tr><td><?= $k ?></td><td><?= $v["kapsanan"] ?>/<?= $v["toplam"] ?></td><td>%<?= $v["skor"] ?></td></tr>
<?php endforeach; ?></table>
<h2>En Çok Saldıran IP'ler</h2><table><tr><th>IP</th><th>Puan</th><th>Kaynak</th></tr>
<?php $l = $engel["liste"]; usort($l, fn($a,$b)=>($b["puan"]??0)<=>($a["puan"]??0));
foreach (array_slice($l,0,15) as $k): ?>
<tr><td><?= htmlspecialchars($k["ip"]) ?></td><td><?= $k["puan"]??"-" ?></td><td><?= $k["kaynak"]??"-" ?></td></tr>
<?php endforeach; ?></table>
<h2>Son Olaylar (MITRE)</h2><table><tr><th>Zaman</th><th>IP</th><th>Kural</th><th>Seviye</th><th>MITRE</th></tr>
<?php foreach (array_slice(array_reverse($olay["olaylar"]),0,20) as $o): ?>
<tr><td><?= $o["zaman"]??"-" ?></td><td><?= htmlspecialchars($o["ip"]??"-") ?></td><td><?= htmlspecialchars($o["ad"]??$o["kural"]??"-") ?></td><td><?= $o["seviye"]??"-" ?></td><td><?= $o["mitre"]??"-" ?></td></tr>
<?php endforeach; ?></table>
<p style="margin-top:30px;color:#888;font-size:12px">CYBER KALKAN · <?= htmlspecialchars($ayar["marka"]??"") ?> · Otomatik rapor</p>
</main>
<?= kalkan_altbilgi() ?>
</body></html>