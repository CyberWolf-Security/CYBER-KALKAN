#!/usr/bin/env python3
# CYBER KALKAN - UZAK TOPLAYICI (merkezden diger sunuculara baglanip log/hash ceker)
# CYBER KALKAN agent karsiligi (pull modeli)
import subprocess, json, os, re
from datetime import datetime
V="/opt/siber-kalkan/VERI"
def oku(y):
    try: return json.load(open(y,encoding="utf-8"))
    except: return {}
def yaz(y,v):
    json.dump(v,open(y,"w",encoding="utf-8"),ensure_ascii=False,indent=1)
def simdi(): return datetime.now().strftime("%d.%m.%Y %H:%M:%S")

ayar=oku(f"{V}/ayarlar.json")
uzaklar=ayar.get("uzak_makineler",[])
if not uzaklar:
    print("UZAK: tanimli makine yok (ayarlar.json -> uzak_makineler)")
    raise SystemExit(0)

kayitlar=[];
for m in uzaklar:
    ad=m.get("ad","?"); host=m.get("host",""); key=m.get("key",""); kullanici=m.get("kullanici","root")
    if not host: continue
    ssh=["ssh","-o","StrictHostKeyChecking=no","-o","ConnectTimeout=15","-o","BatchMode=yes"]
    if key: ssh+=["-i",key]
    hedef=f"{kullanici}@{host}"
    # 1) baglanti testi + sistem bilgisi
    r=subprocess.run(ssh+[hedef,"uptime; nproc; free -m | awk 'NR==2{print $3}'"],capture_output=True,text=True,timeout=40)
    durum={"ad":ad,"host":host,"zaman":simdi(),"erisim":"OK" if r.returncode==0 else "HATA","cikti":r.stdout.strip()[:200]}
    # 2) guvenlik loglarindan supheli satirlar
    if r.returncode==0:
        r2=subprocess.run(ssh+[hedef,'grep -icE "union.*select|<script|wp-login|\\.env|shell\\.php|Failed password" /var/log/nginx/access.log /var/log/auth.log 2>/dev/null | awk -F: "{s+=$2} END{print s+0}"'],capture_output=True,text=True,timeout=40)
        durum["supheli"]=r2.stdout.strip()
    kayitlar.append(durum)
    print(f"{ad} ({host}): {durum['erisim']} supheli={durum.get('supheli','-')}")

yaz(f"{V}/uzak_ajanlar.json",{"guncelleme":simdi(),"makineler":kayitlar})
print(f"UZAK TOPLAYICI: {len(kayitlar)} makine")
