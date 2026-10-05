<?php
/* 🔌 ENTEGRASYON — VirusTotal · MISP · Slack (panel sayfasi)
   ★ DUZELTME: onceden API kodu iceriyordu → menude HTML yerine JSON dokuyordu.
   API tarafi: entegrasyon_api.php (AJAX ile cagrilir) */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();

$mesaj = null;   // [tip, metin]

/* ── AYAR KAYDET ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['islem'] ?? '') === 'kaydet') {
    if (!kalkan_csrf_dogrula($_POST['csrf'] ?? '')) {
        $mesaj = ['kotu', 'Oturum doğrulaması başarısız (CSRF).'];
    } else {
        $ay = kalkan_oku('ayarlar', []);
        foreach (['vt_api_key', 'misp_url', 'misp_key', 'slack_webhook'] as $k) {
            if (isset($_POST[$k])) $ay[$k] = trim((string)$_POST[$k]);
        }
        kalkan_yaz('ayarlar', $ay);
        kalkan_audit('ENTEGRASYON_AYAR', 'guncellendi');
        $mesaj = ['iyi', 'Entegrasyon ayarları kaydedildi.'];
    }
}

/* ── VIRUSTOTAL TEST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['islem'] ?? '') === 'vt') {
    $hedef = trim((string)($_POST['hedef'] ?? ''));
    if ($hedef === '' || !filter_var($hedef, FILTER_VALIDATE_IP)) {
        $mesaj = ['kotu', 'Geçerli bir IP adresi girin.'];
    } else {
        $u = 'http://127.0.0.1:' . (int)($_SERVER['SERVER_PORT'] ?? 8890)
           . '/entegrasyon_api.php?islem=vt_sorgu&ip=' . urlencode($hedef);
        $r = @file_get_contents($u);
        $d = json_decode((string)$r, true) ?: [];
        if (($d['durum'] ?? '') === 'ok') {
            $mesaj = ['iyi', sprintf('VirusTotal %s → kötü: %d · şüpheli: %d',
                     $hedef, (int)($d['kotu'] ?? 0), (int)($d['supheli'] ?? 0))];
        } else {
            $mesaj = ['kotu', 'Sorgu başarısız: ' . (string)($d['mesaj'] ?? $d['kod'] ?? 'bilinmeyen')];
        }
    }
}

$ay = kalkan_oku('ayarlar', []);
$durum = [
    'VirusTotal' => !empty($ay['vt_api_key']),
    'MISP'       => !empty($ay['misp_url']),
    'Slack'      => !empty($ay['slack_webhook']),
];
$aktif = count(array_filter($durum));
?>
<!DOCTYPE html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Entegrasyon — CYBER KALKAN</title>
<link rel="stylesheet" href="assets/panel.css?v=1.7">
<link rel="stylesheet" href="assets/hero.css?v=2">
</head>
<body>
<?= kalkan_menu('entegrasyon.php') ?>
<main class="sarici">
    <div class="cy-hero">
      <div class="cy-ikon">🔌</div>
      <div class="cy-metin">
        <h1 class="cy-baslik">Entegrasyon <span class="cy-surum">VT · MISP · Slack</span></h1>
        <p class="cy-aciklama">Dış tehdit istihbaratı ve bildirim servisleriyle <b>bağlantı</b> yönetimi. Anahtar girilince entegrasyon <b>aktif</b> sayılır.</p>
        <div class="cy-etiketler">
          <span class="cy-etiket">🦠 VirusTotal</span>
          <span class="cy-etiket">🧠 MISP</span>
          <span class="cy-etiket">💬 Slack</span>
        </div>
      </div>
      <div class="cy-tarama"></div>
    </div>

    <!-- ★ CANLI BANT (diger modullerle ortak) -->
    <div class="canli-serit" style="margin-bottom:18px"><span class="nokta aktif"></span><b>CANLI</b><span class="cs-ayrac">·</span><span>Bağlı servis: <b><?= $aktif ?>/3</b></span><span class="cs-ayrac">·</span><span>Güncelleme: <b><?= date('H:i:s') ?></b></span></div>

    <?php if ($mesaj): ?>
    <div class="uyari uyari-<?= $mesaj[0] ?>" style="font-size:16px">
        <?= htmlspecialchars($mesaj[1]) ?>
    </div>
    <?php endif; ?>

    <div class="kartlar">
        <div class="kart <?= $aktif ? 'iyi' : '' ?>">
          <div class="etiket">🔗 AKTİF BAĞLANTI</div>
          <div class="deger"><?= $aktif ?><span style="font-size:19px;opacity:.5"> / 3</span></div>
          <div class="alt">bağlı servis</div>
        </div>
        <?php foreach ($durum as $ad => $ok): ?>
        <div class="kart <?= $ok ? 'iyi' : '' ?>">
          <div class="etiket"><?= kalkan_kacis(strtoupper($ad)) ?></div>
          <div class="deger" style="font-size:24px"><?= $ok ? 'AÇIK' : 'KAPALI' ?></div>
          <div class="alt"><?= $ok ? 'bağlı' : 'anahtar yok' ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <h2>Bağlantı Ayarları</h2>
    <form method="post" class="form-satir" style="flex-wrap:wrap">
        <input type="hidden" name="csrf" value="<?= kalkan_csrf_uret() ?>">
        <input type="hidden" name="islem" value="kaydet">
        <input name="vt_api_key" value="<?= kalkan_kacis((string)($ay['vt_api_key'] ?? '')) ?>"
               placeholder="VirusTotal API anahtarı" spellcheck="false" style="font-size:16px;min-width:260px">
        <input name="misp_url" value="<?= kalkan_kacis((string)($ay['misp_url'] ?? '')) ?>"
               placeholder="MISP URL (https://misp.local)" spellcheck="false" style="font-size:16px;min-width:220px">
        <input name="misp_key" value="<?= kalkan_kacis((string)($ay['misp_key'] ?? '')) ?>"
               placeholder="MISP API anahtarı" spellcheck="false" style="font-size:16px;min-width:200px">
        <input name="slack_webhook" value="<?= kalkan_kacis((string)($ay['slack_webhook'] ?? '')) ?>"
               placeholder="Slack Webhook URL" spellcheck="false" style="font-size:16px;min-width:240px">
        <button type="submit">💾 Kaydet</button>
    </form>

    <h2>VirusTotal Sorgusu</h2>
    <form method="post" class="form-satir">
        <input type="hidden" name="csrf" value="<?= kalkan_csrf_uret() ?>">
        <input type="hidden" name="islem" value="vt">
        <input name="hedef" placeholder="IP adresi (ör. 8.8.8.8)" spellcheck="false" style="font-size:16px">
        <button type="submit">🔎 Sorgula</button>
    </form>
    <p class="dipnot">Sorgu <code>entegrasyon_api.php</code> üzerinden yapılır — VirusTotal API anahtarı gerekir.</p>

</main>
<?= kalkan_altbilgi() ?>
</body></html>
