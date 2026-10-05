<?php
/* OLAY AKISI */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();
$o = kalkan_oku('olaylar', ['olaylar'=>[], 'toplam'=>0]);
$k = kalkan_oku('kararlar', ['kararlar'=>[]]);
$ayar = kalkan_oku('ayarlar', ['panel_adi'=>'CYBER KALKAN']);
$sev = ['KRITIK'=>'#ff3b5c','YUKSEK'=>'#ff9f2e','ORTA'=>'#f5d547','DUSUK'=>'#4fd1c5'];
$fil = $_GET['sev'] ?? '';
$liste = $o['olaylar'] ?? [];
if ($fil) $liste = array_values(array_filter($liste, fn($x)=>($x['seviye']??'')===$fil));
?>
<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Olaylar</title>
<link rel="stylesheet" href="assets/panel.css?v=1.6"><?php kalkan_sayfalama_script(); ?>
</head><body>
<?= kalkan_ustbilgi('olaylar.php') ?>
<main>
<?php kalkan_baslik('📋', 'Olay Kayıtları', 'Tespit edilen güvenlik olayları'); ?>
<section class="kutu">
  <h3>⚡ Olaylar (<?= count($liste) ?>) — toplam <?= (int)($o['toplam']??0) ?></h3>
  <form class="satir" method="get">
    <select name="sev" onchange="this.form.submit()">
      <option value="">Tüm seviyeler</option>
      <?php foreach (array_keys($sev) as $s): ?>
        <option value="<?= $s ?>" <?= $fil===$s?'selected':'' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <table id="tbl_olay"><thead><tr><th>Zaman</th><th>IP</th><th>Kural</th><th>Seviye</th><th>Açıklama</th><th>MITRE</th><th>Kaynak</th></tr></thead><tbody>
  <?php if (!$liste): ?><tr><td colspan="6" class="bos">Kayıt yok</td></tr>
  <?php else: foreach ($liste as $x): $s=$x['seviye']??'DUSUK'; ?>
    <tr><td class="soluk"><?= kalkan_kacis($x['zaman']??'') ?></td>
        <td class="ip"><?= kalkan_kacis($x['ip']??'') ?></td>
        <td class="mono">#<?= (int)($x['kural']??0) ?></td>
        <td><span class="rozet" style="background:<?= $sev[$s]??'#888' ?>22;color:<?= $sev[$s]??'#888' ?>"><?= kalkan_kacis($s) ?></span></td>
        <td><?= kalkan_kacis($x['aciklama']??'') ?></td>
        <td class="mono"><?= kalkan_kacis($x['mitre'] ?? '-') ?></td>
        <td class="soluk"><?= kalkan_kacis($x['kaynak']??'') ?></td></tr>
  <?php endforeach; endif; ?>
  </tbody></table>
</section>
<section class="kutu">
  <h3>⚖️ Karar Günlüğü (<?= count($k['kararlar']??[]) ?>)</h3>
  <table class="tablo"><thead><tr><th>Zaman</th><th>IP</th><th>Karar</th><th>Puan</th><th>Sebep</th></tr></thead><tbody>
  <?php if (empty($k['kararlar'])): ?><tr><td colspan="5" class="bos">Karar yok</td></tr>
  <?php else: foreach (array_slice($k['kararlar'],0,40) as $c): ?>
    <tr><td class="soluk"><?= kalkan_kacis($c['zaman']??'') ?></td>
        <td class="ip"><?= kalkan_kacis($c['ip']??'') ?></td>
        <td><span class="rozet" style="background:#ff3b5c22;color:#ff3b5c"><?= kalkan_kacis($c['karar']??'') ?></span></td>
        <td class="mono"><?= (int)($c['puan']??0) ?></td>
        <td><?= kalkan_kacis($c['sebep']??'') ?></td></tr>
  <?php endforeach; endif; ?>
  </tbody></table>
</section>
<?= kalkan_altbilgi() ?>
</main><script>kalkan_sayfalama('tbl_olay',50);</script>
</body></html>
