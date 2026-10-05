#!/usr/bin/env python3
"""CYBER KALKAN — PAKET C: ENTEGRASYON + COMPLIANCE + CVE + PDF + MACOS"""
import json, os, subprocess, urllib.request, urllib.error
from datetime import datetime
B="/opt/siber-kalkan"; V=f"{B}/VERI"; P="/var/www/kalkan-panel"
KAYIT=[]

# ===== 1) ENTEGRASYONLAR (VT/MISP/Slack/PagerDuty/Shuffle) =====
print("[1] Entegrasyonlar")
ENT = {
 "virustotal": {"ad":"VirusTotal","alan":"vt_api_key","aktif":False,
                "kullanim":"IP/hash/domain itibar sorgusu","url":"https://www.virustotal.com/api/v3/"},
 "misp":       {"ad":"MISP","alan":"misp_url","aktif":False,
                "kullanim":"Tehdit istihbarati paylasimi","url":""},
 "slack":      {"ad":"Slack","alan":"slack_webhook","aktif":False,
                "kullanim":"Bildirim kanali","url":""},
 "pagerduty":  {"ad":"PagerDuty","alan":"pd_key","aktif":False,
                "kullanim":"Kritik alarm eskalasyonu","url":""},
 "shuffle":    {"ad":"Shuffle SOAR","alan":"shuffle_url","aktif":False,
                "kullanim":"Otomasyon is akisi","url":""},
 "thehive":    {"ad":"TheHive","alan":"thehive_url","aktif":False,
                "kullanim":"Vaka yonetimi entegrasyonu","url":""},
 "jira":       {"ad":"Jira","alan":"jira_url","aktif":False,
                "kullanim":"Ticket olusturma","url":""},
 "telegram":   {"ad":"Telegram","alan":"telegram_token","aktif":False,
                "kullanim":"Anlik alarm","url":"https://api.telegram.org"},
 "email":      {"ad":"E-posta/SMTP","alan":"smtp_sunucu","aktif":False,
                "kullanim":"E-posta alarmi","url":""},
 "syslog_out": {"ad":"Syslog iletme","alan":"syslog_hedef","aktif":False,
                "kullanim":"Merkezi SIEM'e iletim","url":""},
}
json.dump({"guncelleme":datetime.now().strftime("%d.%m.%Y %H:%M"),"entegrasyonlar":ENT},
          open(f"{V}/entegrasyonlar.json","w",encoding="utf-8"), indent=1, ensure_ascii=False)
KAYIT.append(("entegrasyon", len(ENT)))

# Entegrasyon API (VT sorgu)
VT_PHP = '''<?php
/* ENTEGRASYON API — VirusTotal/MISP/Slack sorgulari */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();
header('Content-Type: application/json; charset=utf-8');
$ayar = kalkan_oku("ayarlar", []);
$islem = $_GET["islem"] ?? "liste";
if ($islem === "vt_sorgu") {
    $ip = $_GET["ip"] ?? "";
    $vt = $ayar["vt_api_key"] ?? "";
    if (!$vt) { exit(json_encode(["durum"=>"hata","mesaj"=>"VT API anahtari yok"])); }
    $ch = curl_init("https://www.virustotal.com/api/v3/ip_addresses/".urlencode($ip));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>["x-apikey: $vt"], CURLOPT_TIMEOUT=>10]);
    $r = curl_exec($ch); $kod = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($kod === 200) {
        $d = json_decode($r, true);
        $st = $d["data"]["attributes"]["last_analysis_stats"] ?? [];
        exit(json_encode(["durum"=>"ok","ip"=>$ip,"kotu"=>$st["malicious"]??0,"supheli"=>$st["suspicious"]??0], JSON_UNESCAPED_UNICODE));
    }
    exit(json_encode(["durum"=>"hata","kod"=>$kod], JSON_UNESCAPED_UNICODE));
}
if ($islem === "slack") {
    $wh = $ayar["slack_webhook"] ?? "";
    if (!$wh) { exit(json_encode(["durum"=>"hata","mesaj"=>"webhook yok"])); }
    $d = json_encode(["text" => $_GET["mesaj"] ?? "CYBER KALKAN test"]);
    $ch = curl_init($wh);
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$d, CURLOPT_HTTPHEADER=>["Content-Type: application/json"], CURLOPT_TIMEOUT=>8]);
    curl_exec($ch); $k = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    exit(json_encode(["durum"=>$k===200?"ok":"hata","kod"=>$k]));
}
echo json_encode(kalkan_oku("entegrasyonlar", []), JSON_UNESCAPED_UNICODE);
'''
open(f"{P}/entegrasyon.php","w",encoding="utf-8").write(VT_PHP)
KAYIT.append(("entegrasyon_api","/entegrasyon.php"))

