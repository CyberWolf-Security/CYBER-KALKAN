#!/usr/bin/env python3
# CYBER KALKAN - PUSKURTME (KILIC) MODULU
# Saldirganin baglantisini ANINDA keser (TCP RST) + alt ag yayilimi
import subprocess, json, time, os
from datetime import datetime
V = "/opt/siber-kalkan/VERI"
def simdi(): return datetime.now().strftime("%d.%m.%Y %H:%M:%S")
def sh(c):
    try: return subprocess.run(c, shell=True, capture_output=True, text=True, timeout=15).stdout
    except Exception: return ""
def oku(y, d):
    try: return json.load(open(y, encoding="utf-8"))
    except Exception: return d
def yaz(y, v):
    try: json.dump(v, open(y, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    except Exception: pass

# 1) KESME TABLOSU (nftables) - reject with tcp reset = aninda baglanti kesme
sh("nft delete table inet kilic 2>/dev/null")
sh("nft add table inet kilic")
sh("nft add chain inet kilic girdi '{ type filter hook input priority -200 ; policy accept ; }'")
sh("nft add chain inet kilic cikti '{ type filter hook output priority -200 ; policy accept ; }'")
sh("nft add set inet kilic kara '{ type ipv4_addr ; flags interval ; auto-merge ; }'")
# GELEN: reddet + RST (baglanti aninda olur)
sh("nft add rule inet kilic girdi ip saddr @kara reject with tcp reset")
# GIDEN: saldirana giden tum paketleri de kes (cevap yok)
sh("nft add rule inet kilic cikti ip daddr @kara drop")
print("KESME tablosu kuruldu (reject tcp reset + cikis drop)")

# 2) Engelli IP'leri kara listeye al
e = oku(f"{V}/engel.json", {"liste": []})
ipler = [k["ip"] for k in e.get("liste", [])]
def set_ekle(iplist):
    for i in range(0, len(iplist), 100):
        blok = iplist[i:i+100]
        sh("nft add element inet kilic kara '{ " + ", ".join(blok) + " }'")
set_ekle(ipler)
print(f"KARA LISTE: {len(ipler)} IP aninda kesiliyor")

# 3) ALT AG YAYILIMI: ayni /24'ten cok saldiri -> tum /24 kes
aglar = {}
for k in e.get("liste", []):
    ip = k.get("ip", "")
    p = ip.split(".")
    if len(p) == 4:
        ag = ".".join(p[:3]) + ".0/24"
        aglar[ag] = aglar.get(ag, 0) + 1
agresif = [ag for ag, n in aglar.items() if n >= 3]
if agresif:
    set_ekle(agresif)
    print(f"ALT AG YAYILIMI: {len(agresif)} ag kapatildi (>=3 saldiri)")

# 4) DURUM
durum = {"guncelleme": simdi(), "kesilen_ip": len(ipler), "kesilen_ag": len(agresif),
         "mod": "KESME (kilic)", "kural": "reject tcp reset + output drop"}
yaz(f"{V}/puskurtme.json", durum)
print("DURUM:", durum["kesilen_ip"], "IP |", durum["kesilen_ag"], "ag")
