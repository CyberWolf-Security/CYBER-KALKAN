<?php
/* ARAMA / THREAT HUNTING — tum veride sorgu */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();
$q = trim($_GET['q'] ?? '');
$tur = $_GET['tur'] ?? 'hepsi';
$sonuc = [];
$ayar = kalkan_oku('ayarlar', []);

if ($q !== '' && strlen($q) >= 2) {
    $kaynaklar = $tur === 'hepsi'
        ? ['olaylar' => 'olaylar', 'engel' => 'liste', 'kararlar' => 'kararlar', 'vakalar' => 'vakalar']
        : [$tur => ['olaylar' => 'olaylar', 'engel' => 'liste', 'kararlar' => 'kararlar', 'vakalar' => 'vakalar'][$tur]];
    foreach ($kaynaklar as $ad => $anahtar) {
        $d = kalkan_oku($ad, []);
        foreach ($d[$anahtar] ?? [] as $satir) {
            if (stripos(json_encode($satir, JSON_UNESCAPED_UNICODE), $q) !== false) {
                $sonuc[] = ['tur' => $ad, 'veri' => $satir];
            }
        }
    }
}
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Arama</title>
<link rel="stylesheet" href="assets/panel.css?v=1.7"></head><body>
<?= kalkan_ustbilgi('arama.php') ?>
<main>
<?php kalkan_baslik('🔍', 'Tehdit Avı', 'Tüm kayıtlarda gelişmiş arama'); ?>
  <h2 class="bolum">🔍 Arama / Threat Hunting</h2>
  <div class="panel-kutu">
    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap">
      <input name="q" value="<?= kalkan_kacis($q) ?>" placeholder="IP, kural, açıklama, MITRE... (min 2 karakter)" style="flex:3;min-width:240px">
      <select name="tur">
        <?php foreach (['hepsi' => 'Hepsi', 'olaylar' => 'Olaylar', 'engel' => 'Engellenen', 'kararlar' => 'Kararlar', 'vakalar' => 'Vakalar'] as $k => $vv): ?>
          <option value="<?= $k ?>" <?= $tur === $k ? 'selected' : '' ?>><?= $vv ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit">Ara</button>
    </form>
  </div>
  <?php if ($q !== ''): ?>
    <h3><?= count($sonuc) ?> sonuç — "<?= kalkan_kacis($q) ?>"</h3>
    <?php if ($sonuc): ?>
      <table class="tablo"><thead><tr><th>Kaynak</th><th>Detay</th></tr></thead><tbody>
      <?php foreach (array_slice($sonuc, 0, 200) as $s): ?>
        <tr><td><span class="rozet" style="background:#4da3ff22;color:#4da3ff"><?= kalkan_kacis($s['tur']) ?></span></td>
            <td class="mono" style="font-size:15.5px;word-break:break-all"><?= kalkan_kacis(substr(json_encode($s['veri'], JSON_UNESCAPED_UNICODE), 0, 220)) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php else: ?>
      <div class="panel-kutu dim">Eşleşme yok — farklı anahtar dene (IP, "SQL", "T1190", "shell"...)</div>
    <?php endif; ?>
  <?php else: ?>
    <div class="panel-kutu dim">💡 Örnek: <code>45.155</code> · <code>SQL Injection</code> · <code>T1505</code> · <code>ENGELLE</code></div>
  <?php endif; ?>
</main>
<?= kalkan_altbilgi() ?></body></html>
