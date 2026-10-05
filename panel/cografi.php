<?php
declare(strict_types=1);
/* COGRAFI — gercek dunya haritasi (Leaflet + OpenStreetMap) */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();

$cg = kalkan_oku('cografya', ['ip_ulke' => [], 'ulkeler' => []]);
$ulkeler = $cg['ulkeler'] ?? [];
if (!is_array($ulkeler)) $ulkeler = [];

/* ulke adlari + koordinatlar */
$ULKE = [
 'US'=>['ABD',39.8,-98.6],'CN'=>['Çin',35.9,104.2],'SG'=>['Singapur',1.35,103.8],'NL'=>['Hollanda',52.13,5.29],
 'VN'=>['Vietnam',14.06,108.28],'RU'=>['Rusya',61.5,105.3],'DE'=>['Almanya',51.2,10.4],'GB'=>['İngiltere',55.4,-3.4],
 'FR'=>['Fransa',46.2,2.2],'IN'=>['Hindistan',20.6,79.0],'BR'=>['Brezilya',-14.2,-51.9],'KR'=>['G.Kore',35.9,127.8],
 'JP'=>['Japonya',36.2,138.3],'IR'=>['İran',32.4,53.7],'TR'=>['Türkiye',38.96,35.24],'ID'=>['Endonezya',-0.79,113.9],
 'TH'=>['Tayland',15.87,100.99],'MY'=>['Malezya',4.21,101.98],'PH'=>['Filipinler',12.88,121.77],'HK'=>['Hong Kong',22.32,114.17],
 'TW'=>['Tayvan',23.7,120.96],'UA'=>['Ukrayna',48.38,31.17],'PL'=>['Polonya',51.92,19.15],'IT'=>['İtalya',41.87,12.57],
 'ES'=>['İspanya',40.46,-3.75],'SE'=>['İsveç',60.13,18.64],'CH'=>['İsviçre',46.82,8.23],'CA'=>['Kanada',56.13,-106.35],
 'AU'=>['Avustralya',-25.27,133.78],'ZA'=>['G.Afrika',-30.56,22.94],'AE'=>['BAE',23.42,53.85],'SA'=>['S.Arabistan',23.89,45.08],
 'IL'=>['İsrail',31.05,34.85],'EG'=>['Mısır',26.82,30.80],'NG'=>['Nijerya',9.08,8.68],'KE'=>['Kenya',-0.02,37.91],
 'AR'=>['Arjantin',-38.42,-63.62],'MX'=>['Meksika',23.63,-102.55],'CO'=>['Kolombiya',4.57,-74.30],'CL'=>['Şili',-35.68,-71.54],
 'PE'=>['Peru',-9.19,-75.02],'PK'=>['Pakistan',30.38,69.35],'BD'=>['Bangladeş',23.68,90.36],'LK'=>['Sri Lanka',7.87,80.77],
 'KZ'=>['Kazakistan',48.02,66.92],'UZ'=>['Özbekistan',41.38,64.59],'AZ'=>['Azerbaycan',40.14,47.58],'GE'=>['Gürcistan',42.32,43.36],
 'RO'=>['Romanya',45.94,24.97],'BG'=>['Bulgaristan',42.73,25.49],'GR'=>['Yunanistan',39.07,21.82],'PT'=>['Portekiz',39.4,-8.22],
 'IE'=>['İrlanda',53.41,-8.24],'NO'=>['Norveç',60.47,8.47],'FI'=>['Finlandiya',61.92,25.75],'DK'=>['Danimarka',56.26,9.50],
 'CZ'=>['Çekya',49.82,15.47],'AT'=>['Avusturya',47.52,14.55],'BE'=>['Belçika',50.50,4.47],'HU'=>['Macaristan',47.16,19.50],
 'SK'=>['Slovakya',48.67,19.70],'HR'=>['Hırvatistan',45.10,15.20],'RS'=>['Sırbistan',44.02,21.01],'LT'=>['Litvanya',55.17,23.88],
 'LV'=>['Letonya',56.88,24.60],'EE'=>['Estonya',58.60,25.01],'BY'=>['Belarus',53.71,27.95],'MD'=>['Moldova',47.41,28.37],
 'MA'=>['Fas',31.79,-7.09],'DZ'=>['Cezayir',28.03,1.66],'TN'=>['Tunus',33.89,9.54],'LY'=>['Libya',26.34,17.23],
 'ET'=>['Etiyopya',9.15,40.49],'TZ'=>['Tanzanya',-6.37,34.89],'GH'=>['Gana',7.95,-1.02],'AO'=>['Angola',-11.20,17.87],
 'MZ'=>['Mozambik',-18.67,35.53],'ZW'=>['Zimbabve',-19.02,29.15],'NZ'=>['Y.Zelanda',-40.90,174.89],'KH'=>['Kamboçya',12.57,104.99],
 'MM'=>['Myanmar',21.91,95.96],'MN'=>['Moğolistan',46.86,103.85],'IQ'=>['Irak',33.22,43.68],'JO'=>['Ürdün',30.59,36.24],
 'LB'=>['Lübnan',33.85,35.86],'KW'=>['Kuveyt',29.31,47.48],'QA'=>['Katar',25.35,51.18],'OM'=>['Umman',21.51,55.92],
 'YE'=>['Yemen',15.55,48.52],'SY'=>['Suriye',34.80,38.997],'SD'=>['Sudan',12.86,30.22],'UG'=>['Uganda',1.37,32.29],
 'CI'=>['Fildişi',7.54,-5.55],'CM'=>['Kamerun',7.37,12.35],'ZM'=>['Zambiya',-13.13,27.85],'AF'=>['Afganistan',33.94,67.71],
];

