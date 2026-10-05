#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""CYBER KALKAN INLINE IPS v10 - nfqueue -> Suricata (V1)"""
import sys, os, json, subprocess, time, signal

KOK = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
V   = os.path.join(KOK, "veri")
TABLO = "kalkan_ips"
NFT   = os.path.join(KOK, "firewall", "ips.nft")
KILIT = "/tmp/kalkan_ips_aktif"

def sh(argv, t=20):
    try:
        r = subprocess.run(argv, capture_output=True, text=True, timeout=t)
        return r.returncode, (r.stdout or "").strip(), (r.stderr or "").strip()
    except Exception as e:
        return -1, "", str(e)

def beyaz_liste():
    ipler = ["127.0.0.1"]
    try:
        d = json.load(open(os.path.join(V, "beyaz_liste.json")))
        veri = d.get("liste", d) if isinstance(d, dict) else d
        for x in veri:
            ip = x if isinstance(x, str) else x.get("ip", "")
            if ip:
                ipler.append(ip)
    except Exception:
        pass
    try:
        r = subprocess.run(["hostname", "-I"], capture_output=True, text=True, timeout=5)
        ipler += [x for x in (r.stdout or "").split() if x]
    except Exception:
        pass
    return sorted(set(ipler))

def durum():
    print("=== INLINE IPS DURUMU ===")
    rc, o, _ = sh(["nft", "list", "table", "inet", TABLO])
    print("  nftables tablo :", "AKTIF" if rc == 0 else "kapali")
    if rc == 0:
        for s in o.splitlines():
            if "queue" in s:
                print("   ", s.strip()[:100])
    rc2, o2, _ = sh(["docker", "ps", "--filter", "name=kalkan-suricata",
                     "--format", "{{.Names}} {{.Status}}"])
    print("  suricata       :", o2 if rc2 == 0 and o2 else "kapali")
    print("  geri-alma kilidi:", "VAR" if os.path.exists(KILIT) else "yok")

def baslat(onay=False):
    if not onay:
        print("HATA: '--onay' gerekli.")
        print("  Once gozden gecir:  python3 motor/kalkan_ips_v10.py durum")
        print("  Sonra baslat     :  python3 motor/kalkan_ips_v10.py baslat --onay")
        return 1
    bl = beyaz_liste()
    print("  beyaz liste:", len(bl), "IP")
    if not os.path.exists(NFT):
        print("HATA: firewall/ips.nft yok"); return 1
    ac = open(NFT).read()
    for ip in bl:
        ac += '\nadd element inet %s beyaz { %s }\n' % (TABLO, ip)
    ac += "\n"
    gecici = "/tmp/ips_check.nft"
    open(gecici, "w").write(ac)
    rc, _, err = sh(["nft", "-c", "-f", gecici])
    if rc != 0:
        print("HATA: nft sozdizimi:", err[:200]); return 1
    print("  sozdizimi: OK")
    rc, _, err = sh(["nft", "-f", gecici])
    if rc != 0:
        print("HATA: yuklenemedi:", err[:200]); return 1
    print("  IPS nftables: AKTIF")
    rcs, _, _ = sh(["docker", "start", "kalkan-suricata"], 60)
    print("  suricata:", "basladi" if rcs == 0 else "yok (docker servisi kuruluysa calisir)")
    open(KILIT, "w").write("OK")
    print("  kilit yazildi:", KILIT)

def durdur():
    rc, _, err = sh(["nft", "delete", "table", "inet", TABLO])
    print("  nftables:", "silindi" if rc == 0 else "zaten yok")
    sh(["docker", "stop", "kalkan-suricata"], 60)
    if os.path.exists(KILIT):
        os.remove(KILIT)
    print("  IPS durduruldu")

def ozet():
    yol = os.path.join(KOK, "suricata", "logs", "eve.json")
    if not os.path.exists(yol):
        yol = os.path.join(V, "suricata_ozet.json")
    try:
        d = json.load(open(yol))
        print("  kaynak :", yol)
        print("  ozet   :", json.dumps(d, ensure_ascii=False)[:300])
    except Exception as e:
        print("  ozet yok:", e)

if __name__ == "__main__":
    a = sys.argv[1:] or ["durum"]
    cmd = a[0]
    onay = "--onay" in a
    if cmd == "durum":   durum()
    elif cmd == "baslat": sys.exit(baslat(onay))
    elif cmd == "durdur": durdur()
    elif cmd == "ozet":   ozet()
    else:
        print("kullanim: durum | baslat --onay | durdur | ozet")
