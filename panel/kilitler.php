<?php
declare(strict_types=1);
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();
if (($_SESSION['sf_rol'] ?? '') !== 'ADMIN') { http_response_code(403); exit('Yetki yok'); }
$mesaj = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ac'])) {
    $n = 0;
    foreach (glob(KALKAN_VERI . '/kilit_*.json') as $f) { if (@unlink($f)) $n++; }
    $mesaj = "$n kilit açıldı";
}
$kilitler = [];
foreach (glob(KALKAN_VERI . '/kilit_*.json') as $f) {
    $d = json_decode((string)@file_get_contents($f), true);
    $kilitler[] = ['dosya' => basename($f), 'adet' => $d['adet'] ?? 0,
                   'son' => date('H:i:s', (int)($d['son'] ?? 0))];
}
?><!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kilitler · CYBER KALKAN</title><link rel="stylesheet" href="assets/panel.css?v=1.7"></head><body>
<?= kalkan_ustbilgi('kilitler.php') ?>
<main>
<?php kalkan_baslik('🔒', 'Giriş Kilitleri', 'Kilitli hesapları yönet'); ?>
<?php if ($mesaj): ?><div class="uyari iyi"><?= htmlspecialchars($mesaj) ?></div><?php endif; ?>
<section class="panel-kutu">
<form method="post"><button name="ac" value="1" class="dugme">🔓 TÜM KİLİTLERİ AÇ</button></form>
<table class="tablo"><thead><tr><th>IP (hash)</th><th>DENEME</th><th>SON</th></tr></thead><tbody>
<?php if (empty($kilitler)): ?><tr><td colspan="3" style="text-align:center">Kilit yok ✓</td></tr>
<?php else: foreach ($kilitler as $k): ?>
<tr><td class="mono"><?= htmlspecialchars(substr($k['dosya'], 6, 16)) ?>...</td>
<td><?= $k['adet'] ?>/10</td><td><?= $k['son'] ?></td></tr>
<?php endforeach; endif; ?></tbody></table>
</section></main>
<?= kalkan_altbilgi() ?></body></html>
