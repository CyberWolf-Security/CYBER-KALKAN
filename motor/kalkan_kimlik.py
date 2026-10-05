#!/usr/bin/env python3
# CYBER KALKAN - KULLANICI/ROL + 2FA + DENETIM IZI + WAF + LOG ROTASYON
import subprocess, json, os, hashlib, base64, hmac, struct, time, secrets, glob
from datetime import datetime
V = "/opt/siber-kalkan/VERI"
L = "/opt/siber-kalkan/LOG"
def simdi(): return datetime.now().strftime("%d.%m.%Y %H:%M:%S")
def sh(c, t=120):
    try: return subprocess.run(c, shell=True, capture_output=True, text=True, timeout=t).stdout
    except Exception: return ""
def oku(y, d=None):
    try: return json.load(open(y, encoding="utf-8"))
    except Exception: return d if d is not None else {}
def yaz(y, v):
    try: json.dump(v, open(y, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    except Exception: pass

# ---------- 1) KULLANICI + ROL (RBAC) ----------
def kullanicilar():
    k = oku(f"{V}/kullanicilar.json", None)
    if k: return k
    k = {"kullanicilar": [
        {"ad": "admin", "rol": "ADMIN", "sifre_hash": hashlib.sha256(b"kalkan").hexdigest(),
         "totp": "", "aktif": True, "olusturma": simdi()},
        {"ad": "izleyici", "rol": "IZLEYICI", "sifre_hash": hashlib.sha256(b"izle").hexdigest(),
         "totp": "", "aktif": True, "olusturma": simdi()}
    ], "guncelleme": simdi()}
    yaz(f"{V}/kullanicilar.json", k)
    return k

# ---------- 2) AUDIT LOG (kim ne yapti) ----------
def audit_ekle(kullanici, islem, detay=""):
    a = oku(f"{V}/audit.json", {"kayitlar": [], "toplam": 0})
    a["kayitlar"] = ([{"zaman": simdi(), "kullanici": kullanici, "islem": islem,
                       "detay": detay, "ip": os.environ.get("REMOTE_ADDR", "yerel")}]
                     + a.get("kayitlar", []))[:2000]
    a["toplam"] = a.get("toplam", 0) + 1
    yaz(f"{V}/audit.json", a)
    return a["toplam"]

# ---------- 3) 2FA (TOTP - kendi implementasyonumuz) ----------
def totp_uret(sirri):
    """RFC 6238 TOTP - saf python"""
    try:
        anahtar = base64.b32decode(sirri.upper() + "=" * ((8 - len(sirri) % 8) % 8))
    except Exception:
        return ""
    sayac = int(time.time()) // 30
    h = hmac.new(anahtar, struct.pack(">Q", sayac), hashlib.sha1).digest()
    o = h[-1] & 0x0F
    kod = (struct.unpack(">I", h[o:o+4])[0] & 0x7FFFFFFF) % 1000000
    return f"{kod:06d}"

def totp_sirri_uret():
    return base64.b32encode(secrets.token_bytes(20)).decode().rstrip("=")

# ---------- 4) WAF (ModSecurity yok -> nginx kural + kendi WAF'i) ----------
def waf_kur():
    kural_yolu = "/etc/nginx/kalkan-waf.conf"
    kurallar = '''# CYBER KALKAN WAF (kendi imzalarimiz)
# SQLi
if ($args ~* "(union.*select|select.*from|information_schema|or\\s+1=1)") { return 403; }
# XSS
if ($args ~* "(<script|javascript:|onerror=|onerror%3D)") { return 403; }
# Path traversal
if ($args ~* "(\\.\\./|\\.\\.%2f|%2e%2e)") { return 403; }
# Komut enjeksiyonu
if ($args ~* "(;\\s*(cat|id|whoami)|\\|\\(|%0a)") { return 403; }
# Hassas dosya
if ($uri ~* "(/\\.env|/\\.git|/wp-config|/phpmyadmin)") { return 403; }
# User-Agent botlar
if ($http_user_agent ~* "(sqlmap|nikto|nmap|masscan|acunetix|nessus|dirbuster)") { return 403; }
'''
    open(kural_yolu, "w").write(kurallar)
    # nginx test
    t = sh("nginx -t 2>&1")
    return "successful" in t, kural_yolu

# ---------- 5) LOG ROTASYON ----------
def log_rotasyon():
    conf = '''/opt/siber-kalkan/LOG/*.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    copytruncate
}
/var/log/suricata/*.log {
    daily
    rotate 7
    compress
    missingok
    notifempty
    copytruncate
}
'''
    open("/etc/logrotate.d/kalkan", "w").write(conf)
    return os.path.exists("/etc/logrotate.d/kalkan")

# ---------- 6) DOCKER IZLEME ----------
def docker_izle():
    if not sh("which docker"):
        return {"docker": False, "konteyner": 0}
    c = sh("docker ps --format '{{.Names}}|{{.Status}}'").strip().split("\n")
    konteyner = [x for x in c if x]
    yaz(f"{V}/docker.json", {"tarih": simdi(), "konteyner": konteyner, "toplam": len(konteyner)})
    return {"docker": True, "konteyner": len(konteyner)}

# ---------- 7) EPOSTA ALARM ----------
def eposta_alarm_kur():
    conf = {"aktif": False, "smtp": "", "port": 587, "kullanici": "", "sifre": "",
            "hedef": [], "not": "Ayarlar sayfasindan doldur"}
    yol = f"{V}/eposta.json"
    if not os.path.exists(yol): yaz(yol, conf)
    return True

if __name__ == "__main__":
    print("KULLANICILAR:", len(kullanicilar()["kullanicilar"]), "(admin: ADMIN, izleyici: IZLEYICI)")
    print("AUDIT kayit:", audit_ekle("sistem", "MODUL_KURULUM", "kullanici+2fa+waf"))
    print("2FA ornek TOTP (test):", totp_uret("JBSWY3DPEHPK3PXP"))
    print("TOTP sirri ornegi:", totp_sirri_uret()[:16] + "...")
    ok, yol = waf_kur(); print("WAF:", "KURULDU" if ok else "nginx test uyarisi", yol)
    print("LOG ROTASYON:", log_rotasyon())
    print("DOCKER:", docker_izle())
    print("EPOSTA ALARM:", eposta_alarm_kur())
