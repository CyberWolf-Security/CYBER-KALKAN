<?php
declare(strict_types=1);
/* DASHBOARD — CYBER KALKAN */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();

$ist  = kalkan_oku('istatistik', []);
$eng  = kalkan_oku('engel', ['liste' => [], 'toplam' => 0]);
$olay = kalkan_oku('olaylar', ['olaylar' => [], 'toplam' => 0]);
$kur  = kalkan_oku('kurallar', ['kurallar' => []]);
$fim  = kalkan_oku('fim', ['degisimler' => [], 'dosyalar' => []]);

$olaylar = $olay['olaylar'] ?? [];
if (!is_array($olaylar)) $olaylar = [];
$olaylar = array_reverse($olaylar);              // en yeni ilk

/* --- Seviye dagilimi --- */
$sev = ['KRITIK' => 0, 'YUKSEK' => 0, 'ORTA' => 0, 'DUSUK' => 0];
foreach ($olaylar as $o) {
    $s = strtoupper((string)($o['seviye'] ?? 'ORTA'));
    if (!isset($sev[$s])) $s = 'ORTA';
    $sev[$s]++;
}

/* --- Son 24 saat trendi (saatlik) --- */
$saatlik = array_fill(0, 24, 0);
$simdi = time();
foreach ($olaylar as $o) {
    $zs = (string)($o['zaman'] ?? '');
    $t = strtotime($zs);
    if ($t === false && preg_match('/^(\d{2})\.(\d{2})\.(\d{4}) (\d{2}):(\d{2}):(\d{2})$/', $zs, $mz)) {
        $t = mktime((int)$mz[4],(int)$mz[5],(int)$mz[6],(int)$mz[2],(int)$mz[1],(int)$mz[3]);
    }
    if ($t && ($simdi - $t) < 86400) {
        $saatlik[(int)date('G', $t)]++;
    }
}
ksort($saatlik);

/* --- Kategori dagilimi (ilk 8) --- */
$kat = [];
foreach ($olaylar as $o) {
    $k = (string)($o['kategori'] ?? $o['kural_kategori'] ?? $o['kaynak'] ?? 'Diğer');
    if ($k === '') $k = 'Diğer';
    $kat[$k] = ($kat[$k] ?? 0) + 1;
}
arsort($kat);
$kat = array_slice($kat, 0, 8, true);

/* --- Top kaynak IP (ilk 8) --- */
$ip = [];
foreach ($olaylar as $o) {
    $x = (string)($o['ip'] ?? $o['kaynak_ip'] ?? '');
    if ($x !== '') $ip[$x] = ($ip[$x] ?? 0) + 1;
}
arsort($ip);
$ip = array_slice($ip, 0, 8, true);

/* --- MITRE teknikleri --- */
$mitre = [];
foreach ($olaylar as $o) {
    $mm = $o['mitre'] ?? [];
    if (is_string($mm)) { $mm = preg_split('/[,\s]+/', $mm, -1, PREG_SPLIT_NO_EMPTY) ?: []; }
    foreach ((array)$mm as $m) {
        if (is_string($m) && preg_match('/^T\d{4}/', $m)) $mitre[$m] = ($mitre[$m] ?? 0) + 1;
    }
}
arsort($mitre);
$mitre_top = array_slice($mitre, 0, 12, true);

$toplam_olay = (int)($olay['toplam'] ?? count($olaylar));
$engel_sayi  = count($eng['liste'] ?? []);
$kural_sayi  = count($kur['kurallar'] ?? []);
?>
<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Panel · CYBER KALKAN</title>
<link rel="stylesheet" href="assets/panel.css?v=1.7">
<script src="assets/chart.min.js?v=6.2"></script>
</head><body>
<?= kalkan_ustbilgi('index.php') ?>
<main>

<?php kalkan_baslik('📊', d('sy_panel'), d('sy_panel_alt')); ?>
<!-- ★ Kaldirildi: canli-serit artik kalkan_baslik() icinde basiliyor (cift olmasin) -->

