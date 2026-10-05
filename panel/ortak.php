<?php

/**
 * CYBER KALKAN - PANEL CEKIRDEGI
 * CYBERWOLF SECURITY | Sifirdan yazildi
 */
declare(strict_types=1);

/* SURUM — TEK KAYNAK: paket surumu buradan izlenir */
if (!defined('KALKAN_SURUM')) define('KALKAN_SURUM', '1.0');

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
require_once __DIR__ . '/dil.php';

/* ══ GLOBAL ÇEVİRİ FİLTRESİ (EN) — görünen metin, kelime sınırı ══ */
if (function_exists('kalkan_dil') && kalkan_dil() === 'en') {
    ob_start(function (string $html): string {
        static $soz = null;
        if ($soz === null) {
            $yo = '/opt/siber-kalkan/VERI/dil.json';
            $j  = is_readable($yo) ? json_decode((string)file_get_contents($yo), true) : null;
            $soz = (is_array($j) && !empty($j['ceviri']) && is_array($j['ceviri'])) ? $j['ceviri'] : [];
        }
        if (!$soz) return $html;
        try {
            $parcalar = preg_split('/(<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>|<[^>]*>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
            if ($parcalar === false) return $html;
            $uzun = [];
            $kisa = [];
            foreach ($soz as $tr => $en) {
                if (!is_string($tr) || $tr === '' || !is_string($en)) continue;
                if (strlen($tr) >= 8) { $uzun[$tr] = $en; }
                else { $kisa[] = [$tr, $en]; }
            }
            if ($uzun) { uksort($uzun, static fn($a, $b) => strlen($b) <=> strlen($a)); }
            foreach ($parcalar as $i => $p) {
                if ($p === '' || $p[0] === '<') continue;
                $t = $uzun ? strtr($p, $uzun) : $p;
                foreach ($kisa as $kk) {
                    $t = preg_replace('/\b' . preg_quote($kk[0], '/') . '\b/u', $kk[1], $t);
                }
                $parcalar[$i] = $t;
            }
            return implode('', $parcalar);
        } catch (\Throwable $e) {
            return $html;
        }
    });
}

/* ---------- ORTAK MENU ---------- */
function kalkan_menu($aktif = "") {
    $m = [
        "index.php"        => "🏠 Panel",
        "engel.php"        => "🚫 Engel",
        "olaylar.php"      => "📋 Olaylar",
        "vakalar.php"      => "📁 Vakalar",
        "arama.php"        => "🔍 Arama",
        "indexer.php"      => "🗄️ İndeks",
        "ajanlar.php"      => "💻 Ajanlar",
        "fim.php"          => "📝 FIM",
        "guvenlik.php"     => "🛡️ Güvenlik",
        "waf.php"          => "🧱 WAF",
        "ag.php"           => "🌐 Ağ",
        "ips.php"          => "🛡 IPS",
        "kurallar.php"     => "📜 Kurallar",
        "cografi.php"      => "🌍 Harita",
        "aktif.php"        => "⚔️ Savunma",
        "sistem.php"       => "🎯 Sistem",
        "entegrasyon.php"  => "🔌 Entegrasyon",
        "kullanicilar.php" => "👤 Kullanıcılar",
        "denetim.php"      => "📜 Denetim",
        "rapor.php"        => "📄 Rapor",
        "kilitler.php"     => "🔒 Kilitler",
        "ayarlar.php"      => "⚙️ Ayarlar",
        "gelistirenler.php" => "🐺 Geliştirenler",
    ];
    $h = '<nav class="kalkan-menu">';
    foreach ($m as $dosya => $ad) {
        $cls = ($dosya === $aktif) ? ' class="aktif"' : '';
        $etiket = d('m_' . basename($dosya, '.php'), $ad);
        $h .= "<a href=\"$dosya\"$cls>$etiket</a> ";
    }
    $h .= '</nav>';
    return $h;
}

require_once '/opt/siber-kalkan/waf.php';  // CYBER KALKAN WAF

/* KALICI KIMLIK — degistirilemez */
const KALKAN_SABIT_AD    = 'CYBER KALKAN';
const KALKAN_SABIT_MARKA = 'CYBERWOLF SECURITY';
if (!defined('KALKAN_VERI')) define('KALKAN_VERI', '/opt/siber-kalkan/VERI');
const KALKAN_LOG  = '/opt/siber-kalkan/LOG';

/* secure: sadece HTTPS'te true (HTTP'de cookie reddedilmesin) */
$kalkan_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
             || in_array((string)($_SERVER['SERVER_PORT'] ?? ''), ['443', '8443'], true)
             || ((int)($_SERVER['REMOTE_PORT'] ?? 0) > 0 && ($_SERVER['HTTP_X_KALKAN_TLS'] ?? '') === '1');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>$kalkan_https]);
