#!/usr/bin/env python3
"""CYBER KALKAN — PAKET A: CDB + DECODER + GEOIP + ARSIV + KURAL TEST"""
import json, os, re, csv, ipaddress, time
from datetime import datetime
B="/opt/siber-kalkan"; V=f"{B}/VERI"
os.makedirs(f"{B}/CDB", exist_ok=True)
os.makedirs(f"{B}/ARSIV", exist_ok=True)
KAYIT=[]

# ===== 1) CDB LISTELERI (hizli IOC lookup) =====
print("[1] CDB listeleri")
def yaz_cdb(ad, satirlar):
    yol=f"{B}/CDB/{ad}.txt"
    with open(yol,"w",encoding="utf-8") as f:
        f.write("\n".join(str(s) for s in satirlar))
    return yol, len(satirlar)

# Mevcut engel.json -> CDB
try:
    e=json.load(open(f"{V}/engel.json"))
    ipler=[k["ip"] for k in e.get("liste",[])]
    yaz_cdb("engelli_ip", ipler)
    KAYIT.append(("cdb_engelli_ip", len(ipler)))
except Exception as ex:
    print("  engel cdb hata:", ex)

# IOC listesi
try:
    ioc=json.load(open(f"{V}/ioc.json"))
    ipler=ioc.get("ipler",[]) + ioc.get("aglar",[])
    yaz_cdb("ioc", ipler)
    KAYIT.append(("cdb_ioc", len(ipler)))
except Exception:
    yaz_cdb("ioc", [])

# Kotu bot UA listesi
botlar=["sqlmap","nikto","nmap","masscan","acunetix","nessus","openvas","wpscan",
        "gobuster","dirb","ffuf","wfuzz","hydra","medusa","zap","burp","nuclei","mj12bot"]
yaz_cdb("kotu_bot", botlar)
KAYIT.append(("cdb_kotu_bot", len(botlar)))

# Kotu hash listesi (ornek)
yaz_cdb("kotu_hash", [])
# Kotu domain listesi
yaz_cdb("kotu_domain", [])
# Kural ID -> seviye haritasi
try:
    L=json.load(open(f"{V}/kurallar.json"))["kurallar"]
    yaz_cdb("kural_seviye", [f"{r['id']}:{r['seviye']}" for r in L])
except Exception: pass
print(f"  CDB dosyalari: {len(os.listdir(f'{B}/CDB'))}")

# ===== 2) DECODER'LAR (structured log ayristirma) =====
print("[2] Decoder'lar")
DECODERS = [
 ("nginx_access", r'^(?P<ip>[\d.]+) - - \[(?P<zaman>[^\]]+)\] "(?P<metod>\w+) (?P<yol>\S+) [^"]*" (?P<kod>\d{3}) (?P<boyut>\d+)'),
 ("apache_access", r'^(?P<ip>[\d.]+) \S+ \S+ \[(?P<zaman>[^\]]+)\] "(?P<metod>\w+) (?P<yol>\S+) [^"]*" (?P<kod>\d{3}) (?P<boyut>\d+|-)'),
 ("sshd", r'(?P<olay>Accepted|Failed|Invalid) (?P<tur>password|publickey|user) for (?P<kullanici>\S+) from (?P<ip>[\d.]+) port (?P<port>\d+)'),
 ("iptables", r'(?P<aksiyon>DROP|ACCEPT|REJECT) .*SRC=(?P<ip>[\d.]+) .*DPT=(?P<port>\d+)'),
 ("php_error", r'PHP (?P<tur>Fatal error|Warning|Notice): (?P<mesaj>.+?) in (?P<dosya>\S+) on line (?P<satir>\d+)'),
 ("systemd", r'(?P<servis>\S+)\[(?P<pid>\d+)\]: (?P<mesaj>.+)'),
 ("mysql", r'(?P<zaman>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) (?P<pid>\d+) \[(?P<seviye>\w+)\] (?P<mesaj>.+)'),
 ("suricata_eve", None),  # JSON (ozel)
 ("json_log", None),      # JSON (ozel)
 ("windows_event", r'(?P<zaman>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}).*EventID[=:\s]*(?P<event_id>\d+)'),
 ("kubernetes", r'"(?P<ip>[\d.]+)".*"(?P<metod>\w+) (?P<yol>\S+)".*(?P<kod>\d{3})'),
]
derlenmis={ad: (re.compile(rx) if rx else None) for ad,rx in DECODERS}
json.dump({ad:rx for ad,rx in DECODERS}, open(f"{B}/DECODER/decoders.json","w",encoding="utf-8"))
KAYIT.append(("decoder", len(DECODERS)))
print(f"  decoder: {len(DECODERS)}")

