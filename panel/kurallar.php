<?php
/* KURAL EDITORU — kural motoru (panelden yonetim) */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();
$mesaj = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && kalkan_csrf_dogrula($_POST['csrf'] ?? null)) {
    $k = kalkan_oku('kurallar', ['kurallar' => []]);
    $islem = $_POST['islem'] ?? '';
    if ($islem === 'ekle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && ($_POST['ad'] ?? '') !== '' && ($_POST['desen'] ?? '') !== '') {
            $desen_y = (string)$_POST['desen'];
            /* Motor Python'dur: gecerlilik PYTHON ile dogrulanir (PHP PCRE farkli davranir) */
            $py_kontrol = trim(@shell_exec('/usr/bin/python3 -c "import re,sys; re.compile(sys.argv[1]); print(1)" ' . escapeshellarg($desen_y) . ' 2>/dev/null'));
            $php_kontrol = (@preg_match('/' . str_replace('/', '\\/', $desen_y) . '/i', '') !== false);
            if ($py_kontrol !== '1' && !$php_kontrol) {
                $mesaj = 'HATA: gecersiz regex!';
            } else {
                $k['kurallar'][] = ['id'=>$id, 'seviye'=>$_POST['seviye'] ?? 'ORTA',
                    'puan'=>(int)($_POST['puan'] ?? 20), 'ad'=>trim($_POST['ad']),
                    'desen'=>$_POST['desen'], 'mitre'=>trim($_POST['mitre'] ?? ''), 'aktif'=>true];
                kalkan_yaz('kurallar', $k);
                $mesaj = "Kural #$id eklendi";
            }
        }
    } elseif ($islem === 'sil') {
        $id = (int)($_POST['id'] ?? 0);
        $k['kurallar'] = array_values(array_filter($k['kurallar'], fn($x) => (int)$x['id'] !== $id));
        kalkan_yaz('kurallar', $k);
        $mesaj = "Kural #$id silindi";
    } elseif ($islem === 'degistir') {
        $id = (int)($_POST['id'] ?? 0);
        foreach ($k['kurallar'] as &$x) if ((int)$x['id'] === $id) $x['aktif'] = !($x['aktif'] ?? true);
        kalkan_yaz('kurallar', $k);
        $mesaj = "Kural #$id durumu degisti";
    }
}
$k = kalkan_oku('kurallar', ['kurallar' => []]);
$sey = ['KRITIK'=>'#ff3b5c','YUKSEK'=>'#ffb020','ORTA'=>'#00d68f'];
?>
<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><title>Kurallar — CYBER KALKAN</title>
<link rel="stylesheet" href="assets/panel.css?v=1.6"><?php kalkan_sayfalama_script(); ?>
</head><body>
<?= kalkan_ustbilgi('kurallar.php') ?>
<main>
<?php kalkan_baslik('📜', 'Kural Motoru', 'İmza kütüphanesi ve tespit kuralları'); ?>
<h2 class="bolum">⚙️ Kural Motoru — <?= count($k['kurallar']) ?> kural</h2>
<?php if ($mesaj): ?><p style="color:#00d68f"><?= kalkan_kacis($mesaj) ?></p><?php endif; ?>
<form method="post" style="background:rgba(0,212,255,.06);padding:22px 24px;border-radius:14px;margin:18px 0;border:1px solid rgba(0,212,255,.25)">
  <input type="hidden" name="csrf" value="<?= kalkan_csrf() ?>"><input type="hidden" name="islem" value="ekle">
  <b style="font-size:19px;color:#9fe4ff">➕ Yeni kural ekle</b><br><br>
  ID <input name="id" type="number" placeholder="1010" required style="width:120px">
  Seviye <select name="seviye"><option>KRITIK</option><option selected>YUKSEK</option><option>ORTA</option></select>
  Puan <input name="puan" type="number" value="25" style="width:100px">
  MITRE <input name="mitre" placeholder="T1059" style="width:130px"><br><br>
  Ad <input name="ad" placeholder="Kural adi" required style="width:320px">
  Regex <input name="desen" placeholder="(kalansin|malware|exploit)" required style="width:420px">
  <button type="submit">Ekle</button>
</form>
<table id="tbl_kural"><thead><tr><th>ID</th><th>Seviye</th><th>Puan</th><th>Ad</th><th>MITRE</th><th>Durum</th><th></th></tr></thead><tbody>
<?php foreach ($k['kurallar'] as $x): ?>
<tr>
  <td class="mono">#<?= (int)$x['id'] ?></td>
  <td><span class="rozet" style="background:<?= $sey[$x['seviye']] ?? '#888' ?>22;color:<?= $sey[$x['seviye']] ?? '#888' ?>"><?= kalkan_kacis($x['seviye']) ?></span></td>
  <td><?= (int)$x['puan'] ?></td>
  <td><?= kalkan_kacis($x['ad']) ?></td>
  <td class="mono"><?= kalkan_kacis($x['mitre'] ?? '-') ?></td>
  <td><?= !empty($x['aktif']) ? '<span style="color:#00d68f">AKTIF</span>' : '<span style="color:#7b8f9e">KAPALI</span>' ?></td>
  <td>
    <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= kalkan_csrf() ?>">
    <input type="hidden" name="islem" value="degistir"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
    <button type="submit">Aç/Kapat</button></form>
    <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= kalkan_csrf() ?>">
    <input type="hidden" name="islem" value="sil"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
    <button type="submit">Sil</button></form>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table>
</main>
<?= kalkan_altbilgi() ?><script>kalkan_sayfalama('tbl_kural',40);</script>
</body></html>
