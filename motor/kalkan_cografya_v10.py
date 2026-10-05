#!/usr/bin/env python3
"""CYBER KALKAN — Cografya / IP Itibar v10
Ulke tespiti · ASN · risk skoru (Tor/VPN/bulut/veri merkezi) · davranis haritasi"""
import json, os, re, subprocess, ipaddress
from datetime import datetime
from collections import defaultdict, Counter

V = "/opt/siber-kalkan/VERI"
LOG = "/opt/siber-kalkan/LOG"

# ── Yuksek riskli ulkeler (kaynak: aktif saldiri yogunlugu) ──
RISKLI_ULKE = {
    "CN": 3, "RU": 3, "KP": 4, "IR": 4, "VN": 2, "BR": 2, "IN": 2, "ID": 2,
    "UA": 3, "TR": 1, "PK": 2, "NG": 2, "BD": 2, "TH": 2, "PH": 2, "RO": 2,
}
# ── Bilinen bulut/datacenter ASN'leri (bot kaynagi) ──
BULUT_ASN = {
    "AS16509": "Amazon AWS", "AS14618": "Amazon AWS", "AS15169": "Google Cloud",
    "AS396982": "Google Cloud", "AS8075": "Microsoft Azure", "AS14061": "DigitalOcean",
    "AS20473": "Vultr", "AS63949": "Linode", "AS24940": "Hetzner", "AS16276": "OVH",
    "AS12876": "Scaleway", "AS51167": "Contabo", "AS9009": "M247", "AS13335": "Cloudflare",
    "AS45102": "Alibaba Cloud", "AS132203": "Tencent", "AS45090": "Tencent",
    "AS37963": "Alibaba", "AS197540": "Netcup", "AS213230": "Hetzner",
}
# ── Tor cikis / VPN ipuclari ──
def tor_mu(ip):
    try:
        r = subprocess.run(["grep", "-q", ip, "/var/lib/tor/cached-consensus"],
                           capture_output=True)
        return r.returncode == 0
    except Exception:
        return False

def ozel_ip(ip):
    try:
        a = ipaddress.ip_address(ip)
        return a.is_private or a.is_loopback or a.is_reserved or a.is_multicast
    except Exception:
        return False

def dis_ip_bul():
    """Makinenin dis IP'si"""
    for cmd in ["curl -s --max-time 5 ifconfig.me", "curl -s --max-time 5 icanhazip.com",
                "curl -s --max-time 5 api.ipify.org"]:
        try:
            r = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=8)
            ip = r.stdout.strip()
            if re.match(r"^\d+\.\d+\.\d+\.\d+$", ip):
                return ip
        except Exception:
            continue
    return ""

def ip_bilgi(ip):
    """whois/geoip ile ulke + ASN"""
    bilgi = {"ip": ip, "ulke": "?", "asn": "?", "asn_ad": "?", "risk": 0}
    # ★ GÜVENLİK (B-02): IP dogrulanmadan komuta GIRMEZ
    try:
        from kalkan_ipdogrula import gecerli_ip
        if not gecerli_ip(ip):
            return bilgi
    except ImportError:
        if not re.match(r"^[0-9a-fA-F:.]+$", str(ip)) or len(str(ip)) > 45:
            return bilgi
    # geoip (varsa) — LISTE formu, shell yok
    for cmd in [["geoiplookup", ip],
                ["mmdblookup", "--file", "/usr/share/GeoIP/GeoLite2-Country.mmdb",
                 "--ip", ip, "country", "iso_code"]]:
        try:
            r = subprocess.run(cmd, capture_output=True, text=True, timeout=6)
            m = re.search(r"\b([A-Z]{2})\b", r.stdout or "")
            if m:
                bilgi["ulke"] = m.group(1); break
        except Exception:
            continue
    # ★ DUZELTME: geoiplookup/mmdblookup bu sistemde KURULU DEGIL → ulke hep "?" kaliyordu
    # (harita bu yuzden bos gorunuyordu). Yedek olarak ip-api.com (HTTP JSON, ucretsiz).
    if bilgi["ulke"] == "?":
        try:
            import urllib.request
            _u = f"http://ip-api.com/json/{ip}?fields=countryCode,as,asname"
            with urllib.request.urlopen(_u, timeout=6) as _y:
                _j = json.loads(_y.read().decode("utf-8", "ignore"))
            if _j.get("countryCode"):
                bilgi["ulke"] = str(_j["countryCode"])[:2].upper()
            if _j.get("as"):
                _a = str(_j["as"]).split()[0]
                if _a.startswith("AS"):
                    bilgi["asn"] = _a
            if _j.get("asname"):
                bilgi["asn_ad"] = str(_j["asname"])[:40]
        except Exception:
            pass
    # whois ASN — LISTE formu, shell yok (B-02)
    try:
        r = subprocess.run(["whois", ip], capture_output=True, text=True, timeout=10)
        for satir in (r.stdout or "").splitlines():
            m = re.search(r"AS\d+", satir)
            if m and re.match(r"^(origin|OriginAS)", satir.strip(), re.I):
                bilgi["asn"] = m.group(0)
                bilgi["asn_ad"] = BULUT_ASN.get(m.group(0), "?")
                break
    except Exception:
        pass
    # risk
    bilgi["risk"] = RISKLI_ULKE.get(bilgi["ulke"], 0)
    if bilgi["asn"] in BULUT_ASN:
        bilgi["risk"] += 1
        bilgi["bulut"] = BULUT_ASN[bilgi["asn"]]
    return bilgi

