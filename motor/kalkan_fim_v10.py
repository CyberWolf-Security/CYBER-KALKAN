#!/usr/bin/env python3
"""CYBER KALKAN — FIM v10 (Dosya Butunlugu Izleme, gelismis)
Baseline hash · degisim analizi · otomatik yedek/geri alma · risk seviyesi"""
import hashlib, json, os, shutil, subprocess
from datetime import datetime

V = "/opt/siber-kalkan/VERI"
LOG = "/opt/siber-kalkan/LOG"
YEDEK = "/opt/siber-kalkan/yedek/fim"

# ── İzlenecek kritik dosyalar (kategori · risk) ──
IZLENEN = {
    # Sistem kimlik doğrulama
    "/etc/passwd": ("SISTEM", "KRITIK"),
    "/etc/shadow": ("SISTEM", "KRITIK"),
    "/etc/group": ("SISTEM", "YUKSEK"),
    "/etc/sudoers": ("SISTEM", "KRITIK"),
    "/etc/hosts": ("SISTEM", "ORTA"),
    "/etc/resolv.conf": ("SISTEM", "ORTA"),
    "/etc/ssh/sshd_config": ("SISTEM", "KRITIK"),
    "/etc/crontab": ("SISTEM", "YUKSEK"),
    "/root/.ssh/authorized_keys": ("SISTEM", "KRITIK"),
    "/root/.bashrc": ("SISTEM", "ORTA"),
    "/root/.profile": ("SISTEM", "ORTA"),
    # Yetki yükseltme vektörleri
    "/etc/ld.so.preload": ("ROOTKIT", "KRITIK"),
    "/etc/ld.so.conf": ("ROOTKIT", "YUKSEK"),
    "/etc/pam.d/common-auth": ("SISTEM", "KRITIK"),
    "/etc/nsswitch.conf": ("SISTEM", "YUKSEK"),
    # Ağ / güvenlik duvarı
    "/etc/nftables.conf": ("FIREWALL", "KRITIK"),
    "/etc/nftables.d/kilic.nft": ("FIREWALL", "KRITIK"),
    # Web paneli
    "/var/www/kalkan-panel/ortak.php": ("PANEL", "YUKSEK"),
    "/var/www/kalkan-panel/giris.php": ("PANEL", "KRITIK"),
    "/var/www/kalkan-panel/ayarlar.php": ("PANEL", "YUKSEK"),
    "/var/www/kalkan-panel/.htaccess": ("PANEL", "ORTA"),
    # Motor / WAF
    "/opt/siber-kalkan/waf.php": ("WAF", "KRITIK"),
    "/opt/siber-kalkan/waf-router.php": ("WAF", "YUKSEK"),
    "/opt/siber-kalkan/MOTOR/kalkan_motor.py": ("MOTOR", "YUKSEK"),
    "/opt/siber-kalkan/VERI/kurallar.json": ("MOTOR", "YUKSEK"),
    # Servis dosyaları
    "/etc/systemd/system/kalkan-motor.service": ("SERVIS", "YUKSEK"),
    "/etc/systemd/system/kalkan-panel.service": ("SERVIS", "YUKSEK"),
    # Web sunucu (varsa)
    "/etc/apache2/apache2.conf": ("SERVIS", "ORTA"),
    "/etc/nginx/nginx.conf": ("SERVIS", "ORTA"),
    # Shell profilleri (persistence vektörü)
    "/etc/profile": ("SISTEM", "YUKSEK"),
    "/etc/bash.bashrc": ("SISTEM", "YUKSEK"),
}

def sha(yol):
    try:
        h = hashlib.sha256()
        with open(yol, "rb") as f:
            for blk in iter(lambda: f.read(65536), b""):
                h.update(blk)
        return h.hexdigest()
    except Exception:
        return None

def meta(yol):
    """dosya metasi"""
    try:
        st = os.stat(yol)
        return {
            "boyut": st.st_size,
            "izin": oct(st.st_mode)[-4:],
            "sahip": f"{st.st_uid}:{st.st_gid}",
            "degisme": datetime.fromtimestamp(st.st_mtime).strftime("%d.%m.%Y %H:%M:%S"),
        }
    except Exception:
        return {}

def oku(ad, varsa=None):
    try:
        return json.load(open(f"{V}/{ad}.json"))
    except Exception:
        return varsa if varsa is not None else {}

def yaz(ad, veri):
    json.dump(veri, open(f"{V}/{ad}.json", "w"), ensure_ascii=False, indent=1)