<!-- KPI KARTLARI -->
<div class="kartlar">
  <div class="kart vurgu"><div class="et"><?= d('kpi_toplam') ?></div><div class="sg"><?= number_format($toplam_olay, 0, ',', '.') ?></div>
    <div class="alt"><?= count($olaylar) ?> <?= d('kpi_kayit') ?></div></div>
  <div class="kart kritik"><div class="et"><?= d('kpi_kritik') ?></div><div class="sg"><?= $sev['KRITIK'] ?></div>
    <div class="alt"><?= d('kpi_acil') ?></div></div>
  <div class="kart yuksek"><div class="et"><?= d('kpi_yuksek') ?></div><div class="sg"><?= $sev['YUKSEK'] ?></div>
    <div class="alt"><?= d('kpi_inceleme') ?></div></div>
  <div class="kart"><div class="et"><?= d('kpi_engellenen') ?></div><div class="sg"><?= number_format($engel_sayi, 0, ',', '.') ?></div>
    <div class="alt"><?= d('kpi_karaliste') ?></div></div>
  <div class="kart iyi"><div class="et">Aktif Kural</div><div class="sg"><?= number_format($kural_sayi, 0, ',', '.') ?></div>
    <div class="alt">imza kütüphanesi</div></div>
  <div class="kart mor"><div class="et">FIM Değişim</div><div class="sg"><?= (int)($fim['toplam'] ?? count($fim['degisimler'] ?? [])) ?></div>
    <div class="alt">dosya bütünlüğü</div></div>
</div>

<!-- GRAFIKLER -->
<div class="grafikler">
  <div class="panel-kutu">
    <h2 class="bolum">📈 <?= d('sy_olay_trendi') ?> <span class="sayi"><?= d('sy_saatlik') ?></span></h2>
    <div class="grafik-kapak"><canvas id="g_trend"></canvas></div>
  </div>
  <div class="panel-kutu">
    <h2 class="bolum">🎯 <?= d('sy_seviye_dag') ?></h2>
    <div class="grafik-kapak"><canvas id="g_seviye"></canvas></div>
  </div>
</div>

<div class="grafikler">
  <div class="panel-kutu">
    <h2 class="bolum">📊 <?= d('sy_kategori') ?></h2>
    <div class="grafik-kapak"><canvas id="g_kat"></canvas></div>
  </div>
  <div class="panel-kutu">
    <h2 class="bolum">🌐 <?= d('sy_top_ip') ?></h2>
    <div class="grafik-kapak"><canvas id="g_ip"></canvas></div>
  </div>
</div>

<!-- CANLI AKIS + MITRE -->
<div class="ikili">
  <div class="panel-kutu">
    <h2 class="bolum">⚡ <?= d('sy_canli_akis') ?> <span class="sayi"><?= d('sy_son') ?> <?= min(20, count($olaylar)) ?></span></h2>
    <div class="akis">
    <?php if (empty($olaylar)): ?>
      <div style="padding:30px;text-align:center;color:var(--soluk)">Henüz olay yok ✓</div>
    <?php else: ?>
      <?php foreach (array_slice($olaylar, 0, 20) as $o): ?>
        <?php $sv = strtoupper((string)($o['seviye'] ?? 'ORTA'));
              $sc = ['KRITIK'=>'kritik','YUKSEK'=>'yuksek','ORTA'=>'orta','DUSUK'=>'dusuk'][$sv] ?? 'orta'; ?>
        <div class="akis-satir">
          <span class="zm"><?= htmlspecialchars(substr((string)($o['zaman'] ?? '-'), 11, 8)) ?></span>
          <span class="etiket <?= $sc ?>"><?= $sv ?></span>
          <span class="ip"><?= htmlspecialchars((string)($o['ip'] ?? $o['kaynak_ip'] ?? '-')) ?></span>
          <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <?= htmlspecialchars((string)($o['aciklama'] ?? $o['kural'] ?? $o['mesaj'] ?? '-')) ?></span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    </div>
  </div>

  <div class="panel-kutu">
    <h2 class="bolum">🧩 <?= d('sy_mitre') ?> <span class="sayi"><?= count($mitre) ?> <?= d('sy_teknik') ?></span></h2>
    <div style="padding:14px">
      <?php if (empty($mitre_top)): ?>
        <div style="text-align:center;color:var(--soluk);padding:20px">Teknik verisi yok</div>
      <?php else: ?>
        <div class="mitre">
        <?php foreach ($mitre_top as $m => $n): ?>
          <div class="mitre-hucre"><div class="tid"><?= htmlspecialchars($m) ?></div><div class="sayi"><?= $n ?></div></div>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

