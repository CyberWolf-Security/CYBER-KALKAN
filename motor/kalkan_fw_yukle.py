#!/usr/bin/env python3
"""CYBER KALKAN — Engelli IP'leri engel.json'dan nftables @kara setine yükler.
Restart sonrası engeller kaybolmaz (kalkan-fw-yukle.service ile çalışır)."""
import json, subprocess, sys

V = "/opt/siber-kalkan/VERI/engel.json"

def ip_dogrula(ip):
    p = ip.split(".")
    if len(p) != 4: return False
    try: return all(0 <= int(x) <= 255 for x in p)
    except ValueError: return False

try:
    d = json.load(open(V, encoding="utf-8"))
except Exception as e:
    print(f"engel.json okunamadi: {e}"); sys.exit(1)

liste = d.get("liste", d.get("engeller", [])) if isinstance(d, dict) else d
ips = sorted({x.get("ip") for x in liste if isinstance(x, dict) and ip_dogrula(str(x.get("ip", "")))})

if not ips:
    print("yuklenecek engelli IP yok"); sys.exit(0)

# nftables set'ine toplu ekle (500'lük gruplar)
yuklenen = 0
for i in range(0, len(ips), 500):
    grup = ", ".join(ips[i:i+500])
    r = subprocess.run(["nft", "add", "element", "inet", "kilic", "kara", "{", grup, "}"],
                       capture_output=True, text=True, timeout=30)
    if r.returncode == 0:
        yuklenen += len(ips[i:i+500])
    else:
        # tek tek dene
        for ip in ips[i:i+500]:
            rr = subprocess.run(["nft", "add", "element", "inet", "kilic", "kara", "{", ip, "}"],
                                capture_output=True, text=True, timeout=8)
            if rr.returncode == 0: yuklenen += 1

# doğrula
r = subprocess.run(["nft", "list", "set", "inet", "kilic", "kara"], capture_output=True, text=True)
setteki = r.stdout.count(".") if r.returncode == 0 else 0
print(f"engel.json: {len(ips)} IP | yuklenen: {yuklenen} | set'te: ~{setteki}")
