#!/usr/bin/env python3
"""CYBER KALKAN — Firewall Yukleyici v10
nftables kilic tablosu · kara liste senkron · kalicilik · set optimizasyonu · dogrulama"""
import json, os, subprocess, time
from datetime import datetime

V = "/opt/siber-kalkan/VERI"
LOG = "/opt/siber-kalkan/LOG"
NFT_DOSYA = "/etc/nftables.d/kilic.nft"
TABLO = "kilic"
SET = "kara"

def sh(cmd):
    try:
        r = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=25)
        return r.returncode, (r.stdout or "").strip(), (r.stderr or "").strip()
    except Exception as e:
        return 1, "", str(e)

def sh_arg(argv, t=25):
    """★ GÜVENLİK (B-02): Liste formu — shell YOK. Dışarıdan gelen IP
    bu fonksiyonla çalıştırılır; komut enjeksiyonu imkânsız."""
    try:
        r = subprocess.run(argv, capture_output=True, text=True, timeout=t)
        return r.returncode, (r.stdout or "").strip(), (r.stderr or "").strip()
    except Exception as e:
        return 1, "", str(e)

def _ip_gecerli(ip):
    """IP doğrulama (shell'e girmeden önce zorunlu)."""
    try:
        from kalkan_ipdogrula import gecerli_ip
        return gecerli_ip(ip)
    except ImportError:
        import re as _re
        s = str(ip)
        return bool(_re.match(r"^[0-9a-fA-F:.]+$", s)) and len(s) <= 45

def tablo_var():
    rc, out, _ = sh(f"nft list table inet {TABLO}")
    return rc == 0 and f"table inet {TABLO}" in out

def kur():
    """Tablo + set + kurallar yoksa kur"""
    if tablo_var():
        return "mevcut"
    script = f"""add table inet {TABLO}
add set inet {TABLO} {SET} {{ type ipv4_addr; flags interval; }}
add chain inet {TABLO} giris {{ type filter hook input priority -150; policy accept; }}
add chain inet {TABLO} cikis {{ type filter hook output priority -150; policy accept; }}
add rule inet {TABLO} giris ip saddr @{SET} counter reject with tcp reset
add rule inet {TABLO} cikis ip daddr @{SET} counter drop
"""
    p = "/tmp/kilic_kur.nft"
    open(p, "w").write(script)
    rc, _, err = sh(f"nft -f {p}")
    os.remove(p)
    return "kuruldu" if rc == 0 else f"hata: {err[:80]}"

def set_ip_sayisi():
    rc, out, _ = sh(f"nft list set inet {TABLO} {SET}")
    if rc != 0:
        return 0
    return len([x for x in out.replace("{", " ").replace("}", " ").replace(",", " ").split()
                if x.count(".") == 3])

def engelli_liste():
    try:
        return json.load(open(f"{V}/engel.json")).get("liste", [])
    except Exception:
        return []

def senkron():
    """engel.json -> nftables set (fark kadar)"""
    ipler = [x.get("ip") for x in engelli_liste() if x.get("ip")]
    if not ipler:
        return 0, 0
    # toplu element ekleme (parcalar halinde)
    mevcut = set_ip_sayisi()
    eklenen = 0
    for i in range(0, len(ipler), 500):
        # ★ GÜVENLİK (B-02): sadece geçerli IP'ler + liste formu (shell yok)
        blok = [x for x in ipler[i:i+500] if _ip_gecerli(x)]
        if not blok:
            continue
        rc, _, _ = sh_arg(["nft", "add", "element", "inet", TABLO, SET,
                           "{ " + ", ".join(blok) + " }"])
        if rc == 0:
            eklenen += len(blok)
    return len(ipler), set_ip_sayisi()