def tara():
    olay = json.load(open(f"{V}/olaylar.json")).get("olaylar", []) if os.path.exists(f"{V}/olaylar.json") else []
    engel = json.load(open(f"{V}/engel.json")).get("liste", []) if os.path.exists(f"{V}/engel.json") else []

    # olaylardaki tum IP'leri topla
    ipler = set()
    for o in olay:
        ip = o.get("ip", "")
        if ip and re.match(r"^\d+\.\d+\.\d+\.\d+$", ip) and not ozel_ip(ip):
            ipler.add(ip)
    for e in engel:
        ip = e.get("ip", "")
        if ip and re.match(r"^\d+\.\d+\.\d+\.\d+$", ip) and not ozel_ip(ip):
            ipler.add(ip)

    # önbellek kullan (whois yavaş)
    onb = {}
    if os.path.exists(f"{V}/cografya.json"):
        try:
            onb = json.load(open(f"{V}/cografya.json")).get("ip_bilgi", {})
        except Exception:
            pass

    yeni = 0
    for ip in list(ipler)[:400]:
        if ip in onb:
            continue
        onb[ip] = ip_bilgi(ip)
        yeni += 1
        if yeni >= 60:   # tur basina en fazla 60 yeni sorgu (hiz)
            break

    # istatistik
    ulke_say = Counter(v.get("ulke", "?") for v in onb.values())
    asn_say = Counter(v.get("asn_ad", "?") for v in onb.values() if v.get("asn_ad") != "?")
    riskli_ip = [v for v in onb.values() if v.get("risk", 0) >= 3]

    sonuc = {
        "zaman": datetime.now().strftime("%d.%m.%Y %H:%M:%S"),
        "toplam_ip": len(ipler), "cozumlenen": len(onb), "yeni_sorgu": yeni,
        "ulke_sayisi": len([u for u in ulke_say if u != "?"]),
        # ★ DUZELTME: "?" (cozulemeyen) en yuksek sayiya sahip oldugu icin "EN YOGUN" hep "?" cikiyordu
        "en_cok_ulke": (ulke_say.most_common() and
                        next(((u, s) for u, s in ulke_say.most_common() if u != "?"), ("?", 0))),
        "ulke_dagilimi": dict((u, s) for u, s in ulke_say.most_common(25) if u != "?"),
        "asn_dagilimi": dict(asn_say.most_common(15)),
        "riskli_ulke_ip": len(riskli_ip),
        "bulut_ip": sum(1 for v in onb.values() if v.get("bulut")),
        "yuksek_riskli": sorted(riskli_ip, key=lambda x: -x.get("risk", 0))[:20],
        "ip_bilgi": onb,
        "riskli_ulke_listesi": RISKLI_ULKE,
    }
    json.dump(sonuc, open(f"{V}/cografya.json", "w"), ensure_ascii=False, indent=1)

    os.makedirs(LOG, exist_ok=True)
    with open(f"{LOG}/cografya.log", "a") as f:
        f.write(f"[{sonuc['zaman']}] Cografya v10: {len(onb)} IP, {sonuc['ulke_sayisi']} ulke, {len(riskli_ip)} riskli\n")
    return sonuc

if __name__ == "__main__":
    r = tara()
    print(f"[{r['zaman']}] Cografya v10: {r['cozumlenen']} IP cozumlendi · {r['ulke_sayisi']} ulke")
    print(f"  En cok: {r['en_cok_ulke'][0]} ({r['en_cok_ulke'][1]}) · Riskli ulke IP: {r['riskli_ulke_ip']} · Bulut: {r['bulut_ip']}")
    for u, n in list(r["ulke_dagilimi"].items())[:6]:
        print(f"    {u}  {n}")
