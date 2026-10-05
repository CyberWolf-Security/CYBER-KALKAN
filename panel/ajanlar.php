<?php
/* 💻 AJAN YÖNETİMİ (SQLite) — uzak cihazlar + komut gönderimi */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();

$ayar = kalkan_oku('ayarlar', ['panel_adi' => 'CYBER KALKAN']);
$mesaj = ''; $tur = 'ok';

function db() {
    static $d = null;
    if ($d === null) {
        $d = new PDO('sqlite:/opt/siber-kalkan/VERI/kalkan.db');
        $d->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $d->exec('PRAGMA journal_mode=WAL'); $d->exec('PRAGMA busy_timeout=5000');
    }
    return $d;
}

/* ---- GOREV GONDER ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['islem'] ?? '') === 'gorev') {
    $tip = preg_replace('/[^a-z_]/', '', (string)($_POST['gtip'] ?? ''));
    $sec = $_POST['gajanlar'] ?? [];
    try {
        $d = db();
        $var = $d->prepare("SELECT tip, ad FROM gorev_tip WHERE tip=? AND aktif=1");
        $var->execute([$tip]);
        $gt = $var->fetch(PDO::FETCH_ASSOC);
        if (!$gt) { $mesaj = 'Geçersiz görev tipi.'; $tur = 'hata'; }
        else {
            if ($sec === 'TUMU' || (is_array($sec) && in_array('TUMU', $sec))) {
                $hedef = array_column($d->query("SELECT ad FROM ajanlar")->fetchAll(PDO::FETCH_ASSOC), 'ad');
            } elseif (is_array($sec)) {
                $hedef = array_values(array_filter(array_map(fn($x) => preg_replace('/[^A-Za-z0-9_.\-]/', '', (string)$x), $sec)));
            } else { $hedef = []; }
            if ($hedef) {
                $st = $d->prepare("INSERT INTO ajan_gorev (ajan,tip,durum,olusturma) VALUES (?,?,'bekliyor',?)");
                foreach ($hedef as $a) $st->execute([$a, $tip, date('d.m.Y H:i')]);
                $mesaj = count($hedef) . " cihaza \"" . $gt['ad'] . "\" görevi gönderildi — 5 sn içinde çalışır.";
            } else { $mesaj = 'Hedef cihaz seçilmedi.'; $tur = 'hata'; }
        }
    } catch (Throwable $e) { $mesaj = 'Hata: ' . $e->getMessage(); $tur = 'hata'; }
}

/* ---- KOMUT GONDER ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['islem'] ?? '') !== 'gorev') {
    $ajanlar = $_POST['ajanlar'] ?? [];        // dizi veya 'TUMU'
    $tip     = ($_POST['tip'] ?? 'engelle') === 'coz' ? 'coz' : 'engelle';
    $ips     = preg_split('/[\s,;]+/', trim($_POST['ips'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    $gecerli = [];
    foreach ($ips as $i) if (filter_var($i, FILTER_VALIDATE_IP)) $gecerli[] = $i;

    $hedef = [];
    try {
        $d = db();
        if ($ajanlar === 'TUMU' || (is_array($ajanlar) && in_array('TUMU', $ajanlar))) {
            $hedef = array_column($d->query("SELECT ad FROM ajanlar")->fetchAll(PDO::FETCH_ASSOC), 'ad');
        } elseif (is_array($ajanlar)) {
            $hedef = array_values(array_filter(array_map(fn($x) => preg_replace('/[^A-Za-z0-9_.\-]/', '', (string)$x), $ajanlar)));
        }
        if ($gecerli && $hedef) {
            $st = $d->prepare("INSERT INTO ajan_komut (ajan,tip,ip,durum,olusturma) VALUES (?,?,?,'bekliyor',?)");
            $n = 0;
            foreach ($hedef as $a) foreach ($gecerli as $ip) { $st->execute([$a, $tip, $ip, date('d.m.Y H:i')]); $n++; }
            $mesaj = "$n komut kuyruğa alındı (" . count($hedef) . " cihaz × " . count($gecerli) . " IP) — 5 sn içinde uygulanır.";
        } elseif (!$gecerli) { $mesaj = 'Geçerli IP girilmedi.'; $tur = 'hata'; }
        else { $mesaj = 'Hedef cihaz seçilmedi.'; $tur = 'hata'; }
    } catch (Throwable $e) { $mesaj = 'Hata: ' . $e->getMessage(); $tur = 'hata'; }
}

/* ---- VERI ---- */
try {
    $d = db();
    $ajanlar = $d->query("SELECT * FROM ajanlar ORDER BY son DESC")->fetchAll(PDO::FETCH_ASSOC);
    $komutlar = $d->query("SELECT * FROM ajan_komut ORDER BY id DESC LIMIT 40")->fetchAll(PDO::FETCH_ASSOC);
    $olay_say = (int)$d->query("SELECT COUNT(*) FROM ajan_olay")->fetchColumn();
    $son_olay = $d->query("SELECT * FROM ajan_olay ORDER BY id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
    $gt_list = $d->query("SELECT tip, ad, komut FROM gorev_tip WHERE aktif=1 ORDER BY ad")->fetchAll(PDO::FETCH_ASSOC);
    $gorevler = $d->query("SELECT g.*, t.ad AS gad FROM ajan_gorev g LEFT JOIN gorev_tip t ON t.tip=g.tip ORDER BY g.id DESC LIMIT 25")->fetchAll(PDO::FETCH_ASSOC);
    $g_bekleyen = (int)$d->query("SELECT COUNT(*) FROM ajan_gorev WHERE durum IN ('bekliyor','alindi')")->fetchColumn();
} catch (Throwable $e) { $ajanlar = []; $komutlar = []; $olay_say = 0; $son_olay = []; $gt_list = []; $gorevler = []; $g_bekleyen = 0; $mesaj = $mesaj ?: 'DB hatası'; }

$token = $ayar['api_token'] ?? '';
$ev = ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8890');
?>
<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ajanlar · CYBER KALKAN</title>
<link rel="stylesheet" href="assets/panel.css?v=1.7">
</head><body>
<?= kalkan_ustbilgi('ajanlar.php') ?>
<main>
<?php kalkan_baslik('💻', 'Ajanlar', 'Uzak cihazlar, komut gönderimi ve tespitler'); ?>
<?php if ($mesaj): ?><div class="uyari <?= $tur === 'hata' ? 'uyari-hata' : 'uyari-ok' ?>"><?= htmlspecialchars($mesaj) ?></div><?php endif; ?>

<div class="idx-kpi">
  <div class="kpi-kutu"><span class="et">AJAN</span><b class="deger"><?= count($ajanlar) ?></b><span class="alt">kayıtlı cihaz</span></div>
  <div class="kpi-kutu"><span class="et">AKTİF</span><b class="deger"><?= count(array_filter($ajanlar, fn($a) => ($a['durum'] ?? '') === 'AKTIF')) ?></b><span class="alt">son 10 dk</span></div>
  <div class="kpi-kutu"><span class="et">UZAK OLAY</span><b class="deger"><?= $olay_say ?></b><span class="alt">ajan tespiti</span></div>
  <div class="kpi-kutu"><span class="et">KOMUT</span><b class="deger"><?= count($komutlar) ?></b><span class="alt">son kuyruk</span></div>
  <div class="kpi-kutu"><span class="et">GÖREV</span><b class="deger"><?= $g_bekleyen ?></b><span class="alt">bekleyen/çalışan</span></div>
</div>

<div class="kart">
  <h3>📡 Yeni Cihaz Kurulumu (tek satır)</h3>
  <p class="kucuk">Uzak cihazda root olarak çalıştır — ajan kendini kurar ve 5 sn'de bir bağlanır:</p>
  <pre class="kod">curl -s "http://<?= htmlspecialchars($ev) ?>/ajan_al.sh" | bash -s "http://<?= htmlspecialchars($ev) ?>" "<?= htmlspecialchars($token) ?>"</pre>
</div>

<div class="kart">
  <h3>🚫 Komut Gönder</h3>
  <form method="post" class="form-satir">
    <select name="ajanlar">
      <option value="TUMU">TÜM CİHAZLAR (<?= count($ajanlar) ?>)</option>
      <?php foreach ($ajanlar as $a): ?>
        <option value="<?= htmlspecialchars($a['ad']) ?>"><?= htmlspecialchars($a['ad']) ?> (<?= htmlspecialchars($a['ip']) ?>)</option>
      <?php endforeach; ?>
    </select>
    <select name="tip"><option value="engelle">ENGelle</option><option value="coz">Çöz</option></select>
    <input type="text" name="ips" placeholder="IP adresleri (virgülle: 1.2.3.4, 5.6.7.8)" required>
    <button class="btn btn-kirmizi" type="submit">KOMUT GÖNDER</button>
  </form>
</div>

<div class="kart">
  <h3>🎯 Görev Gönder (uzaktan denetim)</h3>
  <p class="kucuk">Seçili cihazlarda güvenlik aracı çalıştır — sonuç merkeze döner:</p>
  <form method="post" class="form-satir">
    <input type="hidden" name="islem" value="gorev">
    <select name="gajanlar">
      <option value="TUMU">TÜM CİHAZLAR (<?= count($ajanlar) ?>)</option>
      <?php foreach ($ajanlar as $a): ?>
        <option value="<?= htmlspecialchars($a['ad']) ?>"><?= htmlspecialchars($a['ad']) ?> (<?= htmlspecialchars($a['ip']) ?>)</option>
      <?php endforeach; ?>
    </select>
    <select name="gtip" required>
      <?php foreach ($gt_list as $g): ?>
        <option value="<?= htmlspecialchars($g['tip']) ?>"><?= htmlspecialchars($g['ad']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">GÖREV GÖNDER</button>
  </form>
</div>

<?php if ($gorevler): ?>
<div class="kart">
  <h3>📋 Görev Geçmişi (<?= count($gorevler) ?>)</h3>
  <table class="tablo">
    <tr><th>#</th><th>CİHAZ</th><th>GÖREV</th><th>DURUM</th><th>OLUŞTURMA</th><th>SONUÇ</th></tr>
    <?php foreach ($gorevler as $g): $d2 = $g['durum'] ?? ''; ?>
    <tr>
      <td><?= (int)$g['id'] ?></td>
      <td><?= htmlspecialchars($g['ajan'] ?? '') ?></td>
      <td><?= htmlspecialchars($g['gad'] ?? $g['tip'] ?? '') ?></td>
      <td><span class="rozet <?= $d2 === 'tamam' ? 'rozet-ok' : ($d2 === 'hata' ? 'rozet-kotu' : 'rozet-bekle') ?>"><?= htmlspecialchars($d2) ?></span></td>
      <td><?= htmlspecialchars($g['olusturma'] ?? '') ?></td>
      <td>
        <?php if (!empty($g['cikti'])): ?>
          <details><summary class="mini-btn">çıktıyı gör</summary><pre class="kod"><?= htmlspecialchars(substr((string)$g['cikti'], 0, 2500)) ?></pre></details>
        <?php else: ?><span class="kucuk">—</span><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<div class="kart">
  <h3>💻 Cihazlar (<?= count($ajanlar) ?>)</h3>
  <?php if (!$ajanlar): ?>
    <p class="kucuk">Henüz cihaz kurulmadı. Yukarıdaki tek satır kurulum komutunu hedef cihazlarda çalıştır.</p>
  <?php else: ?>
  <table class="tablo">
    <tr><th>CİHAZ</th><th>IP</th><th>SON</th><th>DURUM</th><th>CPU</th><th>RAM</th><th>DİSK</th><th>YÜK</th></tr>
    <?php foreach ($ajanlar as $a): ?>
    <tr>
      <td><?= htmlspecialchars($a['ad']) ?></td>
      <td><?= htmlspecialchars($a['ip']) ?></td>
      <td><?= htmlspecialchars($a['son']) ?></td>
      <td><span class="rozet <?= ($a['durum'] ?? '') === 'AKTIF' ? 'rozet-yesil' : 'rozet-gri' ?>"><?= htmlspecialchars($a['durum']) ?></span></td>
      <td><?= htmlspecialchars($a['cpu'] ?: '-') ?></td>
      <td><?= htmlspecialchars($a['ram_mb'] ? ($a['ram_mb'] . ' MB') : '-') ?></td>
      <td><?= htmlspecialchars($a['disk'] ?: '-') ?></td>
      <td><?= htmlspecialchars($a['yuk'] ?: '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<div class="kart">
  <h3>📨 Komut Kuyruğu (son 40)</h3>
  <?php if (!$komutlar): ?><p class="kucuk">Komut geçmişi boş.</p><?php else: ?>
  <table class="tablo">
    <tr><th>#</th><th>CİHAZ</th><th>KOMUT</th><th>IP</th><th>DURUM</th><th>OLUŞTURMA</th></tr>
    <?php foreach ($komutlar as $k): ?>
    <tr>
      <td><?= (int)$k['id'] ?></td>
      <td><?= htmlspecialchars($k['ajan']) ?></td>
      <td><?= $k['tip'] === 'engelle' ? '🚫 ENGEL' : '✅ ÇÖZ' ?></td>
      <td><?= htmlspecialchars($k['ip']) ?></td>
      <td><span class="rozet <?= $k['durum'] === 'tamam' ? 'rozet-yesil' : ($k['durum'] === 'alindi' ? 'rozet-mavi' : 'rozet-gri') ?>"><?= htmlspecialchars($k['durum']) ?></span></td>
      <td><?= htmlspecialchars($k['olusturma']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<?php if ($son_olay): ?>
<div class="kart">
  <h3>🎯 Uzak Cihaz Tespitleri (son 15)</h3>
  <table class="tablo">
    <tr><th>#</th><th>CİHAZ</th><th>KURAL</th><th>KURAL ADI</th><th>SEVİYE</th><th>ZAMAN</th></tr>
    <?php foreach ($son_olay as $o): ?>
    <tr>
      <td><?= (int)$o['kural'] ?></td>
      <td><?= htmlspecialchars($o['ajan']) ?></td>
      <td><?= htmlspecialchars($o['kural']) ?></td>
      <td><?= htmlspecialchars($o['ad']) ?></td>
      <td><span class="rozet rozet-kirmizi"><?= htmlspecialchars($o['seviye']) ?></span></td>
      <td><?= htmlspecialchars($o['zaman']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

</main>
<?= kalkan_altbilgi() ?>
