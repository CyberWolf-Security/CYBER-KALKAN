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
            $cikti = shell_exec('/usr/bin/python3 ' . escapeshellarg($K . '/motor/kalkan_ag_v10.py')
                . ' ' . implode(' ', array_map('escapeshellarg', $arg)) . ' 2>&1');
            $mesaj = ['ok', trim((string)$cikti) ?: 'İşlem tamamlandı.'];
        }
    }
}

$d   = json_decode((string)@shell_exec('/usr/bin/python3 /opt/siber-kalkan/motor/kalkan_ag_v10.py durum 2>/dev/null'), true) ?: [];
$agr = kalkan_oku('ag_kurallar', ['kurallar' => []]);
$kurallar = $agr['kurallar'] ?? [];
$yuklu = strpos((string)@shell_exec('nft list table inet kalkan_ag 2>/dev/null'), 'kalkan_ag') !== false;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ağ Katmanı — CYBER KALKAN</title>
<link rel="stylesheet" href="assets/panel.css?v=1.7">
</head>
<body>
<?= kalkan_menu('ag.php') ?>
<main class="sarici">
    <h1>🌐 Ağ Katmanı <span style="font-size:15px;opacity:.7">L3/L4 · Zone · NAT · Rota · VLAN</span></h1>

    <?php if ($mesaj): ?>
    <div class="uyari uyari-<?= $mesaj[0] === 'ok' ? 'iyi' : 'kotu' ?>" style="font-size:16px">
        <?= htmlspecialchars($mesaj[1]) ?>
    </div>
    <?php endif; ?>

    <div class="kpi-izgara">
        <div class="kpi"><b><?= count($d['arayuzler'] ?? []) ?></b><span>Arayüz</span></div>
        <div class="kpi"><b><?= count($d['rotalar'] ?? []) ?></b><span>Rota</span></div>
        <div class="kpi"><b><?= count($d['nat'] ?? []) ?></b><span>NAT Kuralı</span></div>
        <div class="kpi"><b><?= count($d['vlan'] ?? []) ?></b><span>VLAN</span></div>
        <div class="kpi"><b style="color:<?= $yuklu ? '#4ade80' : '#f59e0b' ?>"><?= $yuklu ? 'AÇIK' : 'KAPALI' ?></b><span>Zone Tablosu</span></div>
    </div>

    <h2>Arayüzler</h2>
    <table class="tablo">
        <tr><th>Ad</th><th>Durum</th><th>Adres</th></tr>
        <?php foreach (array_slice($d['arayuzler'] ?? [], 0, 12) as $a): ?>
        <tr><td><code><?= htmlspecialchars($a['ad']) ?></code></td>
            <td><?= htmlspecialchars($a['durum']) ?></td>
            <td><?= htmlspecialchars($a['ip']) ?></td></tr>
        <?php endforeach; ?>
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
