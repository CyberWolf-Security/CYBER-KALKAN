<?php
/* GUVENLIK DURUMU — Zafiyet + SCA + Rootkit (zafiyet + yapilandirma denetimi motoru) */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();
$av = kalkan_oku('antivirus', ['bulunan'=>[], 'temiz'=>true]);
$rem = kalkan_oku('remediation', ['oneriler'=>[], 'otomatik_uygulanan'=>0]);
$z = kalkan_oku('zafiyet', ['puan'=>0,'guncellenebilir'=>0,'suid_sayisi'=>0,'kritik_kurulu'=>[]]);
$s = kalkan_oku('sca', ['skor'=>0,'gecen'=>0,'toplam'=>0,'kontroller'=>[],'rootkit'=>[],'rootkit_temiz'=>true]);
$renk = fn($p) => $p>=80?'#00d68f':($p>=50?'#ffb020':'#ff4d5e');
?>
<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><title>Guvenlik Durumu — CYBER KALKAN</title>
<link rel="stylesheet" href="assets/panel.css?v=1.6"></head><body>
<?= kalkan_ustbilgi('guvenlik.php') ?>
<main>
<?php kalkan_baslik('🛡️', 'Güvenlik Durumu', 'Risk skoru ve tehdit özeti'); ?>
<div class="kartlar">
  <div class="kart"><div class="etiket">ZAFIYET PUANI</div><div class="deger" style="color:<?= $renk($z['puan']) ?>"><?= (int)$z['puan'] ?></div><div class="alt">/100</div></div>
  <div class="kart"><div class="etiket">GUNCELLENEBILIR PAKET</div><div class="deger" style="color:#ffb020"><?= (int)$z['guncellenebilir'] ?></div></div>
  <div class="kart"><div class="etiket">SCA SKORU</div><div class="deger" style="color:<?= $renk($s['skor']) ?>"><?= (int)$s['skor'] ?></div><div class="alt"><?= (int)$s['gecen'] ?>/<?= (int)$s['toplam'] ?> gecti</div></div>
  <div class="kart"><div class="etiket">ROOTKIT</div><div class="deger" style="color:<?= $s['rootkit_temiz']?'#00d68f':'#ff4d5e' ?>"><?= $s['rootkit_temiz']?'TEMIZ':'TEHLIKE' ?></div></div>
    <div class="kart"><div class="etiket">ANTIVIRUS (ClamAV)</div><div class="deger" style="color:<?= !empty($av['temiz'])?'#00d68f':'#ff3b5c' ?>"><?= !empty($av['temiz'])?'TEMIZ':count($av['bulunan']).' BULGU' ?></div><div class="alt">son tarama: <?= kalkan_kacis($av['tarih']??'-') ?></div></div>
<div class="kart"><div class="etiket">OTOMATIK DUZELTME</div><div class="deger" style="color:#4da3ff"><?= (int)($rem['otomatik_uygulanan']??0) ?></div><div class="alt"><?= count($rem['oneriler']??[]) ?> oneri</div></div>
</div>
<h2 class="bolum">📋 Guvenlik Denetimi (SCA)</h2>
<table class="tablo"><tr><th>Kontrol</th><th>Seviye</th><th>Sonuc</th></tr>
<?php foreach (($s['kontroller'] ?? []) as $k): ?>
<tr><td><?= htmlspecialchars($k['kontrol']) ?></td>
<td><span class="seviye <?= strtolower($k['seviye']) ?>"><?= $k['seviye'] ?></span></td>
<td><?= $k['sonuc']==='GECTI' ? '<span style="color:#00d68f">✓ GECTI</span>' : '<span style="color:#ff4d5e">✗ BASARISIZ</span>' ?></td></tr>
<?php endforeach; ?>
</table>
<h2 class="bolum">🔍 Rootkit Bulgulari</h2>
<?php if ($s['rootkit_temiz']): ?><p style="color:#00d68f">✓ Temiz — supheli bulgu yok</p>
<?php else: ?><ul><?php foreach (($s['rootkit'] ?? []) as $r): ?><li style="color:#ff4d5e"><?= htmlspecialchars($r) ?></li><?php endforeach; ?></ul><?php endif; ?>
<h2 class="bolum">📦 Kritik Paketler</h2>
<table class="tablo"><tr><th>Paket</th><th>Kurulu Surum</th></tr>
<?php foreach (($z['kritik_kurulu'] ?? []) as $p=>$v): ?>
<tr><td><?= htmlspecialchars($p) ?></td><td><?= htmlspecialchars($v) ?></td></tr>
<?php endforeach; ?>
</table>
<?php if ((int)$z['suid_sayisi'] > 25): ?>
<div class="uyari orta" style="display:flex;align-items:center;gap:14px;font-size:18px;font-weight:800;padding:20px 24px;border-radius:14px;background:rgba(255,176,32,.12);border:1px solid rgba(255,176,32,.5);border-left:6px solid #ffb020;color:#ffc457;box-shadow:0 0 26px rgba(255,176,32,.25)"><span style="font-size:30px">&#9888;&#65039;</span><span><?= (int)$z['suid_sayisi'] ?> SUID dosya tespit edildi &mdash; yetki yükseltme riski</span></div>
<?php endif; ?>
<p style="color:#9fc0dc;font-size:16.5px;font-weight:700;margin-top:18px;text-shadow:0 0 10px rgba(0,212,255,.35)">Son tarama: <?= htmlspecialchars($z['tarih'] ?? '-') ?> · CYBERWOLF SECURITY</p>
</main>
<?= kalkan_altbilgi() ?></body></html>