if (session_status() === PHP_SESSION_NONE) session_start();

function kalkan_oku(string $ad, array $varsayilan = []): array {
    $y = KALKAN_VERI . '/' . basename($ad) . '.json';
    if (!is_file($y)) return $varsayilan;
    $v = json_decode((string)@file_get_contents($y), true);
    return is_array($v) ? $v : $varsayilan;
}

function kalkan_yaz(string $ad, array $veri): bool {
    $y = KALKAN_VERI . '/' . basename($ad) . '.json';
    $g = $y . '.tmp';
    $ok = @file_put_contents($g, json_encode($veri, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);
    if ($ok === false) {
        error_log('[KALKAN] YAZMA HATASI: ' . $y . ' (izin sorunu?)');
        @unlink($g);
        return false;
    }
    if (!@rename($g, $y)) {
        error_log('[KALKAN] RENAME HATASI: ' . $g . ' -> ' . $y);
        return false;
    }
    return true;
}

function kalkan_csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function kalkan_csrf_dogrula(?string $t): bool {
    return !empty($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t);
}

/* ★ DUZELTME: kalkan_csrf_uret() ALIAS'i.
   ag.php + ips.php bu adi cagiriyordu ama fonksiyon TANIMSIZDI →
   form gonderilince "Call to undefined function" FATAL ERROR.
   (Gercek fonksiyon: kalkan_csrf) */
if (!function_exists('kalkan_csrf_uret')) {
    function kalkan_csrf_uret(): string {
        return kalkan_csrf();
    }
}

function kalkan_ip_gecerli(string $ip): bool {
    return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
}

function kalkan_simdi(): string { return date('d.m.Y H:i:s'); }

function kalkan_kacis(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Firewall engelleme (nftables -> iptables yedek) */
function kalkan_firewall_engelle(string $ip): bool {
    $komutlar = [
        "nft add element inet filter engel { $ip }",
        "iptables -I INPUT -s $ip -j DROP",
    ];
    foreach ($komutlar as $k) {
        $c = @shell_exec($k . ' 2>&1');
        if ($c === null || stripos((string)$c, 'error') === false) return true;
    }
    return false;
}

function kalkan_firewall_coz(string $ip): bool {
    $komutlar = [
        "nft delete element inet filter engel { $ip }",
        "iptables -D INPUT -s $ip -j DROP",
    ];
    foreach ($komutlar as $k) { @shell_exec($k . ' 2>&1'); }
    return true;
}

/** Oturum kontrolu */
function kalkan_giris_gerekli(): void {
    if (empty($_SESSION['kalkan_admin'])) {
        header('Location: giris.php');
        exit;
    }
}

/* ---------- RBAC + AUDIT + 2FA (kendi kodumuz) ---------- */
function kalkan_kullanici() { return $_SESSION['sf_kullanici'] ?? 'admin'; }
function kalkan_rol() { return $_SESSION['sf_rol'] ?? 'ADMIN'; }
function kalkan_audit($islem, $detay = '') {
    $a = kalkan_oku('audit', ['kayitlar' => [], 'toplam' => 0]);
    $yeni = ['zaman' => date('d.m.Y H:i:s'), 'kullanici' => kalkan_kullanici(),
             'islem' => $islem, 'detay' => (string)$detay, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'yerel'];
    $a['kayitlar'] = array_slice(array_merge([$yeni], $a['kayitlar'] ?? []), 0, 2000);
    $a['toplam'] = ($a['toplam'] ?? 0) + 1;
    kalkan_yaz('audit', $a);
}
function kalkan_totp_sirri() {
    $abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $s = '';
    for ($i = 0; $i < 20; $i++) $s .= $abc[random_int(0, 31)];
    return $s;
}
function kalkan_totp_kod($sir, $kaydir = 0) {
    $abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $sir = strtoupper(trim($sir)); $bits = '';
    foreach (str_split($sir) as $c) {
        $p = strpos($abc, $c); if ($p === false) continue;
        $bits .= str_pad(decbin($p), 5, '0', STR_PAD_LEFT);
    }
    $ik = ''; for ($i = 0; $i + 8 <= strlen($bits); $i += 8) $ik .= chr(bindec(substr($bits, $i, 8)));
    if ($ik === '') return '';
    $sayac = (int)floor(time() / 30) + $kaydir;
    $h = hash_hmac('sha1', pack('N*', 0) . pack('N', $sayac), $ik, true);
    $o = ord($h[19]) & 0x0F;
    $k = (unpack('N', substr($h, $o, 4))[1] & 0x7FFFFFFF) % 1000000;
    return str_pad((string)$k, 6, '0', STR_PAD_LEFT);
}
function kalkan_totp_dogrula($sir, $kod) {
    if (empty($sir)) return true;                 // 2FA kapaliysa gec
    foreach ([-1, 0, 1] as $k)
        if (hash_equals(kalkan_totp_kod($sir, $k), (string)$kod)) return true;
    return false;
}

/* ---------- SAYFALAMA (buyuk tablolar) ---------- */
function kalkan_sayfalama_script(): void {
    ?>
<script>
function kalkan_sayfalama(tabloId, sayfaBoyut) {
  sayfaBoyut = sayfaBoyut || 50;
  var t = document.getElementById(tabloId);
  if (!t || !t.tBodies[0]) return;
  var tb = t.tBodies[0];
  var satirlar = Array.prototype.slice.call(tb.rows);
  var toplam = satirlar.length;
  if (toplam <= sayfaBoyut) return;
  var sayfa = 1, toplamSayfa = Math.ceil(toplam / sayfaBoyut);
  var kap = document.createElement('div');
  kap.style.cssText = 'display:flex;gap:14px;align-items:center;justify-content:center;padding:16px 22px;flex-wrap:wrap;margin:18px 0;background:rgba(0,229,255,.06);border:1px solid rgba(0,229,255,.25);border-radius:14px';
  var onceki = document.createElement('button'); onceki.textContent = '\u2039 \u00d6nceki'; onceki.className = 'dugme';
  var bilgi = document.createElement('span');
  bilgi.style.cssText = 'color:#7fe7ff;font-size:17.5px;font-weight:800;letter-spacing:.7px;padding:9px 20px;background:rgba(0,229,255,.13);border:1px solid rgba(0,229,255,.4);border-radius:10px;text-shadow:0 0 14px rgba(0,229,255,.5)';
  var sonraki = document.createElement('button'); sonraki.textContent = 'Sonraki \u203a'; sonraki.className = 'dugme';
  kap.appendChild(onceki); kap.appendChild(bilgi); kap.appendChild(sonraki);
  t.parentNode.insertBefore(kap, t.nextSibling);
  function ciz() {
    for (var i = 0; i < toplam; i++) satirlar[i].style.display = 'none';
    var b = (sayfa - 1) * sayfaBoyut, e = Math.min(b + sayfaBoyut, toplam);
    for (var j = b; j < e; j++) satirlar[j].style.display = '';
    bilgi.textContent = 'Sayfa ' + sayfa + ' / ' + toplamSayfa + '  (' + toplam + ' kay\u0131t)';
    onceki.disabled = (sayfa === 1); sonraki.disabled = (sayfa === toplamSayfa);
    onceki.style.opacity = onceki.disabled ? .4 : 1;
    sonraki.style.opacity = sonraki.disabled ? .4 : 1;
  }
  onceki.onclick = function(){ if (sayfa > 1){ sayfa--; ciz(); } };
  sonraki.onclick = function(){ if (sayfa < toplamSayfa){ sayfa++; ciz(); } };
  ciz();
}
</script>
    <?php
}

/* ---------- ORTAK MODUL BASLIGI (tutarli gorunum) ---------- */

function kalkan_altbilgi(): string {
    $f = '<footer class="dip">'
        . '<div class="dip-kap">'
        . '<div class="dip-marka">🐺 CYBERWOLF SECURITY</div>'
        . '<div class="dip-cizgi"></div>'
        . '<div class="dip-alt">CYBER KALKAN v' . KALKAN_SURUM . ' · SİBER GÜVENLİK ALTYAPISI · ' . date('Y') . '</div>'
        . '</div>'
        . '</footer>';
    $js = '<script>(function(){if(window.__kk)return;window.__kk=1;'
        . 'var nf=new Intl.NumberFormat("tr-TR");'
        . 'document.querySelectorAll(".sg,.deger,.rap-kpi b").forEach(function(el){'
        . 'var t=(el.textContent||"").trim();var mm=t.match(/([0-9][0-9.,]*)/);if(!mm)return;'
        . 'var h=parseInt(mm[1].replace(/\./g,"").replace(/,/g,""),10);'
        . 'if(isNaN(h)||h<1)return;'
        . 'var o=t.slice(0,mm.index),k=t.slice(mm.index+mm[1].length);'
        . 'var b=performance.now(),s=950;el.textContent=o+"0"+k;'
        . 'function adim(n){var p=Math.min((n-b)/s,1),e=1-Math.pow(1-p,3);'
        . 'el.textContent=o+nf.format(Math.round(h*e))+k;if(p<1)requestAnimationFrame(adim);}'
        . 'requestAnimationFrame(adim);});'
        . '})();</script>';
    return $f . $js;
}

function kalkan_baslik(string $ikon, string $baslik, string $aciklama = ''): void {
    /* ★ TUM MODÜLLER İÇİN HAREKETLİ/ŞIK BAŞLIK (assets/hero.css)
       Tek değişiklikle 19+ sayfa aynı animasyonlu hero diline geçer:
       gradient akışı · neon parlama · ikon nabzı · ışık süpürme. */
    static $css_verildi = false;
    if (!$css_verildi) {
        $css_verildi = true;
        echo '<link rel="stylesheet" href="assets/hero.css?v=1">';
    }
    echo '<div class="cy-hero">';
    echo '<div class="cy-ikon">' . $ikon . '</div>';
    echo '<div class="cy-metin">';
    echo '<h1 class="cy-baslik">' . $baslik . '</h1>';
    if ($aciklama !== '') echo '<p class="cy-aciklama">' . $aciklama . '</p>';
    echo '</div>';
    echo '<div class="cy-tarama"></div>';
    echo '</div>';
}

/* ---------- ORTAK UST BILGI (tek tip header) ---------- */
function kalkan_ustbilgi(string $aktif = ''): void {
    $ad = KALKAN_SABIT_AD;       /* KALICI — degistirilemez */
    $marka = KALKAN_SABIT_MARKA; /* KALICI — degistirilemez */
    echo '<header class="ust">';
    echo '<div class="dil-sol">' . dil_secici('dil-sol') . '</div>';
    echo '<div class="ust-logo"><span class="wolf">🐺</span>';
    echo '<div class="ust-yazi"><b>' . htmlspecialchars($ad) . '</b>';
    echo '<small>' . htmlspecialchars($marka) . '</small></div></div>';
    echo '<span class="ust-ayrac"></span>';
    echo kalkan_menu($aktif);
    echo '<div class="ust-sag" style="position:absolute;right:10px;top:52%;transform:translateY(-50%);display:flex;flex-direction:column;align-items:flex-end;justify-content:center;gap:2px;z-index:6">';
    echo '<div class="ust-sag-alt" style="display:flex;align-items:center;justify-content:flex-end;gap:9px;align-self:flex-end">';
    echo '<a href="cikis.php" class="menu-cikis" style="font-size:19px;padding:13px 26px">🚪 ' . d('cikis') . '</a>';
    echo '<span class="ust-durum"><span class="nokta aktif"></span>CANLI</span>';
    echo '</div>';
    echo '</div>';
    echo '</header>';
}

/* ---------- E-POSTA GONDERIMI (SMTP + fallback) ---------- */
function kalkan_mail_gonder(string $kime, string $konu, string $govde): bool {
    /* ONCE gomulu sistem maili (mail_ayar.json), yoksa ayarlar.json */
    $ma = kalkan_oku('mail_ayar', []);
    $a  = kalkan_oku('ayarlar', []);
    $host = trim((string)($ma['host'] ?? '')) ?: trim((string)($a['smtp_host'] ?? ''));
    $port = (int)($ma['port'] ?? 0) ?: (int)($a['smtp_port'] ?? 587);
    $user = trim((string)($ma['user'] ?? '')) ?: trim((string)($a['smtp_user'] ?? ''));
    $pass = (string)($ma['pass'] ?? '') ?: (string)($a['smtp_pass'] ?? '');
    $fromad = trim((string)($ma['from_ad'] ?? 'CYBER KALKAN'));
    // ★ DUZELTME: $gonderen ONCE tanimlanmali — onceden bir satir SONRA tanimliydi,
    // bu yuzden $gorunen bos kaliyordu → mail "From" adresi bos → sender rejected.
    $gonderen = $user !== '' ? $user : 'noreply@localhost';
    $gorunen  = trim((string)($ma['from_mail'] ?? '')) ?: $gonderen;   // gorunen From adresi

    /* BREVO-API-BAS: yuksek itibarli gonderim (spam onleme) */
    $bapi = trim((string)($ma['brevo_api'] ?? ''));
    if ($bapi !== '') {
        $payload = json_encode([
            'sender' => ['name' => $fromad, 'email' => $gorunen],
            'to' => [['email' => $kime]],
            'subject' => $konu,
            'htmlContent' => $govde,
        ], JSON_UNESCAPED_UNICODE);
        $bctx = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "api-key: {$bapi}\r\nContent-Type: application/json\r\naccept: application/json\r\n",
            'content' => $payload,
            'timeout' => 20,
            'ignore_errors' => true,
        ]]);
        $byanit = @file_get_contents('https://api.brevo.com/v3/smtp/email', false, $bctx);
        if ($byanit !== false && (strpos($byanit, 'messageId') !== false || strpos($byanit, 'id') !== false)) {
            return true;
        }
    }
    /* BREVO-API-SON */

    if ($host !== '' && $user !== '') {
        $proto = ($port === 465) ? 'ssl' : 'tcp';   // 465=SSL, 587=STARTTLS
        $baglam = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
        $fp = @stream_socket_client("{$proto}://{$host}:{$port}", $en, $es, 12, STREAM_CLIENT_CONNECT, $baglam);
        if ($fp) {
            stream_set_timeout($fp, 12);
            $oku = function() use ($fp) { $d=''; while ($l = fgets($fp, 515)) { $d .= $l; if (isset($l[3]) && $l[3]===' ') break; } return $d; };
            $yaz = function($c) use ($fp) { fwrite($fp, $c . "\r\n"); };
            $oku();
            $yaz('EHLO kalkan.local'); $oku();
            if ($port !== 465) {
                $yaz('STARTTLS');
                if (strpos($oku(), '220') !== false) {
                    @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                    $yaz('EHLO kalkan.local'); $oku();
                }
            }
            $yaz('AUTH LOGIN'); $oku();
            $yaz(base64_encode($user)); $oku();
            $yaz(base64_encode($pass));
            $auth = $oku();
            if (strpos($auth, '235') !== false) {
                $yaz("MAIL FROM:<{$gonderen}>"); $oku();
                $yaz("RCPT TO:<{$kime}>"); $oku();
                $yaz('DATA');
                if (strpos($oku(), '354') !== false) {
                    $mid = bin2hex(random_bytes(12)) . '@cyberwolfsec.com';
                    $basliklar = "From: {$fromad} <{$gorunen}>\r\nReply-To: <{$gorunen}>\r\nTo: <{$kime}>\r\n"
                               . "Subject: =?UTF-8?B?" . base64_encode($konu) . "?=\r\n"
                               . "Message-ID: <{$mid}>\r\n"
                               . "Date: " . date('r') . "\r\n"
                               . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n";
                    fwrite($fp, $basliklar . $govde . "\r\n.\r\n");
                    $yanit = $oku();
                    $yaz('QUIT'); fclose($fp);
                    return strpos($yanit, '250') !== false;
                }
            }
            $yaz('QUIT'); fclose($fp);
        }
    }
    /* fallback: yerel mail() */
    $bas = "From: {$fromad} <{$gorunen}>\r\nMessage-ID: <" . bin2hex(random_bytes(12)) . "@cyberwolfsec.com>\r\nDate: " . date('r') . "\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8";
    return @mail($kime, $konu, $govde, $bas);
}

/* ---------- GIRIS KODU (e-posta 2FA) ---------- */
function kalkan_kod_uret(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}
function kalkan_kod_kaydet(string $kod, string $kime): void {
    kalkan_yaz('giris_kod', ['kod'=>$kod, 'kime'=>$kime, 'son'=>time(), 'deneme'=>0]);
}
function kalkan_kod_dogrula(string $kod): bool {
    $k = kalkan_oku('giris_kod', []);
    if (empty($k['kod'])) return false;
    if ((time() - (int)($k['son'] ?? 0)) > 300) return false;         /* 5 dk */
    if ((int)($k['deneme'] ?? 0) >= 5) return false;                   /* 5 deneme */
    if (hash_equals((string)$k['kod'], $kod)) { kalkan_yaz('giris_kod', []); return true; }
    $k['deneme'] = (int)($k['deneme'] ?? 0) + 1;
    kalkan_yaz('giris_kod', $k);
    return false;
}