$harita = [];
$toplam_ip = 0;
foreach ($ulkeler as $u) {
    if (!is_array($u) || count($u) < 2) continue;
    $kod = (string)$u[0]; $sayi = (int)$u[1];
    $toplam_ip += $sayi;
    if (isset($ULKE[$kod])) {
        $harita[] = ['kod'=>$kod, 'ad'=>$ULKE[$kod][0], 'lat'=>$ULKE[$kod][1], 'lon'=>$ULKE[$kod][2], 'sayi'=>$sayi];
    } else {
        $harita[] = ['kod'=>$kod, 'ad'=>$kod, 'lat'=>0, 'lon'=>0, 'sayi'=>$sayi];
    }
}
usort($harita, fn($a,$b) => $b['sayi'] <=> $a['sayi']);
$max = $harita ? max(array_column($harita,'sayi')) : 1;
?><!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Harita · CYBER KALKAN</title>
<link rel="stylesheet" href="assets/panel.css?v=1.7">
<link rel="stylesheet" href="assets/leaflet.css">
<script src="assets/leaflet.js"></script>
</head><body>
<?= kalkan_ustbilgi('cografi.php') ?>
<main>
<?php kalkan_baslik('🌍', 'Saldırı Kaynağı Haritası', 'Gerçek dünya haritası üzerinde tehdit kaynakları'); ?>

<div class="kartlar">
  <div class="kart vurgu"><div class="et">Tespit Edilen Ülke</div><div class="sg"><?= count($harita) ?></div><div class="alt">kaynak</div></div>
  <div class="kart kritik"><div class="et">Toplam Saldırı</div><div class="sg"><?= number_format($toplam_ip,0,',','.') ?></div><div class="alt">IP olayı</div></div>
  <div class="kart yuksek"><div class="et">En Yoğun</div><div class="sg" style="font-size:24px"><?= htmlspecialchars($harita[0]['ad'] ?? '-') ?></div><div class="alt"><?= (int)($harita[0]['sayi'] ?? 0) ?> olay</div></div>
  <div class="kart iyi"><div class="et">Durum</div><div class="sg" style="font-size:22px">İZLENİYOR</div><div class="alt"><span id="nk-ulke">— ülke</span> · gerçek zamanlı</div></div>
</div>

<section class="panel-kutu">
  <h2 class="bolum">🗺️ Canlı Tehdit Haritası <span class="sayi"><?= count($harita) ?> nokta</span>
     <span class="nk-canli" style="margin-left:12px;font-size:15.5px;vertical-align:middle">● CANLI ·
       <b id="nk-sayi">0</b> saldırı · <span id="nk-zaman">--:--:--</span></span></h2>
  <div id="harita" style="height:560px;background:#0b0f17"></div>
</section>

<div class="ikili">
  <section class="panel-kutu">
    <h2 class="bolum">📊 Ülke Dağılımı</h2>
    <table class="tablo"><thead><tr><th>ÜLKE</th><th>KOD</th><th>SALDIRI</th><th>ORAN</th></tr></thead><tbody>
    <?php foreach (array_slice($harita, 0, 20) as $h): $oran = $max > 0 ? (int)round($h['sayi']*100/$max) : 0; ?>
      <tr>
        <td><b><?= htmlspecialchars($h['ad']) ?></b></td>
        <td class="mono"><?= htmlspecialchars($h['kod']) ?></td>
        <td><b><?= $h['sayi'] ?></b></td>
        <td style="min-width:150px">
          <div style="background:var(--bg2);border-radius:5px;height:9px;overflow:hidden">
            <div style="width:<?= $oran ?>%;height:100%;background:linear-gradient(90deg,#00d4ff,#e74c3c)"></div>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($harita)): ?><tr><td colspan="4" style="text-align:center;color:var(--soluk)">Veri yok</td></tr><?php endif; ?>
    </tbody></table>
  </section>

  <section class="panel-kutu">
    <h2 class="bolum">🔴 En Aktif Kaynaklar</h2>
    <div style="padding:14px">
      <?php foreach (array_slice($harita, 0, 8) as $h): ?>
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--kenar)">
          <span><?= htmlspecialchars($h['ad']) ?> <span class="mono" style="color:var(--soluk)"><?= $h['kod'] ?></span></span>
          <span class="etiket kritik"><?= $h['sayi'] ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (empty($harita)): ?><div style="text-align:center;color:var(--soluk);padding:20px">Veri yok</div><?php endif; ?>
    </div>
  </section>
