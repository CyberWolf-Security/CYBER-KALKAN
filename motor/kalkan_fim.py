#!/usr/bin/env python3
# CYBER KALKAN FIM | CYBER KALKAN syscheck karsiligi
# ★ B-12 DUZELTMESI: BU MODUL DEVRE DISI BIRAKILDI.
# kalkan_fim_v10.py kullanilir. Iki modul ayni fim.json'a FARKLI SEMA ile
# yazip birbirini eziyordu (biri "dosyalar", digeri "baseline" anahtari).
# kalkan-fim.service artik kalkan_fim_v10.py kosar.
import sys
if __name__ == "__main__":
    sys.exit("[DEVRE DISI] kalkan_fim_v10.py kullanin")
import os, json, hashlib
from datetime import datetime
V="/opt/siber-kalkan/VERI"; Y=V+"/fim.json"; I=V+"/fim_izleme.json"
def h(p):
    try:
        return hashlib.sha256(open(p,'rb').read()).hexdigest()[:32]
    except: return None
izle=json.load(open(I))["yollar"] if os.path.exists(I) else ["/etc/passwd","/etc/shadow","/etc/sudoers","/etc/hosts","/etc/crontab"]
esk=json.load(open(Y)) if os.path.exists(Y) else {"dosyalar":{},"degisimler":[],"toplam":0}
yeni={"dosyalar":{},"degisimler":esk.get("degisimler",[]),"toplam":esk.get("toplam",0)}
for y in izle:
    for d in ([y] if os.path.isfile(y) else []):
        hh=h(d)
        if hh:
            yeni["dosyalar"][d]=hh
            if d in esk.get("dosyalar",{}) and esk["dosyalar"][d]!=hh:
                yeni["degisimler"].append({"dosya":d,"zaman":datetime.now().strftime("%d.%m.%Y %H:%M:%S"),"sebep":"DEĞİŞTİ"})
                yeni["toplam"]+=1
json.dump(yeni,open(Y,'w'),ensure_ascii=False,indent=1)
print("FIM: %d dosya izlendi, %d degisim" % (len(yeni["dosyalar"]), yeni["toplam"]))