def kalici_yap():
    """kilic.nft dosyasini yaz (boot'ta yuklenir)"""
    icerik = f"""#!/usr/sbin/nft -f
# CYBER KALKAN v10 — Kalici firewall (boot'ta yuklenir)
table inet {TABLO} {{
    set {SET} {{
        type ipv4_addr
        flags interval
    }}
    chain giris {{
        type filter hook input priority -150; policy accept;
        ip saddr @{SET} counter reject with tcp reset
    }}
    chain cikis {{
        type filter hook output priority -150; policy accept;
        ip daddr @{SET} counter drop
    }}
}}
"""
    try:
        with open(NFT_DOSYA, "w") as f:
            f.write(icerik)
        os.chmod(NFT_DOSYA, 0o644)
        return True
    except Exception:
        return False

def engelle(ip, sebep="manuel"):
    # ★ GÜVENLİK (B-02): IP doğrulanmadan engellenmez ve shell'e girmez
    if not _ip_gecerli(ip):
        return False
    try:
        d = json.load(open(f"{V}/engel.json"))
    except Exception:
        d = {"liste": [], "toplam": 0}
    if not any(x.get("ip") == ip for x in d.get("liste", [])):
        d["liste"].append({"ip": ip, "puan": 70, "zaman": datetime.now().strftime("%d.%m.%Y %H:%M"),
                           "sebep": sebep, "kaynak": "fw"})
        d["toplam"] = len(d["liste"])
        json.dump(d, open(f"{V}/engel.json", "w"), ensure_ascii=False)
    rc, _, _ = sh_arg(["nft", "add", "element", "inet", TABLO, SET, "{ " + ip + " }"])
    return rc == 0

def kaldir(ip):
    # ★ GÜVENLİK (B-02)
    if not _ip_gecerli(ip):
        return False
    rc, _, _ = sh_arg(["nft", "delete", "element", "inet", TABLO, SET, "{ " + ip + " }"])
    try:
        d = json.load(open(f"{V}/engel.json"))
        d["liste"] = [x for x in d.get("liste", []) if x.get("ip") != ip]
        d["toplam"] = len(d["liste"])
        json.dump(d, open(f"{V}/engel.json", "w"), ensure_ascii=False)
    except Exception:
        pass
    return rc == 0

def tara():
    var = tablo_var()
    if not var:
        durum = kur()
        var = tablo_var()
    else:
        durum = "mevcut"

    liste = engelli_liste()
    nft_sayi = set_ip_sayisi()
    kalici = os.path.exists(NFT_DOSYA)
    if var and not kalici:
        kalici = kalici_yap()

    # senkron gerekli mi
    fark = len(liste) - nft_sayi
    if var and (fark > 5 or fark < -5):
        senkron()
        nft_sayi = set_ip_sayisi()

    rc, kurallar, _ = sh(f"nft list table inet {TABLO} | grep -cE 'reject|drop'")

    sonuc = {
        "zaman": datetime.now().strftime("%d.%m.%Y %H:%M:%S"),
        "tablo": var, "kurulum": durum, "kalici": kalici,
        "liste_ip": len(liste), "nft_ip": nft_sayi,
        "senkron": abs(len(liste) - nft_sayi) <= 5,
        "kural_sayisi": int(kurallar) if kurallar.isdigit() else 0,
        "dosya": NFT_DOSYA,
    }
    json.dump(sonuc, open(f"{V}/firewall.json", "w"), ensure_ascii=False, indent=1)
    os.makedirs(LOG, exist_ok=True)
    with open(f"{LOG}/firewall.log", "a") as f:
        f.write(f"[{sonuc['zaman']}] FW v10: tablo={var} liste={len(liste)} nft={nft_sayi} kalici={kalici}\n")
    return sonuc

if __name__ == "__main__":
    import sys
    if len(sys.argv) > 1 and sys.argv[1] == "--senkron":
        a, b = senkron()
        print(f"  senkron: liste={a} nft={b}")
        sys.exit(0)
    r = tara()
    print(f"[{r['zaman']}] Firewall v10: tablo={'✓' if r['tablo'] else '✗'} · kalici={'✓' if r['kalici'] else '✗'}")
    print(f"  engel.json: {r['liste_ip']} IP · nftables: {r['nft_ip']} IP · kural: {r['kural_sayisi']} · senkron={'✓' if r['senkron'] else '✗'}")
