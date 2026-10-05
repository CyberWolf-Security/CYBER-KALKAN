#!/usr/bin/env python3
# CYBER KALKAN - ZAFIYET TARAYICI (CYBER KALKAN vulnerability-detector karsiligi)
import subprocess, json, re
from datetime import datetime
V="/opt/siber-kalkan/VERI"
def sh(c):
    try: return subprocess.run(c,shell=True,capture_output=True,text=True,timeout=90).stdout
    except: return ""
# 1) Guncellenebilir paketler = bilinen zafiyet gostergesi
cikti=sh("apt list --upgradable 2>/dev/null | tail -n +2")
paketler=[]
for s in cikti.strip().split("\n"):
    if "/" in s:
        ad=s.split("/")[0]; yeni=s.split()[1] if len(s.split())>1 else ""
        paketler.append({"paket":ad,"yeni":yeni})
# 2) Kritik paketler surum kontrolu
kritik={"openssh-server":8.0,"nginx":1.18,"php":7.4,"python3":3.8,"openssl":1.1}
kurulu={}
for line in sh("dpkg-query -W -f='${Package} ${Version}\n' 2>/dev/null").split("\n"):
    p=line.split()
    if len(p)==2 and p[0] in kritik: kurulu[p[0]]=p[1]
# 3) SUID (yetki yukseltme riski)
suid=[x for x in sh("find / -perm -4000 -type f 2>/dev/null").strip().split("\n") if x][:40]
# 4) Dunya-yazilabilir kritik dosyalar
ww=[x for x in sh("find /etc /opt -perm -o+w -type f 2>/dev/null").strip().split("\n") if x][:20]
rapor={"tarih":datetime.now().strftime("%d.%m.%Y %H:%M"),
       "guncellenebilir":len(paketler),"paketler":paketler[:50],
       "kritik_kurulu":kurulu,"suid_sayisi":len(suid),"suid":suid,
       "dunya_yazilabilir":ww,
       "puan":max(0,100-min(30,len(paketler)//30)-min(20,len(suid)//2)-min(50,len(ww)*10))}
json.dump(rapor,open(f"{V}/zafiyet.json","w"),ensure_ascii=False,indent=1)
print(f"ZAFIYET: guncellenebilir={len(paketler)} suid={len(suid)} ww={len(ww)} puan={rapor['puan']}")


# --- SERT: kritik guvenlik guncellemelerini otomatik uygula ---
import subprocess as _sp
def kritik_guncelle():
    out = _sp.run(["apt-get","-s","upgrade"],capture_output=True,text=True).stdout
    kritik = [l for l in out.splitlines() if "security" in l.lower() and "Inst " in l]
    if kritik:
        _sp.run(["apt-get","-y","-o","Dpkg::Options::=--force-confdef","upgrade"],capture_output=True)
    return len(kritik)