# ===== 2) COMPLIANCE MAPPING (PCI/GDPR/HIPAA/NIST/SOC2/ISO) =====
print("[2] Compliance mapping")
KURALLAR=json.load(open(f"{V}/kurallar.json"))["kurallar"]
CERCEVELER = {
 "PCI-DSS": {"versiyon":"4.0","maddeler":{
    "10.2":"Log kaydi (olay izleme)","10.4":"Log inceleme","11.3":"Sizma testi",
    "11.5":"IDS/IPS","6.4":"Web uygulama guvenligi","8.3":"Kimlik dogrulama","5.3":"Antivirus"}},
 "GDPR": {"versiyon":"2016/679","maddeler":{
    "32":"Guvenlik tedbirleri","33":"Ihlal bildirimi","30":"Isleme kaydi","25":"Tasarim gizliligi"}},
 "HIPAA": {"versiyon":"2013","maddeler":{
    "164.308":"Idari tedbirler","164.312":"Teknik tedbirler","164.314":"Org. gereksinimler"}},
 "NIST-800-53": {"versiyon":"Rev5","maddeler":{
    "AU":"Denetim","SI":"Sistem butunlugu","IR":"Olay mudahalesi","RA":"Risk degerlendirme",
    "SC":"Iletisim koruma","AC":"Erisim kontrolu"}},
 "SOC2": {"versiyon":"Type II","maddeler":{
    "CC6":"Mantiksal erisim","CC7":"Islemler","CC8":"Degisiklik yonetimi","CC9":"Risk azaltma"}},
 "ISO-27001": {"versiyon":"2022","maddeler":{
    "A.5":"Politikalar","A.8":"Varlik yonetimi","A.12":"Islem guvenligi","A.16":"Olay yonetimi"}},
}
# Kural -> cerceve eslesmesi
ESLESME={}
for r in KURALLAR:
    mitre=(r.get("mitre") or "")
    r_id=r["id"]
    maddeler=[]
    if r_id<1100: maddeler += ["PCI-DSS:6.4","NIST-800-53:SI","ISO-27001:A.12"]
    if 1100<=r_id<1200: maddeler += ["PCI-DSS:8.3","NIST-800-53:AC","ISO-27001:A.5"]
    if 1200<=r_id<1300: maddeler += ["PCI-DSS:5.3","NIST-800-53:SI"]
    if "T1078" in mitre or "T1110" in mitre: maddeler += ["SOC2:CC6","GDPR:32"]
    if "T1486" in mitre or "T1485" in mitre: maddeler += ["HIPAA:164.312","ISO-27001:A.16"]
    if "T1041" in mitre or "T1048" in mitre: maddeler += ["GDPR:33","PCI-DSS:10.2"]
    if maddeler: ESLESME[str(r_id)]=list(set(maddeler))

# Uyumluluk skoru (kapsam)
KAPSAM={}
for cf, d in CERCEVELER.items():
    top=len(d["maddeler"])
    kap=0
    for m in d["maddeler"]:
        if any(cf+":"+m in v for v in ESLESME.values()): kap+=1
    KAPSAM[cf]={"toplam":top,"kapsanan":kap,"skor":round(kap*100/top) if top else 0}

json.dump({"guncelleme":datetime.now().strftime("%d.%m.%Y %H:%M"),"cerceveler":CERCEVELER,
           "eslesme":ESLESME,"kapsam":KAPSAM},
          open(f"{V}/compliance_mapping.json","w",encoding="utf-8"), indent=1, ensure_ascii=False)
KAYIT.append(("compliance_cerceve", len(CERCEVELER)))
for cf, k in KAPSAM.items():
    print(f"  {cf:12s}: %{k['skor']} ({k['kapsanan']}/{k['toplam']})")

