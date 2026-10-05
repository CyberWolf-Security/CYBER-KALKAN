#!/usr/bin/env python3
"""CYBER KALKAN — Aktif Savunma v10
Honeypot portlari · puskurtme (kilic) · otomatik engelleme · saldiri dokusu"""
import json, os, socket, subprocess, threading
from datetime import datetime

V = "/opt/siber-kalkan/VERI"
LOG = "/opt/siber-kalkan/LOG"

# ── Honeypot portlari (tuzak servisler) ──
HONEYPOT = {
    21: "FTP", 23: "Telnet", 25: "SMTP", 110: "POP3", 143: "IMAP",
    445: "SMB", 1433: "MSSQL", 3306: "MySQL", 3389: "RDP", 5900: "VNC",
    6379: "Redis", 8080: "HTTP-alt", 8443: "HTTPS-alt", 9200: "Elasticsearch",
    11211: "Memcached", 27017: "MongoDB",
}

# ── Puskurtme (kilic) durumu ──
def kilic_durum():
    d = {"tablo": False, "kara_set": 0, "kurallar": [], "kalici": False}
    try:
        r = subprocess.run("nft list table inet kilic", shell=True, capture_output=True, text=True, timeout=15)
        if "table inet kilic" in (r.stdout or ""):
            d["tablo"] = True
            d["kurallar"] = [s.strip() for s in (r.stdout or "").splitlines() if "reject" in s or "drop" in s][:6]
    except Exception:
        pass
    try:
        r = subprocess.run("nft list set inet kilic kara", shell=True, capture_output=True, text=True, timeout=15)
        d["kara_set"] = len([x for x in (r.stdout or "").split(",") if x.strip()])
    except Exception:
        pass
    d["kalici"] = os.path.exists("/etc/nftables.d/kilic.nft")
    return d

# ── Honeypot dinleyicileri (pasif log) ──
def port_dinle(port, ad, sure=3):
    """Porta baglanti gelirse kaydet (kisa sureli)"""
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        s.settimeout(sure)
        s.bind(("0.0.0.0", port))
        s.listen(5)
        try:
            c, addr = s.accept()
            veri = c.recv(512)
            c.close()
            return {"port": port, "servis": ad, "ip": addr[0], "veri": veri[:120].decode("utf-8", "ignore")}
        except socket.timeout:
            return None
        finally:
            s.close()
    except Exception:
        return None

def engelle(ip, sebep):
    # ★ GÜVENLİK (B-02): IP doğrulanmadan engel listesine ve shell komutuna GİRMEZ.
    # Geçersiz IP / özel ağ / beyaz liste → engellenmez (komut enjeksiyonu önlenir).
    try:
        from kalkan_ipdogrula import gecerli_ip, ozel_ip
        if not gecerli_ip(ip) or ozel_ip(ip):
            return False
    except ImportError:
        import re as _re
        if not _re.match(r"^[0-9a-fA-F:.]+$", str(ip)) or len(str(ip)) > 45:
            return False
    # beyaz liste kontrolü
    try:
        _b = json.load(open(f"{V}/beyaz_liste.json"))
        _bl = _b.get("liste", _b) if isinstance(_b, dict) else _b
        if ip in (set(_bl) if isinstance(_bl, list) else set()):
            return False
    except Exception:
        pass
    try:
        d = json.load(open(f"{V}/engel.json"))
    except Exception:
        d = {"liste": [], "toplam": 0}
    if any(x.get("ip") == ip for x in d.get("liste", [])):
        return False
    d["liste"].append({"ip": ip, "puan": 85, "zaman": datetime.now().strftime("%d.%m.%Y %H:%M"),
                       "sebep": f"AKTIF: {sebep}", "kaynak": "aktif"})
    d["toplam"] = len(d["liste"])
    json.dump(d, open(f"{V}/engel.json", "w"), ensure_ascii=False)
    subprocess.run(["nft", "add", "element", "inet", "kilic", "kara", "{", ip, "}"],
                   stderr=subprocess.DEVNULL, check=False)
    return True

def tara(honeypot_aktif=False):
    k = kilic_durum()
    yakalananlar = []

    # honeypot dinleme (opsiyonel — kisa sureli)
    if honeypot_aktif:
        isler = []
        for port, ad in list(HONEYPOT.items())[:6]:
            isler.append((port, ad))
        # paralel dinle
        sonuc = {}
        def isci(port, ad):
            r = port_dinle(port, ad, 2)
            if r:
                sonuc[port] = r
        th = [threading.Thread(target=isci, args=(p, a)) for p, a in isler]
        for t in th: t.start()
        for t in th: t.join(timeout=3)
        for r in sonuc.values():
            yakalananlar.append(r)
            engelle(r["ip"], f"honeypot portu {r['port']}/{r['servis']}")

    # engellenen IP istatistigi
    try:
        e = json.load(open(f"{V}/engel.json"))
        engel_liste = e.get("liste", [])
    except Exception:
        engel_liste = []
    bugun = datetime.now().strftime("%d.%m.%Y")
    bugun_engel = sum(1 for x in engel_liste if str(x.get("zaman", "")).startswith(bugun))

    sonuc = {
        "zaman": datetime.now().strftime("%d.%m.%Y %H:%M:%S"),
        "kilic": k, "honeypot_port": len(HONEYPOT),
        "honeypot_liste": {str(p): a for p, a in HONEYPOT.items()},
        "yakalanan": yakalananlar, "yakalanan_sayisi": len(yakalananlar),
        "engelli_toplam": len(engel_liste), "engelli_bugun": bugun_engel,
        "mod": "AKTIF" if k["tablo"] else "PASIF",
        "kurallar": k["kurallar"],
    }
    json.dump(sonuc, open(f"{V}/aktif_savunma.json", "w"), ensure_ascii=False, indent=1)
    os.makedirs(LOG, exist_ok=True)
    with open(f"{LOG}/aktif.log", "a") as f:
        f.write(f"[{sonuc['zaman']}] Aktif Savunma v10: kilic={k['tablo']} kara={k['kara_set']} yakalanan={len(yakalananlar)}\n")
    return sonuc

if __name__ == "__main__":
    r = tara("--honeypot" in __import__("sys").argv)
    print(f"[{r['zaman']}] Aktif Savunma v10: mod={r['mod']} · honeypot {r['honeypot_port']} port")
    print(f"  kilic: tablo={'✓' if r['kilic']['tablo'] else '✗'} · kalici={'✓' if r['kilic']['kalici'] else '✗'}")
    print(f"  engelli: {r['engelli_toplam']} (bugun {r['engelli_bugun']})")
    for kr in r["kurallar"]:
        print(f"    {kr}")
