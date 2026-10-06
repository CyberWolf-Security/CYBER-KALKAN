<?php
/* 🗄️ INDEKS — log indeksleme durumu */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();

$mesaj = '';
if (($_GET['yenile'] ?? '') === '1') {
    $r = shell_exec('timeout 120 /usr/bin/python3 /opt/siber-kalkan/MOTOR/kalkan_indexer.py 2>&1');
    if (function_exists('kalkan_audit')) kalkan_audit('INDEKS', 'manuel yeniden indeksleme');
    $mesaj = '🔄 Yeniden indeksleme çalıştırıldı — ' . date('H:i:s');
}

$idx  = kalkan_oku('indexer', ['son' => '-', 'toplam' => 0]);
$olay = kalkan_oku('olaylar', ['toplam' => 0, 'olaylar' => []]);

$loglar = [];
foreach (glob('/opt/siber-kalkan/LOG/*.log') as $f) {
    $loglar[] = ['ad' => basename($f), 'boyut' => filesize($f), 'zaman' => date('d.m.Y H:i', filemtime($f)), 'satir' => @count(file($f))];
}
usort($loglar, fn($a, $b) => $b['boyut'] <=> $a['boyut']);
$toplam_log = count($loglar);
$toplam_satir = array_sum(array_column($loglar, 'satir'));

function kb(int $b): string { return $b > 1048576 ? round($b / 1048576, 1) . ' MB' : round($b / 1024, 1) . ' KB'; }
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>İndeks</title>
<link rel="stylesheet" href="assets/panel.css?v=1.7"></head><body>
<?= kalkan_ustbilgi('indexer.php') ?>
<main>
<?php kalkan_baslik('🗄️', 'Log İndeksi', 'Toplanan kayıtlar ve log dosyaları'); ?>
  <h2>🗄️ İndeks Durumu</h2>

  <?php if ($mesaj !== ''): ?>
  <div class="bilgi-serit">✅ <?= htmlspecialchars($mesaj) ?></div>
  <?php endif; ?>

  <div class="idx-kpi">
    <div class="kart">
      <span class="et">📊 Toplam Olay</span>
      <span class="deger"><?= number_format((int)($olay['toplam'] ?? 0), 0, ',', '.') ?></span>
    </div>
    <div class="kart yesil">
      <span class="et">🗂️ İndekslenen Kayıt</span>
      <span class="deger"><?= number_format($toplam_satir, 0, ',', '.') ?></span>
    </div>
    <div class="kart turuncu">
      <span class="et">📂 Log Dosyası</span>
      <span class="deger"><?= $toplam_log ?></span>
    </div>
    <div class="kart mor">
      <span class="et">🕒 Son İndeksleme</span>
      <span class="deger kucuk-yazi"><?= htmlspecialchars((string)($idx['son'] ?? '-')) ?></span>
    </div>
  </div>

  <div class="panel-kutu" style="margin-top:8px">
    <h3>📂 Log Dosyaları</h3>
    <?php if ($loglar): ?>
    <table><thead><tr><th>DOSYA</th><th>BOYUT</th><th>SATIR</th><th>SON DEĞİŞİM</th></tr></thead><tbody>
    <?php foreach (array_slice($loglar, 0, 25) as $l): ?>
      <tr><td class="mono"><?= htmlspecialchars($l['ad']) ?></td><td><?= kb($l['boyut']) ?></td><td><?= number_format($l['satir'], 0, ',', '.') ?></td><td><?= $l['zaman'] ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><p class="soluk">Log dosyası bulunamadı.</p><?php endif; ?>
  </div>

  <div class="bilgi-serit" style="margin-top:26px">
    <b>ℹ️ İndeks nedir?</b> Motor, log dosyalarındaki binlerce satırı tarayıp olayları kayıt altına alır.
    Bu sayfa indekslenen kayıt sayısını ve log sağlığını gösterir. İndeksleme <b>her 5 dakikada bir otomatik</b> çalışır.
  </div>
</main>
<?= kalkan_altbilgi() ?></body></html>
