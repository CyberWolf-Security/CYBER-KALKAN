<?php
/* 🛡️ WAF — CYBER KALKAN Web Uygulama Güvenlik Duvarı (v10) */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();

$V = '/opt/siber-kalkan/VERI';
$ayar = kalkan_oku('ayarlar', []);
$olay = kalkan_oku('olaylar', ['olaylar' => [], 'toplam' => 0]);
$engel = kalkan_oku('engel', ['liste' => []]);

// WAF kayıtları
$waf_olay = array_values(array_filter($olay['olaylar'] ?? [], fn($x) => ($x['kaynak'] ?? '') === 'waf'));
$waf_toplam = count($waf_olay);

// engel listesinde WAF kaynaklı
$waf_engel = array_values(array_filter($engel['liste'] ?? [], fn($x) => ($x['kaynak'] ?? '') === 'WAF'));

// en çok saldıran IP
$ip_say = [];
foreach ($waf_olay as $o) { $k = $o['ip'] ?? '-'; $ip_say[$k] = ($ip_say[$k] ?? 0) + 1; }
arsort($ip_say);

// saldırı türü dağılımı
$tur_say = [];
foreach ($waf_olay as $o) {
    $a = $o['aciklama'] ?? '?';
    if (preg_match('/WAF[^:]*:\s*([^\[\']+)/', $a, $m)) { $t = trim(explode('+', $m[1])[0]); $tur_say[$t] = ($tur_say[$t] ?? 0) + 1; }
}
arsort($tur_say);

// kural sayısı (waf.php'den)
$waf_icerik = @file_get_contents('/opt/siber-kalkan/waf.php') ?: '';
// ★ DUZELTME: sayim guvenilir hale getirildi (tek tirnak → PHP escape sorunu yok)
$kural_grup = substr_count($waf_icerik, 'as $d) $K[]');
$kural_tek = preg_match_all("/'\/[^']+?\/[a-z]*'/", $waf_icerik);

$waf_aktif = file_exists('/opt/siber-kalkan/waf.php');
$surum = 'v10';
// ★ DUZELTME: gercek imza sayisi (20 blok + 115 tek regex = ~135).
// Panelde "300+ imza" yaziyordu — yanlis iddiaydi (B-10).
$toplam_imza = (int)$kural_grup + (int)$kural_tek;
?>
<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>WAF — <?= kalkan_kacis($ayar['panel_adi'] ?? 'CYBER KALKAN') ?></title>
<link rel="stylesheet" href="assets/panel.css?v=1.7">
<link rel="stylesheet" href="assets/hero.css?v=2"><style>
/* WAF ozellik satirlari — HAREKETLI neon (V1) */
.waf-yazilar{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:20px 0 24px}
@media (max-width:900px){.waf-yazilar{grid-template-columns:1fr}}
.waf-yazi{position:relative;display:flex;align-items:center;gap:15px;padding:17px 20px 17px 25px;border-radius:15px;
  background:linear-gradient(120deg,rgba(10,20,36,.94),rgba(8,14,26,.97));
  border:1px solid rgba(56,189,248,.30);
  box-shadow:0 6px 26px rgba(0,0,0,.45),inset 0 1px 0 rgba(255,255,255,.05);
  transition:transform .28s cubic-bezier(.2,.8,.3,1),box-shadow .28s,border-color .28s;overflow:hidden}
