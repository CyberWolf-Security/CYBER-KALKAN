#!/usr/bin/env python3
"""CYBER KALKAN — IDS BAGLAMA (Suricata -> otomatik engelleme)
Suricata alert'lerini okur, tekrarlayan saldirgan IP'yi kara listeye alir.
Gercek IPS (nfqueue) yerine guvenli yaklasim: eve.json -> motor -> nftables kilic
"""
import json, os, time, subprocess
from datetime import datetime

V = "/opt/siber-kalkan/VERI"
EVE = "/var/log/suricata/eve.json"
KILIC = "/opt/siber-kalkan/MOTOR"

# Muaf (panel/kendi ağı asla engellenmez)
MUAF = ("127.", "192.168.", "10.200.0.1", "10.0.", "172.16.", "0.0.0.0")

# Ciddi Suricata kategorileri (sadece bunlar engellenir)
CIDDI = ("attempted-admin", "attempted-user", "web-application-attack",
         "attempted-dos", "shellcode", "exploit-kit", "trojan-activity",
         "attempted-recon", "credential-theft", "command-and-control")

def muaf_mi(ip):
    return any(ip.startswith(m) for m in MUAF)

def oku_ayar():
    try:
        return json.load(open(f"{V}/ayarlar.json"))
    except Exception:
        return {}

def engelle(ip, sebep, puan=75):
    """IP'yi engel.json + nftables kilic'e ekle"""
    yol = f"{V}/engel.json"
    try:
        d = json.load(open(yol))
    except Exception:
        d = {"liste": [], "toplam": 0}
    if any((x.get("ip") == ip) for x in d.get("liste", [])):
        return False
    d.setdefault("liste", []).append({
        "ip": ip, "puan": puan, "zaman": datetime.now().strftime("%d.%m.%Y %H:%M"),
        "sebep": sebep, "kaynak": "IDS"
    })
    d["toplam"] = len(d["liste"])
    json.dump(d, open(yol, "w"), ensure_ascii=False)
    subprocess.run(["nft", "add", "element", "inet", "kilic", "kara", "{", ip, "}"],
                   stderr=subprocess.DEVNULL, check=False)
    # olay kaydı
    try:
        o = json.load(open(f"{V}/olaylar.json"))
    except Exception:
        o = {"olaylar": [], "toplam": 0}
    o.setdefault("olaylar", []).insert(0, {
        "zaman": datetime.now().strftime("%d.%m.%Y %H:%M:%S"), "ip": ip,
        "kural": "IDS", "seviye": "YUKSEK", "aciklama": f"IDS: {sebep}",
        "kaynak": "ids", "mitre": ""
    })
    o["olaylar"] = o["olaylar"][:500]
    o["toplam"] = o.get("toplam", 0) + 1
    json.dump(o, open(f"{V}/olaylar.json", "w"), ensure_ascii=False)
    return True

def tara():
    if not os.path.exists(EVE):
        return 0, 0, "eve.json yok (Suricata log kapalı olabilir)"
    ayar = oku_ayar()
    if not ayar.get("ids_engelle", True):
        return 0, 0, "IDS engelleme kapalı (ayarlar.json: ids_engelle=false)"

    # aynı IP'den 3+ ciddi alert -> engelle
    sayac = {}
    okunan = 0
    # ★ B-16 DUZELTMESI: KALICI OFFSET — her kosumda son 2MB bastan okunup
    # ayni alarmlar tekrar tekrar sayiliyordu (sisme + yanlis engelleme).
    OFF = f"{V}/ids_offset.json"
    try:
        _off = json.load(open(OFF))
        if not isinstance(_off, dict):
            _off = {}
    except Exception:
        _off = {}
    try:
        with open(EVE, "rb") as f:
            st = os.fstat(f.fileno())
            boyut = st.st_size
            # inode ayni ve offset gecerliyse kaldigi yerden devam et
            if _off.get("ino") == st.st_ino and 0 <= int(_off.get("pos", 0)) <= boyut:
                bas = int(_off["pos"])
            else:
                bas = max(0, boyut - 2_000_000)   # ilk kosum / rotasyon
            f.seek(bas)
            _ham = f.read().decode("utf-8", "ignore")
            _yeni_pos = f.tell()
    except Exception as e:
        return 0, 0, f"okuma hatasi: {e}"
    satirlar = _ham.splitlines()
    if bas > 0 and satirlar:
        satirlar = satirlar[1:]   # yarim kalan satiri atla
    # offset'i simdi kaydet (okundu sayilir; islenmese bile tekrar okunmaz)
    try:
        json.dump({"ino": st.st_ino, "pos": _yeni_pos}, open(OFF, "w"))
    except Exception:
        pass

    # ★ B-16: IP doğrulama yardımcısı
    try:
        from kalkan_ipdogrula import gecerli_ip as _gip
    except Exception:
        def _gip(x):
            import re as _re
            return bool(_re.match(r"^[0-9a-fA-F:.]+$", str(x))) and len(str(x)) <= 45
    for s in satirlar:
        try:
            e = json.loads(s)
        except Exception:
            continue
        if e.get("event_type") != "alert":
            continue
        src = e.get("src_ip", "")
        # ★ B-16: gecersiz IP engelleme zincirine GIRMEZ
        if not src or muaf_mi(src) or not _gip(src):
            continue
        kat = str(e.get("alert", {}).get("category", "")).lower()
        sev = int(e.get("alert", {}).get("severity", 3))
        imza = e.get("alert", {}).get("signature", "")[:60]
        if any(c in kat for c in CIDDI) or sev == 1:
            sayac.setdefault(src, {"adet": 0, "imza": imza})
            sayac[src]["adet"] += 1
            okunan += 1

    engellenen = 0
    for ip, v in sayac.items():
        if v["adet"] >= 3:
            if engelle(ip, f"Suricata {v['adet']}x: {v['imza']}"):
                engellenen += 1
    return okunan, engellenen, f"{okunan} ciddi alert, {engellenen} yeni engel"

if __name__ == "__main__":
    o, e, m = tara()
    print(f"[{datetime.now().strftime('%H:%M:%S')}] {m}")
