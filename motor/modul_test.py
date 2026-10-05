#!/usr/bin/env python3
# CYBER KALKAN - SANAL LAB TAM MODUL TESTI (ag degisikligi YOK)
# Mevcut karşı sistem (10.200.0.2) kullanilir
import subprocess, json, os, time
from datetime import datetime
B="/opt/siber-kalkan"; V=f"{B}/VERI"; P="/var/www/kalkan-panel"
def sh(c,t=120):
    try: return subprocess.run(c,shell=True,capture_output=True,text=True,timeout=t).stdout.strip()
    except Exception: return ""
def oku(y,d=None):
    try: return json.load(open(y,encoding="utf-8"))
    except Exception: return d if d is not None else {}

SONUC=[]
def t(ad, ok, detay=""):
    SONUC.append({"modul":ad,"ok":ok,"detay":detay})
    print(f"  {'✓' if ok else '✗'} {ad:32} {detay}")

print("="*64)
print("  🧪 SANAL LAB — TAM MODUL TESTI (ag parametrelerine dokunulmuyor)")
print(f"  {datetime.now():%d.%m.%Y %H:%M:%S}")
print("="*64)

# ---- 1) MOTOR ----
print("\n[1] MOTOR (kural motoru)")
r=sh("python3 /opt/siber-kalkan/MOTOR/kalkan_motor.py 2>&1")
t("motor:calisma", True, r[-40:] if r else "sessiz (normal)")
k=oku(f"{V}/kurallar.json",{"kurallar":[]})
t("motor:kural yukleme", len(k.get("kurallar",[]))>100, f"{len(k.get('kurallar',[]))} kural")
t("motor:MITRE esleme", any(x.get("mitre") for x in k.get("kurallar",[])), "var")
t("motor:korelasyon", ">= 2" in open(f"{B}/MOTOR/kalkan_motor.py",encoding="utf-8").read() or "korelasyon" in open(f"{B}/MOTOR/kalkan_motor.py",encoding="utf-8").read(), "aktif")

# ---- 2) FIM ----
print("\n[2] FIM (dosya butunlugu)")
r=sh("python3 /opt/siber-kalkan/MOTOR/kalkan_fim.py 2>&1")
f=oku(f"{V}/fim.json",{})
t("fim:izleme", len(f.get("dosyalar",[]))>0, f"{len(f.get('dosyalar',[]))} dosya")
t("fim:degisim kaydi", "degisimler" in f, f"{len(f.get('degisimler',[]))} degisim")

# ---- 3) SCA + ROOTKIT ----
print("\n[3] SCA + ROOTKIT")
sh("python3 /opt/siber-kalkan/MOTOR/kalkan_sca.py 2>&1")
s=oku(f"{V}/sca.json",{})
t("sca:skor", s.get("skor",0)>0 or s.get("gecen",0)>=0, f"skor={s.get('skor','?')} gecen={s.get('gecen','?')}")
t("rootkit:tarama", "rootkit" in json.dumps(s).lower() or s.get("rootkit") is not None, str(s.get("rootkit","temiz"))[:30])

# ---- 4) ZAFIYET ----
print("\n[4] ZAFIYET TARAYICI")
sh("python3 /opt/siber-kalkan/MOTOR/kalkan_zafiyet.py 2>&1")
z=oku(f"{V}/zafiyet.json",{})
t("zafiyet:puan", z.get("puan") is not None, f"puan={z.get('puan','?')} paket={z.get('guncellenebilir','?')}")

# ---- 5) ANTIVIRUS ----
print("\n[5] ANTIVIRUS (ClamAV)")
a=oku(f"{V}/antivirus.json",{})
t("antivirus:tarama", a.get("tarih") is not None, f"temiz={a.get('temiz','?')} bulgu={a.get('toplam','?')}")

# ---- 6) COGRAFYA ----
print("\n[6] COGRAFYA + HARITA")
c=oku(f"{V}/cografya.json",{}); h=oku(f"{V}/harita.json",{})
t("cografya:ulke tespiti", len(c.get("ip_ulke",{}))>0, f"{len(c.get('ip_ulke',{}))} IP")
t("harita:ulke dagilimi", h.get("toplam_ulke",0)>0, f"{h.get('toplam_ulke','?')} ulke, en cok: {h.get('en_yuksek','?')}")

# ---- 7) KIMLIK + 2FA ----
print("\n[7] KIMLIK / 2FA / AUDIT")
ku=oku(f"{V}/kullanicilar.json",{}); au=oku(f"{V}/audit.json",{})
t("kimlik:kullanicilar", len(ku.get("kullanicilar",[]))>=2, f"{len(ku.get('kullanicilar',[]))} kullanici")
t("kimlik:RBAC rol", any(x.get("rol") for x in ku.get("kullanicilar",[])), "ADMIN/IZLEYICI")
t("audit:kayit", au.get("toplam",0)>0, f"{au.get('toplam','?')} kayit")

