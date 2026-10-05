<?php
/* VAKA YONETIMI — olay takibi (CYBER KALKAN'a ozel) */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();
$mesaj = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && kalkan_csrf_dogrula($_POST['csrf'] ?? null)) {
    $v = kalkan_oku('vakalar', ['vakalar' => [], 'toplam' => 0]);
    $islem = $_POST['islem'] ?? '';
    if ($islem === 'ekle') {
        $v['vakalar'][] = ['id' => 'VK-' . date('ymd') . '-' . rand(100, 999),
            'baslik' => trim($_POST['baslik'] ?? ''), 'ip' => trim($_POST['ip'] ?? ''),
            'onem' => $_POST['onem'] ?? 'ORTA', 'durum' => 'ACIK',
            'not' => '', 'zaman' => date('d.m.Y H:i')];
        $v['toplam'] = ($v['toplam'] ?? 0) + 1;
        $mesaj = '✓ Vaka acildi';
    } elseif ($islem === 'durum') {
        foreach ($v['vakalar'] as &$x) {
            if (($x['id'] ?? '') === ($_POST['id'] ?? '')) {
                $x['durum'] = $_POST['yeni'] ?? $x['durum'];
                $x['not'] = trim($_POST['not'] ?? $x['not']);
            }
        }
        unset($x); $mesaj = '✓ Vaka guncellendi';
    }
    kalkan_yaz('vakalar', $v);
}
$v = kalkan_oku('vakalar', ['vakalar' => [], 'toplam' => 0]);
$acik = count(array_filter($v['vakalar'], fn($x) => ($x['durum'] ?? '') === 'ACIK'));
$ayar = kalkan_oku('ayarlar', []);
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Vakalar</title>
<link rel="stylesheet" href="assets/panel.css?v=1.6"></head><body>
<?= kalkan_ustbilgi('vakalar.php') ?>
<main>
<?php kalkan_baslik('📁', 'Vaka Yönetimi', 'Olay soruşturma kayıtları'); ?>
  <h2 class="bolum">📁 Vaka Yönetimi <span class="dim">(<?= $acik ?> açık · <?= count($v['vakalar']) ?> toplam)</span></h2>
  <?php if ($mesaj): ?><div class="uyari-ok"><?= kalkan_kacis($mesaj) ?></div><?php endif; ?>

  <div class="panel-kutu">
    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
      <input type="hidden" name="csrf" value="<?= kalkan_csrf() ?>"><input type="hidden" name="islem" value="ekle">
      <div style="flex:2"><label class="dim">Başlık</label><input name="baslik" required style="width:100%" placeholder="Örn: SQLi saldırısı"></div>
      <div style="flex:1"><label class="dim">IP</label><input name="ip" style="width:100%" placeholder="1.2.3.4"></div>
      <div><label class="dim">Önem</label>
        <select name="onem"><option>KRITIK</option><option selected>YUKSEK</option><option>ORTA</option><option>DUSUK</option></select></div>
      <button type="submit">Vaka Aç</button>
    </form>
  </div>

  <table class="tablo"><thead><tr><th>ID</th><th>Başlık</th><th>IP</th><th>Önem</th><th>Durum</th><th>Açılış</th><th>Not / İşlem</th></tr></thead><tbody>
  <?php foreach (array_reverse($v['vakalar']) as $x): ?>
    <tr>
      <td class="mono"><?= kalkan_kacis($x['id'] ?? '') ?></td>
      <td><?= kalkan_kacis($x['baslik'] ?? '') ?></td>
      <td class="mono"><?= kalkan_kacis($x['ip'] ?? '') ?></td>
      <td><span class="rozet" style="background:#ff3b5c22;color:#ff6b87"><?= kalkan_kacis($x['onem'] ?? '') ?></span></td>
      <td><span class="rozet" style="background:<?= ($x['durum'] ?? '') === 'ACIK' ? '#ff9f4322;color:#ff9f43' : '#00d68f22;color:#00d68f' ?>"><?= kalkan_kacis($x['durum'] ?? '') ?></span></td>
      <td class="dim"><?= kalkan_kacis($x['zaman'] ?? '') ?></td>
      <td>
        <form method="post" style="display:flex;gap:4px">
          <input type="hidden" name="csrf" value="<?= kalkan_csrf() ?>"><input type="hidden" name="islem" value="durum">
          <input type="hidden" name="id" value="<?= kalkan_kacis($x['id'] ?? '') ?>">
          <input name="not" value="<?= kalkan_kacis($x['not'] ?? '') ?>" placeholder="not" style="width:110px;font-size:15.5px">
          <select name="yeni" style="font-size:15.5px">
            <?php foreach (['ACIK', 'INCELENIYOR', 'KAPANDI', 'YANLIS-ALARM'] as $s): ?>
              <option <?= ($x['durum'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" style="padding:4px 8px;font-size:15.5px">Kaydet</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</main>
<?= kalkan_altbilgi() ?></body></html>
