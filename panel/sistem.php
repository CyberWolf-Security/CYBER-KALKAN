<?php
/* SISTEM — Compliance + Yedek + UEBA + Honeyfile + Syslog */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();
$c = kalkan_oku('compliance', ['skor' => 0, 'kontroller' => []]);
$y = kalkan_oku('yedek', []);
$u = kalkan_oku('ueba', ['anormal' => [], 'toplam' => 0]);
$h = kalkan_oku('honeyfile', ['dosyalar' => [], 'acilmalar' => []]);
/* ★ DUZELTME: anahtar uyusmazligi — moduller su alanlari yazar:
   honeyfile.json → 'acik_dosyalar' / 'toplam_ihlal' (panel 'acilmalar' ariyordu)
   ueba.json      → 'anormal_ip' (panel 'toplam' ariyordu) */
$h_ihlal = $h['acik_dosyalar'] ?? $h['acilmalar'] ?? $h['son_ihlaller'] ?? [];
$h_toplam = isset($h['toplam_ihlal']) ? (int)$h['toplam_ihlal'] : count($h_ihlal);
$u_toplam = isset($u['anormal_ip']) ? (int)$u['anormal_ip']
          : (isset($u['adet']) ? (int)$u['adet'] : (int)($u['toplam'] ?? 0));
$ayar = kalkan_oku('ayarlar', []);
$b = kalkan_oku('bulut', ['toplam'=>0,'aws_cli'=>false,'gcp_cli'=>false]);
$ev = kalkan_oku('evtx', ['dosya_sayisi'=>0,'toplam'=>0]);
$syslog = trim(@shell_exec("ss -tlnp 2>/dev/null | grep -c ':514'"));
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Sistem Sağlığı</title>
<link rel="stylesheet" href="assets/panel.css?v=1.7"></head><body>
<?= kalkan_ustbilgi('sistem.php') ?>
<main>
<?php kalkan_baslik('🎯', 'Sistem Durumu', 'Servisler, kaynaklar ve sağlık'); ?>
  <h2 class="bolum">🎯 Sistem Sağlığı ve Uyumluluk</h2>

