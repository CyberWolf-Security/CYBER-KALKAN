<?php
/* INLINE IPS — Suricata nfqueue durum + alarm ozeti */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();

$KOK = dirname(__DIR__);
$mesaj = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['islem'])) {
    $mod = escapeshellarg($KOK . '/motor/kalkan_ips_v10.py');
    if ($_POST['islem'] === 'baslat') {
        $c = "python3 $mod baslat --onay 2>&1";
    } elseif ($_POST['islem'] === 'durdur') {
        $c = "python3 $mod durdur 2>&1";
    } else {
        $c = '';
    }
    if ($c) { $mesaj = trim((string)shell_exec($c)); }
}

/* ★ DURUM — motor (root) 60 sn'de bir VERI/ips_durum.json yazar; panel (www-data) SADECE OKUR.
   Eskiden shell_exec("nft list…") / shell_exec("docker ps…") vardı → www-data yetkisiz olduğu için
   boş dönüyor ve IPS "kapalı" görünüyordu (gerçekte çalışırken). */
$ids    = kalkan_oku('ips_durum', []);
$tablo  = (int)($ids['kuyruk']     ?? 0);
$sur    = (string)($ids['konteyner'] ?? '');
$kural  = (int)($ids['kural']      ?? 0);
$l7     = (int)($ids['l7']         ?? 0);
$hata   = (int)($ids['hata']       ?? 0);
$aktif  = !empty($ids['aktif']);
$mod    = (string)($ids['mod']     ?? '');
$ids_zam = (string)($ids['zaman']  ?? '');

/* alarm ozeti */
$ozet = [];
foreach ([$KOK . '/VERI/suricata_ozet.json', $KOK . '/suricata/logs/ozet.json'] as $y) {
    if (is_file($y)) {
        $j = json_decode((string)file_get_contents($y), true);
        if (is_array($j)) { $ozet = $j; break; }
    }
}
$toplam = $ozet['toplam'] ?? count($ozet['alarmlar'] ?? $ozet['liste'] ?? []);
$kategoriler = $ozet['kategoriler'] ?? $ozet['kategori'] ?? [];
$saldirganlar = $ozet['saldirganlar'] ?? $ozet['iplar'] ?? $ozet['liste'] ?? [];

?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>IPS — CYBER KALKAN</title>
<link rel="stylesheet" href="assets/panel.css?v=1.7">
</head>
<body>
<?php kalkan_ustbilgi('IPS'); ?>
<main class="sarici">

<?php kalkan_baslik('🛡', 'INLINE IPS', 'ağdan geçen paketleri çekirdek seviyesinde inceler, saldırıyı hedefe ulaşmadan DÜŞÜRÜR — NFQUEUE + Suricata'); ?>

  <?php if ($mesaj !== ''): ?>
  <div class="kutu basarili"><pre><?= htmlspecialchars($mesaj) ?></pre></div>
  <?php endif; ?>

  <div class="kartlar">
    <div class="kart">
      <div class="etiket">nftables Kuyruğu</div>
      <div class="deger"><?= $aktif ? 'AKTİF' : 'kapalı' ?></div>
      <div class="alt"><?= (int)$tablo ?> kuyruk</div>
    </div>
    <div class="kart">
      <div class="etiket">Suricata</div>
      <div class="deger"><?= ($sur !== '' && strpos($sur,'Up')!==false) ? 'ÇALIŞIYOR' : 'kapalı' ?></div>
      <div class="alt"><?= htmlspecialchars($sur ?: '-') ?></div>
    </div>
    <div class="kart iyi">
      <div class="etiket">Yüklü Kural</div>
      <div class="deger"><?= $kural > 0 ? number_format($kural, 0, ',', '.') : '-' ?></div>
      <div class="alt"><?= (int)$hata ?> hata · L7 <?= number_format($l7, 0, ',', '.') ?></div>
    </div>
    <div class="kart<?= $aktif ? ' iyi' : ' kritik' ?>">
      <div class="etiket">Mod</div>
      <div class="deger"><?= $aktif ? 'IPS' : 'IDS' ?></div>
      <div class="alt"><?= $aktif ? 'engelleme aktif' : 'izleme' ?> · <?= htmlspecialchars($ids_zam !== '' ? $ids_zam : '-') ?></div>
    </div>
  </div>

  <div class="kutu">
    <h2>Kontrol</h2>
    <form method="post" style="display:flex;gap:12px;flex-wrap:wrap">
      <button class="dugme" name="islem" value="baslat" type="submit">▶ IPS Başlat (onay)</button>
      <button class="dugme kirmizi" name="islem" value="durdur" type="submit">■ IPS Durdur</button>
    </form>
    <p class="not">Başlatma sırasında: SSH + HestiaCP portları muaf, fail-open, beyaz liste korumalı.</p>
  </div>

  <?php if (!empty($kategoriler) && is_array($kategoriler)): ?>
  <div class="kutu">
    <h2>Kategori Dağılımı</h2>
    <table class="tablo">
      <tr><th>Kategori</th><th>Adet</th></tr>
      <?php foreach ($kategoriler as $k => $v): ?>
      <tr><td><?= htmlspecialchars((string)$k) ?></td><td><?= (int)$v ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

  <?php if (!empty($saldirganlar) && is_array($saldirganlar)): ?>
  <div class="kutu">
    <h2>Saldırgan IP'ler</h2>
    <table class="tablo">
      <tr><th>#</th><th>IP</th></tr>
      <?php $i = 0; foreach (array_slice($saldirganlar, 0, 50) as $k => $x): $i++; ?>
      <tr><td><?= $i ?></td><td><?= htmlspecialchars(is_array($x) ? ($x['ip'] ?? json_encode($x, 320)) : (string)$x) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

</main>
<?= kalkan_altbilgi() ?>
</body>
</html>
