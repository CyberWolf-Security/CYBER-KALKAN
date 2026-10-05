<?php
/* 🐺 DEVELOPERS — CYBERWOLF SECURITY */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();

$ayar = kalkan_oku('ayarlar', ['panel_adi' => 'CYBER KALKAN']);

// istatistik topla
$kur = kalkan_oku('kurallar', ['kurallar' => []]);
$olay = kalkan_oku('olaylar', ['toplam' => 0]);
$engel = kalkan_oku('engel', ['toplam' => 0]);
$modul = count(glob('/opt/siber-kalkan/MOTOR/*.py'));
$sayfa = count(glob(__DIR__ . '/*.php'));
$kural = count($kur['kurallar'] ?? []);
$db_boy = @filesize('/opt/siber-kalkan/VERI/kalkan.db') ?: 0;

function b($n) { return number_format((float)$n, 0, ',', '.'); }

/* ---- İLETİŞİM FORMU GÖNDERİMİ (Brevo/mail) ---- */
$il_mesaj = ''; $il_tip = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['islem'] ?? '') === 'iletisim') {
    $ad   = trim((string)($_POST['ad'] ?? ''));
    $posta= trim((string)($_POST['eposta'] ?? ''));
    $konu = trim((string)($_POST['konu'] ?? ''));
    $govde= trim((string)($_POST['mesaj'] ?? ''));
    if ($ad === '' || $govde === '' || !filter_var($posta, FILTER_VALIDATE_EMAIL)) {
        $il_mesaj = d('il_hata'); $il_tip = 'ha';
    } else {
        $alici = trim((string)($ayar['eposta_alici'] ?? 'ekselanss@gmail.com'));
        $govde_html = '<div style="font-family:Segoe UI,Arial,sans-serif;background:#0b0f17;color:#dbe4f0;padding:26px;border-radius:12px">'
            . '<h2 style="color:#00d4ff;margin:0 0 16px">🐺 CYBER KALKAN — İletişim Formu</h2>'
            . '<table style="width:100%;font-size:15px;border-collapse:collapse">'
            . '<tr><td style="padding:7px 0;color:#8296b0">Ad Soyad</td><td style="padding:7px 0;color:#dff6ff"><b>' . kalkan_kacis($ad) . '</b></td></tr>'
            . '<tr><td style="padding:7px 0;color:#8296b0">E-posta</td><td style="padding:7px 0;color:#dff6ff"><b>' . kalkan_kacis($posta) . '</b></td></tr>'
            . '<tr><td style="padding:7px 0;color:#8296b0">Konu</td><td style="padding:7px 0;color:#dff6ff">' . kalkan_kacis($konu !== '' ? $konu : '—') . '</td></tr>'
            . '</table>'
            . '<div style="margin-top:16px;padding:14px;background:#151d2c;border-radius:10px;color:#dbe4f0;font-size:15px;line-height:1.6;white-space:pre-wrap">' . kalkan_kacis($govde) . '</div>'
            . '<p style="color:#8296b0;font-size:14px;margin-top:14px">Gönderen IP: ' . kalkan_kacis($_SERVER['REMOTE_ADDR'] ?? '-') . ' · ' . date('d.m.Y H:i') . '</p></div>';
        $gitti = kalkan_mail_gonder($alici, 'CYBER KALKAN — İletişim: ' . ($konu !== '' ? $konu : $ad), $govde_html);
        if ($gitti) { $il_mesaj = d('il_ok'); $il_tip = 'iyi'; }
        else { $il_mesaj = d('il_gonderilemedi'); $il_tip = 'ha'; }
    }
}
?>
<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Geliştirenler · CYBER KALKAN</title>
<link rel="stylesheet" href="assets/panel.css?v=1.7">
</head><body>
<?= kalkan_ustbilgi('gelistirenler.php') ?>
<main>
<?php kalkan_baslik('🐺', 'Geliştirenler', 'Bu sistemi yapan, kuran ve işleten ekip'); ?>

<div class="kart" style="text-align:center;padding:34px 22px">
  <div style="font-size:52px;line-height:1;margin-bottom:10px">🐺</div>
  <div style="font-size:30px;font-weight:900;color:#00e5ff;letter-spacing:3px;text-shadow:0 0 22px rgba(0,229,255,.55)">CYBERWOLF SECURITY</div>
  <div style="font-size:16px;color:#8bd;letter-spacing:3px;margin-top:8px"></div>
  <div style="margin-top:18px;font-size:16px;color:#abd;line-height:1.8">
    Siber güvenlik altyapıları tasarımı · tehdit izleme ve müdahale sistemleri<br>
    Merkezi güvenlik yönetimi ve çok cihazlı koruma çözümleri
  </div>
</div>