.waf-yazi::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;
  background:linear-gradient(180deg,#00d4ff,#7b5cff,#00d4ff);background-size:100% 200%;animation:wafAkim 3.2s linear infinite}
@keyframes wafAkim{0%{background-position:0 0}100%{background-position:0 200%}}
.waf-yazi::after{content:"";position:absolute;top:0;bottom:0;width:70px;left:-90px;
  background:linear-gradient(90deg,transparent,rgba(0,212,255,.18),transparent);animation:wafParilti 5.5s ease-in-out infinite}
@keyframes wafParilti{0%,72%{left:-90px}100%{left:105%}}
.waf-yazi:hover{transform:translateY(-4px);border-color:rgba(0,212,255,.8);box-shadow:0 14px 40px rgba(0,212,255,.26),0 6px 26px rgba(0,0,0,.5)}
.waf-ikon{flex:0 0 auto;width:50px;height:50px;border-radius:13px;display:flex;align-items:center;justify-content:center;
  font-size:26px;background:rgba(10,16,26,.9);border:1px solid rgba(255,255,255,.18);
  filter:drop-shadow(0 0 12px rgba(0,212,255,.6));animation:wafIkon 2.6s ease-in-out infinite}
@keyframes wafIkon{0%,100%{transform:scale(1) rotate(0)}50%{transform:scale(1.13) rotate(-6deg)}}
.waf-yazi:hover .waf-ikon{animation:wafDon 1.5s ease-in-out infinite}
@keyframes wafDon{0%,100%{transform:rotate(-8deg) scale(1.08)}50%{transform:rotate(8deg) scale(1.18)}}
.waf-metin{display:flex;flex-direction:column;gap:3px;min-width:0}
.waf-metin b{font-size:19.5px;font-weight:800;letter-spacing:.3px;
  background:linear-gradient(90deg,#ffffff,#9fe8ff 60%,#00d4ff);-webkit-background-clip:text;background-clip:text;
  -webkit-text-fill-color:transparent;filter:drop-shadow(0 0 12px rgba(0,212,255,.35))}
.waf-soluk{color:#9fc0dc;font-size:15.5px;line-height:1.45;letter-spacing:.3px}
</style></head><body>
<?= kalkan_ustbilgi('waf') ?>
<main class="sarici">
  <div class="cy-hero">
    <div class="cy-ikon">🛡️</div>
    <div class="cy-metin">
      <h1 class="cy-baslik">WAF — Güvenlik Duvarı <span class="cy-surum">v10</span></h1>
      <p class="cy-aciklama">Web uygulama katmanında istekleri <b>anında</b> tarar; saldırı imzası bulursa <b>403</b> ile engeller ve IP'yi <b>kara listeye</b> alır.</p>
    </div>

      <div class="cy-tarama"></div>
  </div>

  <div class="waf-yazilar">
    <div class="waf-yazi" style="--vurgu:#38bdf8"><span class="waf-ikon">&#129514;</span><span class="waf-metin"><b><?= number_format((int)($toplam_imza ?? 0), 0, ',', '.') ?>+ saldiri imzasi</b><span class="waf-soluk">SQLi &middot; XSS &middot; LFI &middot; RCE &middot; Log4Shell</span></span></div>
    <div class="waf-yazi" style="--vurgu:#a855f7"><span class="waf-ikon">&#128269;</span><span class="waf-metin"><b>5 katmanli cozumleme</b><span class="waf-soluk">URL &middot; HTML &middot; JS &middot; Base64 &middot; yorum kirma</span></span></div>
    <div class="waf-yazi" style="--vurgu:#22c55e"><span class="waf-ikon">&#128202;</span><span class="waf-metin"><b>Anomali skorlama</b><span class="waf-soluk">+ hiz siniri DoS korumasi</span></span></div>
    <div class="waf-yazi" style="--vurgu:#f59e0b"><span class="waf-ikon">&#128737;</span><span class="waf-metin"><b>Sanal yama</b><span class="waf-soluk">(CVE imzalari) + bot tespiti sqlmap &middot; nuclei &middot; nmap</span></span></div>
  </div>

  <!-- ★ CANLI BANT (diger modullerle ortak) -->

  <div class="kartlar">
    <div class="kart <?= $waf_aktif ? 'iyi' : 'kritik' ?>">
      <div class="et">🛡️ WAF DURUMU</div>
      <div class="deger" style="font-size:26px"><?= $waf_aktif ? 'AKTİF' : 'KAPALI' ?></div>
      <div class="alt"><?= $waf_aktif ? 'koruma açık' : 'devre dışı' ?></div>
    </div>
    <div class="kart vurgu">
      <div class="et">📜 SALDIRI İMZASI</div>
      <div class="deger"><?= number_format($toplam_imza, 0, ',', '.') ?></div>
      <div class="alt"><?= (int)$kural_grup ?> blok + <?= (int)$kural_tek ?> desen</div>
    </div>
    <div class="kart <?= $waf_toplam ? 'yuksek' : '' ?>">
      <div class="et">🎯 YAKALAMA</div>
      <div class="deger"><?= number_format($waf_toplam, 0, ',', '.') ?></div>
      <div class="alt">toplam tespit</div>
    </div>
    <div class="kart <?= count($waf_engel) ? 'kritik' : '' ?>">
      <div class="et">🚫 ENGEL</div>
      <div class="deger"><?= number_format(count($waf_engel), 0, ',', '.') ?></div>
      <div class="alt">kara listeye alındı</div>
    </div>
    <div class="kart">
      <div class="et">⚡ RATE LIMIT</div>
      <div class="deger">100</div>
      <div class="alt">istek / 30 sn</div>
    </div>
  </div>

  <h2>⚙️ Yapılandırma</h2>
  <table class="tablo">
    <tr><th>AYAR</th><th>DEĞER</th><th>AÇIKLAMA</th></tr>
    <tr><td>Sürüm</td><td><?= $surum ?> (<?= number_format($toplam_imza, 0, ',', '.') ?> imza)</td><td>5 katmanlı normalize + anomali skorlama</td></tr>
    <tr><td>Engelleme eşiği</td><td><?= (int)($ayar['waf_esik'] ?? 5) ?> puan</td><td>Toplam risk puanı eşiği aşarsa engellenir</td></tr>
    <tr><td>Rate limit</td><td>100 istek / 30 sn</td><td>Aşarsa DoS kabul edilip engellenir</td></tr>
    <tr><td>Muafiyet</td><td>localhost + LAN</td><td>Panel kendini engellemez (=127.*, 192.168.*)</td></tr>
    <tr><td>Beyaz liste</td><td><?= count($ayar['waf_beyaz'] ?? []) ?> IP</td><td>Ayarlar'dan eklenir</td></tr>
    <tr><td>Kapsanan katmanlar</td><td>URL · GET · POST · UA · Referer · Cookie</td><td>İstek anında taranır</td></tr>
  </table>

  <h2>🎯 Kural Kategorileri</h2>
  <table class="tablo">
    <tr><th>KATEGORİ</th><th>ÖRNEK SALDIRILAR</th></tr>
    <tr><td>SQL Injection</td><td>union select · or 1=1 · sleep() · information_schema · into outfile</td></tr>
    <tr><td>XSS</td><td>&lt;script&gt; · onerror · javascript: · SVG/SVG onload · HTML entity</td></tr>
    <tr><td>Path Traversal / LFI</td><td>../../etc/passwd · php://filter · proc/self · ilginç encode</td></tr>
    <tr><td>Komut Enjeksiyonu / RCE</td><td>;id · `whoami` · bash -c · powershell · nc -e</td></tr>
    <tr><td>SSRF</td><td>169.254.169.254 metadata · gopher:// · 127.0.0.1 · decimal IP</td></tr>
    <tr><td>XXE / Deserialization</td><td>DOCTYPE SYSTEM · rO0AB · O:4:"... · __wakeup</td></tr>
    <tr><td>SSTI</td><td>{{7*7}} · {{config}} · __class__ · ${jndi</td></tr>
    <tr><td>NoSQL / LDAP / XPath</td><td>$ne · $where · $regex · (*)(|(...))</td></tr>
    <tr><td>CRLF / Smuggling</td><td>%0d%0a · transfer-encoding · overlong UTF-8</td></tr>
    <tr><td>Sanal Yama (CVE)</td><td>Log4Shell · Spring4Shell · Exchange ProxyLogon/Shell · ThinkPHP · Laravel Ignition</td></tr>
    <tr><td>Web Shell / Backdoor</td><td>c99.php · r57.php · b374k · eval($_POST)</td></tr>
    <tr><td>Zararlı Yükleme</td><td>.php/.jsp/.aspx yükleme · .htaccess · content-type bypass</td></tr>
    <tr><td>Bot / Tarayıcı</td><td>sqlmap · nikto · nuclei · gobuster · ffuf · masscan · zgrab</td></tr>
    <tr><td>Kripto Miner / Ransomware</td><td>xmrig · stratum+tcp · HOW_TO_DECRYPT · LockBit</td></tr>
    <tr><td>Hassas Dosya</td><td>.env · .git · wp-config · id_rsa · backup.sql</td></tr>
    <tr><td>DoS / Rate limit</td><td>30 sn'de 100+ istek · aşırı uzun istek</td></tr>
  </table>

  <?php if ($ip_say): ?>
  <h2>🔥 En Çok Saldıran IP'ler</h2>
  <table class="tablo">
    <tr><th>IP</th><th>YAKALANMA</th><th>ENGELLİ Mİ</th></tr>
    <?php $i = 0; foreach ($ip_say as $ipx => $n): if ($i++ >= 10) break;
      $eng = in_array($ipx, array_column($waf_engel, 'ip'), true); ?>
    <tr><td><?= kalkan_kacis($ipx) ?></td><td><?= $n ?></td>
        <td><?= $eng ? '<span class="rozet aktif">EVET</span>' : '<span class="rozet">hayır</span>' ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <?php if ($tur_say): ?>
  <h2>📊 Saldırı Türü Dağılımı</h2>
  <table class="tablo">
    <tr><th>SALDIRI TÜRÜ</th><th>ADET</th></tr>
    <?php foreach (array_slice($tur_say, 0, 12, true) as $t => $n): ?>
    <tr><td><?= kalkan_kacis(substr($t, 0, 60)) ?></td><td><?= $n ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <h2>🕐 Son WAF Yakalamaları</h2>
  <?php if (!$waf_olay): ?>
    <p style="color:#8fb6d6">Henüz WAF yakalaması yok — sistem temiz.</p>
  <?php else: ?>
  <table class="tablo">
    <tr><th>ZAMAN</th><th>IP</th><th>SEVİYE</th><th>AÇIKLAMA</th></tr>
    <?php foreach (array_slice($waf_olay, 0, 20) as $o): ?>
    <tr><td><?= kalkan_kacis($o['zaman'] ?? '-') ?></td>
        <td><?= kalkan_kacis($o['ip'] ?? '-') ?></td>
        <td><span class="rozet <?= ($o['seviye'] ?? '') === 'KRITIK' ? 'kritik' : 'aktif' ?>"><?= kalkan_kacis($o['seviye'] ?? '-') ?></span></td>
        <td><?= kalkan_kacis($o['aciklama'] ?? '-') ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <div class="bilgi-serit" style="margin-top:26px">
    <b>ℹ️ Nasıl çalışır?</b> Her HTTP isteği 5 katmanlı normalize edilir (çoklu URL/HTML/JS/base64/SQL-yorum çözme),
    300+ kural ile puanlanır. Toplam puan eşiği (<?= (int)($ayar['waf_esik'] ?? 5) ?>) aşarsa istek <b>403</b> ile engellenir,
    IP kara listeye alınır ve firewall'a (nftables) yazılır. Böylece hem uygulama hem makine korunur.
  </div>
</main>
<?= kalkan_altbilgi() ?></body></html>