</main>

<script>
Chart.defaults.color = '#8296b0';
Chart.defaults.borderColor = '#243044';
Chart.defaults.font.family = "'Segoe UI',system-ui,sans-serif";
const IZGARA = {grid:{color:'#1e2a3d'},ticks:{color:'#8296b0'}};

// 1) Trend
new Chart(document.getElementById('g_trend'), {
  type:'line',
  data:{labels:<?= json_encode(array_map(fn($h)=>sprintf('%02d:00',$h), array_keys($saatlik))) ?>,
    datasets:[{label:'Olay',data:<?= json_encode(array_values($saatlik)) ?>,
      borderColor:'#00d4ff',backgroundColor:'rgba(0,212,255,.12)',fill:true,tension:.35,
      pointBackgroundColor:'#00d4ff',pointRadius:2,borderWidth:2}]},
  options:{responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:false}},
    scales:{x:IZGARA,y:{...IZGARA,beginAtZero:true,ticks:{...IZGARA.ticks,precision:0}}}}
});

// 2) Seviye (halka)
new Chart(document.getElementById('g_seviye'), {
  type:'doughnut',
  data:{labels:['Kritik','Yüksek','Orta','Düşük'],
    datasets:[{data:[<?= $sev['KRITIK'] ?>,<?= $sev['YUKSEK'] ?>,<?= $sev['ORTA'] ?>,<?= $sev['DUSUK'] ?>],
      backgroundColor:['#e74c3c','#f39c12','#f1c40f','#3aa0ff'],borderColor:'#151d2c',borderWidth:3}]},
  options:{responsive:true,maintainAspectRatio:false,cutout:'62%',
    plugins:{legend:{position:'bottom',labels:{padding:14,usePointStyle:true}}}}
});

// 3) Kategori (yatay cubuk)
new Chart(document.getElementById('g_kat'), {
  type:'bar',
  data:{labels:<?= json_encode(array_keys($kat)) ?>,
    datasets:[{label:'Olay',data:<?= json_encode(array_values($kat)) ?>,
      backgroundColor:'rgba(0,212,255,.55)',borderColor:'#00d4ff',borderWidth:1,borderRadius:4}]},
  options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:false}},
    scales:{x:{...IZGARA,beginAtZero:true},y:{...IZGARA,ticks:{...IZGARA.ticks,font:{size:11}}}}}
});

// 4) Top IP (cubuk)
new Chart(document.getElementById('g_ip'), {
  type:'bar',
  data:{labels:<?= json_encode(array_keys($ip)) ?>,
    datasets:[{label:'Olay',data:<?= json_encode(array_values($ip)) ?>,
      backgroundColor:'rgba(231,76,60,.55)',borderColor:'#e74c3c',borderWidth:1,borderRadius:4}]},
  options:{responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:false}},
    scales:{x:{...IZGARA,ticks:{...IZGARA.ticks,font:{size:9},maxRotation:45}},
            y:{...IZGARA,beginAtZero:true}}}
});
</script>
<?= kalkan_altbilgi() ?>

<script>
/* ══ CANLI GÜNCELLEME — 15 saniyede bir (KALKAN_CANLI) ══ */
const KALKAN_CANLI = 15000;
setInterval(function () {
  if (document.hidden) return;
  fetch('index.php?canli=1', { cache: 'no-store' })
    .then(function (r) { return r.ok ? r.text() : null; })
    .then(function (h) {
      if (!h) return;
      const yeni = new DOMParser().parseFromString(h, 'text/html');
      document.querySelectorAll('[data-canli]').forEach(function (el) {
        const k = el.getAttribute('data-canli');
        const y = yeni.querySelector('[data-canli="' + k + '"]');
        if (y && y.textContent !== el.textContent) el.textContent = y.textContent;
      });
    })
    .catch(function () {});
}, KALKAN_CANLI);
</script>
</body></html>