# ===== 3) CVE DB SYNC (NVD) =====
print("[3] CVE veritabani")
CVE_DB={"guncelleme":datetime.now().strftime("%d.%m.%Y %H:%M"),"kaynak":"NVD+CISA KEV","cveler":[]}
# Bilinen kritik CVE'ler (offline liste)
BILINEN_CVE = [
 {"cve":"CVE-2021-44228","ad":"Log4Shell","cvss":10.0,"kev":True,"mitre":"T1190"},
 {"cve":"CVE-2021-34527","ad":"PrintNightmare","cvss":8.8,"kev":True,"mitre":"T1068"},
 {"cve":"CVE-2020-1472","ad":"Zerologon","cvss":10.0,"kev":True,"mitre":"T1068"},
 {"cve":"CVE-2019-0708","ad":"BlueKeep","cvss":9.8,"kev":True,"mitre":"T1210"},
 {"cve":"CVE-2017-0144","ad":"EternalBlue","cvss":9.3,"kev":True,"mitre":"T1210"},
 {"cve":"CVE-2022-30190","ad":"Follina","cvss":7.8,"kev":True,"mitre":"T1204"},
 {"cve":"CVE-2023-34362","ad":"MOVEit","cvss":9.8,"kev":True,"mitre":"T1190"},
 {"cve":"CVE-2023-4966","ad":"CitrixBleed","cvss":9.4,"kev":True,"mitre":"T1190"},
 {"cve":"CVE-2024-3400","ad":"PAN-OS","cvss":10.0,"kev":True,"mitre":"T1190"},
 {"cve":"CVE-2024-3094","ad":"XZ Backdoor","cvss":10.0,"kev":True,"mitre":"T1195"},
 {"cve":"CVE-2024-6387","ad":"regreSSHion","cvss":8.1,"kev":True,"mitre":"T1190"},
 {"cve":"CVE-2025-0282","ad":"Ivanti","cvss":9.0,"kev":True,"mitre":"T1190"},
]
CVE_DB["cveler"]=BILINEN_CVE
json.dump(CVE_DB, open(f"{V}/cve_db.json","w",encoding="utf-8"), indent=1, ensure_ascii=False)
KAYIT.append(("cve_db", len(BILINEN_CVE)))
print(f"  CVE kayit: {len(BILINEN_CVE)} (KEV: {sum(1 for c in BILINEN_CVE if c['kev'])})")

# ===== 4) PDF RAPOR (HTML->print) =====
print("[4] Rapor uretimi")
RAPOR_PHP = '''<?php
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();
$ayar = kalkan_oku("ayarlar", []);
$engel = kalkan_oku("engel", ["liste"=>[]]);
$olay = kalkan_oku("olaylar", ["olaylar"=>[],"toplam"=>0]);
$kural = kalkan_oku("kurallar", ["kurallar"=>[]]);
$comp = kalkan_oku("compliance_mapping", ["kapsam"=>[]]);
$fm = kalkan_oku("fim", []);
?><!doctype html><html lang="tr"><head><meta charset="utf-8">
<title>CYBER KALKAN Rapor</title><style>
body{font-family:Arial;margin:30px;color:#222}h1{color:#0b6}
h2{border-bottom:2px solid #0b6;padding:5px 0;margin-top:25px}
table{border-collapse:collapse;width:100%;margin:10px 0}
th,td{border:1px solid #ccc;padding:8px;text-align:left}
th{background:#0b6;color:#fff}.kpi{display:flex;gap:20px;margin:20px 0}
.kpi div{background:#f0f8f5;padding:15px;border-left:4px solid #0b6;flex:1}
@media print{.no-print{display:none}}
</style></head><body>
<div class="no-print"><button onclick="window.print()">🖨️ PDF olarak kaydet</button></div>
<h1>🐺 CYBER KALKAN — Güvenlik Raporu</h1>
<p>Marka: <?= htmlspecialchars($ayar["marka"] ?? "CYBERWOLF SECURITY") ?> · Tarih: <?= date("d.m.Y H:i") ?></p>
<div class="kpi">
<div><b style="font-size:24px"><?= count($engel["liste"]) ?></b><br>Engellenen IP</div>
<div><b style="font-size:24px"><?= $olay["toplam"] ?></b><br>Toplam Olay</div>
<div><b style="font-size:24px"><?= count($kural["kurallar"]) ?></b><br>Aktif Kural</div>
<div><b style="font-size:24px"><?= count($fm["degisimler"] ?? []) ?></b><br>FIM Değişiklik</div>
</div>
<h2>Uyumluluk Durumu</h2><table><tr><th>Standart</th><th>Kapsam</th><th>Skor</th></tr>
<?php foreach ($comp["kapsam"] as $k=>$v): ?>
<tr><td><?= $k ?></td><td><?= $v["kapsanan"] ?>/<?= $v["toplam"] ?></td><td>%<?= $v["skor"] ?></td></tr>
<?php endforeach; ?></table>
<h2>En Çok Saldıran IP'ler</h2><table><tr><th>IP</th><th>Puan</th><th>Kaynak</th></tr>
<?php $l = $engel["liste"]; usort($l, fn($a,$b)=>($b["puan"]??0)<=>($a["puan"]??0));
foreach (array_slice($l,0,15) as $k): ?>
<tr><td><?= htmlspecialchars($k["ip"]) ?></td><td><?= $k["puan"]??"-" ?></td><td><?= $k["kaynak"]??"-" ?></td></tr>
<?php endforeach; ?></table>
<h2>Son Olaylar (MITRE)</h2><table><tr><th>Zaman</th><th>IP</th><th>Kural</th><th>Seviye</th><th>MITRE</th></tr>
<?php foreach (array_slice(array_reverse($olay["olaylar"]),0,20) as $o): ?>
<tr><td><?= $o["zaman"]??"-" ?></td><td><?= htmlspecialchars($o["ip"]??"-") ?></td><td><?= htmlspecialchars($o["ad"]??$o["kural"]??"-") ?></td><td><?= $o["seviye"]??"-" ?></td><td><?= $o["mitre"]??"-" ?></td></tr>
<?php endforeach; ?></table>
<p style="margin-top:30px;color:#888;font-size:12px">CYBER KALKAN · <?= htmlspecialchars($ayar["marka"]??"") ?> · Otomatik rapor</p>
</body></html>'''
open(f"{P}/rapor.php","w",encoding="utf-8").write(RAPOR_PHP)
KAYIT.append(("rapor","/rapor.php"))

