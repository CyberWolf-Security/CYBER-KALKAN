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
/* ★ TÜM SEÇİCİLER .rap İLE KAPSAMLANDI — eskiden global (body,h1,table,th...) olduğu için
   raporun stili ÜST BANTI ve menüyü bozuyordu (body margin/renk, th arka planı vb.) */
.rap{font-family:Arial,Helvetica,sans-serif;color:#e6f0ff}
.rap h1{color:#3ddc97;font-size:26px;margin:14px 0 6px}
.rap h2{color:#e6f0ff;border-bottom:2px solid #0b6;padding:6px 0;margin-top:24px;font-size:20px}
.rap table{border-collapse:collapse;width:100%;margin:12px 0}
.rap th,.rap td{border:1px solid rgba(120,180,220,.35);padding:9px 12px;text-align:left;font-size:15px}
.rap th{background:rgba(11,102,102,.45);color:#e6f0ff;font-weight:700;letter-spacing:.4px}
.rap .kpi{display:flex;gap:16px;margin:18px 0;flex-wrap:wrap}
.rap .kpi div{background:rgba(20,32,48,.85);border:1px solid rgba(56,189,248,.3);
  padding:16px 20px;border-left:4px solid #38bdf8;flex:1 1 180px;border-radius:10px;color:#cbd5e1}
.rap .kpi b{color:#7dd3fc}
/* ★ RAPOR BUTONLARI — şık gradient + neon hover */
.rp-btnler{ display:flex; gap:13px; flex-wrap:wrap; margin:18px 0; }
.rap .rp-btn{
  display:inline-flex; align-items:center; gap:9px;
  padding:13px 26px; border-radius:12px;
  font-size:16px; font-weight:800; letter-spacing:.35px;
  cursor:pointer; border:1px solid; background:transparent;
  transition:transform .18s ease, box-shadow .28s ease, background .22s ease, border-color .22s ease;
  font-family:inherit;
}
.rap .rp-btn:hover{ transform:translateY(-2px); }
.rap .rp-btn:active{ transform:translateY(0); }
.rap .rp-btn-pdf{
  background:linear-gradient(135deg,rgba(231,76,60,.24),rgba(231,76,60,.07)) !important;
  border:1px solid rgba(231,76,60,.55) !important; color:#ff9a8f !important;
}
.rap .rp-btn-pdf:hover{ box-shadow:0 8px 26px rgba(231,76,60,.40); border-color:#ff6b5b; color:#ffb3ab; }
.rap .rp-btn-rapor{
  background:linear-gradient(135deg,rgba(56,189,248,.24),rgba(129,140,248,.09)) !important;
  border:1px solid rgba(56,189,248,.55) !important; color:#7dd3fc !important;
}
.rap .rp-btn-rapor:hover{ box-shadow:0 8px 26px rgba(56,189,248,.42); border-color:#38bdf8; color:#bae6fd; }
/* açıkken (rapor görünümü aktif) yeşile döner */
.rap .rp-btn-rapor.aktif{
  background:linear-gradient(135deg,rgba(46,204,113,.26),rgba(46,204,113,.08)) !important;
  border:1px solid rgba(46,204,113,.62) !important; color:#8dffbd !important;
}
.rap .rp-btn-rapor.aktif:hover{ box-shadow:0 8px 26px rgba(46,204,113,.42); }

/* ★ yazdirirken ust bant / hero / buton gizlensin (temiz cikti) */
@media print{
  .no-print,.ust,nav,.cy-hero,.canli-serit,.altbilgi,footer{display:none !important}
  body{margin:10px}
}
/* ★ EKRANDA yazdırma görünümü (butonla açılan) */
body.yazdirma .ust, body.yazdirma nav, body.yazdirma .cy-hero,
body.yazdirma .canli-serit, body.yazdirma .no-print,
body.yazdirma .altbilgi, body.yazdirma footer{ display:none !important; }
body.yazdirma .sarici{ padding:14px !important; margin:0 !important; max-width:100% !important; }
</style></head><body>
<?= kalkan_ustbilgi('rapor.php') ?>
<main class="sarici rap">
<?php kalkan_baslik('📄', 'Güvenlik Raporu', 'engel · olay · kural · uyumluluk özeti — yazdırılabilir'); ?>
<div class="no-print rp-btnler">
  <button class="rp-btn rp-btn-pdf" onclick="window.print()">🖨️ PDF olarak kaydet</button>
  <button class="rp-btn rp-btn-rapor" id="ygBtn" onclick="ygDegistir()">👁️ Rapor Görünümü</button>
</div>
<script>
/* ★ RAPOR GÖRÜNÜMÜ — üst bant/menü/hero gizlenir, yalnız rapor içeriği kalır */
function ygDegistir(){
  var acik = document.body.classList.toggle('yazdirma');
  var b = document.getElementById('ygBtn');
  b.textContent = acik ? '↩️ Panele Dön' : '👁️ Rapor Görünümü';
  b.classList.toggle('aktif', acik);
  window.scrollTo(0,0);
}
/* Esc ile çıkış */
document.addEventListener('keydown',function(e){ if(e.key==='Escape' && document.body.classList.contains('yazdirma')) ygDegistir(); });
</script>
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