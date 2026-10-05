<?php
/* AYARLAR */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();
$mesaj = '';
$ayar = kalkan_oku('ayarlar', ['surum'=>'1.0','marka'=>'CYBERWOLF SECURITY','panel_adi'=>'CYBER KALKAN',
                                'esik'=>5,'pencere_sn'=>60,'otomatik_engel'=>true]);

if (isset($_GET['tarpit'])) {
    $th = (($_GET['tarpit'] ?? '') === 'ac') ? 'ac' : 'kapat';
    @shell_exec('/usr/bin/sudo -n /opt/siber-kalkan/tarpit.sh ' . escapeshellarg($th) . ' 2>&1');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && kalkan_csrf_dogrula($_POST['csrf'] ?? null)) {
    $islem = $_POST['islem'] ?? '';
    if ($islem === 'kaydet') {
        /* KALICI KIMLIK — panel adi degistirilemez */
        /* KALICI KIMLIK — marka degistirilemez */
        $ayar['esik']      = max(1, min(100, (int)($_POST['esik'] ?? 5)));
        $ayar['pencere_sn']= max(10, min(3600, (int)($_POST['pencere_sn'] ?? 60)));
        $ayar['smtp_saglayici'] = trim($_POST['smtp_saglayici'] ?? 'gmail');
        $ayar['smtp_host'] = trim($_POST['smtp_host'] ?? $_POST['smtp_host_h'] ?? '');
        $ayar['smtp_port'] = max(1,min(65535,(int)($_POST['smtp_port'] ?? $_POST['smtp_port_h'] ?? 587)));
        $ayar['telegram_token'] = trim($_POST['telegram_token'] ?? '');
        foreach (['smtp_host','smtp_port','smtp_user','smtp_from','eposta_alici'] as $sk)
            $ayar[$sk] = trim((string)($_POST[$sk] ?? ''));
        if (!empty($_POST['smtp_pass'])) $ayar['smtp_pass'] = (string)$_POST['smtp_pass'];
        $ayar['eposta_2fa'] = !empty($_POST['eposta_2fa']);
        $ayar['telegram_chat']  = trim($_POST['telegram_chat'] ?? '');
        $ayar['otomatik_engel'] = !empty($_POST['otomatik_engel']);
        kalkan_yaz('ayarlar', $ayar);
        $mesaj = ['ok','Ayarlar kaydedildi ✓'];
    } elseif ($islem === 'tarpit') {
        /* TARPIT AC/KAPAT — agir savunma */
        $hedef = (($_POST['tarpit'] ?? '') === 'ac') ? 'ac' : 'kapat';
        @shell_exec('/usr/bin/sudo -n /opt/siber-kalkan/tarpit.sh ' . escapeshellarg($hedef) . ' 2>&1');
        $mesaj = ['ok', $hedef === 'ac'
            ? '⚔️ TARPIT AÇILDI — kara listedeki saldırganlar 9099 portunda süründürülecek'
            : '🛑 TARPIT KAPATILDI — normal engelleme (anında kesme)'];
    } elseif ($islem === '2fa_ac' || $islem === '2fa_kapat') {
        $hedef_k = trim((string)($_POST['eposta_alici'] ?? ($ayar['eposta_alici'] ?? '')));
        if ($islem === '2fa_ac' && ($hedef_k === '' || !filter_var($hedef_k, FILTER_VALIDATE_EMAIL))) {
            $ayar['eposta_2fa'] = false;
            $mesaj = ['hata', '2FA a&#231;&#305;lamad&#305;: &#246;nce kodun gidece&#287;i ge&#231;erli bir e-posta adresi girin.'];
        } else {
            $ayar['eposta_2fa'] = ($islem === '2fa_ac');
            if ($hedef_k !== '' && filter_var($hedef_k, FILTER_VALIDATE_EMAIL)) { $ayar['eposta_alici'] = $hedef_k; }
            $mesaj = ['ok', $islem === '2fa_ac' ? '&#128272; 2 ad&#305;ml&#305; giri&#351; A&Ccedil;ILDI' : '&#128275; 2 ad&#305;ml&#305; giri&#351; KAPATILDI'];
        }
        kalkan_yaz('ayarlar', $ayar);
        kalkan_audit('2FA', $islem === '2fa_ac' ? 'acildi' : 'kapatildi');
    } elseif ($islem === 'mailayar') {
        $ma = kalkan_oku('mail_ayar', ['saglayici'=>'kendi','host'=>'mail.cyberwolfsec.com','port'=>587,'from_ad'=>'CYBER KALKAN']);
        $ma['user'] = trim((string)($_POST['ma_user'] ?? ''));
        if (!empty($_POST['ma_pass'])) $ma['pass'] = (string)$_POST['ma_pass'];
        kalkan_yaz('mail_ayar', $ma);
        $mesaj = ['ok','Sistem mail hesab&#305; kaydedildi &#10003;'];
    } elseif ($islem === 'smtptest') {
        $alic2 = trim((string)($_POST['eposta_alici'] ?? ''));
        if ($alic2 === '') {
            $mesaj = ['hata','Alıcı adres boş — önce alıcı e-postayı yazın'];
        } else {
            $g2 = kalkan_mail_gonder($alic2, 'CYBER KALKAN — Test', "Bu bir test mesajıdır.\n\nSMTP ayarınız çalışıyor. ✅");
            $mesaj = $g2 ? ['ok', '✅ Test maili gönderildi → '.$alic2.' (gelen kutunu kontrol et)']
                         : ['hata','❌ Gönderilemedi — SMTP sunucu/kullanıcı/şifreyi kontrol edin'];
        }
    } elseif ($islem === 'sifre') {
        $y = trim((string)($_POST['yeni'] ?? ''));
        if (strlen($y) < 6) $mesaj = ['ha','Şifre en az 6 karakter olmalı.'];
        else {
            $ayar['sifre_hash'] = password_hash($y, PASSWORD_DEFAULT);
            kalkan_yaz('ayarlar', $ayar);
            /* TUTARLILIK: giris kullanicilar.json'daki sha256 ile dogrular → onu da guncelle */
            $kul = kalkan_oku('kullanicilar', ['kullanicilar' => []]);
            $gunc = false;
            foreach ($kul['kullanicilar'] as &$kx) {
                if (($kx['ad'] ?? '') === 'admin') {
                    $kx['sifre_hash'] = hash('sha256', $y);
                    $kx['sifre_guncelleme'] = date('d.m.Y H:i');
                    $gunc = true;
                }
            }
            unset($kx);
            if ($gunc) kalkan_yaz('kullanicilar', $kul);
            /* NOT: session_regenerate_id KALDIRILDI — oturum dussun diye degil,
               kullanici 'sifre degismedi' sanmasin. Oturum aynen devam eder. */
            $mesaj = ['ok','✅ Şifre güncellendi! Yeni şifreniz: <b>hemen geçerli</b>. '
                      . 'Çıkış yapıp yeni şifreyle girebilirsiniz.']; 
        }
    }
}
$kural_dosya = KALKAN_VERI . '/kurallar/kurallar.json';
$kural_var = is_file($kural_dosya);
?>
<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Ayarlar</title>
<link rel="stylesheet" href="assets/panel.css?v=1.7"></head><body>
<?= kalkan_ustbilgi('ayarlar.php') ?>
<main>
<?php kalkan_baslik('⚙️', 'Ayarlar', 'Panel, motor, güvenlik ve e-posta ayarları'); ?>
<h2 class="bolum">⚙️ Panel Ayarlar&#305;</h2>
<?php if ($mesaj): ?><div class="uyari <?= $mesaj[0] ?>"><?= kalkan_kacis($mesaj[1]) ?></div><?php endif; ?>
<div class="ikili">
  <section class="kutu">
    <h3>⚙️ Motor Ayarları</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= kalkan_csrf() ?>">
      <input type="hidden" name="islem" value="kaydet">
      <p class="soluk" style="margin:8px 0 4px">Panel adı <span style="color:#8296b0;font-size:15.5px">(kalıcı)</span></p>
      <input name="panel_adi" value="<?= KALKAN_SABIT_AD ?>" readonly style="width:100%;opacity:.65;cursor:not-allowed" title="Kalıcı — değiştirilemez">
      <p class="soluk" style="margin:8px 0 4px">Marka <span style="color:#8296b0;font-size:15.5px">(kalıcı)</span></p>
      <input name="marka" value="<?= KALKAN_SABIT_MARKA ?>" readonly style="width:100%;opacity:.65;cursor:not-allowed" title="Kalıcı — değiştirilemez">
      <p class="soluk" style="margin:8px 0 4px">Risk eşiği (puan)</p>
      <input name="esik" type="number" min="1" max="100" value="<?= (int)$ayar['esik'] ?>" style="width:100%">
      <p class="soluk" style="margin:8px 0 4px">Zaman penceresi (saniye)</p>
      
    <label>Telegram Bot Token</label>
    <input name="telegram_token" value="<?= kalkan_kacis($ayar['telegram_token'] ?? '') ?>" placeholder="123456:ABC..." style="width:100%">
    <label>Telegram Chat ID</label>
    <input name="telegram_chat" value="<?= kalkan_kacis($ayar['telegram_chat'] ?? '') ?>" placeholder="1492630268" style="width:100%">
    <label>Pencere (saniye)</label>
    <input name="pencere_sn" type="number" min="10" max="3600" value="<?= (int)$ayar['pencere_sn'] ?>" style="width:100%">
      <label class="tik-satir">
        <input type="checkbox" name="otomatik_engel" value="1" <?= !empty($ayar['otomatik_engel'])?'checked':'' ?>>
        <span class="tik-switch" aria-hidden="true"></span>
        <span>
          <span class="tik-baslik">🛡️ Otomatik engelleme</span>
          <span class="tik-aciklama"><b>İşaretli:</b> motor, risk puanı eşiğini aşan IP'yi <b>kendisi</b> firewall'a ekler (iptables/nftables).<br><b>İşaretsiz:</b> yalnızca karar günlüğüne yazar, engellemez — onayı sen verirsin.</span>
        </span>
      </label>
      <button type="submit">💾 Kaydet</button>
    </form>
  </section>
  <section class="kutu">
    <h3>🔐 Panel Şifresi</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= kalkan_csrf() ?>">
      <input type="hidden" name="islem" value="sifre">
      <input name="yeni" type="password" placeholder="Yeni şifre (min 6)" style="width:100%" required>
      <button type="submit" style="margin-top:11px;width:100%">🔑 Güncelle</button>
    </form>
    <h3 style="margin-top:24px">&#128231; E-posta ile Giri&#351; Kodu (2 ad&#305;ml&#305; giri&#351;)</h3>
    <div class="panel-kutu" style="padding:22px 24px;margin-bottom:18px">
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
        <span style="font-size:17px;font-weight:800;color:#cfe3f5">DURUM:</span><?= !empty($ayar['eposta_2fa']) ? '<span class=\'seviye iyi\' style=\'font-size:17px;padding:9px 20px\'>&#10003; A&Ccedil;IK</span>' : '<span class=\'seviye kritik\' style=\'font-size:17px;padding:9px 20px\'>&#10007; KAPALI</span>' ?>
      </div>
      <p class="soluk" style="margin:0 0 10px">&#128233; Kodun gelece&#287;i e-posta adresi</p>
      <input name="eposta_alici" type="email" form="fa_form" value="<?= kalkan_kacis($ayar['eposta_alici'] ?? '') ?>" placeholder="ornek@gmail.com" style="width:100%">
      <p class="soluk" style="margin:12px 0 0"><?php
        /* ★ Sadece GORUNUM: sabit "noreply@localhost" yerine gercek gonderim adresini goster.
           Mail GONDERIM koduna dokunulmadi (ortak.php aynen duruyor). */
        $ma_y = kalkan_oku('mail_ayar', []);
        $gonder_ad = trim((string)($ma_y['from_mail'] ?? ''));
        if ($gonder_ad === '') $gonder_ad = trim((string)($ma_y['user'] ?? ''));
        if ($gonder_ad === '') $gonder_ad = trim((string)($ma_y['smtp_from'] ?? ''));
        if ($gonder_ad === '') $gonder_ad = (string)($ayar['eposta_alici'] ?? '');
      ?>&#9989; G&#246;nderim haz&#305;r: <b><?= $gonder_ad !== '' ? kalkan_kacis($gonder_ad) : 'tan&#305;ms&#305;z' ?></b> &middot; <?= $gonder_ad !== '' ? 'ek ayar gerekmez' : 'SMTP ayar&#305; gerekli' ?></p>
    </div>
    <form method="post" id="fa_form" style="display:flex;gap:12px;flex-wrap:wrap">
      <input type="hidden" name="csrf" value="<?= kalkan_csrf() ?>">
      <button type="submit" name="islem" value="<?= !empty($ayar['eposta_2fa']) ? '2fa_kapat' : '2fa_ac' ?>" class="dugme"
        style="flex:1;min-width:220px;font-size:19px;padding:16px 26px;background:<?= !empty($ayar['eposta_2fa']) ? 'linear-gradient(135deg,#ff6b8a,#c2325a)' : 'linear-gradient(135deg,#00e5a0,#00a877)' ?>">
        <?= !empty($ayar['eposta_2fa']) ? '&#128275; 2FA KAPAT' : '&#128272; 2FA A&Ccedil;' ?>
      </button>
      <button type="submit" name="islem" value="smtptest" formnovalidate class="dugme"
        style="flex:1;min-width:200px;font-size:17px;padding:16px 22px;background:linear-gradient(135deg,#ff9f43,#ff6b6b)">&#128232; Test Maili G&#246;nder</button>
    </form>
<h3 style="margin-top:22px">📋 Sistem Bilgisi</h3>
    <table class="tablo">
      <tr><td class="soluk">Sürüm</td><td class="mono"><?= kalkan_kacis($ayar['surum'] ?? '1.0') ?></td></tr>
      <tr><td class="soluk">Kurallar</td><td class="mono"><?= $kural_var ? 'yüklendi' : 'gömülü (motor içinde)' ?></td></tr>
      <tr><td class="soluk">Veri dizini</td><td class="mono"><?= kalkan_kacis(KALKAN_VERI) ?></td></tr>
    </table>
  </section>
</div>
<?= kalkan_altbilgi() ?>
</main></body></html>