<div class="kart">
  <h3>🛡️ Sistemimiz — CYBER KALKAN</h3>
  <table class="tablo">
    <tr><th>ALAN</th><th>DEĞER</th></tr>
    <tr><td>Sistem adı</td><td><b style="color:#0ef">CYBER KALKAN v<?= KALKAN_SURUM ?></b></td></tr>
    <tr><td>Amaç</td><td>Siber güvenlik altyapısı ve tehdit savunma merkezi</td></tr>
    <tr><td>Mimari</td><td>Merkezi motor + uzak ajan (N cihaz, sınırsız ölçek)</td></tr>
    <tr><td>Kural motoru</td><td><?= b($kural) ?> kural · <?= $modul ?> modül</td></tr>
    <tr><td>Panel</td><td><?= $sayfa ?> sayfa · JSON + SQLite veri katmanı</td></tr>
    <tr><td>Veri tabanı</td><td>kalkan.db · <?= b(round($db_boy / 1024)) ?> KB (WAL modu)</td></tr>
    <tr><td>İşlenen olay</td><td><?= b($olay['toplam'] ?? 0) ?> kayıt · <?= b($engel['toplam'] ?? 0) ?> engellenen IP</td></tr>
    <tr><td>Geliştirici</td><td><b style="color:#0ef">CYBERWOLF SECURITY</b></td></tr>
    <tr><td>Yıl</td><td><?= date('Y') ?></td></tr>
  </table>
</div>

<div class="kart">
  <h3>⚙️ Teknoloji Katmanları</h3>
  <table class="tablo">
    <tr><th>KATMAN</th><th>TEKNOLOJİ</th></tr>
    <tr><td>Kural motoru</td><td>Python 3 (önceden derlenmiş regex, 5 sn döngü)</td></tr>
    <tr><td>Panel</td><td>PHP 8.4 · koyu neon tema · 1366×768 optimize</td></tr>
    <tr><td>Veri</td><td>JSON depoları + SQLite (WAL) · ajan/olay/görev tabloları</td></tr>
    <tr><td>Uzak ajan</td><td>Bash + Python · pull modeli (NAT dostu) · systemd servis</td></tr>
    <tr><td>Engelleme</td><td>nftables / iptables · tarpit · kara liste</td></tr>
    <tr><td>Log toplama</td><td>rsyslog 514 (UDP/TCP) · cihaz başına ayrı dosya</td></tr>
    <tr><td>Görev sistemi</td><td>15 güvenli denetim görevi (AV, rootkit, zafiyet, FIM, port...)</td></tr>
    <tr><td>Kod koruması</td><td>SHA-256 bütünlük kontrolü · otomatik hash doğrulama</td></tr>
  </table>
</div>

<div class="kart">
  <table class="tablo">
    <tr><th>SÜRÜM</th><th>ÖNE ÇIKAN</th></tr>
    <tr><td>v<?= KALKAN_SURUM ?></td><td>Geliştirenler sayfası · geliştirici kimliği</td></tr>
  </table>
</div>

<div class="kart">
  <h3 style="text-align:center">✉️ <?= d('iletisim') ?></h3>
  <div class="iletisim-kutu">
    <div class="iletisim-bilgi"><span class="ib-et"><?= d('gelistirici') ?></span><span class="ib-de"><b>CYBERWOLF SECURITY</b></span></div>
    <div class="iletisim-bilgi"><span class="ib-et"><?= d('eposta') ?></span><span class="ib-de"><b><?= kalkan_kacis($ayar['eposta_alici'] ?? 'ekselanss@gmail.com') ?></b></span></div>
    <div class="iletisim-bilgi"><span class="ib-et"><?= d('sistem') ?></span><span class="ib-de">CYBER KALKAN v<?= KALKAN_SURUM ?></span></div>
  </div>
  <p class="iletisim-alt"><?= d('il_aciklama') ?></p>
  <?php if ($il_mesaj !== ''): ?>
    <div class="uyari <?= $il_tip ?>" style="max-width:640px;margin:0 auto 14px"><?= $il_mesaj ?></div>
  <?php endif; ?>
  <form method="post" class="iletisim-form">
    <input type="hidden" name="islem" value="iletisim">
    <div class="iletisim-satir">
      <div><label><?= d('il_ad') ?></label><input name="ad" required maxlength="80"></div>
      <div><label><?= d('il_posta') ?></label><input type="email" name="eposta" required maxlength="120"></div>
    </div>
    <label><?= d('il_konu') ?></label><input name="konu" maxlength="120">
    <label><?= d('il_mesaj') ?></label><textarea name="mesaj" required maxlength="2000"></textarea>
    <button type="submit" class="dugme">📨 <?= d('il_gonder') ?></button>
  </form>
</div>

<div class="kart" style="text-align:center">
  <div style="font-size:16px;color:#8bd;letter-spacing:1.5px">
    © <?= date('Y') ?> <b style="color:#00e5ff">CYBERWOLF SECURITY</b> · Tüm hakları saklıdır
  </div>
  <div style="font-size:15px;color:#7aa8c8;margin-top:8px">
    CYBER KALKAN — SİBER GÜVENLİK ALTYAPISI
  </div>
</div>

</main>
<?= kalkan_altbilgi() ?>
</body></html>