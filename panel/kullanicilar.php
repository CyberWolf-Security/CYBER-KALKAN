<?php
/* KULLANICI YONETIMI + 2FA (RBAC) */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();
if (kalkan_rol() !== 'ADMIN') { header('Location: index.php'); exit; }
$mesaj = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && kalkan_csrf_dogrula($_POST['csrf'] ?? null)) {
    $k = kalkan_oku('kullanicilar', ['kullanicilar' => []]);
    $i = $_POST['islem'] ?? '';
    if ($i === 'ekle') {
        $ad = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['ad'] ?? '')));
        if ($ad && !array_filter($k['kullanicilar'], fn($x) => $x['ad'] === $ad)) {
            $k['kullanicilar'][] = ['ad' => $ad, 'rol' => $_POST['rol'] ?? 'IZLEYICI',
                'sifre_hash' => hash('sha256', $_POST['sifre'] ?? 'kalkan'), 'totp' => '',
                'aktif' => true, 'olusturma' => date('d.m.Y H:i')];
            $mesaj = "✓ Kullanıcı eklendi: $ad";
            kalkan_audit('KULLANICI_EKLE', $ad);
        } else { $mesaj = '✗ Geçersiz veya mevcut kullanıcı'; }
    } elseif ($i === 'sil') {
        $k['kullanicilar'] = array_values(array_filter($k['kullanicilar'],
            fn($x) => $x['ad'] !== ($_POST['ad'] ?? '') || $x['ad'] === 'admin'));
        $mesaj = '✓ Kullanıcı silindi'; kalkan_audit('KULLANICI_SIL', $_POST['ad'] ?? '');
    } elseif ($i === 'totp') {
        foreach ($k['kullanicilar'] as &$x) {
            if ($x['ad'] === ($_POST['ad'] ?? '')) {
                $x['totp'] = $_POST['totp_aktif'] ? ($x['totp'] ?: kalkan_totp_sirri()) : '';
            }
        }
        unset($x); $mesaj = '✓ 2FA güncellendi'; kalkan_audit('2FA_GUNCELLE', $_POST['ad'] ?? '');
    }
    kalkan_yaz('kullanicilar', $k);
}
$k = kalkan_oku('kullanicilar', ['kullanicilar' => []]);
$a = kalkan_oku('audit', ['kayitlar' => [], 'toplam' => 0]);
$ayar = kalkan_oku('ayarlar', []);
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Kullanıcılar</title>
<link rel="stylesheet" href="assets/panel.css?v=1.7"></head><body>
<?= kalkan_ustbilgi('kullanicilar.php') ?>
<main>
<?php kalkan_baslik('👤', 'Kullanıcılar', 'Yönetici hesapları ve yetkiler'); ?>
  <h2 class="bolum">👤 Kullanıcı Yönetimi <span class="dim">(RBAC + 2FA)</span></h2>
  <?php if ($mesaj): ?><div class="uyari-ok"><?= kalkan_kacis($mesaj) ?></div><?php endif; ?>

  <div class="panel-kutu">
    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
      <input type="hidden" name="csrf" value="<?= kalkan_csrf() ?>"><input type="hidden" name="islem" value="ekle">
      <div><label class="dim">Kullanıcı adı</label><input name="ad" required style="width:130px"></div>
      <div><label class="dim">Şifre</label><input name="sifre" type="password" required style="width:130px"></div>
      <div><label class="dim">Rol</label><select name="rol"><option>IZLEYICI</option><option>ADMIN</option></select></div>
      <button type="submit">Ekle</button>
    </form>
  </div>

  <table class="tablo"><thead><tr><th>Kullanıcı</th><th>Rol</th><th>2FA</th><th>Oluşturma</th><th>İşlem</th></tr></thead><tbody>
  <?php foreach ($k['kullanicilar'] as $x): ?>
    <tr>
      <td class="mono"><?= kalkan_kacis($x['ad']) ?></td>
      <td><span class="rozet" style="background:<?= $x['rol'] === 'ADMIN' ? '#ff3b5c22;color:#ff6b87' : '#4da3ff22;color:#4da3ff' ?>"><?= kalkan_kacis($x['rol']) ?></span></td>
      <td>
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf" value="<?= kalkan_csrf() ?>"><input type="hidden" name="islem" value="totp">
          <input type="hidden" name="ad" value="<?= kalkan_kacis($x['ad']) ?>">
          <span class="soluk" style="font-size:15.5px">Ayarlar → E-posta 2FA</span>
        </form>
        <?php if (!empty($x['totp'])): ?><div class="dim mono" style="font-size:15.5px"><?= kalkan_kacis($x['totp']) ?></div><?php endif; ?>
      </td>
      <td class="dim"><?= kalkan_kacis($x['olusturma'] ?? '') ?></td>
      <td><?php if ($x['ad'] !== 'admin'): ?>
        <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= kalkan_csrf() ?>">
        <input type="hidden" name="islem" value="sil"><input type="hidden" name="ad" value="<?= kalkan_kacis($x['ad']) ?>">
        <button type="submit" style="padding:3px 8px;font-size:15.5px;background:#ff3b5c">Sil</button></form>
      <?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</main>
<?= kalkan_altbilgi() ?></body></html>
