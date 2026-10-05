<?php
/* 🔗 AJAN KAYIT (SQLITE + GÖREV) — sınırsız ölçek
   POST /ajan_kayit.php   (X-Kalkan-Token başlığı)
   Gövde: {"ad":"cihaz1","sistem":{...},"loglar":[...],
           "gorev_sonuclar":[{"id":5,"durum":"tamam","cikti":"..."}]}
   Döner: {"durum":"ok","yakalanan":N,"komutlar":[...],"gorevler":[...]}
*/
require __DIR__ . '/ortak.php';
header('Content-Type: application/json; charset=utf-8');

$ayar  = kalkan_oku('ayarlar', []);
$token = $_SERVER['HTTP_X_KALKAN_TOKEN'] ?? ($_GET['token'] ?? '');
if ($token === '' || !hash_equals((string)($ayar['api_token'] ?? ''), (string)$token)) {
    http_response_code(403);
    exit(json_encode(['durum' => 'hata', 'mesaj' => 'token gecersiz']));
}

$girdi = json_decode(file_get_contents('php://input'), true);
if (!is_array($girdi)) $girdi = [];

$ad  = preg_replace('/[^A-Za-z0-9_.\-]/', '', (string)($girdi['ad'] ?? ''));
if ($ad === '') $ad = 'bilinmeyen';
$ip  = $_SERVER['REMOTE_ADDR'] ?? '';
$simdi = date('d.m.Y H:i');

/* ---- DB baglantisi ---- */
$db = null;
try {
    $db = new PDO('sqlite:/opt/siber-kalkan/VERI/kalkan.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA busy_timeout = 4000');
} catch (Throwable $e) { $db = null; }

/* ---- 1) AJAN KAYDI / GUNCELLEME ---- */
if ($db) {
    $s = is_array($girdi['sistem'] ?? null) ? $girdi['sistem'] : [];
    try {
        $st = $db->prepare(
            "INSERT INTO ajanlar (ad, ip, ilk, son, durum, cpu, ram_mb, disk, yuk, surum, ham)
             VALUES (:ad, :ip, :simdi, :simdi, 'AKTIF', :cpu, :ram, :disk, :yuk, :surum, :ham)
             ON CONFLICT(ad) DO UPDATE SET
                ip=:ip, son=:simdi, durum='AKTIF', cpu=:cpu, ram_mb=:ram,
                disk=:disk, yuk=:yuk, surum=:surum, ham=:ham"
        );
        $st->execute([
            ':ad' => $ad, ':ip' => $ip, ':simdi' => $simdi,
            ':cpu' => (string)($s['cpu'] ?? ''), ':ram' => (string)($s['ram_mb'] ?? ''),
            ':disk' => (string)($s['disk'] ?? ''), ':yuk' => (string)($s['yuk'] ?? ''),
            ':surum' => (string)($s['surum'] ?? ''), ':ham' => json_encode($s, JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $e) { }
}

/* ---- 2) LOG SATIRLARINI KURAL MOTORU ILE TARA ---- */
$yakalanan = 0;
$loglar = $girdi['loglar'] ?? [];
if ($db && is_array($loglar) && $loglar) {
    $kurallar = kalkan_oku('kurallar', ['kurallar' => []])['kurallar'] ?? [];
    try {
        $ekle = $db->prepare(
            "INSERT INTO ajan_olay (zaman, ajan, ip, kural, seviye, ad, mitre, puan, ham)
             VALUES (:z, :aj, :ip, :kr, :sv, :ad, :mt, :pn, :hm)"
        );
        foreach (array_slice($loglar, 0, 300) as $satir) {
            $satir = (string)$satir;
            if ($satir === '') continue;
            foreach ($kurallar as $k) {
                if (empty($k['aktif'])) continue;
                $desen = (string)($k['desen'] ?? '');
                if ($desen === '') continue;
                if (@preg_match('#' . str_replace('#', '\#', $desen) . '#i', $satir)) {
                    $ekle->execute([
                        ':z' => $simdi, ':aj' => $ad, ':ip' => $ip,
                        ':kr' => (string)($k['id'] ?? ''), ':sv' => (string)($k['seviye'] ?? 'ORTA'),
                        ':ad' => (string)($k['ad'] ?? ''), ':mt' => (string)($k['mitre'] ?? ''),
                        ':pn' => (int)($k['puan'] ?? 0), ':hm' => substr($satir, 0, 300),
                    ]);
                    $yakalanan++;
                    break;
                }
            }
        }
    } catch (Throwable $e) { }
}

/* ---- 3) GOREV SONUCLARINI ISLE ---- */
$gs = $girdi['gorev_sonuclar'] ?? [];
if ($db && is_array($gs) && $gs) {
    try {
        $gu = $db->prepare("UPDATE ajan_gorev SET durum=:d, bitis=:b, sonuc=:s, cikti=:c WHERE id=:i AND ajan=:a");
        foreach (array_slice($gs, 0, 20) as $g) {
            if (!isset($g['id'])) continue;
            $gu->execute([
                ':d' => (($g['durum'] ?? 'tamam') === 'tamam' ? 'tamam' : 'hata'),
                ':b' => $simdi, ':s' => (string)($g['sonuc'] ?? ''),
                ':c' => substr((string)($g['cikti'] ?? ''), 0, 4000),
                ':i' => (int)$g['id'], ':a' => $ad,
            ]);
        }
    } catch (Throwable $e) { }
}

/* ---- 4) BEKLEYEN GOREVLERI GONDER (alindi isaretle) ---- */
$gorevler = [];
if ($db) {
    try {
        $gs2 = $db->prepare("SELECT g.id, g.tip, g.parametre, t.komut
                             FROM ajan_gorev g LEFT JOIN gorev_tip t ON t.tip = g.tip
                             WHERE g.ajan=:a AND g.durum='bekliyor' ORDER BY g.id LIMIT 100");
        $gs2->execute([':a' => $ad]);
        $gorevler = $gs2->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($gorevler)) $gorevler = [];
        if ($gorevler) {
            $gu2 = $db->prepare("UPDATE ajan_gorev SET durum='alindi', baslama=:b WHERE id=:i");
            foreach ($gorevler as $g) $gu2->execute([':b' => $simdi, ':i' => $g['id']]);
        }
    } catch (Throwable $e) { $gorevler = []; }
}

/* ---- 5) KOMUT KUYRUGU (engelle/coz) ---- */
$kq = kalkan_oku('ajan_kuyruk', ['komutlar' => []]);
if (!is_array($kq)) $kq = ['komutlar' => []];
if (!isset($kq['komutlar']) || !is_array($kq['komutlar'])) $kq['komutlar'] = [];
$benim = $kq['komutlar'][$ad] ?? [];
unset($kq['komutlar'][$ad]);
if (!$kq['komutlar']) $kq['komutlar'] = new stdClass();
kalkan_yaz('ajan_kuyruk', $kq);

echo json_encode([
    'durum'        => 'ok',
    'ajan'         => $ad,
    'sunucu_saati' => date('c'),
    'yakalanan'    => $yakalanan,
    'komutlar'     => array_values($benim),
    'gorevler'     => $gorevler,
], JSON_UNESCAPED_UNICODE);