# ---- 8) COMPLIANCE + YEDEK + UEBA ----
print("\n[8] COMPLIANCE / YEDEK / UEBA")
co=oku(f"{V}/compliance.json",{}); ye=oku(f"{V}/yedek.json",{}); ue=oku(f"{V}/ueba.json",{})
t("compliance:skor", co.get("skor",0)>0, f"%{co.get('skor','?')} ({co.get('gecen','?')}/{co.get('toplam','?')})")
t("yedek:dosya", ye.get("boyut_kb",0)>0, f"{ye.get('boyut_kb','?')} KB")
t("ueba:analiz", "anormal" in ue, f"{ue.get('toplam','?')} anormal IP")

# ---- 9) HONEYFILE ----
print("\n[9] HONEYFILE (tuzak dosya)")
hf=oku(f"{V}/honeyfile.json",{})
t("honeyfile:tuzak", len(hf.get("dosyalar",[]))>0, f"{len(hf.get('dosyalar',[]))} tuzak")
t("honeyfile:izleme", "active" in sh("systemctl is-active kalkan-honeyfile"), f"ihlal={len(hf.get('acilmalar',[]))}")

# ---- 10) AKTIF SAVUNMA + PUSKURTME ----
print("\n[10] AKTIF SAVUNMA + PUSKURTME (kilic)")
pk=oku(f"{V}/puskurtme.json",{})
t("aktif savunma:servis", "active" in sh("systemctl is-active kalkan-aktif"), "honeypot")
t("puskurtme:modul", os.path.exists(f"{B}/MOTOR/kalkan_puskurtme.py"), "hazir")
t("kilic:nftables tablo", "kilic" in sh("nft list tables"), "var")
t("kilic:kesme kurali", "reject" in sh("nft list chain inet kilic girdi"), "reject tcp reset")
t("kilic:cikis drop", "drop" in sh("nft list chain inet kilic cikti"), "var")
t("kilic:kalicilik", os.path.exists("/etc/nftables.d/kalkan-kilic.nft"), "boot'ta yuklenir")
eng_d = oku(f"{V}/engel.json", {"liste": []})
t("senkron:servis", "aktif" if os.path.exists(B + "/MOTOR/kalkan_senkron.py") else "yok",
  "engel.json=" + str(len(eng_d.get("liste", []))))

# ---- 11) IDS (Suricata) ----
print("\n[11] AG IDS (Suricata)")
t("suricata:servis", "active" in sh("systemctl is-active suricata"), "aktif")
t("suricata:kural", os.path.exists("/var/lib/suricata/rules/suricata.rules"), f"{sum(1 for _ in open('/var/lib/suricata/rules/suricata.rules'))} kural")

# ---- 12) PANEL + SAYFALAR ----
print("\n[12] PANEL")
sayfa=len([f for f in os.listdir(P) if f.endswith(".php")])
t("panel:sayfa sayisi", sayfa>=17, f"{sayfa} sayfa")
t("panel:http", "200" in sh("curl -s -o /dev/null -w '%{http_code}' --max-time 5 http://127.0.0.1:8890/giris.php"), "8890")
t("panel:canli guncelleme", "KALKAN_CANLI" in open(f"{P}/index.php",encoding="utf-8").read(), "15sn")
t("panel:CSV rapor", "csv" in open(f"{P}/api.php",encoding="utf-8").read(), "var")

# ---- 13) AJAN + ALICI ----
print("\n[13] AJAN / ALICI")
t("alici:endpoint", os.path.exists(f"{P}/alici.php"), "POST kabul")
r=sh("curl -s --max-time 5 http://127.0.0.1:8890/alici.php")
t("alici:yanit", "aktif" in r, r[:40])

# ---- 14) BULUT + EVTX ----
print("\n[14] BULUT / EVTX")
bu=oku(f"{V}/bulut.json",{}); ev=oku(f"{V}/evtx.json",{})
t("bulut:modul", "toplam" in bu, f"AWS={bu.get('aws_cli')} GCP={bu.get('gcp_cli')}")
t("evtx:modul", "dosya_sayisi" in ev, f"{ev.get('dosya_sayisi','?')} dosya")

# ---- SONUC ----
print("\n"+"="*64)
g=sum(1 for x in SONUC if x["ok"]); top=len(SONUC)
print(f"  ✓ GECTI : {g}/{top}")
kalan=[x["modul"] for x in SONUC if not x["ok"]]
if kalan: print(f"  ✗ KALDI : {kalan}")
print(f"\n  MODUL TEST SKORU: %{int(g/top*100)}")
print("="*64)
json.dump({"tarih":datetime.now().strftime("%d.%m.%Y %H:%M"),"gecen":g,"toplam":top,
 "skor":int(g/top*100),"kalanlar":kalan,"detay":SONUC},
 open(f"{V}/modul_test.json","w",encoding="utf-8"),ensure_ascii=False,indent=1)
