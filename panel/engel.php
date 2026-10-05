<?php
/* ENGEL YONETIMI */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();

$mesaj = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!kalkan_csrf_dogrula($_POST['csrf'] ?? null)) {
        $mesaj = ['ha','CSRF doğrulaması başarısız.'];
    } else {
        $eng = kalkan_oku('engel', ['liste'=>[], 'toplam'=>0]);
        $islem = $_POST['islem'] ?? '';

        if ($islem === 'ekle') {
            $ip = trim((string)($_POST['ip'] ?? ''));
            if (!kalkan_ip_gecerli($ip)) {
                $mesaj = ['ha','Geçersiz IPv4 adresi.'];
            } else {
                $var = array_filter($eng['liste'], fn($e) => ($e['ip'] ?? '') === $ip);
                if ($var) { $mesaj = ['ha',"$ip zaten engelli."]; }
                else {
                    kalkan_firewall_engelle($ip);
                    array_unshift($eng['liste'], ['ip'=>$ip,'puan'=>100,'zaman'=>kalkan_simdi(),
                                                 'sebep'=>(string)($_POST['sebep'] ?? 'manuel'),'kaynak'=>'PANEL']);
                    $eng['toplam'] = count($eng['liste']);
                    $eng['guncelleme'] = kalkan_simdi();
                    kalkan_yaz('engel', $eng);
                    $mesaj = ['ok',"$ip engellendi ✓"];
                }
            }
        } elseif ($islem === 'coz') {
            $ip = trim((string)($_POST['ip'] ?? ''));
            if (kalkan_ip_gecerli($ip)) {
                kalkan_firewall_coz($ip);
                $eng['liste'] = array_values(array_filter($eng['liste'], fn($e) => ($e['ip'] ?? '') !== $ip));
                $eng['toplam'] = count($eng['liste']);
                $eng['guncelleme'] = kalkan_simdi();
                kalkan_yaz('engel', $eng);
                $mesaj = ['ok',"$ip engeli çözüldü"];
            }
        }
    }
}
$eng = kalkan_oku('engel', ['liste'=>[]]);
$ayar = kalkan_oku('ayarlar', ['panel_adi'=>'CYBER KALKAN']);
?>
<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Engel Yönetimi — <?= kalkan_kacis($ayar['panel_adi']) ?></title>
<link rel="stylesheet" href="assets/panel.css?v=1.6"><?php kalkan_sayfalama_script(); ?>
</head><body>
<?= kalkan_ustbilgi('engel.php') ?>
<main>
<?php kalkan_baslik('🚫', 'Engel Yönetimi', 'IP engelleme ve kara liste'); ?>
<?php if ($mesaj): ?><div class="uyari <?= $mesaj[0] ?>"><?= kalkan_kacis($mesaj[1]) ?></div><?php endif; ?>

<section class="kutu">
  <h3>➕ IP Engelle</h3>
  <form class="satir" method="post">
    <input type="hidden" name="csrf" value="<?= kalkan_csrf() ?>">
    <input type="hidden" name="islem" value="ekle">
    <input name="ip" placeholder="1.2.3.4" required pattern="\d{1,3}(\.\d{1,3}){3}" style="min-width:190px">
    <select name="sebep">
      <option value="manuel">Manuel</option><option value="saldiri">Saldırı</option>
      <option value="brute-force">Brute Force</option><option value="tarama">Tarama</option>
      <option value="spam">Spam</option>
    </select>
    <button class="kir" type="submit">🚫 Engelle</button>
  </form>
</section>

<section class="kutu">
  <h3>🛡️ Engelli IP Listesi (<?= count($eng['liste']) ?>)</h3>
  <table id="tbl_engel"><thead><tr><th>IP</th><th>Puan</th><th>Sebep</th><th>Kaynak</th><th>Zaman</th><th>İşlem</th></tr></thead><tbody>
  <?php if (empty($eng['liste'])): ?>
    <tr><td colspan="6" class="bos">Engelli IP yok</td></tr>
  <?php else: foreach (array_reverse($eng['liste']) as $e): ?>
    <tr><td class="ip"><?= kalkan_kacis($e['ip']??'') ?></td>
        <td class="mono"><?= (int)($e['puan']??0) ?></td>
        <td><?= kalkan_kacis($e['sebep']??'-') ?></td>
        <td class="soluk"><?= kalkan_kacis($e['kaynak']??'-') ?></td>
        <td class="soluk"><?= kalkan_kacis($e['zaman']??'-') ?></td>
        <td><form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= kalkan_csrf() ?>">
            <input type="hidden" name="islem" value="coz">
            <input type="hidden" name="ip" value="<?= kalkan_kacis($e['ip']??'') ?>">
            <button class="yes" type="submit">Çöz</button></form></td></tr>
  <?php endforeach; endif; ?>
  </tbody></table>
</section>
<?= kalkan_altbilgi() ?>
</main><script>kalkan_sayfalama('tbl_engel',50);</script>
</body></html>