</div>
</main>
<?= kalkan_altbilgi() ?>

<script>
// LEAFLET — gercek dunya haritasi (koyu tema)
var harita = L.map('harita', {zoomControl:true, attributionControl:true, worldCopyJump:true})
  .setView([28, 20], 2);

// === RENKLI KATMANLAR (Esri — API key GEREKMEZ) ===
var uydu = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {attribution:'&copy; Esri', maxZoom:18});
var sokak = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {attribution:'&copy; Esri', maxZoom:18});
var topo = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {attribution:'&copy; Esri', maxZoom:18});
var deniz = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Ocean/World_Ocean_Base/MapServer/tile/{z}/{y}/{x}', {attribution:'&copy; Esri', maxZoom:13});
var koyu = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}', {attribution:'&copy; Esri', maxZoom:16});
// Yer isimleri (ulke/sehir) — uydu uzerine
var etiket = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {maxZoom:18, pane:'overlayPane', opacity:.85});

uydu.addTo(harita);          // VARSAYILAN: RENKLI UYDU
etiket.addTo(harita);
L.control.layers(
  { '🛰️ Uydu (renkli)': uydu, '🗺️ Sokak': sokak, '⛰️ Topografya': topo, '🌊 Okyanus': deniz, '🌑 Koyu': koyu },
  null, { position:'topright', collapsed:false }
).addTo(harita);

// === CANLI KATMAN (nabiz + otomatik guncelleme) ===
var veri = <?= json_encode($harita, JSON_UNESCAPED_UNICODE) ?>;   // ilk yukleme
var katmanlar = L.layerGroup().addTo(harita);
var oncekiToplam = 0;

function renk(s){ return s>=20 ? '#ff3b5c' : (s>=8 ? '#ff9f43' : '#4da3ff'); }

function ciz(veri, yeniGelen){
  katmanlar.clearLayers();
  veri.forEach(function(u){
    var r = u.sayi, cl = renk(r), yari = Math.min(7 + Math.log2(r+1)*4, 30);
    // SALDIRI dairesi (once → altta)
    var daire = L.circleMarker([u.lat,u.lon],{
      radius:yari, color:'#ffffff', weight:2.5,
      fillColor:cl, fillOpacity:.9, className:'nk-daire'
    }).addTo(katmanlar);
    // NEON NABIZ halkasi (SONRA → ustte, daireyi kapatmaz)
    var ikon = L.divIcon({className:'', iconSize:[80,80], iconAnchor:[40,40],
      html:'<span class="nk-nabiz" style="--nk:'+cl+';--rg:'+(yari+7)+'px"></span>'});
    L.marker([u.lat,u.lon],{icon:ikon, zIndexOffset:2000, interactive:false}).addTo(katmanlar);
    daire.bindPopup('<b style="font-size:17px">'+(u.ad||u.kod)+' ('+u.kod+')</b><br>'+
      '<span style="color:'+cl+';font-size:22px;font-weight:800">'+r+'</span> saldiri<br>'+
      '<span style="color:#8fa8c4;font-size:15.0px">son guncelleme: '+new Date().toLocaleTimeString('tr-TR')+'</span>');
    // YENI gelen ulke → PING efekti
    if (yeniGelen && yeniGelen[u.kod]) {
      var ping = L.circleMarker([u.lat,u.lon],{
        radius:yari+6, color:cl, weight:3, fill:false, opacity:1, className:'nk-ping'
      }).addTo(katmanlar);
      setTimeout(function(){ katmanlar.removeLayer(ping); }, 2600);
    }
  });
}

function canliYenile(){
  fetch('cografi_veri.php?_=' + Date.now(), {cache:'no-store'})
    .then(function(r){ return r.json(); })
    .then(function(d){
      var yeniGelen = null;
      if (oncekiToplam && d.toplam > oncekiToplam){
        yeniGelen = {}; d.veri.forEach(function(u){ yeniGelen[u.kod]=1; });
      }
      oncekiToplam = d.toplam;
      ciz(d.veri, yeniGelen);
      var z = document.getElementById('nk-zaman');
      if (z) z.textContent = d.zaman;
      var s = document.getElementById('nk-sayi');
      if (s){ s.textContent = d.toplam; s.classList.remove('nk-flas'); void s.offsetWidth; s.classList.add('nk-flas'); }
      var u = document.getElementById('nk-ulke');
      if (u) u.textContent = d.ulke + ' ülke';
    })
    .catch(function(){});
}

ciz(veri, null);
canliYenile();
setInterval(canliYenile, 8000);   // 8 saniyede bir canli guncelle

</script>
</body></html>
