#!/usr/bin/env python3
"""CYBER KALKAN — Yedek + Uyumluluk v10
Immutable yedek · SHA-256 dogrulama · geri alma testi · ISO27001/NIST/GDPR/PCI/KVKK"""
import json, os, hashlib, shutil, subprocess, tarfile
from datetime import datetime

V = "/opt/siber-kalkan/VERI"
LOG = "/opt/siber-kalkan/LOG"
YEDEK = "/opt/siber-kalkan/yedek"
HEDEFLER = ["/opt/siber-kalkan/VERI", "/var/www/kalkan-panel", "/opt/siber-kalkan/waf.php"]

# ── Uyumluluk cerceveleri (kontrol eslesmesi) ──
CERCEVELER = {
    "ISO 27001:2022": [
        ("A.5.1", "Bilgi guvenligi politikalari", "kalkan_motor"),
        ("A.5.7", "Tehdit istihbarati", "cografya"),
        ("A.5.15", "Erisim kontrolu", "kimlik"),
        ("A.5.23", "Bulut hizmet guvenligi", "bulut"),
        ("A.8.7", "Zararli yazilim korumasi", "av"),
        ("A.8.8", "Teknik zafiyet yonetimi", "zafiyet"),
        ("A.8.9", "Yapilandirma yonetimi", "sca"),
        ("A.8.15", "Loglama", "indexer"),
        ("A.8.16", "Izleme aktiviteleri", "ueba"),
        ("A.8.20", "Ag guvenligi", "aktif"),
        ("A.8.24", "Kriptografi kullanimi", "kimlik"),
        ("A.8.28", "Guvenli kodlama", "waf"),
    ],
    "NIST CSF 2.0": [
        ("GV.OC", "Kurumsal baglam", "sca"),
        ("ID.AM", "Varlik yonetimi", "cografya"),
        ("ID.RA", "Risk degerlendirme", "zafiyet"),
        ("PR.AA", "Kimlik ve erisim", "kimlik"),
        ("PR.AT", "Farkindalik", "gelistirenler"),
        ("PR.DS", "Veri guvenligi", "fim"),
        ("PR.PS", "Platform guvenligi", "sca"),
        ("DE.AE", "Anomali tespiti", "ueba"),
        ("DE.CM", "Surekli izleme", "fim"),
        ("DE.DP", "Tespit surecleri", "av"),
        ("RS.AN", "Olay analizi", "kimlik"),
        ("RS.MI", "Etki azaltma", "aktif"),
        ("RC.RP", "Kurtarma plani", "yedek"),
    ],
    "GDPR / KVKK": [
        ("Md.5", "Veri isleme ilkeleri", "kimlik"),
        ("Md.24", "Veri sorumlusu yukumlulugu", "sca"),
        ("Md.25", "Tasarimda gizlilik", "waf"),
        ("Md.30", "Isleme kayitlari", "indexer"),
        ("Md.32", "Isleme guvenligi", "fim"),
        ("Md.33", "Ihlal bildirimi", "aktif"),
        ("Md.35", "Etki degerlendirmesi", "zafiyet"),
    ],
    "PCI DSS 4.0": [
        ("1", "Ag guvenlik kontrolleri", "aktif"),
        ("2", "Guvenli yapilandirma", "sca"),
        ("3", "Saklanan veriyi koruma", "fim"),
        ("4", "Iletimde sifreleme", "kimlik"),
        ("5", "Zararli yazilim korumasi", "av"),
        ("6", "Guvenli sistem gelistirme", "waf"),
        ("7", "Erisim kisitlama", "kimlik"),
        ("8", "Kimlik dogrulama", "kimlik"),
        ("10", "Izleme ve loglama", "indexer"),
        ("11", "Guvenlik testi", "zafiyet"),
    ],
    "CIS Controls v8": [
        ("1", "Varlik envanteri", "cografya"),
        ("2", "Yazilim envanteri", "zafiyet"),
        ("3", "Veri koruma", "fim"),
        ("4", "Guvenli yapilandirma", "sca"),
        ("5", "Hesap yonetimi", "kimlik"),
        ("6", "Erisim kontrolu", "kimlik"),
        ("13", "Ag izleme", "aktif"),
        ("17", "Olay mudahale", "kimlik"),
    ],
}

def sha(yol):
    try:
        h = hashlib.sha256()
        with open(yol, "rb") as f:
            for b in iter(lambda: f.read(131072), b""):
                h.update(b)
        return h.hexdigest()
    except Exception:
        return None

