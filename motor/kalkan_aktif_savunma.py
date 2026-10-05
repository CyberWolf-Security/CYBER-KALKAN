#!/usr/bin/env python3
# CYBER KALKAN - AKTIF SAVUNMA (tarpit + honeypot + sinkhole)
# CYBER KALKAN'ta OLMAYAN modul | Sifirdan yazildi
import socket, threading, json, time, os
from datetime import datetime
V = "/opt/siber-kalkan/VERI"
LOG = "/opt/siber-kalkan/LOG"
TARPIT_PORT = 9099
HONEYPOT_PORTS = [2222, 3336, 8081, 4443]   # sahte servisler

def yaz(y, v):
    try: json.dump(v, open(y, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    except Exception: pass

def oku(y, d):
    try: return json.load(open(y, encoding="utf-8"))
    except Exception: return d

def simdi(): return datetime.now().strftime("%d.%m.%Y %H:%M:%S")

# ---------- 1) TARPIT: saldirgani surundur ----------
def tarpit_isle(baglanti, adres):
    ip = adres[0]
    try:
        baglanti.send(b"HTTP/1.1 200 OK\r\nContent-Type: text/html\r\n\r\n")
        for _ in range(50):                 # 50 x 10sn = ~8 dk asili tut
            baglanti.send(b" ")             # 1 byte
            time.sleep(10)
    except Exception:
        pass
    finally:
        try: baglanti.close()
        except Exception: pass

def tarpit_baslat():
    s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    s.bind(("0.0.0.0", TARPIT_PORT)); s.listen(200)
    while True:
        try:
            b, a = s.accept()
            threading.Thread(target=tarpit_isle, args=(b, a), daemon=True).start()
        except Exception:
            time.sleep(1)

# ---------- 2) HONEYPOT: sahte servisler ----------
def honeypot_isle(baglanti, adres, port):
    ip = adres[0]
    kayit = {"zaman": simdi(), "ip": ip, "port": port, "tur": "honeypot"}
    try:
        # sahte banner (servise gore)
        banner = {2222: b"SSH-2.0-OpenSSH_8.9\r\n", 3336: b"5.7.38-MariaDB\r\n",
                  8081: b"HTTP/1.1 200 OK\r\nServer: nginx\r\n\r\n", 4443: b"RDP\r\n"}.get(port, b"OK\r\n")
        baglanti.send(banner)
        time.sleep(5)
        veri = baglanti.recv(1024)
        if veri: kayit["veri"] = veri[:120].decode("utf-8", "replace")
    except Exception:
        pass
    finally:
        try: baglanti.close()
        except Exception: pass
    h = oku(f"{V}/honeypot.json", {"kayitlar": [], "toplam": 0})
    h["kayitlar"] = ([kayit] + h.get("kayitlar", []))[:300]
    h["toplam"] = h.get("toplam", 0) + 1
    h["guncelleme"] = simdi()
    yaz(f"{V}/honeypot.json", h)

def honeypot_dinle(port):
    s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    try:
        s.bind(("0.0.0.0", port)); s.listen(50)
    except Exception:
        return
    while True:
        try:
            b, a = s.accept()
            threading.Thread(target=honeypot_isle, args=(b, a, port), daemon=True).start()
        except Exception:
            time.sleep(1)

# ---------- 3) ABUSE RAPORU ----------
def abuse_uret():
    e = oku(f"{V}/engel.json", {"liste": []})
    c = oku(f"{V}/cografya.json", {"ip_ulke": {}})
    satirlar = []
    for k in e.get("liste", [])[:200]:
        ip = k.get("ip", "")
        ulke = c.get("ip_ulke", {}).get(ip, "?")
        satirlar.append(f"{ip:18} | puan {k.get('puan',0):4} | {ulke} | {k.get('zaman','')}")
    rapor = (f"CYBERWOLF SECURITY - ABUSE RAPORU\n"
             f"Tarih: {simdi()}\nToplam engelli: {len(e.get('liste', []))}\n"
             f"{'-'*60}\n" + "\n".join(satirlar))
    open(f"{V}/abuse_rapor.txt", "w", encoding="utf-8").write(rapor)
    print(f"ABUSE RAPORU: {len(satirlar)} IP")

if __name__ == "__main__":
    os.makedirs(LOG, exist_ok=True)
    print("AKTIF SAVUNMA baslatiliyor...")
    threading.Thread(target=tarpit_baslat, daemon=True).start()
    for p in HONEYPOT_PORTS:
        threading.Thread(target=honeypot_dinle, args=(p,), daemon=True).start()
    abuse_uret()
    print(f"TARPIT: {TARPIT_PORT} | HONEYPOT: {HONEYPOT_PORTS}")
    while True:
        time.sleep(60)
        abuse_uret()
