#!/usr/bin/env python3
"""
KOD KORUMA — kritik dosyalarin butunlugunu izler ve gerekirse onarir
  Kayit : /opt/siber-kalkan/VERI/kod_koruma.json
  Kullanim:
    python3 kalkan_koruma.py --kaydet   (hash kaydet / referans olustur)
    python3 kalkan_koruma.py --kontrol  (degisiklik var mi?)
    python3 kalkan_koruma.py --onar     (degisen dosyalari paketten geri yukle)
"""
import hashlib, json, os, sys, shutil, subprocess
from datetime import datetime

V = "/opt/siber-kalkan/VERI"
KAYIT = f"{V}/kod_koruma.json"
YEDEK_KOK = "/root/Masaüstü/CYBER_KALKAN_KURULUM"

# korunacak kritik dosyalar (kilitlenecek + hash izlenecek)
KRITIK = [
    "/opt/siber-kalkan/MOTOR/kalkan_motor.py",
    "/opt/siber-kalkan/MOTOR/kalkan_av.py",
    "/opt/siber-kalkan/MOTOR/kalkan_fim.py",
    "/opt/siber-kalkan/MOTOR/kalkan_indexer.py",
    "/opt/siber-kalkan/MOTOR/kalkan_ekstra.py",
    "/opt/siber-kalkan/VERI/kurallar.json",
    "/var/www/kalkan-panel/ortak.php",
    "/var/www/kalkan-panel/giris.php",
    "/var/www/kalkan-panel/ajan_kayit.php",
    "/var/www/kalkan-panel/assets/panel.css",
]

def sha(yol):
    try:
        h = hashlib.sha256()
        with open(yol, "rb") as f:
            for blok in iter(lambda: f.read(65536), b""):
                h.update(blok)
        return h.hexdigest()
    except Exception:
        return None

def oku():
    try:
        return json.load(open(KAYIT, encoding="utf-8"))
    except Exception:
        return {}

