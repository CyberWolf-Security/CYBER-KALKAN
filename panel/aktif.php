<?php
/* AKTIF SAVUNMA — Tarpit + Honeypot + Abuse (CYBER KALKAN'a ozel modul) */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();
$h = kalkan_oku('honeypot', ['kayitlar' => [], 'toplam' => 0]);
$e = kalkan_oku('engel', ['liste' => []]);
$c = kalkan_oku('cografya', ['ip_ulke' => []]);
$tarpit = @shell_exec("nft list set inet kilic kara 2>/dev/null");
$tarpit_durum = trim(@shell_exec('/usr/bin/sudo -n /opt/siber-kalkan/tarpit.sh 2>/dev/null'));
$tacik = strpos($tarpit_durum, 'ACIK') !== false;
$tarpit_sayi = $tarpit ? (substr_count($tarpit, ',') + 1) : 0;
/* ★ Kaldirildi: $abuse = kalkan_oku('abuse_sayisi') → degisken HIC KULLANILMIYORDU
   (abuse raporu asagida dogrudan abuse_rapor.txt'den okunuyor). */

$tmesaj = '';
if (isset($_GET['tarpit']) || (($_POST['islem'] ?? '') === 'tarpit')) {
    $th = ((($_GET['tarpit'] ?? $_POST['tarpit'] ?? '') === 'ac')) ? 'ac' : 'kapat';
    @shell_exec('/usr/bin/sudo -n /opt/siber-kalkan/tarpit.sh ' . escapeshellarg($th) . ' 2>&1');
    $tmesaj = ($th === 'ac') ? '&#9876;&#65039; TARPIT ACILDI' : '&#128165; TARPIT KAPATILDI';
    kalkan_audit('TARPIT', $th === 'ac' ? 'acildi' : 'kapatildi');
    $tarpit_durum = trim(@shell_exec('/usr/bin/sudo -n /opt/siber-kalkan/tarpit.sh 2>/dev/null'));
    $tacik = strpos($tarpit_durum, 'ACIK') !== false;
}
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Aktif Savunma</title>
<link rel="stylesheet" href="assets/panel.css?v=1.7"></head><body>
<?= kalkan_ustbilgi('aktif.php') ?>
<main>
<?php kalkan_baslik('⚔️', 'Aktif Savunma', 'Kılıç modu ve otomatik müdahale'); ?>
  <h2 class="bolum">⚔️ Aktif Savunma — Saldırganı Bertaraf Et</h2>
  <div class="kartlar">
    <div class="kart"><div class="etiket">⚔️ KILIÇ — ANINDA KESME</div><div class="deger" style="color:#ff3b5c"><?= $tarpit_sayi ?></div><div class="alt">bağlantısı ANINDA kesilen IP</div></div>
    <div class="kart"><div class="etiket">HONEYPOT TUZAĞI</div><div class="deger" style="color:#ff9f43"><?= (int)($h['toplam'] ?? 0) ?></div><div class="alt">yakalanan bağlantı</div></div>
    <div class="kart"><div class="etiket">ABUSE RAPORU</div><div class="deger" style="color:#4da3ff"><?= count($e['liste'] ?? []) ?></div><div class="alt">bildirilecek saldırgan</div></div>
    <?php $pk = kalkan_oku('puskurtme', ['kesilen_ip'=>0,'kesilen_ag'=>0]); ?>
    <div class="kart"><div class="etiket">⚔️ PÜSKÜRTME (KILIÇ)</div><div class="deger" style="color:#ff3b5c"><?= (int)($pk['kesilen_ip']??0) ?></div><div class="alt"><?= (int)($pk['kesilen_ag']??0) ?> alt ağ kesildi · ANINDA</div></div>
    <div class="kart"><div class="etiket">TARPIT SÜRÜNDÜRME</div><div class="deger" style="color:"<?= $tacik ? '#00ffa3' : '#ff6b8a' ?>""><?= $tacik ? 'AÇIK' : 'KAPALI' ?></div><div class="alt">9099 portunda asılı tutma</div></div>
  </div>

  <h3>⚔️ KILIÇ MODU — SERT (reject tcp reset + çıkış drop + hız sınırı 1/sn)</h3>

  <h3>🍯 Honeypot Tuzağı — sahte servisler (2222 SSH · 3336 MySQL · 8081 HTTP · 4443 RDP)</h3>
  <table class="tablo"><thead><tr><th>Zaman</th><th>IP</th><th>Ülke</th><th>Port</th><th>Gelen Veri</th></tr></thead><tbody>
  <?php foreach (array_slice($h['kayitlar'] ?? [], 0, 60) as $k): ?>
    <tr><td class="dim"><?= kalkan_kacis($k['zaman'] ?? '') ?></td>
        <td class="mono"><?= kalkan_kacis($k['ip'] ?? '') ?></td>
        <td><?= kalkan_kacis($c['ip_ulke'][$k['ip'] ?? ''] ?? '?') ?></td>
        <td class="mono"><?= (int)($k['port'] ?? 0) ?></td>
        <td class="dim"><?= kalkan_kacis(substr($k['veri'] ?? '-', 0, 60)) ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($h['kayitlar'])): ?><tr><td colspan="5" class="dim">Henüz tuzak kaydı yok — bekleniyor...</td></tr><?php endif; ?>
  </tbody></table>

  <h3>📣 Abuse Raporu — saldırganın ISP'sine şikayet (yasal karşı hamle)</h3>
  <div class="panel-kutu"><pre style="font-size:15.5px;max-height:260px;overflow:auto"><?= kalkan_kacis(substr(@file_get_contents(KALKAN_VERI . '/abuse_rapor.txt') ?: 'Rapor henüz üretilmedi', 0, 1500)) ?></pre></div>

  <h3>&#9876;&#65039; TARPIT &mdash; Saldırganı Süründür</h3>
  <div class="panel-kutu" style="border-left:4px solid #ff9f43">
    <p class="soluk" style="margin:0 0 14px">Kara listedeki saldirganlar ANINDA kesilmek yerine
      <b>9099 portunda ~8 dakika asili tutulur</b> &mdash; kaynagini tuketir, yorulur.</p>
    <?php if ($tmesaj !== ''): ?><div style="margin:0 0 14px;padding:11px 16px;border-radius:10px;background:rgba(0,214,143,.12);border:1px solid rgba(0,214,143,.5);color:#7dffc0;font-weight:700;font-size:16px"><?= $tmesaj ?></div><?php endif; ?>
    <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
      <span class="etiket">DURUM</span>
      <span style="font-weight:800;font-size:19px;color:<?= $tacik ? '#00ffa3' : '#ff6b8a' ?>">
        <?= $tacik ? '&#9876;&#65039; ACIK' : '&#128165; KAPALI' ?></span>
      <a href="?tarpit=ac" class="dugme" style="background:linear-gradient(135deg,#00ffa3,#00b37a);color:#04121a;font-size:18px;font-weight:800;padding:15px 30px;text-decoration:none;display:inline-block">&#9876;&#65039; TARPIT A&Ccedil;</a>
      <a href="?tarpit=kapat" class="dugme" style="background:linear-gradient(135deg,#ff6b8a,#c2325a);font-size:18px;font-weight:800;padding:15px 30px;text-decoration:none;display:inline-block">&#128165; TARPIT KAPAT</a>
    </div>
  </div>
</main>
<?= kalkan_altbilgi() ?></body></html>