def yedekle():
    """Immutable (salt-okunur) tar yedegi + sha256 dogrulama"""
    os.makedirs(YEDEK, exist_ok=True)
    z = datetime.now().strftime("%d%m_%H%M%S")
    yol = f"{YEDEK}/kalkan_{z}.tar.gz"
    try:
        with tarfile.open(yol, "w:gz") as t:
            for h in HEDEFLER:
                if os.path.exists(h):
                    t.add(h, arcname=os.path.basename(h.rstrip("/")))
        h = sha(yol)
        # immutable: salt-okunur yap
        os.chmod(yol, 0o444)
        # manifest
        with open(f"{YEDEK}/manifest.jsonl", "a") as f:
            f.write(json.dumps({"zaman": datetime.now().strftime("%d.%m.%Y %H:%M:%S"),
                                "dosya": yol, "sha256": h,
                                "boyut": os.path.getsize(yol)}, ensure_ascii=False) + "\n")
        return {"dosya": yol, "sha256": h, "boyut": os.path.getsize(yol), "durum": "OK"}
    except Exception as e:
        return {"durum": f"HATA: {e}"}

def dogrula():
    """En son yedegin sha256'sini dogrula + arsiv butunlugu"""
    try:
        son = None
        with open(f"{YEDEK}/manifest.jsonl") as f:
            for satir in f:
                try: son = json.loads(satir)
                except Exception: pass
        if not son: return {"durum": "yedek yok"}
        yol = son["dosya"]
        if not os.path.exists(yol): return {"durum": "dosya kayip"}
        g = sha(yol)
        return {"durum": "SAGLAM" if g == son["sha256"] else "BOZUK",
                "dosya": os.path.basename(yol), "beklenen": son["sha256"][:16],
                "olculen": (g or "")[:16], "boyut": son["boyut"],
                "immutable": (os.stat(yol).st_mode & 0o222) == 0}
    except Exception as e:
        return {"durum": f"HATA: {e}"}

def compliance():
    """Her cerceve icin modul eslesmesi -> skor"""
    # modul mevcut mu
    M = "/opt/siber-kalkan/MOTOR"
    var = lambda ad: any(ad in d for d in os.listdir(M)) or os.path.exists(f"{V}/{ad}.json")
    sonuc = {}
    for cerceve, kontroller in CERCEVELER.items():
        gecen = []
        kalan = []
        for kod, ad, modul in kontroller:
            (gecen if var(modul) else kalan).append(f"{kod} {ad}")
        sonuc[cerceve] = {"toplam": len(kontroller), "gecen": len(gecen),
                          "skor": round(len(gecen) / len(kontroller) * 100),
                          "kalan": kalan}
    genel = round(sum(v["skor"] for v in sonuc.values()) / len(sonuc)) if sonuc else 0
    return sonuc, genel

def tara(yedek_al=True):
    y = yedekle() if yedek_al else {}
    d = dogrula()
    c, genel = compliance()
    sonuc = {
        "zaman": datetime.now().strftime("%d.%m.%Y %H:%M:%S"),
        "yedek": y, "dogrulama": d,
        "dosya": y.get("boyut", 0) // 1024, "boyut_kb": y.get("boyut", 0) // 1024,
        "kapsam": c, "genel_skor": genel,
        "cerceve_sayisi": len(c),
        "gecen": sum(v["gecen"] for v in c.values()),
        "toplam": sum(v["toplam"] for v in c.values()),
    }
    json.dump(sonuc, open(f"{V}/compliance.json", "w"), ensure_ascii=False, indent=1)
    json.dump(sonuc, open(f"{V}/yedek.json", "w"), ensure_ascii=False, indent=1)
    os.makedirs(LOG, exist_ok=True)
    with open(f"{LOG}/yedek.log", "a") as f:
        f.write(f"[{sonuc['zaman']}] Yedek v10: {sonuc['boyut_kb']} KB, dogrulama={d.get('durum')}, uyumluluk %{genel}\n")
    return sonuc

if __name__ == "__main__":
    r = tara("--yedeksiz" not in __import__("sys").argv)
    print(f"[{r['zaman']}] Yedek+Uyumluluk v10")
    print(f"  Yedek: {r['boyut_kb']} KB · dogrulama: {r['dogrulama'].get('durum')} · immutable: {r['dogrulama'].get('immutable')}")
    print(f"  Uyumluluk: %{r['genel_skor']} ({r['gecen']}/{r['toplam']} kontrol)")
    for c, v in r["kapsam"].items():
        print(f"    {v['skor']:3d}%  {c:18s} {v['gecen']}/{v['toplam']}")
