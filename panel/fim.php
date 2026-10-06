<?php
declare(strict_types=1);
/* FIM — Dosya Butunlugu Izleme */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();

$f = kalkan_oku('fim', ['dosyalar' => [], 'degisimler' => [], 'toplam' => 0]);

/* ★ DUZELTME: modul 'baseline'(izlenen dosya listesi) + 'izlenen'(sayi) + 'saglam' yazar.
   Eski kod $f['dosyalar'] ariyordu (YOK) → "IZLENEN DOSYA 0" gorunuyordu (gercek 31).
   Ayrica 'SON 24 SAAT' karti son 20 kaydi sayiyordu (yanlis). */
$dosyalar   = is_array($f['dosyalar'] ?? null) ? $f['dosyalar']
            : (is_array($f['baseline'] ?? null) ? $f['baseline'] : []);
$degisimler = is_array($f['degisimler'] ?? null) ? $f['degisimler'] : [];
$izlenen    = (int)($f['izlenen'] ?? count($dosyalar));
$saglam     = (int)($f['saglam'] ?? 0);
/* ★ KUMULATIF toplam onceliklidir ('degisim_sayisi' = SADECE bu turdaki yeni degisim → 0 olabilir) */
$degisim_s  = (int)($f['toplam'] ?? count($degisimler));
if ($degisim_s === 0 && $degisimler) { $degisim_s = count($degisimler); }

/* gercek son 24 saat (zaman damgasi varsa) */
$esik = time() - 86400; $son24 = 0;
foreach ($degisimler as $d) {
    $z = (string)($d['zaman'] ?? $d['tarih'] ?? '');
    if ($z === '') { continue; }
    $ts = strtotime(str_replace('.', '-', $z));
    if ($ts !== false && $ts >= $esik) { $son24++; }
}
if ($son24 === 0 && $degisimler) { $son24 = count($degisimler); }

function fim_kacis($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>FIM · CYBER KALKAN</title><link rel="stylesheet" href="assets/panel.css?v=1.7"></head><body>
<?= kalkan_ustbilgi('fim.php') ?>
<main>
<?php kalkan_baslik('📝', 'Dosya Bütünlüğü (FIM)', 'Kritik dosyaların izlenmesi — değişiklik tespiti'); ?>

<div class="kartlar">
  <div class="kart"><div class="et">İZLENEN DOSYA</div><div class="sg"><?= (int)$izlenen ?></div></div>
  <div class="kart iyi"><div class="et">SAĞLAM</div><div class="sg"><?= (int)$saglam ?></div></div>
  <div class="kart vurgu"><div class="et">TOPLAM DEĞİŞİM</div><div class="sg"><?= (int)$degisim_s ?></div></div>
  <div class="kart kritik"><div class="et">SON 24 SAAT</div><div class="sg"><?= (int)$son24 ?></div></div>
</div>

<section class="panel-kutu">
  <h2 class="bolum">📋 Değişiklik Günlüğü (son 50)</h2>
  <table class="tablo"><thead><tr><th>DOSYA</th><th>ZAMAN</th><th>DURUM</th></tr></thead><tbody>
  <?php if (empty($degisimler)): ?>
    <tr><td colspan="3" style="text-align:center;color:#7b8f9e">Henüz değişiklik yok ✓</td></tr>
  <?php else: ?>
    <?php $son = array_slice(array_reverse(array_values($degisimler)), 0, 50); ?>
    <?php foreach ($son as $d): ?>
      <tr>
        <td class="mono"><?= fim_kacis($d['dosya'] ?? $d['yol'] ?? '-') ?></td>
        <td><?= fim_kacis($d['zaman'] ?? '-') ?></td>
        <td><span class="etiket"><?= fim_kacis($d['olay'] ?? $d['durum'] ?? 'DEĞİŞTİ') ?></span></td>
      </tr>
    <?php endforeach; ?>
  <?php endif; ?>
  </tbody></table>
</section>

<section class="panel-kutu">
  <h2 class="bolum">🎯 İzlenen Dosyalar</h2>
  <table class="tablo"><thead><tr><th>DOSYA</th><th>HASH</th></tr></thead><tbody>
  <?php foreach ($dosyalar as $yol => $h): ?>
    <tr><td class="mono"><?= fim_kacis(is_string($yol) ? $yol : (is_array($h) ? ($h['dosya'] ?? $yol) : $yol)) ?></td>
        <td class="mono"><?= fim_kacis(is_array($h) ? substr((string)($h['hash'] ?? '-'), 0, 16) : substr((string)$h, 0, 16)) ?>...</td></tr>
  <?php endforeach; ?>
  </tbody></table>
</section>
</main>
<?= kalkan_altbilgi() ?></body></html>
