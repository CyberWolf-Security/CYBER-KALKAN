#!/usr/bin/env python3
# CYBER KALKAN - FIREWALL SENKRON (engel.json <-> nftables kilic)
# Eksik kalan IP'leri toplu ekler; motor hatasi olsa bile senkron saglar
import subprocess, json, os
from datetime import datetime
V = "/opt/siber-kalkan/VERI"
def simdi(): return datetime.now().strftime("%d.%m.%Y %H:%M")
def sh(c, t=60):
    try: return subprocess.run(c, shell=True, capture_output=True, text=True, timeout=t).stdout
    except Exception: return ""
def oku(y, d=None):
    try: return json.load(open(y, encoding="utf-8"))
    except Exception: return d if d is not None else {}
def yaz(y, v):
    try: json.dump(v, open(y, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    except Exception: pass

# 1) nftables'taki mevcut IP'ler
nft_ham = sh("nft list set inet kilic kara 2>/dev/null")
nft_ipler = set()
bul = []
for satir in nft_ham.splitlines():
    for p in satir.replace("elements = {", "").replace("}", "").split(","):
        p = p.strip()
        if p and p[0].isdigit():
            bul.append(p)
nft_ipler = set(bul)

# 2) engel.json'daki tum IP'ler
e = oku(f"{V}/engel.json", {"liste": [], "toplam": 0})
engel_ipler = [k["ip"] for k in e.get("liste", []) if k.get("ip")]

# 3) eksik olanlari ekle
eksik = [ip for ip in engel_ipler if ip not in nft_ipler]
eklenen = 0
for i in range(0, len(eksik), 100):
    blok = eksik[i:i+100]
    r = sh("nft add element inet kilic kara '{ " + ", ".join(blok) + " }'")
    eklenen += len(blok)

# 4) alt ag yayilimi (3+ saldiri -> /24)
aglar = {}
for k in e.get("liste", []):
    p = (k.get("ip") or "").split(".")
    if len(p) == 4:
        ag = ".".join(p[:3]) + ".0/24"
        aglar[ag] = aglar.get(ag, 0) + 1
agresif = [ag for ag, n in aglar.items() if n >= 3]
ag_eklenen = 0
if agresif:
    for i in range(0, len(agresif), 50):
        blok = agresif[i:i+50]
        sh("nft add element inet kilic kara '{ " + ", ".join(blok) + " }'")
        ag_eklenen += len(blok)

# 5) tarpit seti de varsa senkronla
if "tarpit" in sh("nft list tables"):
    sh(f"nft flush set inet tarpit engelli 2>/dev/null")
    for i in range(0, len(engel_ipler), 100):
        blok = engel_ipler[i:i+100]
        sh("nft add element inet tarpit engelli '{ " + ", ".join(blok) + " }'")

durum = {"guncelleme": simdi(), "engel_json": len(engel_ipler), "nft_oncesi": len(nft_ipler),
         "eklenen": eklenen, "ag_yayilimi": ag_eklenen, "nft_sonrasi": len(engel_ipler)}
yaz(f"{V}/senkron.json", durum)
print(f"SENKRON: engel.json={len(engel_ipler)} | nft oncesi={len(nft_ipler)} | EKLENEN={eklenen} | AG={ag_eklenen}")