def yaz(d):
    os.makedirs(V, exist_ok=True)
    json.dump(d, open(KAYIT, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    subprocess.run(["chown", "www-data:www-data", KAYIT])

def kod_saglam_mi(dosyalar=None):
    """KRITIK: bozuk kodu referans yapmayi engelle — .py dosyalarini derle"""
    import py_compile, tempfile
    hatali = []
    for y in (dosyalar or KRITIK):
        if not y.endswith(".py") or not os.path.exists(y):
            continue
        try:
            py_compile.compile(y, cfile=tempfile.mktemp(), doraise=True)
        except Exception:
            hatali.append(os.path.basename(y))
    return hatali

def kaydet(zorla=False):
    # GUVENLIK: bozuk .py dosyasi referans yapilamaz
    if not zorla:
        h = kod_saglam_mi()
        if h:
            print(f"  ✗ REDDEDILDI: bozuk kod referans yapilamaz → {h}")
            print("    (once --onar ile temiz surumu geri yukle)")
            return False
    d = {"olusturma": datetime.now().strftime("%d.%m.%Y %H:%M:%S"), "dosyalar": {}, "ihlaller": []}
    for y in KRITIK:
        h = sha(y)
        if h:
            d["dosyalar"][y] = h
    yaz(d)
    print(f"  kaydedildi: {len(d['dosyalar'])} kritik dosya hashi")
    return True

def kontrol(sessiz=False):
    d = oku()
    if not d.get("dosyalar"):
        if not sessiz: print("  referans yok — once --kaydet")
        return []
    fark = []
    for yol, ref in d["dosyalar"].items():
        su = sha(yol)
        if su is None:
            fark.append((yol, "KAYIP"))
        elif su != ref:
            fark.append((yol, f"DEGISMIS ({ref[:8]} -> {su[:8]})"))
    if not sessiz:
        if fark:
            for y, n in fark: print(f"  ⚠️  {os.path.basename(y)}: {n}")
        else:
            print(f"  ✓ {len(d['dosyalar'])} kritik dosya saglam")
    return fark

def dosya_bozuk_mu(yol):
    """dosya gercekten BOZUK mu (syntax hatasi)? Mesru degisiklik degil."""
    import py_compile, json as _json
    try:
        if yol.endswith(".py"):
            py_compile.compile(yol, cfile="/tmp/_koruma_test.pyc", doraise=True)
            return False
        if yol.endswith(".php"):
            r = subprocess.run(["php", "-l", yol], capture_output=True, text=True)
            return r.returncode != 0
        if yol.endswith(".json"):
            _json.load(open(yol, encoding="utf-8"))
            return False
        if yol.endswith(".css"):
            t = open(yol, encoding="utf-8", errors="ignore").read()
            return t.count("{") != t.count("}")
        # bilinmeyen tip: bos/0 byte ise bozuk say
        return os.path.getsize(yol) == 0
    except Exception:
        return True


def onar():
    """degisen dosyalari paket klasorunden geri yukle"""
    fark = kontrol(sessiz=True)
    if not fark:
        print("  onarilacak dosya yok")
        return 0
    n = 0
    mesru = []
    for yol, _ in fark:
        ad = os.path.basename(yol)
        # AKILLI AYRIM: dosya gercekten bozuk mu, yoksa mesru degisiklik mi?
        if not dosya_bozuk_mu(yol):
            mesru.append(ad)
            print(f"  ℹ️  mesru degisiklik (saglam): {ad} — DOKUNULMADI")
            continue
        # gercekten bozuk -> paketten geri yukle
        subprocess.run(["chattr", "-i", yol], capture_output=True)
        kaynak = None
        for kok, _, dosyalar in os.walk(YEDEK_KOK):
            if ad in dosyalar:
                kaynak = os.path.join(kok, ad)
                break
        if kaynak:
            # ÖNCE canlıyı sakla (veri kaybı olmasın — v10.1)
            try:
                sakla = yol + ".onarim_oncesi"
                shutil.copy2(yol, sakla)
                print(f"     (canlı kopya saklandı: {os.path.basename(sakla)})")
            except Exception as e:
                print(f"     ⚠ canlı kopya saklanamadı: {e}")
            shutil.copy2(kaynak, yol)
            subprocess.run(["chown", "www-data:www-data", yol], capture_output=True)
            n += 1
            print(f"  ♻️  BOZUK onarildi: {ad}  ←  paket")
        else:
            print(f"  ✗ yedek bulunamadi: {ad}")
        # NOT: kullanici istegiyle dosya kilitleme (chattr +i) KALDIRILDI —
        # kilitler motorun/panelin yazmasini engelleyip sistemi kilitliyordu.
        # Onarim yine paketten geri yukler; kilit uygulanmaz.
    if mesru:
        print(f"  ℹ️  {len(mesru)} mesru degisiklik korundu: {', '.join(mesru)}")
        kaydet(zorla=True)
    # onarim sonrasi: hala bozuk varsa kaydetme (korumayi koru)
    if n:
        kalan = kontrol(sessiz=True)
        if kalan:
            print("  ⚠️  onarim sonrasi hala sorunlu dosya var — referans GUNCELLENMEDI")
        else:
            kaydet(zorla=True)
    return n

def kilitle(ac=False):
    """chattr +i ile kritik dosyalari kilitle (ac=True ise kilidi ac)"""
    isaret = "-i" if ac else "+i"
    n = 0
    for y in KRITIK:
        if os.path.exists(y):
            r = subprocess.run(["chattr", isaret, y], capture_output=True, text=True)
            if r.returncode == 0:
                n += 1
    print(f"  {'kilidi acildi' if ac else 'kilitlendi'}: {n} dosya (chattr {'-i' if ac else '+i'})")

if __name__ == "__main__":
    a = sys.argv[1] if len(sys.argv) > 1 else "--kontrol"
    if a == "--kaydet":
        kaydet()
    elif a == "--kontrol":
        f = kontrol()
        sys.exit(1 if f else 0)
    elif a == "--senkron":
        # canlı -> yedek senkron (eski yedeğin geri yazmasını önler)
        n = 0; h = 0
        HEDEF = [
            ("/var/www/kalkan-panel", YEDEK_KOK + "/panel"),
            ("/opt/siber-kalkan/MOTOR", YEDEK_KOK + "/MOTOR"),
        ]
        for kaynak_dizin, hedef_dizin in HEDEF:
            if not os.path.isdir(kaynak_dizin):
                continue
            os.makedirs(hedef_dizin, exist_ok=True)
            for kok, _, dosyalar in os.walk(kaynak_dizin):
                if "/assets" in kok or "/.git" in kok:
                    for ad in dosyalar:
                        try:
                            shutil.copy2(os.path.join(kok, ad), os.path.join(hedef_dizin, "assets", ad))
                            h += 1
                        except Exception:
                            pass
                    continue
                for ad in dosyalar:
                    if not ad.endswith((".php", ".py")):
                        continue
                    try:
                        shutil.copy2(os.path.join(kok, ad), os.path.join(hedef_dizin, ad))
                        n += 1
                    except Exception:
                        pass
        # kök ortak.php/panel kopyaları
        for kaynak, hedef in [
            ("/var/www/kalkan-panel/ortak.php", YEDEK_KOK + "/ortak.php"),
            ("/opt/siber-kalkan/waf.php", YEDEK_KOK + "/waf.php"),
        ]:
            if os.path.exists(kaynak):
                try:
                    os.makedirs(os.path.dirname(hedef), exist_ok=True)
                    shutil.copy2(kaynak, hedef); n += 1
                except Exception:
                    pass
        print(f"  ✓ senkron: {n} php/py + {h} asset  (canlı -> yedek)")
    elif a == "--onar":
        onar()
    elif a == "--kilitle":
        kilitle()
    elif a == "--kilidi-ac":
        kilitle(ac=True)
    else:
        print("kullanim: --kaydet | --kontrol | --onar | --kilitle | --kilidi-ac")
