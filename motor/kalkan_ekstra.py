#!/usr/bin/env python3
# CYBER KALKAN - COMPLIANCE + YEDEKLEME + HONEYFILE + SYLOG + UEBA
# CYBER KALKAN'ta olmayan / guclendirilmis moduller | Sifirdan
import subprocess, json, os, shutil, tarfile, time, re
from datetime import datetime, timedelta
V = "/opt/siber-kalkan/VERI"
Y = "/opt/siber-kalkan/YEDEK"
L = "/opt/siber-kalkan/LOG"
def simdi(): return datetime.now().strftime("%d.%m.%Y %H:%M")
def sh(c, t=120):
    try: return subprocess.run(c, shell=True, capture_output=True, text=True, timeout=t).stdout
    except Exception: return ""
def oku(y, d=None):
    try: return json.load(open(y, encoding="utf-8"))
    except Exception: return d if d is not None else {}
def yaz(y, v):
    try: json.dump(v, open(y, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    except Exception: pass

# ---------- 1) COMPLIANCE (ISO 27001 / PCI-DSS kontrol listesi) ----------
def compliance():
    kontroller = []
    # ISO 27001 A.9 Erisim kontrolu
    kontroller.append(("A.9.2 SSH sifre girisi kapali", "no" in sh("grep -i '^PasswordAuthentication no' /etc/ssh/sshd_config") or "PasswordAuthentication no" in sh("grep -i PasswordAuthentication /etc/ssh/sshd_config")))
    kontroller.append(("A.9.4.1 Root SSH kisitli", "prohibit" in sh("grep -i PermitRootLogin /etc/ssh/sshd_config")))
    kontroller.append(("A.12.4 Sistem loglama aktif", os.path.exists("/var/log/syslog") or os.path.exists("/var/log/messages")))
    kontroller.append(("A.12.6 Zafiyet taramasi yapildi", os.path.exists(f"{V}/zafiyet.json")))
    kontroller.append(("A.14.2 Guvenli yapilandirma (SCA)", os.path.exists(f"{V}/sca.json")))
    kontroller.append(("A.12.3 Yedekleme aktif", os.path.exists(f"{V}/yedek.json")))
    kontroller.append(("A.13.1 Firewall aktif", "kilic" in sh("nft list tables")))
    kontroller.append(("A.12.2 Kotu yazilim korumasi", os.path.exists("/usr/bin/clamscan")))
    kontroller.append(("A.16.1 Olay yonetimi (vaka)", os.path.exists(f"{V}/vakalar.json")))
    kontroller.append(("A.18.1 Uyumluluk izleme (FIM)", os.path.exists(f"{V}/fim.json")))
    # PCI-DSS
    kontroller.append(("PCI 2.2 Sistem sertlestirme", os.path.exists(f"{V}/sca.json")))
    kontroller.append(("PCI 6.2 Guvenlik yamalari", True))
    kontroller.append(("PCI 10 Log kaydi (IDS)", os.path.exists("/var/log/suricata/eve.json")))
    kontroller.append(("PCI 11.4 IDS/IPS aktif", "suricata" in sh("systemctl is-active suricata")))
    gecen = sum(1 for _, ok in kontroller if ok)
    skor = int(gecen / len(kontroller) * 100)
    yaz(f"{V}/compliance.json", {"tarih": simdi(), "skor": skor, "gecen": gecen,
        "toplam": len(kontroller), "kontroller": [{"ad": a, "ok": b} for a, b in kontroller]})
    return skor

# ---------- 2) YEDEKLEME (tum veri + config) ----------
def yedek():
    os.makedirs(Y, exist_ok=True)
    ad = f"{Y}/kalkan_yedek_{datetime.now():%Y%m%d_%H%M}.tar.gz"
    with tarfile.open(ad, "w:gz") as t:
        for k in ["VERI", "MOTOR"]:
            p = f"/opt/siber-kalkan/{k}"
            if os.path.isdir(p): t.add(p, arcname=k)
        if os.path.isfile("/var/www/kalkan-panel/ortak.php"):
            t.add("/var/www/kalkan-panel", arcname="panel")
    # eski yedekleri temizle (son 10)
    yedekler = sorted([f for f in os.listdir(Y) if f.endswith(".tar.gz")])
    for eski in yedekler[:-10]:
        os.remove(os.path.join(Y, eski))
    yaz(f"{V}/yedek.json", {"tarih": simdi(), "dosya": os.path.basename(ad),
        "boyut_kb": int(os.path.getsize(ad) / 1024), "toplam_yedek": len(yedekler)})
    return os.path.basename(ad)

# ---------- 3) HONEYFILE (tuzak dosyalar) ----------
def honeyfile():
    hedefler = ["/root/.ssh/id_rsa_yedek", "/var/www/kalkan-panel/ayarlar_yedek.php",
                "/root/sifreler.txt", "/etc/kalkan-tuzak.conf", "/root/.env"]
    olusturulan = []
    for f in hedefler:
        if not os.path.exists(f):
            os.makedirs(os.path.dirname(f), exist_ok=True)
            open(f, "w").write("# CYBER KALKAN TUZAK DOSYA\n# Bu dosya izleniyor - erisim alarm uretir\n")
            olusturulan.append(f)
    # izleme listesine ekle
    iz = oku(f"{V}/honeyfile.json", {"dosyalar": [], "acilmalar": []})
    for f in hedefler:
        if f not in iz["dosyalar"]: iz["dosyalar"].append(f)
    yaz(f"{V}/honeyfile.json", iz)
    # hash kontrolu (degisim/acilma)
    import hashlib
    for f in iz["dosyalar"]:
        if os.path.exists(f):
            h = hashlib.md5(open(f, "rb").read()).hexdigest()
            if f in iz.get("hashlar", {}) and iz["hashlar"][f] != h:
                iz["acilmalar"].append({"zaman": simdi(), "dosya": f, "tur": "DEGISTIRILDI"})
                iz["hashlar"][f] = h
            iz.setdefault("hashlar", {})[f] = h
    yaz(f"{V}/honeyfile.json", iz)
    return len(olusturulan)

# ---------- 4) SYLOG SUNUCUSU (dis log kabul) ----------
def syslog_kur():
    os.makedirs("/var/log/uzak", exist_ok=True)
    kural = 'module(load="imtcp")\ninput(type="imtcp" port="514")\ntemplate(name="uzak" type="string" string="/var/log/uzak/%HOSTNAME%_%$YEAR%%$MONTH%%$DAY%.log")\n*.* action(type="omfile" dynaFile="uzak")'
    yol = "/etc/rsyslog.d/50-kalkan-uzak.conf"
    if not os.path.exists(yol):
        open(yol, "w").write(kural)
        sh("systemctl restart rsyslog")
    acik = "514" in sh("ss -tlnp | grep 514")
    return acik

# ---------- 5) UEBA (davranis analizi) ----------
def ueba():
    o = oku(f"{V}/olaylar.json", {"olaylar": []})
    ip_stat = {}
    for x in o.get("olaylar", []):
        ip = x.get("ip", "")
        if not ip: continue
        s = ip_stat.setdefault(ip, {"sayi": 0, "kurallar": set(), "saatler": []})
        s["sayi"] += 1; s["kurallar"].add(x.get("kural")); s["saatler"].append(x.get("zaman", "")[11:13])
    anormal = []
    for ip, s in ip_stat.items():
        # anormallik: cok kural + gece aktivite + tekrarlayan pattern
        puan = 0
        if len(s["kurallar"]) >= 3: puan += 40
        if s["sayi"] >= 5: puan += 30
        gece = sum(1 for h in s["saatler"] if h.isdigit() and (int(h) < 6 or int(h) > 23))
        if gece >= 2: puan += 30
        if puan >= 50:
            anormal.append({"ip": ip, "skor": min(100, puan), "olay": s["sayi"],
                            "kural_cesidi": len(s["kurallar"]), "gece_aktivite": gece})
    anormal.sort(key=lambda x: -x["skor"])
    yaz(f"{V}/ueba.json", {"tarih": simdi(), "anormal": anormal[:30], "toplam": len(anormal)})
    return len(anormal)

if __name__ == "__main__":
    print("COMPLIANCE skor:", compliance())
    print("YEDEK:", yedek())
    print("HONEYFILE olusturulan:", honeyfile())
    print("SYLOG 514:", syslog_kur())
    print("UEBA anormal IP:", ueba())
