<?php
/* DENETIM IZI (audit log) */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();
$a = kalkan_oku('audit', ['kayitlar' => [], 'toplam' => 0]);
$ayar = kalkan_oku('ayarlar', []);
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Denetim İzi</title>
<link rel="stylesheet" href="assets/panel.css?v=1.7"></head><body>
<?= kalkan_ustbilgi('denetim.php') ?>
<main>
<?php kalkan_baslik('📜', 'Denetim İzi', 'Kim, ne zaman, ne yaptı'); ?>
  <h2 class="bolum">📜 Denetim İzi <span class="dim">(<?= (int)($a['toplam'] ?? 0) ?> kayıt — kim ne yaptı)</span></h2>
  <table class="tablo"><thead><tr><th>Zaman</th><th>Kullanıcı</th><th>IP</th><th>İşlem</th><th>Detay</th></tr></thead><tbody>
  <?php foreach (array_slice($a['kayitlar'] ?? [], 0, 300) as $x): ?>
    <tr><td class="dim mono"><?= kalkan_kacis($x['zaman'] ?? '') ?></td>
        <td class="mono"><?= kalkan_kacis($x['kullanici'] ?? '') ?></td>
        <td class="mono"><?= kalkan_kacis($x['ip'] ?? '') ?></td>
        <td><span class="rozet" style="background:#4da3ff22;color:#4da3ff"><?= kalkan_kacis($x['islem'] ?? '') ?></span></td>
        <td class="dim"><?= kalkan_kacis($x['detay'] ?? '') ?></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</main>
<?= kalkan_altbilgi() ?></body></html>