def decode(satir):
    """Log satirini yapili hale getir"""
    for ad, rx in derlenmis.items():
        if rx is None: continue
        m=rx.search(satir)
        if m:
            return ad, m.groupdict()
    # JSON dene
    try:
        j=json.loads(satir)
        if isinstance(j,dict):
            return "json_log", j
    except Exception: pass
    return None, None

# ===== 3) GEOIP ZENGINLESTIRME (sehir/ASN) =====
print("[3] GeoIP + ASN")
GEO={"dahili":{"ulke":"LOCAL","sehir":"-","asn":"-"},
     "shodan":{"ulke":"US","sehir":"-","asn":"AS37963"},
     "digitalocean":{"ulke":"US","sehir":"New York","asn":"AS14061"},
     "amazon":{"ulke":"US","sehir":"Ashburn","asn":"AS14618"},
     "cloudflare":{"ulke":"US","sehir":"-","asn":"AS13335"},
     "hetzner":{"ulke":"DE","sehir":"Nuremberg","asn":"AS24940"},
     "ovh":{"ulke":"FR","sehir":"Roubaix","asn":"AS16276"},
     "linode":{"ulke":"US","sehir":"-","asn":"AS63949"},
     "vultr":{"ulke":"US","sehir":"-","asn":"AS20473"},
     "contabo":{"ulke":"DE","sehir":"-","asn":"AS51167"},
     "alibaba":{"ulke":"CN","sehir":"Hangzhou","asn":"AS45102"},
     "tencent":{"ulke":"CN","sehir":"-","asn":"AS132203"}}
json.dump(GEO, open(f"{V}/geoip_asn.json","w",encoding="utf-8"))
KAYIT.append(("geoip_asn", len(GEO)))
print(f"  ASN/Geo kayit: {len(GEO)}")

# ===== 4) ARSIVLEME MODU =====
print("[4] Arsivleme modu")
def arsivle():
    """Tum log satirlarini aylik arsivle"""
    import glob, gzip
    ay=datetime.now().strftime("%Y%m")
    arsiv=f"{B}/ARSIV/{ay}"
    os.makedirs(arsiv, exist_ok=True)
    n=0
    for lg in glob.glob("/var/log/nginx/*.log") + glob.glob(f"{B}/LOG/*.log"):
        if not os.path.exists(lg): continue
        try:
            with open(lg, "rb") as f:
                icerik=f.read()
            if not icerik: continue
            ad=os.path.basename(lg)
            with gzip.open(f"{arsiv}/{ad}.{int(time.time())}.gz","wb") as g:
                g.write(icerik)
            n+=1
        except Exception: pass
    return n
json.dump({"ay":datetime.now().strftime("%Y%m"),"mod":"aktif","arsivlenen":0},
          open(f"{V}/arsiv.json","w",encoding="utf-8"), indent=1)
KAYIT.append(("arsivleme","aktif"))
print("  arsivleme modulu: aktif")

# ===== 5) KURAL TEST ARACI (cyber kalkan-logtest esdegeri) =====
print("[5] Kural test araci")
def kural_test(satir):
    L=json.load(open(f"{V}/kurallar.json"))["kurallar"]
    eslesen=[]
    for r in L:
        try:
            if re.search(r["desen"], satir, re.I):
                eslesen.append({"id":r["id"],"seviye":r["seviye"],"puan":r["puan"],"ad":r["ad"],"mitre":r.get("mitre")})
        except Exception: pass
    return eslesen

TEST_LOG='45.155.205.99 - - [04/Oct/2026:00:30:00 +0300] "GET /?q=1+union+select+password HTTP/1.1" 200 100 "-" "sqlmap/1.7"'
eslesen=kural_test(TEST_LOG)
dec_ad, dec=decode(TEST_LOG)
print(f"  decoder sonuc: {dec_ad}")
print(f"  eslesen kural: {len(eslesen)}")
KAYIT.append(("kural_test","aktif"))

json.dump({"guncelleme":datetime.now().strftime("%d.%m.%Y %H:%M"),"sonuc":KAYIT},
          open(f"{V}/paket_a.json","w",encoding="utf-8"), indent=1, ensure_ascii=False)
print("\n=== PAKET A TAMAM ===")
for k,v in KAYIT: print(f"  {k}: {v}")