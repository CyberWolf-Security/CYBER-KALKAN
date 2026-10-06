<?php
/* AĞ KATMANI (L3/L4) — Zone · NAT · Port yönlendirme · Rota · VLAN */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();

$mesaj = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!kalkan_csrf_dogrula($_POST['csrf'] ?? null)) {
        $mesaj = ['ha', 'CSRF doğrulaması başarısız.'];
    } else {
        $islem = $_POST['islem'] ?? '';
        $K = '/opt/siber-kalkan';
        $arg = [];
        if ($islem === 'port') {
            $arg = ['port-yonlendir', (string)(int)($_POST['dis_port'] ?? 0),
                    (string)($_POST['ic_ip'] ?? ''), (string)(int)($_POST['ic_port'] ?? 0)];
        } elseif ($islem === 'rota') {
            $arg = ['rota-ekle', (string)($_POST['ag'] ?? ''), (string)($_POST['gw'] ?? '')];
        } elseif ($islem === 'vlan') {
            $arg = ['vlan-ekle', (string)($_POST['arayuz'] ?? ''),
                    (string)(int)($_POST['vid'] ?? 0), (string)($_POST['adres'] ?? '')];
        } elseif ($islem === 'uygula') {
            $arg = ['uygula', '--onay'];
        } elseif ($islem === 'gerial') {
            $arg = ['geri-al'];
        }
        if ($arg) {
            $cikti = shell_exec('/usr/bin/python3 ' . escapeshellarg($K . '/MOTOR/kalkan_ag_v10.py')
                . ' ' . implode(' ', array_map('escapeshellarg', $arg)) . ' 2>&1');
            $mesaj = ['ok', trim((string)$cikti) ?: 'İşlem tamamlandı.'];
        }
    }
}

// ★ DUZELTME: yol MOTOR (buyuk harf) + --json bayragi (onceden 'motor/' yoktu ve
// modul metin basiyordu → json_decode bos → KPI'lar 0 gorunuyordu)
/* ★ DURUM — motor (root) 60 sn'de bir VERI/ag_durum.json yazar; panel (www-data) SADECE OKUR.
   Eskiden shell_exec(python3 …) + shell_exec(nft …) vardi → www-data yetkisiz → bos sonuc. */
