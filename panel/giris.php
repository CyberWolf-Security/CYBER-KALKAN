<?php
/* GIRIS — sifre + 2FA + audit (RBAC) */
require __DIR__ . '/ortak.php';
$hata = '';
$kod_asamasi = false;
$kod_dogrulandi = false;
$kod_mesaj = '';
$ayar = kalkan_oku('ayarlar', []);
$hash = $ayar['sifre_hash'] ?? '';
// eski kilitleri temizle (2 dk gecmis)
foreach (glob(KALKAN_VERI . '/kilit_*.json') as $kf) {
    $kd = json_decode((string)@file_get_contents($kf), true);
    if (($kd['adet'] ?? 0) >= 10 && (time() - (int)($kd['son'] ?? 0)) > 120) @unlink($kf);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $kilit_yolu = KALKAN_VERI . '/kilit_' . md5($kip) . '.json';
    $k = is_file($kilit_yolu) ? json_decode((string)file_get_contents($kilit_yolu), true) : ['adet' => 0, 'son' => 0];
    if (($k['adet'] ?? 0) >= 10 && (time() - (int)($k['son'] ?? 0)) < 120) {
        $hata = 'Çok fazla deneme — 2 dakika bekleyin (kilit: ' . (10 - (int)($k['adet'] ?? 0)) . ' hakkın kaldı beklemelisin).';
    } else {
        $s = (string)($_POST['sifre'] ?? '');
        $kod = (string)($_POST['totp'] ?? '');
        $kul = kalkan_oku('kullanicilar', ['kullanicilar' => []]);
        $k2 = null;
        /* ★ B-17 DUZELTMESI: yalniz 'admin' degil, girilen kullanici adi denenir.
           (kullanicilar.json'daki 'izleyici' hesabi boylece fiilen calisir) */
        $girilen_ad = preg_replace('/[^A-Za-z0-9_.\-]/', '', (string)($_POST['kullanici'] ?? ''));
        if ($girilen_ad === '') $girilen_ad = 'admin';
        foreach ($kul['kullanicilar'] ?? [] as $x) {
            if (($x['ad'] ?? '') === $girilen_ad) { $k2 = $x; break; }
        }
        if (!$k2) { foreach ($kul['kullanicilar'] ?? [] as $x) { if (($x['ad'] ?? '') === 'admin') { $k2 = $x; break; } } }
        $sir = $k2['totp'] ?? '';
        // ★ B-17: kullaniciya ozel hash (yoksa global ayar hash'i)
        $hash = (string)($k2['sifre_hash'] ?? '') ?: $hash;

        $ok = false;
        /* guvenlik (B-06): SADECE bcrypt/argon hash kabul edilir.
           Tuzsuz sha256 fallback KALDIRILDI — kırılabilir olduğu için
           ("kalkan" gibi kısa şifreler rainbow table ile anında çözülür).
           Eski kurulumlar için: sifre_hash bcrypt'e yükseltilmelidir. */
        if ($hash !== '' && password_verify($s, $hash)) $ok = true;

        /* --- E-POSTA 2FA: sadece SMTP TAM ise aktif olur --- */
        $posta = trim((string)($ayar['eposta_alici'] ?? ''));
        $ma = kalkan_oku('mail_ayar', []);
        $mhost = trim((string)($ma['host'] ?? '')) ?: trim((string)($ayar['smtp_host'] ?? ''));
        $muser = trim((string)($ma['user'] ?? '')) ?: trim((string)($ayar['smtp_user'] ?? ''));
        $mpass = (string)($ma['pass'] ?? '') ?: (string)($ayar['smtp_pass'] ?? '');
        $e2fa = !empty($ayar['eposta_2fa']) && $posta !== ''
             && $mhost !== '' && $muser !== '' && $mpass !== '';
        if ($ok && $e2fa && $posta !== '' && (string)($_POST['adim'] ?? '') !== 'kod') {
            $yeni_kod = kalkan_kod_uret();
            kalkan_kod_kaydet($yeni_kod, $posta);
            $govde = '<div style="font-family:Segoe UI,Arial,sans-serif;background:#0b0f17;color:#dbe4f0;padding:28px;border-radius:12px">'
                   . '<h2 style="color:#00d4ff;margin:0 0 14px">🐺 CYBER KALKAN</h2>'
                   . '<p>Giriş doğrulama kodunuz:</p>'
                   . '<div style="font-size:34px;font-weight:800;letter-spacing:9px;color:#00d4ff;'
                   . 'background:#151d2c;padding:16px;border-radius:10px;text-align:center;margin:16px 0">' . $yeni_kod . '</div>'
                   . '<p style="color:#8296b0;font-size:15.0px">Kod 5 dakika geçerlidir. Bu girişi siz yapmadıysanız şifrenizi değiştirin.</p></div>';
            $gonderildi = kalkan_mail_gonder($posta, 'CYBER KALKAN — Giriş Kodunuz', $govde);
            $kod_asamasi = true;
            $kod_mesaj = $gonderildi ? "Doğrulama kodu <b>" . kalkan_kacis($posta) . "</b> adresine gönderildi."
                                     : "Kod gönderilemedi! SMTP ayarlarını kontrol edin.";
        } elseif ($e2fa && $posta !== '' && (string)($_POST['adim'] ?? '') === 'kod') {
            /* kod dogrulama — sifre tekrar gonderilmez, kod yeterli */
            if (kalkan_kod_dogrula($kod)) {
                $ok = true;
                $kod_dogrulandi = true;
            } else {
                $ok = false;
                $hata = 'Kod hatalı veya süresi doldu.';
                $kod_asamasi = true;
            }
        }

        /* 2FA (e-posta) aktifse TOTP bypass YOK — yalnizca kod dogrulamasi gecerli */
        $totp_ok = (!$e2fa && $ok && kalkan_totp_dogrula($sir, (string)($_POST['totp'] ?? '')));
        if (!empty($kod_dogrulandi) || $totp_ok) {
            @unlink($kilit_yolu);
            // ★ B-17: OTURUM SABITLEME (session fixation) korumasi
            session_regenerate_id(true);
            $_SESSION['kalkan_admin'] = true;
            $_SESSION['sf_kullanici'] = $girilen_ad;
            $_SESSION['sf_rol'] = $k2['rol'] ?? 'ADMIN';
            kalkan_audit('GIRIS', 'basarili');
            header('Location: index.php'); exit;
        } else {
            $k['adet'] = (int)($k['adet'] ?? 0) + 1; $k['son'] = time();
            @file_put_contents($kilit_yolu, json_encode($k), LOCK_EX);
            kalkan_audit('GIRIS_BASARISIZ', $ok ? 'hatali 2FA' : 'hatali sifre');
            $hata = ($ok || !empty($kod_asamasi)) ? 'Hatalı 2FA kodu' : ('Hatalı şifre (' . (int)$k['adet'] . '/10)');
        }
    }
}
?>
<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Giriş — CYBER KALKAN</title>
<link rel="stylesheet" href="assets/panel.css?v=1.6"></head><body>
<div class="giris-yan sol"><svg class="kalkan-svg" viewBox="0 0 100 122" xmlns="http://www.w3.org/2000/svg">
<defs>
<linearGradient id="kg" x1="0" y1="0" x2="0" y2="1">
<stop offset="0" stop-color="#00e5ff" stop-opacity=".45"/>
<stop offset=".55" stop-color="#0090ff" stop-opacity=".18"/>
<stop offset="1" stop-color="#0050ff" stop-opacity=".05"/>
</linearGradient>
<linearGradient id="kc" x1="0" y1="0" x2="1" y2="1">
<stop offset="0" stop-color="#7df9ff"/>
<stop offset="1" stop-color="#00a2ff"/>
</linearGradient>
</defs>
<path d="M50 5 L92 22 v42 c0 31-18 48-42 54 -24-6-42-23-42-54 V22 Z" fill="url(#kg)" stroke="url(#kc)" stroke-width="2.6"/>
<path d="M50 17 L81 30 v32 c0 23-14 36-31 41 -17-5-31-18-31-41 V30 Z" fill="none" stroke="#22d3ee" stroke-width="1.2" opacity=".65"/>
<circle cx="50" cy="58" r="26" fill="rgba(0,212,255,.06)" stroke="rgba(0,212,255,.25)" stroke-width="1"/>
<path d="M37 58 l10 10 18-21" fill="none" stroke="#7df9ff" stroke-width="4.2" stroke-linecap="round" stroke-linejoin="round"/>
</svg><div class="dikey">CYBER</div></div>
<div class="giris-yan sag"><svg class="kalkan-svg" viewBox="0 0 100 122" xmlns="http://www.w3.org/2000/svg">
<defs>
<linearGradient id="kg" x1="0" y1="0" x2="0" y2="1">
<stop offset="0" stop-color="#00e5ff" stop-opacity=".45"/>
<stop offset=".55" stop-color="#0090ff" stop-opacity=".18"/>
<stop offset="1" stop-color="#0050ff" stop-opacity=".05"/>
</linearGradient>
<linearGradient id="kc" x1="0" y1="0" x2="1" y2="1">
<stop offset="0" stop-color="#7df9ff"/>
<stop offset="1" stop-color="#00a2ff"/>
</linearGradient>
</defs>
<path d="M50 5 L92 22 v42 c0 31-18 48-42 54 -24-6-42-23-42-54 V22 Z" fill="url(#kg)" stroke="url(#kc)" stroke-width="2.6"/>
<path d="M50 17 L81 30 v32 c0 23-14 36-31 41 -17-5-31-18-31-41 V30 Z" fill="none" stroke="#22d3ee" stroke-width="1.2" opacity=".65"/>
<circle cx="50" cy="58" r="26" fill="rgba(0,212,255,.06)" stroke="rgba(0,212,255,.25)" stroke-width="1"/>
<path d="M37 58 l10 10 18-21" fill="none" stroke="#7df9ff" stroke-width="4.2" stroke-linecap="round" stroke-linejoin="round"/>
</svg><div class="dikey">SECURITY</div></div>
<div class="giris">
  <?= dil_secici('dil-kose') ?>
  <div class="giris-kurt">🐺</div>
  <div class="giris-kutu">
    <div class="giris-ad"><?= kalkan_kacis($ayar['panel_adi'] ?? 'CYBER KALKAN') ?></div>
    <div class="giris-marka"><?= kalkan_kacis($ayar['marka'] ?? 'CYBERWOLF SECURITY') ?></div>
    <p class="giris-alt"><?= d('panel_alt') ?></p>
  <?php if ($hata): ?><div class="uyari ha" style="max-width:410px;width:100%"><?= kalkan_kacis($hata) ?></div><?php endif; ?>
  <?php if (!empty($kod_asamasi)): ?>
  <div class="uyari iyi" style="max-width:410px;width:100%">📧 <?= $kod_mesaj ?? '' ?></div>
  <form method="post">
    <input type="hidden" name="adim" value="kod">
    <label><?= d('dogrulama_kodu') ?></label>
    <input name="totp" placeholder="<?= d('kod_yer') ?>" inputmode="numeric" maxlength="6" required autofocus
           style="text-align:center;font-size:26px;letter-spacing:10px;font-weight:700">
    <button type="submit" class="dugme"><?= d('dogrula_giris') ?></button>
  </form>
  <p class="dipnot"><?= d('kod_gelmedi') ?> <a href="giris.php"><?= d('bastan_dene') ?></a> · <?= d('kod_sure') ?></p>
  <?php else: ?>
  <form method="post">
    <label>Kullanıcı</label>
    <input type="text" name="kullanici" value="admin" autocomplete="username" spellcheck="false">
    <label><?= d('sifre') ?></label>
    <input type="password" name="sifre" placeholder="<?= d('sifre_yer') ?>" required autofocus>
    <button type="submit" class="dugme"><?= d('giris_yap') ?></button>
  </form>
  <p class="giris-yasak-alt">&#128274; <?= d('yetkisiz') ?></p>
  <?php endif; ?>
  </div>
</div>
</body></html>
