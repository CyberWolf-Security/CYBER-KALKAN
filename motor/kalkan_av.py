#!/usr/bin/env python3
# CYBER KALKAN - ANTIVIRUS (ClamAV entegrasyonu) + REMEDIATION (otomatik duzeltme)
import subprocess, json, os
from datetime import datetime
V = "/opt/siber-kalkan/VERI"
def sh(c, t=600):
    try: return subprocess.run(c, shell=True, capture_output=True, text=True, timeout=t).stdout
    except: return ""
def simdi(): return datetime.now().strftime("%d.%m.%Y %H:%M")

# --- 1) ANTIVIRUS TARAMA (onemli dizinler) ---
hedefler = ["/tmp", "/var/tmp", "/dev/shm", "/var/www", "/opt/siber-kalkan", "/root/Masaüstü"]
bulunan = []
for h in hedefler:
    if not os.path.isdir(h): continue
    cikti = sh(f"clamscan -r --infected --no-summary --move=/var/quarantine --max-filesize=25M {h} 2>/dev/null", 900)
    for s in cikti.splitlines():
        if "FOUND" in s: bulunan.append(s.strip())
json.dump({"tarih": simdi(), "taranan_dizin": len(hedefler), "bulunan": bulunan,
           "temiz": len(bulunan) == 0, "toplam": len(bulunan)},
          open(f"{V}/antivirus.json", "w", encoding="utf-8"), ensure_ascii=False, indent=1)
print(f"ANTIVIRUS: {len(bulunan)} bulgu")

# --- 2) REMEDIATION (guvenli otomatik duzeltmeler) ---
oneriler = []
# a) Dunya-yazilabilir kritik dosyalar
ww = [x for x in sh("find /etc /opt /var/www -perm -o+w -type f 2>/dev/null").split("\n") if x][:30]
for f in ww:
    r = sh(f"chmod o-w {f} 2>/dev/null && echo OK")
    oneriler.append({"tur": "izin", "dosya": f, "uygulandi": "OK" in r})
# b) Zayif SSH ayarlari (oneri - otomatik uygulanmaz)
sshd = sh("grep -E '^PermitRootLogin|^PasswordAuthentication' /etc/ssh/sshd_config 2>/dev/null")
if "PermitRootLogin yes" in sshd:
    oneriler.append({"tur": "ssh", "oneri": "PermitRootLogin yes -> prohibit-password", "uygulandi": False})
# c) Gereksiz SUID (rapor)
suid = [x for x in sh("find / -perm -4000 -type f 2>/dev/null").split("\n") if x]
json.dump({"tarih": simdi(), "oneriler": oneriler, "suid_sayisi": len(suid),
           "otomatik_uygulanan": sum(1 for o in oneriler if o.get("uygulandi"))},
          open(f"{V}/remediation.json", "w", encoding="utf-8"), ensure_ascii=False, indent=1)
print(f"REMEDIATION: {len(oneriler)} oneri, {sum(1 for o in oneriler if o.get('uygulandi'))} otomatik uygulandi")
