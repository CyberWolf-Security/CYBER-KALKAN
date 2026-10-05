<?php
/* 🌐 DİL MOTORU — TR/EN (JSON tabanlı, çerez ile kalıcı)
   Kullanım:  require 'dil.php';  ... <?= d('giris_yap') ?>
   Dil değiştir:  ?dil=en  (çerez 1 yıl saklanır)            */

if (!defined('KALKAN_VERI')) {
    define('KALKAN_VERI', '/opt/siber-kalkan/VERI');
}

/* dil seç: GET ?dil=xx → çerez yaz; yoksa çerez; yoksa tr */
function kalkan_dil(): string {
    static $dil = null;
    if ($dil !== null) return $dil;                 /* bir kez hesapla */
    if (isset($_GET['dil']) && in_array($_GET['dil'], ['tr', 'en'], true)) {
        if (!headers_sent()) {
            setcookie('kalkan_dil', $_GET['dil'], [   /* tek sefer gönder */
                'expires'  => time() + 31536000,
                'path'     => '/',
                'samesite' => 'Lax',
            ]);
        }
        return $dil = $_GET['dil'];
    }
    $c = (string)($_COOKIE['kalkan_dil'] ?? 'tr');
    return $dil = (in_array($c, ['tr', 'en'], true) ? $c : 'tr');
}

/* sözlükten çeviri al (bulunamazsa TR'ye, o da yoksa anahtarın kendisine düşer) */
function d(string $anahtar, string $varsayilan = ''): string {
    static $sozluk = null;
    if ($sozluk === null) {
        $yol = KALKAN_VERI . '/dil.json';
        $sozluk = is_file($yol)
            ? (json_decode((string)@file_get_contents($yol), true) ?: [])
            : [];
    }
    $d = kalkan_dil();
    return (string)($sozluk[$d][$anahtar]
        ?? $sozluk['tr'][$anahtar]
        ?? ($varsayilan !== '' ? $varsayilan : $anahtar));
}

/* neon TR/EN seçici (aktif olan vurgulu) */
function dil_secici(string $sinif = ''): string {
    $aktif = kalkan_dil();
    $h = '<div class="dil-sec ' . $sinif . '">';
    foreach (['tr' => '🇹🇷 TR', 'en' => '🇬🇧 EN'] as $kod => $etiket) {
        $sec = $kod === $aktif ? ' dil-aktif' : '';
        $h .= '<a class="dil-btn' . $sec . '" href="?dil=' . $kod . '">' . $etiket . '</a>';
    }
    return $h . '</div>';
}


/* ══ OTOMATİK ÇEVİRİ KATMANI ══
   t('Türkçe metin')  → TR modunda metni aynen, EN modunda sözlükten çevirisini döner.
   Sözlükte yoksa metni aynen döner (sayfa bozulmaz). */
if (!function_exists('t')) {
    function t(string $metin): string {
        if (kalkan_dil() === 'tr') return $metin;
        static $soz = null;
        if ($soz === null) {
            $y = @json_decode(@file_get_contents('/opt/siber-kalkan/VERI/dil.json'), true);
            $soz = $y['ceviri'] ?? [];
        }
        return $soz[$metin] ?? $metin;
    }
}