# ===== 5) macOS AJAN =====
print("[5] macOS ajan")
MACOS_AJAN = '''#!/bin/bash
# CYBER KALKAN — macOS AJAN (launchd)
MERKEZ="${1:-http://192.168.1.4:8890}"
TOKEN="${2:-cyber-kalkan-2026}"
DIZIN="/usr/local/cyber-kalkan"
mkdir -p $DIZIN
cat > $DIZIN/ajan.sh <<'AJ'
#!/bin/bash
M="$1"; T="$2"
HOST=$(hostname); IP=$(ipconfig getifaddr en0 2>/dev/null || echo "0.0.0.0")
MODEL=$(sysctl -n hw.model 2>/dev/null)
OS=$(sw_vers -productVersion 2>/dev/null)
LOG=""
# Guvenlik olaylari
LOG+=$(log show --predicate 'eventMessage CONTAINS "failed" OR eventMessage CONTAINS "denied"' --last 2m 2>/dev/null | tail -50)
# Sistem
UP=$(uptime | sed 's/.*up //;s/,.*//')
LOAD=$(sysctl -n vm.loadavg 2>/dev/null)
DATA=$(python3 - <<PY
import json
print(json.dumps({"host":"$HOST","ip":"$IP","os":"macOS $OS","model":"$MODEL","uptime":"$UP","load":"$LOAD","log":"""$LOG"""[:2000]}))
PY
)
curl -s -X POST "$M/alici.php" -H "Content-Type: application/json" -H "X-Kalkan-Token: $T" -d "$DATA" >/dev/null 2>&1
# Config cek
curl -s "$M/ajan_config.php?token=$T" > $DIZIN/last_config.json 2>/dev/null
AJ
chmod +x $DIZIN/ajan.sh
cat > /Library/LaunchDaemons/com.cyberwolf.kalkan.plist <<PL
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
<key>Label</key><string>com.cyberwolf.kalkan</string>
<key>ProgramArguments</key><array><string>$DIZIN/ajan.sh</string><string>$MERKEZ</string><string>$TOKEN</string></array>
<key>StartInterval</key><integer>60</integer>
<key>RunAtLoad</key><true/>
</dict></plist>
PL
launchctl load /Library/LaunchDaemons/com.cyberwolf.kalkan.plist 2>/dev/null
echo "✓ macOS ajan kuruldu: $DIZIN/ajan.sh (60sn aralik)"
'''
open(f"{B}/AJAN/ajan-macos.sh","w",encoding="utf-8").write(MACOS_AJAN)
os.chmod(f"{B}/AJAN/ajan-macos.sh", 0o755)
KAYIT.append(("macos_ajan","/opt/siber-kalkan/AJAN/ajan-macos.sh"))

json.dump({"guncelleme":datetime.now().strftime("%d.%m.%Y %H:%M"),"sonuc":KAYIT},
          open(f"{V}/paket_c.json","w",encoding="utf-8"), indent=1, ensure_ascii=False)
print("\n=== PAKET C TAMAM ===")
for k,v in KAYIT: print(f"  {k}: {v}")