<?php
/* YEDEK LISTESI + GERI YUKLEME */
$yedekler = array_reverse(array_slice(glob('/opt/siber-kalkan/YEDEK/*.tar.gz') ?: [], -10));
$restore_mesaj = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && kalkan_csrf_dogrula($_POST['csrf'] ?? null) && ($_POST['islem'] ?? '') === 'restore') {
    $sec = basename($_POST['dosya'] ?? '');
    $yol = '/opt/siber-kalkan/YEDEK/' . $sec;
    if (preg_match('/^kalkan_yedek_[0-9_]+\\.tar\\.gz$/', $sec) && is_file($yol)) {
        @shell_exec('cd /opt/siber-kalkan && tar xzf ' . escapeshellarg($yol) . ' 2>&1');
        kalkan_audit('YEDEK_RESTORE', $sec);
        $restore_mesaj = '✓ Geri yüklendi: ' . $sec;
    } else { $restore_mesaj = '✗ Geçersiz dosya'; }
}
?>

  <div class="kartlar">
    <div class="kart"><div class="etiket">UYUMLULUK (ISO/PCI)</div><div class="deger" style="color:<?= ($c['skor'] ?? 0) >= 80 ? '#00d68f' : '#ff9f43' ?>">%<?= (int)($c['skor'] ?? 0) ?></div><div class="alt"><?= (int)($c['gecen'] ?? 0) ?>/<?= (int)($c['toplam'] ?? 0) ?> kontrol</div></div>
    <div class="kart"><div class="etiket">UEBA ANORMAL</div><div class="deger" style="color:#ff9f43"><?= $u_toplam ?></div><div class="alt">şüpheli davranış</div></div>
    <div class="kart"><div class="etiket">HONEYFILE</div><div class="deger" style="color:#4da3ff"><?= count($h['dosyalar'] ?? []) ?></div><div class="alt"><?= $h_toplam ?> ihlal</div></div>
    <div class="kart"><div class="etiket">YEDEK</div><div class="deger" style="color:#00d68f"><?= (int)($y['boyut_kb'] ?? 0) ?> KB</div><div class="alt"><?= kalkan_kacis($y['tarih'] ?? '-') ?></div></div>
    <div class="kart"><div class="etiket">BULUT (AWS/GCP)</div><div class="deger" style="color:#a855f7"><?= (int)($b['toplam']??0) ?></div><div class="alt">AWS:<?= !empty($b['aws_cli'])?'✓':'✗' ?> GCP:<?= !empty($b['gcp_cli'])?'✓':'✗' ?></div></div>
    <div class="kart"><div class="etiket">EVTX (WINDOWS)</div><div class="deger" style="color:#4da3ff"><?= (int)($ev['dosya_sayisi']??0) ?></div><div class="alt"><?= (int)($ev['toplam']??0) ?> kayıt</div></div>
    <div class="kart"><div class="etiket">SYSLOG (514)</div><div class="deger" style="color:<?= $syslog ? '#00d68f' : '#ff3b5c' ?>"><?= $syslog ? 'AÇIK' : 'KAPALI' ?></div><div class="alt">dış log kabulü</div></div>
  </div>

  <h3>📋 Uyumluluk Kontrolleri (ISO 27001 / PCI-DSS)</h3>
  <table class="tablo"><thead><tr><th>Kontrol</th><th>Durum</th></tr></thead><tbody>
  <?php foreach ($c['kontroller'] ?? [] as $k): ?>
    <tr><td><?= kalkan_kacis($k['ad']) ?></td>
        <td><span class="rozet" style="background:<?= $k['ok'] ? '#00d68f22;color:#00d68f' : '#ff3b5c22;color:#ff6b87' ?>"><?= $k['ok'] ? '✓ GEÇTİ' : '✗ EKSİK' ?></span></td></tr>
  <?php endforeach; ?>
  </tbody></table>

  <h3>🧠 UEBA — Anormal Davranış (IP bazlı)</h3>
  <table class="tablo"><thead><tr><th>IP</th><th>Risk Skoru</th><th>Olay</th><th>Kural Çeşidi</th><th>Gece Aktivite</th></tr></thead><tbody>
  <?php foreach (array_slice($u['anormal'] ?? [], 0, 25) as $a): ?>
    <tr><td class="mono"><?= kalkan_kacis($a['ip']) ?></td>
        <td><span class="rozet" style="background:#ff3b5c22;color:#ff6b87"><?= (int)$a['skor'] ?></span></td>
        <td><?= (int)$a['olay'] ?></td><td><?= (int)$a['kural_cesidi'] ?></td><td><?= (int)$a['gece_aktivite'] ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($u['anormal'])): ?><tr><td colspan="5" class="dim">Anormal davranış yok ✓</td></tr><?php endif; ?>
  </tbody></table>

  <h3>🍯 Honeyfile — Tuzak Dosyalar</h3>
  <table class="tablo"><thead><tr><th>Tuzak Dosya</th><th>Durum</th></tr></thead><tbody>
  <?php foreach ($h['dosyalar'] ?? [] as $f): ?>
    <tr><td class="mono" style="font-size:15.5px"><?= kalkan_kacis($f) ?></td>
        <td><span class="rozet" style="background:#4da3ff22;color:#4da3ff">İZLENİYOR</span></td></tr>
  <?php endforeach; ?>
  </tbody></table>

  <h3>💾 Yedekler <span class="dim">(son 10 · geri yüklenebilir)</span></h3>
  <?php if (!empty($restore_mesaj)): ?><div class="uyari-ok"><?= kalkan_kacis($restore_mesaj) ?></div><?php endif; ?>
  <table class="tablo"><thead><tr><th>Dosya</th><th>Boyut</th><th>Tarih</th><th>İşlem</th></tr></thead><tbody>
  <?php foreach ($yedekler as $y): $b = basename($y); ?>
    <tr><td class="mono" style="font-size:15.5px"><?= kalkan_kacis($b) ?></td>
        <td><?= round(filesize($y)/1024) ?> KB</td>
        <td class="dim"><?= date('d.m.Y H:i', filemtime($y)) ?></td>
        <td class="mono"><?php if (($_SESSION['sf_rol'] ?? 'ADMIN') === 'ADMIN'): ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Geri yüklensin mi?')">
            <input type="hidden" name="csrf" value="<?= kalkan_csrf() ?>">
            <input type="hidden" name="islem" value="restore">
            <input type="hidden" name="dosya" value="<?= kalkan_kacis($b) ?>">
            <button type="submit" style="padding:2px 8px;font-size:15.5px">Geri Yükle</button>
          </form><?php else: ?>—<?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($yedekler)): ?><tr><td colspan="4" class="dim">Yedek yok</td></tr><?php endif; ?>
  </tbody></table>
</main>
<?= kalkan_altbilgi() ?></body></html>
