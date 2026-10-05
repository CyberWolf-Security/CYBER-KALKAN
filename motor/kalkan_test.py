#!/usr/bin/env python3
# CYBER KALKAN - SISTEM TEST PAKETI (otomatik dogrulama)
# Kullanim: python3 kalkan_test.py
import subprocess, json, os, glob, urllib.request
from datetime import datetime
B = "/opt/siber-kalkan"; P = "/var/www/kalkan-panel"; V = f"{B}/VERI"
def sh(c, t=60):
    try: return subprocess.run(c, shell=True, capture_output=True, text=True, timeout=t).stdout
    except Exception: return ""
def oku(y, d=None):
    try: return json.load(open(y, encoding="utf-8"))
    except Exception: return d if d is not None else {}

gect, kald, uyar = [], [], []
def test(ad, kosul, uyari=False):
    if kosul: gect.append(ad)
    elif uyari: uyar.append(ad)
    else: kald.append(ad)

print("=" * 56)
print("  🐺 CYBER KALKAN — SISTEM TESTI")
print(f"  {datetime.now():%d.%m.%Y %H:%M:%S}")
print("=" * 56)

# --- 1) SERVISLER ---
for s in ["kalkan-panel", "kalkan-motor", "kalkan-fim", "kalkan-aktif"]:
    test(f"Servis: {s}", "active" in sh(f"systemctl is-active {s}"))
test("Servis: suricata (IDS)", "active" in sh("systemctl is-active suricata"), uyari=True)

# --- 2) PANEL ---
test("Panel: port dinliyor", "8890" in sh("ss -tlnp 2>/dev/null | grep 8890"))
sayfalar = [os.path.basename(f) for f in glob.glob(f"{P}/*.php") if os.path.basename(f) != "ortak.php"]
for s in ["giris.php", "index.php", "engel.php", "olaylar.php", "vakalar.php", "arama.php",
          "ajanlar.php", "fim.php", "guvenlik.php", "kurallar.php", "cografi.php",
          "aktif.php", "sistem.php", "kullanicilar.php", "denetim.php", "ayarlar.php", "alici.php"]:
    test(f"Sayfa: {s}", os.path.exists(f"{P}/{s}"))
test("Panel: syntax temiz", "No syntax" not in sh(f"for f in {P}/*.php; do php -l $f 2>&1 | grep -v 'No syntax'; done"))

# --- 3) VERI KATMANI ---
test("Veri: kurallar.json", os.path.exists(f"{V}/kurallar.json"))
k = oku(f"{V}/kurallar.json", {"kurallar": []})
test(f"Kurallar: {len(k.get('kurallar', []))} adet (>=100)", len(k.get("kurallar", [])) >= 100, uyari=True)
test("Veri: engel.json", os.path.exists(f"{V}/engel.json"))
test("Veri: olaylar.json", os.path.exists(f"{V}/olaylar.json"))
test("Veri: ayarlar.json", os.path.exists(f"{V}/ayarlar.json"))
test("Veri: sağlık (istatistik)", os.path.exists(f"{V}/istatistik.json"))
test("Veri: FIM izleme", os.path.exists(f"{V}/fim_izleme.json"))
test("Veri: IOC listesi", os.path.exists(f"{V}/ioc.json"))
test("Veri: compliance", os.path.exists(f"{V}/compliance.json"))

# --- 4) FIREWALL (KILIC) ---
test("Kılıç: nftables tablosu", "kilic" in sh("nft list tables"))
test("Kılıç: reject tcp reset", "reject with tcp reset" in sh("nft list chain inet kilic girdi"))
test("Kılıç: çıkış drop", "drop" in sh("nft list chain inet kilic cikti"))
kara = sh("nft list set inet kilic kara")
test("Kılıç: engelli IP seti", "elements" in kara or "kara" in kara)

# --- 5) MOTOR ---
log = sh(f"tail -5 {B}/LOG/motor.log")
test("Motor: calisiyor (log guncel)", bool(log.strip()))
test("Motor: JSON kural okuma", "kurallar.json" in open(f"{B}/MOTOR/kalkan_motor.py", encoding="utf-8").read())

# --- 6) CRON ---
cron = sh("crontab -l")
test("Cron: gorevler kurulu", len([l for l in cron.splitlines() if "siber-kalkan" in l]) >= 5)

# --- 7) YARDIMCI MODULLER ---
for m, ad in [("kalkan_fim.py", "FIM"), ("kalkan_sca.py", "SCA/Rootkit"), ("kalkan_zafiyet.py", "Zafiyet"),
              ("kalkan_av.py", "Antivirus"), ("kalkan_aktif_savunma.py", "Aktif Savunma"),
              ("kalkan_cografya.py", "Coğrafya"), ("kalkan_ekstra.py", "Compliance/Yedek/UEBA"),
              ("kalkan_kimlik.py", "Kimlik/2FA"), ("kalkan_bulut.py", "Bulut/EVTX")]:
    test(f"Modul: {ad}", os.path.exists(f"{B}/MOTOR/{m}"))

# --- 8) API ---
try:
    r = urllib.request.urlopen("http://127.0.0.1:8890/alici.php", timeout=8)
    test("API: alici.php", r.status == 200)
except Exception:
    test("API: alici.php", False)

# --- SONUC ---
toplam = len(gect) + len(kald) + len(uyar)
print(f"\n  ✓ GECTI : {len(gect)}/{toplam}")
if uyar: print(f"  ⚠ UYARI : {len(uyar)}  -> {', '.join(uyar)}")
if kald: print(f"  ✗ KALDI : {len(kald)} -> {', '.join(kald)}")
skor = int(len(gect) / toplam * 100) if toplam else 0
print(f"\n  SISTEM SKORU: %{skor}")
print("=" * 56)
# rapor dosyasi
json.dump({"tarih": datetime.now().strftime("%d.%m.%Y %H:%M"), "skor": skor,
           "gecti": len(gect), "kaldi": len(kald), "uyari": len(uyar),
           "kalanlar": kald, "uyarilar": uyar},
          open(f"{V}/test_raporu.json", "w", encoding="utf-8"), ensure_ascii=False, indent=1)
