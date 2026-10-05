#!/usr/bin/env python3
# CYBER KALKAN - SCA (Guvenlik Yapilandirma Denetimi) + ROOTKIT TARAMA
import subprocess, json, os
from datetime import datetime
V="/opt/siber-kalkan/VERI"
def sh(c):
    try: return subprocess.run(c,shell=True,capture_output=True,text=True,timeout=60).stdout.strip()
    except: return ""
def var(y): return os.path.exists(y)
sonuc=[]
def chk(ad, kosul, seviye, aciklama):
    sonuc.append({"kontrol":ad,"sonuc":"GECTI" if kosul else "BASARISIZ","seviye":seviye,"aciklama":aciklama})
# --- SCA: CIS benzeri kontroller ---
chk("SSH root giris kapali", "PermitRootLogin no" in (open("/etc/ssh/sshd_config").read() if var("/etc/ssh/sshd_config") else ""), "YUKSEK", "SSH ile root girisi")
chk("Firewall aktif", sh("nft list ruleset 2>/dev/null | wc -l") not in ("","0") or sh("ufw status 2>/dev/null")!="", "KRITIK", "Ag filtresi")
chk("Sifre bos degil (shadow)", var("/etc/shadow"), "YUKSEK", "Parola dosyasi")
chk("Cron yetkileri", sh("ls -l /etc/crontab 2>/dev/null | grep -c 'rw-------\\|rw-r--r--'") != "0", "ORTA", "Cron dosya izinleri")
chk("Basarisiz giris kaydi", sh("grep -c 'Failed password' /var/log/auth.log 2>/dev/null") >= "0", "ORTA", "Auth loglari")
chk("Nginx guvenli", var("/etc/nginx/nginx.conf"), "ORTA", "Web sunucu")
chk("Panel servisi aktif", "active" in sh("systemctl is-active kalkan-panel 2>/dev/null"), "KRITIK", "Kalkan paneline erisim")
chk("Motor cron kayitli", "kalkan_motor" in sh("crontab -l 2>/dev/null"), "KRITIK", "Otomatik tarama")
# --- ROOTKIT tarama ---
rootkit=[]
# 1) SUID ile gizlenmis prosesler
for p in os.listdir("/proc"):
    if p.isdigit():
        try:
            exe=os.readlink(f"/proc/{p}/exe")
            if "(deleted)" in exe: rootkit.append(f"silinmis-binary PID {p}: {exe}")
        except: pass
# 2) /dev altinda supheli dosyalar
for f in os.listdir("/dev"):
    if os.path.isfile(f"/dev/{f}") and sh(f"file /dev/{f} 2>/dev/null").find("executable")>=0:
        rootkit.append(f"/dev/{f} calistirilabilir")
# 3) Gizli prosesler (ps ile gorunmeyen)
ps=int(sh("ps aux 2>/dev/null | wc -l") or 0); proc=int(sh("ls /proc | grep -c '^[0-9]*$'") or 0)
if proc-ps > 15: rootkit.append(f"proses uyumsuzlugu: /proc={proc} ps={ps}")
# 4) Supheli cron
cr=sh("crontab -l 2>/dev/null"); 
for s in ["curl","wget","base64","/tmp/","nc ","bash -i"]:
    if s in cr: rootkit.append(f"cron supheli komut: {s}")
gecen=sum(1 for x in sonuc if x["sonuc"]=="GECTI")
rapor={"tarih":datetime.now().strftime("%d.%m.%Y %H:%M"),"kontroller":sonuc,
       "gecen":gecen,"toplam":len(sonuc),"skor":round(gecen/len(sonuc)*100) if sonuc else 0,
       "rootkit":rootkit,"rootkit_temiz":len(rootkit)==0}
json.dump(rapor,open(f"{V}/sca.json","w"),ensure_ascii=False,indent=1)
print(f"SCA: {gecen}/{len(sonuc)} gecti skor={rapor['skor']} | ROOTKIT: {'TEMIZ' if rapor['rootkit_temiz'] else len(rootkit)} bulgu")


# --- SERT: gizli proses + rootkit derin tarama ---
def derin_tarama():
    bulgular=[]
    # /proc'ta gorunmeyen proses
    import os as _os
    for pid in _os.listdir("/proc"):
        if pid.isdigit() and not _os.path.exists(f"/proc/{pid}/exe"):
            try:
                cmd=open(f"/proc/{pid}/cmdline").read().replace("\0"," ").strip()
                if cmd: bulgular.append(f"gizli proses {pid}: {cmd[:60]}")
            except: pass
    # supheli /tmp dosyalari
    for d in ["/tmp","/dev/shm","/var/tmp"]:
        for f in _os.listdir(d) if _os.path.isdir(d) else []:
            fp=f"{d}/{f}"
            if _os.path.isfile(fp) and _os.access(fp,_os.X_OK) and _os.path.getsize(fp)>1000:
                bulgular.append(f"calistirilabilir: {fp}")
    return bulgular
