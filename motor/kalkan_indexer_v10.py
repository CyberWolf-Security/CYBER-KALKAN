#!/usr/bin/env python3
"""CYBER KALKAN — Log Indexer v10
Coklu log formati · regex parse · olay cikarma · korelasyon · trend"""
import json, os, re, gzip
from datetime import datetime
from collections import Counter, defaultdict

V = "/opt/siber-kalkan/VERI"
LOG = "/opt/siber-kalkan/LOG"

# ── Log kaynaklari ──
KAYNAKLAR = [
    ("/var/log/auth.log", "auth", [
        (r"Failed password for (?:invalid user )?(\S+) from ([\d.]+)", "SSH basarisiz giris", "YUKSEK"),
        (r"Accepted (?:password|publickey) for (\S+) from ([\d.]+)", "SSH basarili giris", "BILGI"),
        (r"sudo:.*COMMAND=(.+)", "sudo komut", "ORTA"),
        (r"Invalid user (\S+) from ([\d.]+)", "Gecersiz kullanici", "YUKSEK"),
        (r"authentication failure.*rhost=([\d.]+)", "Kimlik dogrulama hatasi", "ORTA"),
    ]),
    ("/var/log/syslog", "syslog", [
        (r"segfault at .* ip ([0-9a-f]+)", "Segfault", "ORTA"),
        (r"Out of memory.*Kill process (\d+)", "OOM killer", "YUKSEK"),
        (r"kernel panic", "Kernel panic", "KRITIK"),
        (r"(error|failed|critical)", "Genel hata", "DUSUK"),
    ]),
    ("/var/log/kern.log", "kernel", [
        (r"iptables|nftables.*DROP.*SRC=([\d.]+)", "Firewall drop", "BILGI"),
        (r"apparmor.*DENIED.*profile=(\S+)", "AppArmor engel", "ORTA"),
        (r"audit.*denied.*path=(\S+)", "Audit erisim engel", "ORTA"),
    ]),
    ("/var/log/apache2/access.log", "web", [
        (r'^([\d.]+).*"(GET|POST|HEAD) (\S+).*" (\d{3})', "Web istek", "BILGI"),
        (r'" (\d{3}) \d+.*" - ".*"$', "Web yanit", "BILGI"),
    ]),
    ("/var/log/nginx/access.log", "web", [
        (r'^([\d.]+).*"(GET|POST) (\S+).*" (\d{3})', "Web istek", "BILGI"),
    ]),
    ("/var/log/suricata/eve.json", "ids", [
        (r'"event_type":"alert".*"signature":"([^"]+)"', "IDS alarm", "YUKSEK"),
    ]),
]

def satir_oku(yol, max_satir=3000):
    if not os.path.exists(yol):
        return []
    try:
        if yol.endswith(".gz"):
            with gzip.open(yol, "rt", errors="ignore") as f:
                return f.readlines()[-max_satir:]
        with open(yol, errors="ignore") as f:
            return f.readlines()[-max_satir:]
    except Exception:
        return []

def parse_zaman(satir):
    # yaygin log zaman formatlari
    for desen, fmt in [
        (r"([A-Z][a-z]{2}\s+\d{1,2} \d{2}:\d{2}:\d{2})", "%b %d %H:%M:%S"),
        (r"(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})", "%Y-%m-%d %H:%M:%S"),
    ]:
        m = re.search(desen, satir)
        if m:
            try:
                t = datetime.strptime(m.group(1).replace("T", " "), fmt)
                return t.strftime("%d.%m.%Y %H:%M:%S")
            except Exception:
                pass
    return datetime.now().strftime("%d.%m.%Y %H:%M:%S")

def tara():
    olaylar = []
    kaynak_stat = {}
    ip_say = Counter()
    tip_say = Counter()
    toplam_satir = 0

    for yol, ad, desenler in KAYNAKLAR:
        satirlar = satir_oku(yol)
        if not satirlar:
            continue
        kaynak_stat[ad] = kaynak_stat.get(ad, 0) + len(satirlar)
        toplam_satir += len(satirlar)
        # .gz uzantisi degilse log dosyasini da kontrol et
        if not os.path.exists(yol) and os.path.exists(yol + ".1"):
            satirlar = satir_oku(yol + ".1")
        for s in satirlar:
            for desen, baslik, sev in desenler:
                m = re.search(desen, s)
                if m:
                    gruplar = m.groups()
                    ip = next((g for g in gruplar if g and re.match(r"^\d+\.\d+\.\d+\.\d+$", str(g))), "")
                    kayit = {"zaman": parse_zaman(s), "kaynak": ad, "baslik": baslik,
                             "seviye": sev, "ip": ip,
                             "detay": " ".join(str(g) for g in gruplar if g)[:120]}
                    olaylar.append(kayit)
                    tip_say[baslik] += 1
                    if ip:
                        ip_say[ip] += 1
                    break

    # trend (bugun vs dun)
    bugun = datetime.now().strftime("%d.%m.%Y")
    bugun_olay = sum(1 for o in olaylar if o["zaman"].startswith(bugun))

    # korelasyon: ayni IP 3+ farkli tip
    ip_tip = defaultdict(set)
    for o in olaylar:
        if o["ip"]: ip_tip[o["ip"]].add(o["baslik"])
    korelasyon = [{"ip": ip, "tip_sayisi": len(t), "tipler": list(t)[:5]}
                  for ip, t in ip_tip.items() if len(t) >= 3]

    sonuc = {
        "zaman": datetime.now().strftime("%d.%m.%Y %H:%M:%S"),
        "son": datetime.now().strftime("%d.%m.%Y %H:%M:%S"),
        "toplam": len(olaylar), "satir": toplam_satir,
        "bugun": bugun_olay, "kaynak_sayisi": len(kaynak_stat),
        "kaynaklar": kaynak_stat,
        "tip_dagilimi": dict(tip_say.most_common(20)),
        "en_cok_ip": dict(ip_say.most_common(15)),
        "korelasyon": korelasyon[:20],
        "olaylar": olaylar[-200:],
    }
    json.dump(sonuc, open(f"{V}/indexer.json", "w"), ensure_ascii=False, indent=1)
    os.makedirs(LOG, exist_ok=True)
    with open(f"{LOG}/indexer.log", "a") as f:
        f.write(f"[{sonuc['zaman']}] Indexer v10: {toplam_satir} satir, {len(olaylar)} olay, {len(kaynak_stat)} kaynak\n")
    return sonuc

if __name__ == "__main__":
    r = tara()
    print(f"[{r['zaman']}] Indexer v10: {r['satir']} satir tarandi · {r['toplam']} olay · {r['kaynak_sayisi']} kaynak")
    print(f"  Bugun: {r['bugun']} · Korelasyon: {len(r['korelasyon'])} IP")
    for t, n in list(r["tip_dagilimi"].items())[:6]:
        print(f"    {n:5d}  {t}")