def tara():
    onceki = oku("fim", {"baseline": {}, "degisimler": [], "toplam": 0})
    baseline = onceki.get("baseline", {})
    degisimler = []
    simdi = datetime.now()

    for yol, (kategori, risk) in IZLENEN.items():
        yeni_hash = sha(yol)
        once_hash = baseline.get(yol, {}).get("hash")

        if yeni_hash is None:                      # dosya yok
            if once_hash is not None:
                degisimler.append({"zaman": simdi.strftime("%d.%m.%Y %H:%M:%S"), "dosya": yol,
                    "tip": "SILINDI", "risk": risk, "kategori": kategori,
                    "aciklama": f"{kategori}: {os.path.basename(yol)} silindi"})
        elif once_hash is None:                    # yeni takip
            pass
        elif yeni_hash != once_hash:               # DEĞİŞTİ
            d = {"zaman": simdi.strftime("%d.%m.%Y %H:%M:%S"), "dosya": yol,
                 "tip": "DEGISTI", "risk": risk, "kategori": kategori,
                 "eski_hash": once_hash[:16], "yeni_hash": yeni_hash[:16],
                 "meta": meta(yol),
                 "aciklama": f"{kategori}: {os.path.basename(yol)} degisti"}
            # kritik -> otomatik yedek sakla
            if risk == "KRITIK":
                os.makedirs(YEDEK, exist_ok=True)
                yb = f"{YEDEK}/{os.path.basename(yol)}.{simdi.strftime('%d%m_%H%M%S')}.bak"
                try:
                    shutil.copy2(yol, yb); d["yedek"] = yb
                except Exception:
                    pass
            degisimler.append(d)

        # ★ B-12 DUZELTMESI: KRITIK dosyada taban cizgisi OTOMATIK GUNCELLENMEZ.
        # Aksi halde saldirganin degisikligi "yeni normal" sayilip bir daha gorunmez.
        # Onay icin VERI/fim_onay.json -> {"onayli": ["/tam/yol"]}
        _onayli = set(oku("fim_onay", {"onayli": []}).get("onayli", []))
        _degisti = (once_hash is not None and yeni_hash != once_hash)
        if risk == "KRITIK" and _degisti and yol not in _onayli:
            print(f"  \u26a0\ufe0f  KRITIK degisti, taban cizgisi ONAYSIZ guncellenmedi: {yol}")
        else:
            baseline[yol] = {"hash": yeni_hash, "kategori": kategori, "risk": risk,
                             "meta": meta(yol), "son_kontrol": simdi.strftime("%d.%m.%Y %H:%M:%S")}

    # olay kaydı (KRITIK/YUKSEK değişimler)
    if degisimler:
        olay = oku("olaylar", {"olaylar": [], "toplam": 0})
        for d in degisimler:
            if d["risk"] in ("KRITIK", "YUKSEK"):
                olay["olaylar"].insert(0, {
                    "zaman": d["zaman"], "ip": "localhost", "kural": "FIM",
                    "seviye": d["risk"], "aciklama": f"FIM: {d['aciklama']}",
                    "kaynak": "fim", "mitre": "T1565.001"})
        olay["olaylar"] = olay["olaylar"][:500]
        olay["toplam"] = olay.get("toplam", 0) + len([d for d in degisimler if d["risk"] in ("KRITIK","YUKSEK")])
        yaz("olaylar", olay)

    sonuc = {
        "zaman": simdi.strftime("%d.%m.%Y %H:%M:%S"),
        "izlenen": len(IZLENEN), "saglam": sum(1 for y in IZLENEN if sha(y) and baseline.get(y,{}).get("hash") == sha(y)),
        "degisim_sayisi": len(degisimler), "toplam": onceki.get("toplam", 0) + len(degisimler),
        "baseline": baseline,
        "degisimler": (degisimler + onceki.get("degisimler", []))[:300],
        "kategoriler": {k: sum(1 for v in IZLENEN.values() if v[0] == k) for k in set(v[0] for v in IZLENEN.values())},
    }
    yaz("fim", sonuc)
    os.makedirs(LOG, exist_ok=True)
    with open(f"{LOG}/fim.log", "a") as f:
        f.write(f"[{sonuc['zaman']}] {len(IZLENEN)} dosya izlendi, {len(degisimler)} degisim\n")
    return sonuc

if __name__ == "__main__":
    r = tara()
    print(f"[{r['zaman']}] FIM v10: {r['izlenen']} dosya · {r['saglam']} saglam · {r['degisim_sayisi']} degisim")
    for d in r.get("degisimler", [])[:5]:
        if not isinstance(d, dict):
            continue
        print(f"  [{d.get('risk','?')}] {d.get('tip','?')} {d.get('dosya','?')}")
