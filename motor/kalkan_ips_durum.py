#!/usr/bin/env python3
"""IPS DURUM OZETI — root calisir (motor dongusunden cagrilir), JSON yazar.
Panel www-data oldugu icin nft/docker CALISTIRAMAZ; bu yuzden durum burada toplanir."""
import json, os, subprocess, datetime

V = "/opt/siber-kalkan/VERI"
def sh(c, t=12):
    try:
        return subprocess.run(c, shell=True, capture_output=True, text=True, timeout=t).stdout
    except Exception:
        return ""

# nftables kuyruk sayisi (kalkan_ips tablosu)
kuyruk = sh("nft list table inet kalkan_ips 2>/dev/null | grep -c queue").strip()

# konteyner durumu
konteyner = sh("docker ps --filter name=kalkan-suricata --format '{{.Status}}' 2>/dev/null").strip()

# kural sayilari (suricata acilis logundan — guvenilir kaynak)
_dlog = sh("docker logs kalkan-suricata 2>&1 | grep -oE '[0-9]+ rules successfully loaded' | tail -1").strip()
kural = int(_dlog.split()[0]) if _dlog and _dlog.split()[0].isdigit() else 0
_fail = sh("docker logs kalkan-suricata 2>&1 | grep -oE '[0-9]+ rules failed' | tail -1").strip()
hata = int(_fail.split()[0]) if _fail and _fail.split()[0].isdigit() else 0

# L7 (uygulama katmani) kural sayisi
l7 = sh("docker exec kalkan-suricata sh -c \"grep -cE 'app-layer|http\\.|tls\\.|dns\\.|smb\\.' /var/lib/suricata/rules/suricata.rules 2>/dev/null\" 2>/dev/null").strip()

# alarm ozeti (varsa)
ozet = {}
for y in (f"{V}/suricata_ozet.json", "/opt/siber-kalkan/suricata/logs/ozet.json"):
    if os.path.isfile(y):
        try:
            ozet = json.load(open(y, encoding="utf-8"))
            break
        except Exception:
            pass

d = {
    "zaman": datetime.datetime.now().strftime("%d.%m.%Y %H:%M:%S"),
    "aktif": bool(int(kuyruk or 0) > 0 and "Up" in konteyner),
    "kuyruk": int(kuyruk or 0),
    "kural": kural,
    "hata": hata,
    "l7": int(l7 or 0),
    "konteyner": konteyner or "yok",
    "alarm": int(ozet.get("toplam", 0) or 0),
    "mod": "IPS (engelleme)" if int(kuyruk or 0) > 0 else "IDS (izleme)",
}
os.makedirs(V, exist_ok=True)
json.dump(d, open(f"{V}/ips_durum.json", "w", encoding="utf-8"), ensure_ascii=False, indent=1)
print(json.dumps(d, ensure_ascii=False))
