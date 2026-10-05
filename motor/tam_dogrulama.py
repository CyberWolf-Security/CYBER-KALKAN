#!/usr/bin/env python3
# CYBER KALKAN - TAM DOGRULAMA TESTI (kalicilik + servis + firewall + modul)
import subprocess, json, os, time, glob
from datetime import datetime
B="/opt/siber-kalkan"; P="/var/www/kalkan-panel"; V=f"{B}/VERI"
def sh(c,t=90):
    try: return subprocess.run(c,shell=True,capture_output=True,text=True,timeout=t).stdout.strip()
    except Exception: return ""
def oku(y,d=None):
    try: return json.load(open(y,encoding="utf-8"))
    except Exception: return d if d is not None else {}

G,K,U=[],[],[]
def t(ad,ok,uyari=False):
    (G if ok else (U if uyari else K)).append(ad)

print("="*62); print("  🔬 TAM DOGRULAMA TESTI — kalicilik dahil"); print(f"  {datetime.now():%d.%m.%Y %H:%M:%S}"); print("="*62)

# 1) SERVISLER
print("\n[1] SERVISLER")
for s in ["kalkan-panel","kalkan-motor","kalkan-fim","kalkan-aktif","kalkan-honeyfile"]:
    a=sh(f"systemctl is-active {s}"); t(f"servis:{s}", a=="active")
    e=sh(f"systemctl is-enabled {s} 2>/dev/null"); t(f"kalici:{s}", e=="enabled")
    print(f"    {s:20} aktif={a:8} kalici={e}")
t("servis:suricata", sh("systemctl is-active suricata")=="active", uyari=True)

# 2) FIREWALL KALICILIK
print("\n[2] FIREWALL (kilic)")
kilic = "kilic" in sh("nft list tables")
t("firewall:tablo var", kilic)
t("firewall:kalici dosya", os.path.exists("/etc/nftables.d/kalkan-kilic.nft"))
t("firewall:nftables enabled", "enabled" in sh("systemctl is-enabled nftables"))
t("firewall:reject kurali", "reject" in sh("nft list chain inet kilic girdi"))
t("firewall:cikis drop", "drop" in sh("nft list chain inet kilic cikti"))
nft_icerik = sh("nft list set inet kilic kara")
t("firewall:IP dolu", "10.200.0" in nft_icerik or "elements" in nft_icerik)
print(f"    kilic tablo={kilic} | kalici dosya={os.path.exists('/etc/nftables.d/kalkan-kilic.nft')}")

# 3) SENKRON TUTARLILIK
print("\n[3] SENKRON (engel.json vs nftables)")
e=oku(f"{V}/engel.json",{"liste":[]}); engel_say=len(e.get("liste",[]))
nft_satir=len(sh("nft list set inet kilic kara").split(","))
s=oku(f"{V}/senkron.json",{})
t("senkron:veri var", s.get("engel_json",0)>0)
t("senkron:tutarlilik", abs(s.get("engel_json",0)-s.get("nft_sonrasi",0))<50)
print(f"    engel.json={engel_say} | senkron raporu={s.get('engel_json')}→{s.get('nft_sonrasi')}")

# 4) MODULLER (dosya + calisma)
print("\n[4] MODULLER")
moduller = {
 "motor":"kalkan_motor.py","fim":"kalkan_fim.py","sca":"kalkan_sca.py",
 "zafiyet":"kalkan_zafiyet.py","antivirus":"kalkan_av.py","cografya":"kalkan_cografya.py",
 "aktif_savunma":"kalkan_aktif_savunma.py","senkron":"kalkan_senkron.py",
 "compliance":"kalkan_ekstra.py","kimlik":"kalkan_kimlik.py","bulut":"kalkan_bulut.py",
 "honeyfile":"kalkan_honeyfile_izle.py","puskurtme":"kalkan_puskurtme.py"}
for ad,f in moduller.items():
    yol=f"{B}/MOTOR/{f}"
    var=os.path.exists(yol)
    sozd=""
    if var:
        sozd=sh(f"python3 -m py_compile {yol} 2>&1")
    t(f"modul:{ad}", var and not sozd)
    if not var or sozd: print(f"    ✗ {ad}: {'YOK' if not var else sozd[:60]}")

# 5) VERI KATMANI
print("\n[5] VERI KATMANI")
for f in ["kurallar","engel","olaylar","kararlar","ayarlar","ioc","compliance","fim","honeyfile","senkron"]:
    t(f"veri:{f}.json", os.path.exists(f"{V}/{f}.json"))
k=oku(f"{V}/kurallar.json",{"kurallar":[]}); t(f"kural>=100 ({len(k.get('kurallar',[]))})", len(k.get("kurallar",[]))>=100)
i=oku(f"{V}/ioc.json",{}); t(f"ioc dolu ({len(i.get('ipler',[]))})", len(i.get("ipler",[]))>0)

# 6) PANEL
print("\n[6] PANEL")
php=glob.glob(f"{P}/*.php"); t(f"panel sayfa ({len(php)})", len(php)>=17)
t("panel port", "8890" in sh("ss -tlnp | grep 8890"))
t("panel http", "200" in sh("curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8890/giris.php"))

# 7) CRON
print("\n[7] CRON")
cron=sh("crontab -l"); n=len([l for l in cron.splitlines() if "siber-kalkan" in l])
t(f"cron ({n})", n>=6); print(f"    cron gorevi: {n}")

# 8) KARSI SISTEM (saldirgan) DURUMU
print("\n[8] KARSI SISTEM (10.200.0.2)")
ns=sh("ip netns list"); t("karsi sistem:namespace", "saldirgan" in ns)
if "saldirgan" in ns:
    blk=oku(f"{V}/engel.json",{"liste":[]})
    t("karsi sistem:engelli", any(x["ip"]=="10.200.0.2" for x in blk.get("liste",[])))
    # kesme testi
    r=sh("ip netns exec saldirgan timeout 6 curl -s -o /dev/null -w '%{http_code}' --max-time 5 http://10.200.0.1:8080/ 2>/dev/null")
    t("karsi sistem:KESILDI (baglanti yok)", r=="", uyari=False)
    print(f"    10.200.0.2 → {'✗ BAGLANTI YOK (KESILDI ✓)' if r=='' else 'kod='+r}")

# SONUC
print("\n"+"="*62)
top=len(G)+len(K)+len(U)
print(f"  ✓ GECTI : {len(G)}/{top}")
if U: print(f"  ⚠ UYARI : {len(U)} → {', '.join(U[:6])}")
if K: print(f"  ✗ KALDI : {len(K)} → {', '.join(K[:8])}")
skor=int(len(G)/top*100) if top else 0
print(f"\n  SKOR: %{skor}")
print("="*62)
json.dump({"tarih":datetime.now().strftime("%d.%m.%Y %H:%M"),"skor":skor,"gecti":len(G),
 "kaldi":len(K),"uyari":len(U),"kalanlar":K,"uyarilar":U},
 open(f"{V}/tam_dogrulama.json","w",encoding="utf-8"),ensure_ascii=False,indent=1)
