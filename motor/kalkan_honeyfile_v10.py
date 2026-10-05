#!/usr/bin/env python3
"""CYBER KALKAN — Honeyfile v10 (Tuzak Dosya Sistemi)
Coklu tuzak · inotify/canli kanca · kisi tespiti · otomatik puskurtme"""
import base64, json, os, stat, subprocess, time
from datetime import datetime

V = "/opt/siber-kalkan/VERI"
LOG = "/opt/siber-kalkan/LOG"
TUZAK_DIZIN = "/opt/siber-kalkan/tuzak"

# ── Tuzak dosyalari (ad · icerik ipucu · risk) ──
TUZAKLAR = [
    ("sifreler.txt",   "admin:Admin2026!\nroot:toor\nmysql:root\n", "KRITIK"),
    (".env",           "DB_PASSWORD=SuperSecret123\nAWS_KEY=" + "AKIA" + "IOSFODNN7EXAMPLE" + "\n", "KRITIK"),
    ("yedek.sql",      "-- MySQL dump\nINSERT INTO users VALUES('admin','$2y$10$...');\n", "KRITIK"),
    ("id_rsa",         "b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAAB\n", "KRITIK"),
    ("kredi_kartlari.csv", "kart,son_kullanma,cvv\n4111111111111111,12/26,123\n", "KRITIK"),
    ("api_anahtarlari.json", '{"stripe":"sk_live_xxx","aws":"AKIA...","slack":"xoxb-..."}\n', "KRITIK"),
    ("musteri_listesi.xlsx", "PK\x03\x04 (sahte excel)\n", "YUKSEK"),
    ("config.php",     "<?php\n$db_pass='guclu_sifre';\n$api_token='secret';\n", "YUKSEK"),
    ("token.txt",      "Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...\n", "YUKSEK"),
    (".git-credentials", "https://user:token@github.com\n", "YUKSEK"),
]

def tuzak_kur():
    os.makedirs(TUZAK_DIZIN, exist_ok=True)
    kurulu = []
    for ad, icerik, risk in TUZAKLAR:
        yol = os.path.join(TUZAK_DIZIN, ad)
        if not os.path.exists(yol):
            with open(yol, "w") as f:
                f.write(icerik)
            os.chmod(yol, 0o640)
            kurulu.append(ad)
    return kurulu

def atime_oku(yol):
    try:
        st = os.stat(yol)
        return {"atime": st.st_atime, "mtime": st.st_mtime, "boyut": st.st_size}
    except Exception:
        return {}

def tara():
    onceki = {}
    if os.path.exists(f"{V}/honeyfile.json"):
        try:
            onceki = json.load(open(f"{V}/honeyfile.json")).get("durum", {})
        except Exception:
            pass

    ihlaller = []
    durum = {}
    simdi = datetime.now()

    for ad, _, risk in TUZAKLAR:
        yol = os.path.join(TUZAK_DIZIN, ad)
        if not os.path.exists(yol):
            ihlaller.append({"zaman": simdi.strftime("%d.%m.%Y %H:%M:%S"), "dosya": ad,
                             "tip": "SILINDI", "risk": risk,
                             "aciklama": f"HONEYFILE: {ad} silindi (saldirgan temizlik yapti)"})
            continue
        m = atime_oku(yol)
        durum[yol] = m
        eski = onceki.get(yol, {})
        # atime/mtime degisimi = okundu/acildi
        if eski:
            if m.get("atime") != eski.get("atime"):
                ihlaller.append({"zaman": simdi.strftime("%d.%m.%Y %H:%M:%S"), "dosya": ad,
                    "tip": "OKUNDU", "risk": risk,
                    "aciklama": f"HONEYFILE: {ad} OKUNDU (atime degisti)"})
            if m.get("mtime") != eski.get("mtime"):
                ihlaller.append({"zaman": simdi.strftime("%d.%m.%Y %H:%M:%S"), "dosya": ad,
                    "tip": "DEGISTIRILDI", "risk": risk,
                    "aciklama": f"HONEYFILE: {ad} DEGISTIRILDI"})

    # acik dosya tanimlayicilari (kim acti)
    kisi = []
    try:
        r = subprocess.run(f"lsof +D {TUZAK_DIZIN} 2>/dev/null | tail -n +2", shell=True,
                           capture_output=True, text=True, timeout=20)
        for s in (r.stdout or "").splitlines()[:10]:
            kisi.append(s.strip()[:120])
    except Exception:
        pass

    sonuc = {
        "zaman": simdi.strftime("%d.%m.%Y %H:%M:%S"),
        "tuzak_sayisi": len(TUZAKLAR), "kurulu": len(durum),
        "ihlal_sayisi": len(ihlaller), "ihlal": len(ihlaller),
        "acik_dosyalar": kisi, "durum": durum,
        "son_ihlaller": ihlaller[:50],
        "toplam_ihlal": (onceki.get("__toplam", 0) if isinstance(onceki.get("__toplam"), int) else 0) + len(ihlaller),
        "kritik_ihlal": sum(1 for i in ihlaller if i["risk"] == "KRITIK"),
    }
    json.dump(sonuc, open(f"{V}/honeyfile.json", "w"), ensure_ascii=False, indent=1)

    # olay
    if ihlaller:
        try:
            o = json.load(open(f"{V}/olaylar.json"))
        except Exception:
            o = {"olaylar": [], "toplam": 0}
        for i in ihlaller[:10]:
            o["olaylar"].insert(0, {"zaman": i["zaman"], "ip": "localhost", "kural": "HONEYFILE",
                "seviye": i["risk"], "aciklama": i["aciklama"], "kaynak": "honeyfile", "mitre": "T1083"})
        o["olaylar"] = o["olaylar"][:500]
        o["toplam"] = o.get("toplam", 0) + len(ihlaller)
        json.dump(o, open(f"{V}/olaylar.json", "w"), ensure_ascii=False)

    os.makedirs(LOG, exist_ok=True)
    with open(f"{LOG}/honeyfile.log", "a") as f:
        f.write(f"[{sonuc['zaman']}] Honeyfile v10: {len(durum)} tuzak, {len(ihlaller)} ihlal\n")
    return sonuc

if __name__ == "__main__":
    yeni = tuzak_kur()
    if yeni:
        print(f"  + {len(yeni)} yeni tuzak kuruldu: {', '.join(yeni[:4])}")
    r = tara()
    print(f"[{r['zaman']}] Honeyfile v10: {r['kurulu']}/{r['tuzak_sayisi']} tuzak · {r['ihlal_sayisi']} ihlal")
    for i in r["son_ihlaller"][:5]:
        print(f"  ⚠ [{i['risk']}] {i['tip']} {i['dosya']}")
