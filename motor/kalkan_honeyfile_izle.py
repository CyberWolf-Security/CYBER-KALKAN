#!/usr/bin/env python3
# CYBER KALKAN - HONEYFILE IZLEME (saf python inotify - ctypes, bagimlilik YOK)
import ctypes, ctypes.util, os, struct, json, threading, time
from datetime import datetime
V = "/opt/siber-kalkan/VERI"; L = "/opt/siber-kalkan/LOG"
def simdi(): return datetime.now().strftime("%d.%m.%Y %H:%M:%S")
def oku(y, d=None):
    try: return json.load(open(y, encoding="utf-8"))
    except Exception: return d if d is not None else {}
def yaz(y, v):
    try: json.dump(v, open(y, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    except Exception: pass

# --- libc bagla ---
libc = ctypes.CDLL(ctypes.util.find_library("c") or "libc.so.6", use_errno=True)
IN_ACCESS, IN_MODIFY, IN_ATTRIB, IN_OPEN, IN_CLOSE_WRITE = 0x001, 0x002, 0x004, 0x020, 0x008
IN_MOVED_FROM, IN_MOVED_TO, IN_DELETE, IN_CREATE = 0x040, 0x080, 0x200, 0x100
MASKE = IN_ACCESS | IN_MODIFY | IN_ATTRIB | IN_OPEN | IN_CLOSE_WRITE | IN_MOVED_TO | IN_DELETE | IN_CREATE
OLAY_ADI = {IN_ACCESS: "OKUNDU", IN_OPEN: "ACILDI", IN_MODIFY: "DEGISTIRILDI",
            IN_ATTRIB: "OZNITELIK", IN_CLOSE_WRITE: "YAZILDI", IN_DELETE: "SILINDI",
            IN_CREATE: "OLUSTURULDU", IN_MOVED_TO: "TASINDI"}

def alarm(dosya, olay, ek=""):
    h = oku(f"{V}/honeyfile.json", {"dosyalar": [], "acilmalar": []})
    kayit = {"zaman": simdi(), "dosya": dosya, "olay": olay, "kaynak": "inotify", "ek": ek}
    h["acilmalar"] = ([kayit] + h.get("acilmalar", []))[:200]
    h["guncelleme"] = simdi()
    yaz(f"{V}/honeyfile.json", h)
    o = oku(f"{V}/olaylar.json", {"olaylar": [], "toplam": 0})
    o["olaylar"] = ([{"zaman": simdi(), "ip": "yerel/ajan", "kural": 3000, "seviye": "KRITIK",
                      "aciklama": f"HONEYFILE IHLALI: {os.path.basename(dosya)} ({olay})",
                      "kaynak": "honeyfile", "mitre": "T1083"}] + o.get("olaylar", []))[:500]
    o["toplam"] = o.get("toplam", 0) + 1
    yaz(f"{V}/olaylar.json", o)
    with open(f"{L}/honeyfile.log", "a") as f:
        f.write(f"[{simdi()}] IHLAL {olay}: {dosya} {ek}\n")
    print(f"[{simdi()}] 🚨 {olay}: {dosya}", flush=True)

def izle():
    fd = libc.inotify_init()
    if fd < 0:
        print("inotify_init hata"); return
    h = oku(f"{V}/honeyfile.json", {"dosyalar": []})
    eklenen = 0
    wd_dosya = {}
    for d in h.get("dosyalar", []):
        if os.path.exists(d):
            wd = libc.inotify_add_watch(fd, d.encode(), MASKE)
            wd_dosya[wd] = d
            eklenen += 1
    print(f"[{simdi()}] HONEYFILE IZLEME (saf python inotify): {eklenen} dosya", flush=True)
    tampon = 4096
    while True:
        try:
            veri = os.read(fd, tampon)
        except Exception:
            time.sleep(1); continue
        i = 0
        while i < len(veri):
            try:
                wd, maske, cookie, uzunluk = struct.unpack_from("iIII", veri, i)
                i += 16
                isim = veri[i:i+uzunluk].split(b"\0")[0].decode("utf-8", "replace")
                i += uzunluk
                if not isim:
                    isim = wd_dosya.get(wd, "")
                    if not isim: continue
                # kendi scriptlerimizi atla
                if "kalkan" in isim: continue
                # olay adini bul
                ad = next((v for k, v in OLAY_ADI.items() if maske & k), f"0x{maske:x}")
                alarm(wd_dosya.get(wd, isim), ad, f"wd={wd}")
            except Exception:
                break

if __name__ == "__main__":
    # ilk taramada hash kaydet (referans)
    h = oku(f"{V}/honeyfile.json", {"dosyalar": []})
    import hashlib
    hashler = h.get("hashlar", {})
    for d in h.get("dosyalar", []):
        if os.path.exists(d):
            hashler[d] = hashlib.md5(open(d, "rb").read()).hexdigest()
    h["hashlar"] = hashler
    yaz(f"{V}/honeyfile.json", h)
    izle()
