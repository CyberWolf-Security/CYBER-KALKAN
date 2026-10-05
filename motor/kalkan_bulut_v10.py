#!/usr/bin/env python3
"""CYBER KALKAN — Bulut + EVTX v10
AWS/GCP/Azure yapilandirma denetimi · EVTX (Windows olay) parse · ortak risk"""
import json, os, re, struct, subprocess
from datetime import datetime

V = "/opt/siber-kalkan/VERI"
LOG = "/opt/siber-kalkan/LOG"
EVTX_DIZIN = "/opt/siber-kalkan/evtx"

# ── Bulut yapilandirma kontrolleri ──
BULUT_KONTROL = {
    "AWS": [
        ("iam", "Root hesabinda MFA", "Kök hesapta çok faktörlü doğrulama"),
        ("s3", "Bucket public degil", "S3 public erişim engeli"),
        ("cloudtrail", "CloudTrail acik", "API çağrı loglaması"),
        ("guardduty", "GuardDuty acik", "Tehdit tespiti"),
        ("sg", "Guvenlik grubu 0.0.0.0/0 degil", "Açık portlar (SSH/RDP)"),
        ("ebs", "EBS sifreli", "Disk şifreleme"),
    ],
    "GCP": [
        ("iam", "Org policy var", "Kurumsal politika kısıtları"),
        ("fw", "Firewall 0.0.0.0/0 degil", "Açık firewall"),
        ("logging", "Audit log acik", "Denetim logları"),
        ("scc", "Security Command Center", "Güvenlik bulguları"),
    ],
    "Azure": [
        ("defender", "Defender for Cloud", "Bulut güvenlik duruşu"),
        ("nsg", "NSG 0.0.0.0/0 degil", "Açık ağ grubu"),
        ("mfa", "MFA zorunlu", "Koşullu erişim"),
    ],
}

def bulut_denetle():
    sonuc = {}
    # CLI araçlari var mi
    araclar = {}
    for a in ["aws", "gcloud", "az"]:
        r = subprocess.run(f"command -v {a}", shell=True, capture_output=True, text=True)
        araclar[a] = r.returncode == 0
    sonuc["_araclar"] = araclar

    if araclar["aws"]:
        try:
            r = subprocess.run("aws sts get-caller-identity --output json 2>/dev/null", shell=True,
                               capture_output=True, text=True, timeout=20)
            sonuc["AWS"] = {"hesap": json.loads(r.stdout or "{}").get("Account", "?"), "aktif": True}
            # S3 public kontrol
            try:
                rr = subprocess.run("aws s3api list-buckets --query 'Buckets[].Name' --output json 2>/dev/null",
                                    shell=True, capture_output=True, text=True, timeout=20)
                buckets = json.loads(rr.stdout or "[]")
                sonuc["AWS"]["bucket"] = len(buckets)
            except Exception:
                sonuc["AWS"]["bucket"] = 0
        except Exception as e:
            sonuc["AWS"] = {"aktif": False, "hata": str(e)[:60]}
    if araclar["gcloud"]:
        try:
            r = subprocess.run("gcloud config get-value project 2>/dev/null", shell=True,
                               capture_output=True, text=True, timeout=20)
            sonuc["GCP"] = {"proje": r.stdout.strip() or "?", "aktif": bool(r.stdout.strip())}
        except Exception:
            sonuc["GCP"] = {"aktif": False}
    if araclar["az"]:
        try:
            r = subprocess.run("az account show --query name -o tsv 2>/dev/null", shell=True,
                               capture_output=True, text=True, timeout=20)
            sonuc["Azure"] = {"hesap": r.stdout.strip() or "?", "aktif": bool(r.stdout.strip())}
        except Exception:
            sonuc["Azure"] = {"aktif": False}

    # Yerel kontroller (bulut kullanilmasa bile)
    yerel = []
    # IMDS erisimi (SSRF riski)
    try:
        r = subprocess.run("curl -s --max-time 2 -H 'Metadata-Flavor: Google' http://169.254.169.254/ 2>/dev/null | head -c 50",
                           shell=True, capture_output=True, text=True, timeout=6)
        yerel.append({"kontrol": "Bulut metadata erisimi (SSRF riski)", "durum": "ACIK" if r.stdout.strip() else "kapali/kapali"})
    except Exception:
        pass
    sonuc["_yerel"] = yerel
    sonuc["_aktif_bulut"] = sum(1 for k, v in sonuc.items() if not k.startswith("_") and v.get("aktif"))
    return sonuc

# ── EVTX (Windows olay logu) parse — basit binary tarama ──
def evtx_tara():
    if not os.path.isdir(EVTX_DIZIN):
        return {"dosya": 0, "olay": 0, "bulgular": []}
    dosyalar = [f for f in os.listdir(EVTX_DIZIN) if f.lower().endswith(".evtx")]
    bulgular = []
    for f in dosyalar[:20]:
        yol = os.path.join(EVTX_DIZIN, f)
        try:
            with open(yol, "rb") as fh:
                veri = fh.read(4_000_000)
            metin = veri.decode("utf-16-le", "ignore") + veri.decode("utf-8", "ignore")
            # ilgi cekici olay kimlikleri
            for eid, ad, risk in [("4625", "Basarisiz giris", "ORTA"), ("4624", "Basarili giris", "BILGI"),
                                   ("4672", "Ozel yetki atandi", "YUKSEK"), ("4688", "Surec olusturma", "BILGI"),
                                   ("4720", "Kullanici olusturuldu", "YUKSEK"), ("7045", "Servis kuruldu", "YUKSEK"),
                                   ("1102", "Log temizlendi", "KRITIK")]:
                if eid in metin:
                    bulgular.append({"dosya": f, "olay_id": eid, "ad": ad, "risk": risk})
        except Exception:
            pass
    return {"dosya": len(dosyalar), "olay": len(bulgular), "bulgular": bulgular[:50]}

def tara():
    b = bulut_denetle()
    e = evtx_tara()
    sonuc = {
        "zaman": datetime.now().strftime("%d.%m.%Y %H:%M:%S"),
        "bulut": b, "evtx": e,
        "aktif_bulut": b.get("_aktif_bulut", 0),
        "evtx_dosya": e["dosya"], "evtx_olay": e["olay"],
        "modul": "bulut",
    }
    json.dump(sonuc, open(f"{V}/bulut.json", "w"), ensure_ascii=False, indent=1)
    os.makedirs(LOG, exist_ok=True)
    with open(f"{LOG}/bulut.log", "a") as f:
        f.write(f"[{sonuc['zaman']}] Bulut v10: {sonuc['aktif_bulut']} aktif saglayici, {e['dosya']} evtx\n")
    return sonuc

if __name__ == "__main__":
    r = tara()
    a = r["bulut"].get("_araclar", {})
    print(f"[{r['zaman']}] Bulut+EVTX v10")
    print(f"  CLI: aws={'✓' if a.get('aws') else '✗'} gcloud={'✓' if a.get('gcloud') else '✗'} az={'✓' if a.get('az') else '✗'}")
    print(f"  Aktif bulut saglayici: {r['aktif_bulut']} · EVTX: {r['evtx_dosya']} dosya / {r['evtx_olay']} olay")
    for k in ("AWS", "GCP", "Azure"):
        if k in r["bulut"]:
            print(f"    {k}: {r['bulut'][k]}")
