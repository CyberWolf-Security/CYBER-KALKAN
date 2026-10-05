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

/* durum */
$tablo = trim((string)shell_exec("nft list table inet kalkan_ips 2>/dev/null | grep -c queue"));
$sur   = trim((string)shell_exec("docker ps --filter name=kalkan-suricata --format '{{.Status}}' 2>/dev/null"));

/* alarm ozeti */
$ozet = [];
foreach ([$KOK . '/veri/suricata_ozet.json', $KOK . '/suricata/logs/ozet.json'] as $y) {
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
<link rel="stylesheet" href="stil.css">
</head>
<body>
<?php kalkan_ustbilgi('IPS'); ?>
<main class="sarici">

  <h1 class="baslik">🛡 INLINE IPS</h1>

  <?php if ($mesaj !== ''): ?>
  <div class="kutu basarili"><pre><?= htmlspecialchars($mesaj) ?></pre></div>
  <?php endif; ?>

  <div class="kartlar">
    <div class="kart">
      <div class="etiket">nftables Kuyruğu</div>
      <div class="deger"><?= ((int)$tablo > 0) ? 'AKTİF' : 'kapalı' ?></div>
      <div class="alt"><?= (int)$tablo ?> kural</div>
    </div>
    <div class="kart">
      <div class="etiket">Suricata</div>
      <div class="deger"><?= $sur !== '' ? 'ÇALIŞIYOR' : 'kapalı' ?></div>
      <div class="alt"><?= htmlspecialchars($sur ?: '-') ?></div>
    </div>
    <div class="kart">
      <div class="etiket">Alarm</div>
      <div class="deger"><?= (int)$toplam ?></div>
      <div class="alt">tespit edilen olay</div>
    </div>
    <div class="kart">
      <div class="etiket">Saldırgan IP</div>
      <div class="deger"><?= is_array($saldirganlar) ? count($saldirganlar) : 0 ?></div>
      <div class="alt">kaynak adres</div>
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
<?php kalkan_altbilgi(); ?>
</body>
</html>
