#!/usr/bin/env python3
"""CYBER KALKAN — Kimlik / Erisim v10
RBAC · 2FA · oturum guvenligi · parola politikasi · kilit · audit"""
import json, os, hashlib, hmac, re, subprocess, time
from datetime import datetime, timedelta

V = "/opt/siber-kalkan/VERI"
LOG = "/opt/siber-kalkan/LOG"

# ── Parola politikasi (v10) ──
POLITIKA = {
    "min_uzunluk": 12, "buyuk_harf": True, "kucuk_harf": True,
    "rakam": True, "ozel_karakter": True, "max_deneme": 5,
    "kilit_suresi_dk": 30, "oturum_suresi_dk": 60, "2fa_zorunlu": True,
}
# ── Zayıf parolalar ──
ZAYIF = {"123456", "password", "kalkan", "admin", "root", "qwerty", "12345678",
         "1234", "111111", "abc123", "admin123", "letmein", "welcome", "toor",
         "sifre", "parola", "test", "guest", "master", "system"}

def parola_guclu(p):
    hata = []
    if len(p) < POLITIKA["min_uzunluk"]: hata.append(f"en az {POLITIKA['min_uzunluk']} karakter")
    if POLITIKA["buyuk_harf"] and not re.search(r"[A-ZÇĞİÖŞÜ]", p): hata.append("büyük harf")
    if POLITIKA["kucuk_harf"] and not re.search(r"[a-zçğıöşü]", p): hata.append("küçük harf")
    if POLITIKA["rakam"] and not re.search(r"\d", p): hata.append("rakam")
    if POLITIKA["ozel_karakter"] and not re.search(r"[^\w\s]", p): hata.append("özel karakter")
    if p.lower() in ZAYIF: hata.append("bilinen zayıf parola")
    return (not hata), hata

def deneme_oku():
    try:
        return json.load(open(f"{V}/deneme.json"))
    except Exception:
        return {}

def deneme_yaz(d):
    json.dump(d, open(f"{V}/deneme.json", "w"), ensure_ascii=False)

def kilitli_mi(ip):
    d = deneme_oku()
    k = d.get(ip, {})
    if k.get("kilit_bitis") and datetime.now() < datetime.fromisoformat(k["kilit_bitis"]):
        return True, k
    return False, k

def audit_oku():
    try:
        return json.load(open(f"{V}/audit.json"))
    except Exception:
        return {"kayitlar": []}

def tara():
    # 1. Kullanicilari yukle
    try:
        K = json.load(open(f"{V}/kullanicilar.json"))
    except Exception:
        K = []
    if isinstance(K, dict): K = K.get("kullanicilar", [])

    # 2. RBAC kontrol
    roller = {}
    for u in K:
        r = u.get("rol", "?")
        roller[r] = roller.get(r, 0) + 1

    # 3. Parola hash tipi
    sorunlar = []
    for u in K:
        h = u.get("sifre_hash", "")
        if h.startswith("$2y$") or h.startswith("$2a$") or h.startswith("$argon2"):
            tip = "bcrypt/argon2 (guclu)"
        elif len(h) == 64 and re.fullmatch(r"[a-f0-9]{64}", h):
            tip = "sha256 (TUZSUZ - zayif!)"
            sorunlar.append(f"{u.get('ad')}: tuzsuz sha256 hash")
        else:
            tip = "bilinmiyor"
            sorunlar.append(f"{u.get('ad')}: taninmayan hash formati")
        u["_hash_tip"] = tip

    # 4. 2FA durumu
    tfa = sum(1 for u in K if u.get("totp") or u.get("totp_secret"))
    if K and tfa < len(K) and POLITIKA["2fa_zorunlu"]:
        sorunlar.append(f"2FA {tfa}/{len(K)} kullanicida (zorunlu: hepsi)")

    # 5. Aktif kilitler
    d = deneme_oku()
    aktif_kilit = [ip for ip in d if kilitli_mi(ip)[0]]

    # 6. Audit istatistigi
    a = audit_oku()
    kayitlar = a.get("kayitlar", [])
    basarisiz = [k for k in kayitlar if "BASARISIZ" in str(k.get("olay", ""))][-20:]

    sonuc = {
        "zaman": datetime.now().strftime("%d.%m.%Y %H:%M:%S"),
        "kullanici_sayisi": len(K), "roller": roller,
        "kullanicilar": [{"ad": u.get("ad"), "rol": u.get("rol"),
                          "hash": u.get("_hash_tip"), "2fa": bool(u.get("totp") or u.get("totp_secret"))} for u in K],
        "2fa_aktif": tfa, "politika": POLITIKA,
        "aktif_kilit": aktif_kilit, "kilit_sayisi": len(aktif_kilit),
        "audit_kayit": len(kayitlar), "son_basarisiz": basarisiz,
        "sorunlar": sorunlar, "sorun_sayisi": len(sorunlar),
        "skor": max(0, 100 - len(sorunlar) * 15),
    }
    json.dump(sonuc, open(f"{V}/kimlik.json", "w"), ensure_ascii=False, indent=1)
    os.makedirs(LOG, exist_ok=True)
    with open(f"{LOG}/kimlik.log", "a") as f:
        f.write(f"[{sonuc['zaman']}] Kimlik v10: {len(K)} kullanici, 2FA {tfa}, {len(sorunlar)} sorun\n")
    return sonuc

if __name__ == "__main__":
    r = tara()
    print(f"[{r['zaman']}] Kimlik v10: {r['kullanici_sayisi']} kullanici · {r['kilit_sayisi']} kilit · skor {r['skor']}")
    for u in r["kullanicilar"]:
        print(f"  {u['ad']:12s} {u['rol']:10s} hash={u['hash']} 2FA={'✓' if u['2fa'] else '✗'}")
    for s in r["sorunlar"]:
        print(f"  ⚠ {s}")
