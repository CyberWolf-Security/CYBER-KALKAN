#!/usr/bin/env python3
# CYBER KALKAN - HA IZLEME (dugum sagligi + failover)
import json, subprocess, socket, time
from datetime import datetime
V="/opt/siber-kalkan/VERI"
def oku(n,d=None):
    try: return json.load(open(f"{V}/{n}.json",encoding="utf-8"))
    except Exception: return d or {}
def yaz(n,v): json.dump(v,open(f"{V}/{n}.json","w",encoding="utf-8"),indent=1,ensure_ascii=False)

c=oku("cluster",{"dugumler":[]})
for d in c.get("dugumler",[]):
    ip=d.get("ip"); port=d.get("port",8890)
    try:
        s=socket.socket(); s.settimeout(3); s.connect((ip,port)); s.close()
        d["durum"]="aktif"; d["son_gorulme"]=datetime.now().strftime("%d.%m.%Y %H:%M:%S")
    except Exception:
        if d.get("rol")=="MASTER":
            d["durum"]="KRITIK (merkez erisilemez)"
        else:
            d["durum"]="erisilemez"
c["guncelleme"]=datetime.now().strftime("%d.%m.%Y %H:%M")
yaz("cluster",c)

# Yerel servis sagligi
saglik={}
for svc in ["kalkan-panel","kalkan-motor","kalkan-fim","kalkan-aktif","suricata"]:
    r=subprocess.run(["systemctl","is-active",svc],capture_output=True,text=True)
    saglik[svc]=r.stdout.strip()
yaz("ha_saglik",{"guncelleme":datetime.now().strftime("%d.%m.%Y %H:%M"),"servisler":saglik})