$d   = kalkan_oku('ag_durum', []);
$agr = kalkan_oku('ag_kurallar', ['kurallar' => []]);
$kurallar = $agr['kurallar'] ?? [];
$yuklu = !empty($d['zone']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ağ Katmanı — CYBER KALKAN</title>
<link rel="stylesheet" href="assets/panel.css?v=1.7">
<link rel="stylesheet" href="assets/hero.css?v=2"></head><body>
<?= kalkan_ustbilgi('ag.php') ?>
<main class="sarici">
    <div class="cy-hero">
      <div class="cy-ikon">🌐</div>
      <div class="cy-metin">
        <h1 class="cy-baslik">Ağ Katmanı <span class="cy-surum">L3/L4</span></h1>
        <p class="cy-aciklama">
          <b>Zone · NAT · Rota · VLAN</b> yönetimi — nftables tabanlı çekirdek ağ katmanı.
          Değişiklikler <b>onay</b> gerektirir, yönetim portları <b>muaf</b>, kendini kilitleme <b>korumalı</b>.
        </p>
        <div class="cy-etiketler">
          <span class="cy-etiket">🧱 Zone (LAN/WAN/DMZ)</span>
          <span class="cy-etiket">🔀 SNAT · DNAT</span>
          <span class="cy-etiket">🧭 Statik Rota</span>
          <span class="cy-etiket">🏷️ VLAN 802.1Q</span>
        </div>
      </div>
      <div class="cy-tarama"></div>
    </div>

    <!-- ★ CANLI BANT (diger modullerle ortak) -->

    <?php if ($mesaj): ?>
    <div class="uyari uyari-<?= $mesaj[0] === 'ok' ? 'iyi' : 'kotu' ?>" style="font-size:16px">
        <?= htmlspecialchars($mesaj[1]) ?>
    </div>
    <?php endif; ?>

    <?php
      $ar   = $d['arayuzler'] ?? [];
      $up   = count(array_filter((array)$ar, fn($x) => ($x['durum'] ?? '') === 'UP'));
      /* ★ TIP GUVENLIGI: motor bu alanlari SAYI olarak yaziyor; count(int) PHP 8'de FATAL.
         Hem dizi hem sayi kabul edilir (iki bicim destekli). */
      $say = fn($v) => is_array($v) ? count($v) : (int)$v;
      $rota = $say($d['rotalar'] ?? 0);
      $nat  = $say($d['nat'] ?? 0);
      $vlan = $say($d['vlan'] ?? 0);
    ?>
    <div class="kartlar">
      <div class="kart vurgu">
        <div class="et">🌐 ARAYÜZ</div>
        <div class="deger"><?= count($ar) ?></div>
        <div class="alt"><?= $up ?> aktif</div>
      </div>
      <div class="kart">
        <div class="et">🧭 ROTA</div>
        <div class="deger"><?= $rota ?></div>
        <div class="alt">statik yol</div>
      </div>
      <div class="kart <?= $nat ? 'iyi' : '' ?>">
        <div class="et">🔀 NAT KURALI</div>
        <div class="deger"><?= $nat ?></div>
        <div class="alt">SNAT · DNAT</div>
      </div>
      <div class="kart <?= $vlan ? 'iyi' : '' ?>">
        <div class="et">🏷️ VLAN</div>
        <div class="deger"><?= $vlan ?></div>
        <div class="alt">802.1Q</div>
      </div>
      <div class="kart <?= $yuklu ? 'iyi' : 'yuksek' ?>">
        <div class="et">🛡️ ZONE TABLOSU</div>
        <div class="deger" style="font-size:26px"><?= $yuklu ? 'AÇIK' : 'KAPALI' ?></div>
        <div class="alt"><?= $yuklu ? 'yüklü' : 'yüklü değil' ?></div>
      </div>
    </div>

    <style>
    /* ── Ağ Katmanı — tablo durum renkleri (panel.css .tablo td rengini eziyor) ── */
    .ag-tablo td:nth-child(2){font-weight:700;letter-spacing:.4px}
    .ag-tablo td.d-up{color:#00d68f !important}
    .ag-tablo td.d-down{color:#ff4d5e !important}
    .ag-tablo td.d-unk{color:#94a3b8 !important}
    .ag-tablo td code{font-size:14.5px}
    </style>

    <h2>Arayüzler</h2>
    <table class="tablo ag-tablo">
        <tr><th>Ad</th><th>Durum</th><th>Adres</th></tr>
        <?php foreach (array_slice($d['arayuzler'] ?? [], 0, 12) as $a):
              $du = strtoupper((string)($a['durum'] ?? ''));
              $dk = $du === 'UP' ? 'd-up' : ($du === 'DOWN' ? 'd-down' : 'd-unk'); ?>
        <tr><td><code><?= htmlspecialchars($a['ad']) ?></code></td>
            <td class="<?= $dk ?>"><?= htmlspecialchars($du) ?></td>
            <td><code><?= htmlspecialchars($a['ip']) ?></code></td></tr>
        <?php endforeach; ?>
        <?php if (!$d['arayuzler']): ?>
        <tr><td colspan="3" style="opacity:.5;text-align:center;padding:14px">Arayüz bilgisi okunamadı</td></tr>
        <?php endif; ?>
    </table>

    <h2>Port Yönlendirme (DNAT)</h2>
    <form method="post" class="form-satir">
        <input type="hidden" name="csrf" value="<?= kalkan_csrf_uret() ?>">
        <input type="hidden" name="islem" value="port">
        <input name="dis_port" placeholder="Dış port (ör. 8080)" inputmode="numeric" style="font-size:16px">
        <input name="ic_ip" placeholder="İç IP (ör. 10.0.0.10)" style="font-size:16px">
        <input name="ic_port" placeholder="İç port (ör. 80)" inputmode="numeric" style="font-size:16px">
        <button type="submit">Ekle</button>
    </form>

    <h2>Statik Rota</h2>
    <form method="post" class="form-satir">
        <input type="hidden" name="csrf" value="<?= kalkan_csrf_uret() ?>">
        <input type="hidden" name="islem" value="rota">
        <input name="ag" placeholder="Ağ (ör. 10.10.0.0/24)" style="font-size:16px">
        <input name="gw" placeholder="Gateway (ör. 192.168.1.1)" style="font-size:16px">
        <button type="submit">Ekle</button>
    </form>

    <h2>VLAN</h2>
    <form method="post" class="form-satir">
        <input type="hidden" name="csrf" value="<?= kalkan_csrf_uret() ?>">
        <input type="hidden" name="islem" value="vlan">
        <input name="arayuz" placeholder="Arayüz (ör. eth0)" style="font-size:16px">
        <input name="vid" placeholder="VLAN ID (1-4094)" inputmode="numeric" style="font-size:16px">
        <input name="adres" placeholder="Adres (ör. 10.100.0.1/24)" style="font-size:16px">
        <button type="submit">Ekle</button>
    </form>

    <h2>Tanımlı Kurallar <span style="font-size:14px;opacity:.7">(uygula ile aktif olur)</span></h2>
    <?php if (!$kurallar): ?>
        <p style="opacity:.7;font-size:16px">Henüz kural yok.</p>
    <?php else: ?>
    <table class="tablo">
        <tr><th>Tip</th><th>Detay</th><th>Zaman</th></tr>
        <?php foreach ($kurallar as $k): ?>
        <tr>
            <td><?= htmlspecialchars($k['tip'] ?? '?') ?></td>
            <td><?= htmlspecialchars($k['tip'] === 'dnat'
                ? ($k['dis_port'] . ' → ' . $k['ic_ip'] . ':' . $k['ic_port'])
                : ($k['tip'] === 'rota' ? ($k['ag'] . ' via ' . $k['gw'])
                : ($k['arayuz'] . '.' . $k['vid'] . ' ' . $k['adres']))) ?></td>
            <td><?= htmlspecialchars($k['zaman'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <h2>Uygulama</h2>
    <div class="dugme-grup">
        <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= kalkan_csrf_uret() ?>">
            <input type="hidden" name="islem" value="uygula">
            <button type="submit" onclick="return confirm('Ağ kuralları yüklenecek. Emin misin?')">▶ Kuralları Uygula</button>
        </form>
        <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= kalkan_csrf_uret() ?>">
            <input type="hidden" name="islem" value="gerial">
            <button type="submit" onclick="return confirm('Son yedeğe dönülecek. Emin misin?')">↩ Geri Al</button>
        </form>
    </div>
    <p style="opacity:.7;font-size:14px;margin-top:10px">
        ⚠ Ağ değişikliği bağlantıyı etkileyebilir. Yönetim portları (22, 2083, 2087, 8083) ve
        mevcut bağlantılar her zaman muaftır. Sorun olursa "Geri Al".
    </p>
</main>
<?= kalkan_altbilgi() ?>
</body>
</html>
