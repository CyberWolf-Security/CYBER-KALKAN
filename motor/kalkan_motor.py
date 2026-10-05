#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CYBER KALKAN - KURAL MOTORU (Motor / Manager katmani)
CYBERWOLF SECURITY | Sifirdan yazildi - CYBER KALKAN'tan kopya YOK
Gorev: log oku -> kural uygula -> karar ver -> IP engelle -> JSON'a yaz
"""
import json, os, re, time, fcntl, subprocess, hashlib
from datetime import datetime

B = "/opt/siber-kalkan"
V = f"{B}/VERI"
L = f"{B}/LOG"

# ---------- yardimcilar ----------
def simdi():
    return datetime.now().strftime("%d.%m.%Y %H:%M:%S")

def oku(yol, varsayilan):
    try:
        with open(yol, "r", encoding="utf-8") as f:
            return json.load(f)
    except Exception:
        return varsayilan

def yaz(yol, veri):
    """Atomik yazma (kilit ile)"""
    gecici = yol + ".tmp"
    with open(gecici, "w", encoding="utf-8") as f:
        json.dump(veri, f, ensure_ascii=False, indent=1)
    os.replace(gecici, yol)

def logla(mesaj):
    with open(f"{L}/motor.log", "a", encoding="utf-8") as f:
        f.write(f"[{simdi()}] {mesaj}\n")

# ---------- kural seti (sifirdan) ----------
KURALLAR = [(k["id"], k["seviye"], k["puan"], k["ad"], k["desen"], k.get("mitre","")) 
            for k in oku(f"{V}/kurallar.json", {"kurallar": []}).get("kurallar", []) if k.get("aktif", True)]


# ---------- TEHDIT ISTIHBARATI (IOC) ----------
def ioc_yukle():
    """Kotu IP listesi - yerel dosya (kendi kodumuz)"""
    ioc = set()
    d = oku(f"{V}/ioc.json", {"ip_listesi": []})
    for x in d.get("ip_listesi", []):
        if isinstance(x, str): ioc.add(x)
        elif isinstance(x, dict) and x.get("ip"): ioc.add(x["ip"])
    return ioc

# ---------- KORELASYON ----------
def korelasyon(puanlar, olay_sayaci):
    """Ayni IP'de cok kural eslesirse seviyeyi yukselt"""
    yukselt = {}
    for ip, adet in olay_sayaci.items():
        if adet >= 2:
            yukselt[ip] = "KRITIK"
    return yukselt

# ---------- durum ----------
def durum_yukle():
    return {
        "pencere": {},   # ip -> [(zaman, puan)]
        "engel": oku(f"{V}/engel.json", {"liste": []}),
        "olaylar": oku(f"{V}/olaylar.json", {"olaylar": []}),
        "kararlar": oku(f"{V}/kararlar.json", {"kararlar": []}),
        "ayarlar": oku(f"{V}/ayarlar.json", {"esik": 5, "pencere_sn": 60}),
    }


# ---------- telegram alarm ----------
def telegram(mesaj):
    a = oku(f"{V}/ayarlar.json", {})
    t, c = a.get("telegram_token",""), str(a.get("telegram_chat",""))
    if not t or not c: return
    try:
        import urllib.request, urllib.parse
        d = urllib.parse.urlencode({"chat_id":c,"text":mesaj,"parse_mode":"HTML"}).encode()
        urllib.request.urlopen(f"https://api.telegram.org/bot{t}/sendMessage", data=d, timeout=8)
    except Exception:
        pass

# ---------- log okuma ----------
LOG_KAYNAKLARI = [
    "/var/log/nginx/access.log",
    "/var/log/nginx/error.log",
    "/var/log/apache2/access.log",
    "/var/log/apache2/error.log",
    "/var/log/auth.log",
    "/var/log/syslog",
    "/var/log/kern.log",
    "/var/log/mail.log",
    "/var/log/mysql/error.log",
    "/var/log/dpkg.log",
    "/opt/siber-kalkan/LOG/lab.log",
] + [f for f in __import__("glob").glob("/var/log/*.log")]
# NOT: uzak ajan loglari HER TURDA dinamik eklenir (asagida) — yeni cihaz icin restart GEREKMEZ

IP_RE = re.compile(r"^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})")

# --- PERFORMANS: desenleri bir kez derle (re cache tasmasini onler) ---
KURALLAR_DERLI = []
for _kid, _sev, _puan, _ad, _desen, _mit in KURALLAR:
    try:
        KURALLAR_DERLI.append((_kid, _sev, _puan, _ad, re.compile(_desen, re.IGNORECASE), _mit))
    except re.error:
        pass  # bozuk deseni atla


def journald_oku(satir_sayisi=300):
    """systemd journal kayitlari (kendi kodumuz)"""
    kayitlar = []
    try:
        r = subprocess.run(["journalctl", "-n", str(satir_sayisi), "--no-pager", "-o", "cat"],
                           capture_output=True, text=True, timeout=15)
        for satir in r.stdout.splitlines():
            if satir.strip():
                kayitlar.append(("journald", satir))
    except Exception:
        pass
    return kayitlar


def suricata_oku(satir=300):
    """Suricata IDS alarmlarini oku (eve.json) - kendi kodumuz"""
    kayitlar = []
    yol = "/var/log/suricata/eve.json"
    if not os.path.exists(yol):
        return kayitlar
    try:
        out = subprocess.run(["tail", "-n", str(satir), yol],
                             capture_output=True, text=True, timeout=10).stdout
        for s2 in out.splitlines():
            try:
                j = json.loads(s2)
                if j.get("event_type") == "alert":
                    a = j.get("alert", {})
                    kayitlar.append(("suricata",
                        f"{j.get('src_ip','')} IDS-ALARM {a.get('signature','?')} [sev={a.get('severity',3)}] {a.get('category','')}"))
            except Exception:
                pass
    except Exception:
        pass
    return kayitlar

def loglari_oku(satir_sayisi=400):
    kayitlar = []
    kayitlar += journald_oku()
    kayitlar += suricata_oku()
    # OFFSET TAKIBI: sadece YENI satirlari oku (performans)
    ofsetler = {}
    try:
        ofsetler = json.load(open(f"{L}/log_offset.json", encoding="utf-8"))
    except Exception:
        pass
    # uzak ajan/syslog dosyalari HER TURDA yeniden taranir (yeni cihaz icin restart gerekmez)
    yollar = LOG_KAYNAKLARI + [f for f in __import__("glob").glob("/opt/siber-kalkan/LOG/uzak/*.log")]
    for yol in yollar:
        if not os.path.exists(yol):
            continue
        try:
            st = os.stat(yol)
            e = ofsetler.get(yol, {})
            # rotate/inode degisimi -> bastan; yoksa kaldigi yerden
            if e.get("inode") != st.st_ino or e.get("boyut", 0) > st.st_size:
                poz = 0
            else:
                poz = e.get("offset", 0)
            with open(yol, "r", encoding="utf-8", errors="ignore") as f:
                f.seek(poz)
                yeni = f.readlines()
                yeni_poz = f.tell()
            # ilk okumada cok buyuk dosyayi sinirla (ilk sefer)
            if poz == 0 and len(yeni) > 20000:
                yeni = yeni[-20000:]
            ofsetler[yol] = {"inode": st.st_ino, "offset": yeni_poz, "boyut": st.st_size}
            for s in yeni:
                if s.strip():
                    kayitlar.append((yol, s.rstrip()))
        except Exception:
            pass
    try:
        json.dump(ofsetler, open(f"{L}/log_offset.json", "w", encoding="utf-8"))
    except Exception:
        pass
    return kayitlar

def ip_bul(satir):
    m = IP_RE.match(satir.strip())
    if m:
        return m.group(1)
    # auth.log formati: "Failed password for root from 1.2.3.4 port"
    m = re.search(r"from\s+(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})", satir)
    return m.group(1) if m else None

# ---------- motor ----------
def calistir():
    d = durum_yukle()
    ayar = d["ayarlar"]
    esik = int(ayar.get("esik", 5))
    pencere = int(ayar.get("pencere_sn", 60))
    # ★ DUZELTME (olu kod): engelleme esikleri AYARLARDAN okunur.
    # Onceden sabit 100/40 yaziyordu → panelde ayar degistirilse bile motor
    # etkilenmiyordu, 'esik' degiskeni hic kullanilmiyordu.
    e_tek    = int(ayar.get("engel_puan", 40))     # tek olay puan esigi
    e_toplam = int(ayar.get("engel_toplam", 100))  # pencere ici toplam puan esigi
    simdi_sn = time.time()

    engelli_ip = {k["ip"] for k in d["engel"].get("liste", [])}
    ioc = ioc_yukle()
    olay_sayaci = {}
    yeni_olay = []
    yeni_karar = []
    puanlar = {}

    for kaynak, satir in loglari_oku():
        ip = ip_bul(satir)
        if not ip or ip in ("127.0.0.1", "::1") or ip in engelli_ip:
            continue

        olay_sayaci[ip] = olay_sayaci.get(ip, 0) + 1

        # IOC: bilinen kotu IP -> aninda engelle
        if ip in ioc:
            yeni_olay.append({"zaman": simdi(), "ip": ip, "kural": 1900, "seviye": "KRITIK",
                              "aciklama": "Bilinen kotu IP (tehdit istihbarati)", "kaynak": "IOC",
                              "mitre": "T1071"})
            puanlar[ip] = puanlar.get(ip, 0) + 90
            continue

        # Suricata IDS alarmi -> dogrudan olay
        if "IDS-ALARM" in satir:
            sev = "KRITIK" if "[sev=1]" in satir else ("YUKSEK" if "[sev=2]" in satir else "ORTA")
            yeni_olay.append({"zaman": simdi(), "ip": ip, "kural": 2000, "seviye": sev,
                              "aciklama": "Suricata IDS: " + satir.split("IDS-ALARM", 1)[1].strip()[:80],
                              "kaynak": "suricata", "mitre": "T1190"})
            puanlar[ip] = puanlar.get(ip, 0) + (45 if sev == "KRITIK" else 30)
            olay_sayaci[ip] = olay_sayaci.get(ip, 0) + 1
            continue

        # kural eslesmesi
        for kid, seviye, puan, aciklama, _rx, mitre_id in KURALLAR_DERLI:
            if _rx.search(satir):
                yeni_olay.append({
                    "zaman": simdi(),
                    "ip": ip,
                    "kural": kid,
                    "seviye": seviye,
                    "aciklama": aciklama,
                    "kaynak": os.path.basename(kaynak),
                    "mitre": mitre_id,
                })
                puanlar[ip] = puanlar.get(ip, 0) + puan
                break

        # brute-force (auth.log basarisiz giris)
        if "Failed password" in satir or "authentication failure" in satir:
            yeni_olay.append({"zaman": simdi(), "ip": ip, "kural": 1009, "seviye": "YUKSEK",
                              "aciklama": "SSH basarisiz giris", "kaynak": "auth"})
            puanlar[ip] = puanlar.get(ip, 0) + 25

    # --- esik kontrolu + engelleme ---
    # ★ B-11 DUZELTMESI: korelasyon() artik CAGRILIYOR.
    # Ayni IP'de 2+ kural eslesirse seviye KRITIK'e yukseltilir.
    olay_sayaci = {}
    for _o in yeni_olay:
        _ipk = _o.get("ip", "")
        if _ipk:
            olay_sayaci[_ipk] = olay_sayaci.get(_ipk, 0) + 1
    yukselt = korelasyon(puanlar, olay_sayaci)

    for ip, puan in puanlar.items():
        _korel = ip in yukselt
        gecmis = [t for t in d["pencere"].get(ip, []) if simdi_sn - t[0] < pencere]
        gecmis.append((simdi_sn, puan))
        d["pencere"][ip] = gecmis
        toplam = sum(p[1] for p in gecmis)

        # ★ DUZELTME: esikler artik ayarlardan geliyor (e_tek/e_toplam) +
        # pencere icindeki olay SAYISI 'esik' degerini gecerse de tetikler.
        if toplam >= e_toplam or puan >= e_tek or len(gecmis) >= esik or _korel:
            if ip not in engelli_ip:
                # engellenen IP'nin son olayindan aciklama + MITRE al (kapsam hatasi duzeltmesi)
                son = next((o for o in reversed(yeni_olay) if o.get("ip") == ip), None)
                acik_e  = (son or {}).get("aciklama", "risk puani esigi asildi")
                mitre_e = (son or {}).get("mitre", "-")
                _oto = bool(oku(f"{V}/ayarlar.json", {}).get("otomatik_engel", True))
                yeni_karar.append({
                    "zaman": simdi(), "ip": ip,
                    "karar": "ENGELLENDİ" if _oto else "ENGELLE (ONAY BEKLİYOR)",
                    "puan": toplam,
                    "sebep": f"risk puani {toplam}" + ("" if _oto else " - otomatik engelleme KAPALI")
                })
                if _oto:
                    d["engel"]["liste"].append({
                        "ip": ip, "puan": toplam, "zaman": simdi(),
                        "sebep": "otomatik", "kaynak": "MOTOR"
                    })
                    firewall_engelle(ip)
                telegram(f"\U0001F6AB <b>CYBER KALKAN</b>\nIP engellendi: <code>{ip}</code>\nSebep: {acik_e}\nMITRE: {mitre_e}\nPuan: {puanlar.get(ip,0)}")
                if _oto: engelli_ip.add(ip)

    # --- kaydet ---
    if yeni_olay:
        d["olaylar"]["olaylar"] = (yeni_olay + d["olaylar"].get("olaylar", []))[:500]
        d["olaylar"]["toplam"] = d["olaylar"].get("toplam", 0) + len(yeni_olay)
        d["olaylar"]["guncelleme"] = simdi()
        yaz(f"{V}/olaylar.json", d["olaylar"])

    if yeni_karar:
        d["kararlar"]["kararlar"] = (yeni_karar + d["kararlar"].get("kararlar", []))[:300]
        d["kararlar"]["toplam"] = d["kararlar"].get("toplam", 0) + len(yeni_karar)
        d["kararlar"]["guncelleme"] = simdi()
        yaz(f"{V}/kararlar.json", d["kararlar"])

        d["engel"]["toplam"] = len(d["engel"]["liste"])
        d["engel"]["guncelleme"] = simdi()
        yaz(f"{V}/engel.json", d["engel"])

    # istatistik
    yaz(f"{V}/istatistik.json", {
        "guncelleme": simdi(),
        "toplam_olay": d["olaylar"].get("toplam", 0),
        "toplam_engel": len(d["engel"]["liste"]),
        "kritik": sum(1 for o in d["olaylar"].get("olaylar", [])[:100] if o.get("seviye") == "KRITIK"),
        "aktif_ajan": 1,
    })

    logla(f"{len(yeni_olay)} olay, {len(yeni_karar)} yeni engel")

# ---------- firewall ----------
def firewall_engelle(ip):
    """nftables ile engelle (basarisizsa iptables)"""
    for cmd in (["nft", "add", "element", "inet", "kilic", "kara", "{", ip, "}"],
                ["iptables", "-I", "INPUT", "-s", ip, "-j", "DROP"]):
        try:
            r = subprocess.run(cmd, capture_output=True, text=True, timeout=8)
            if r.returncode == 0:
                logla(f"FIREWALL engellendi: {ip}")
                return True
        except Exception:
            continue
    logla(f"FIREWALL HATA: {ip}")
    return False

if __name__ == "__main__":
    calistir()

# ---------- DONGUSEL MOD (performans: regex bir kez derlenir) ----------
if __name__ == "__main__" and "--dongu" in __import__("sys").argv:
    import sys, signal
    DONGU_ARALIK = 5
    def _kapat(sig, frame):
        logla("motor kapatiliyor")
        sys.exit(0)
    signal.signal(signal.SIGTERM, _kapat)
    signal.signal(signal.SIGINT, _kapat)
    logla(f"DONGU MODU basladi ({DONGU_ARALIK}sn)")
    _tur = 0
    while True:
        try:
            calistir()
        except Exception as e:
            logla(f"dongu hata: {e}")
        # her turda indeks tablosunu tazele (25 ms - ihmal edilebilir)
        try:
            import subprocess as _sp
            _sp.run(["/usr/bin/python3", "/opt/siber-kalkan/MOTOR/kalkan_indexer.py"],
                    stdout=_sp.DEVNULL, stderr=_sp.DEVNULL, timeout=20)
        except Exception:
            pass
        # KOD BUTUNLUK KONTROLU (her 12 turda bir = 60 sn) — bozulma varsa otomatik onar
        _tur += 1
        if _tur % 12 == 0:
            try:
                import subprocess as _sp2
                _r = _sp2.run(["/usr/bin/python3", "/opt/siber-kalkan/MOTOR/kalkan_koruma.py", "--kontrol"],
                              capture_output=True, text=True, timeout=30)
                if _r.returncode != 0:   # bozulma tespit edildi
                    logla("KOD BOZULMASI TESPIT EDILDI — otomatik onarim basliyor")
                    _sp2.run(["/usr/bin/python3", "/opt/siber-kalkan/MOTOR/kalkan_koruma.py", "--onar"],
                             capture_output=True, text=True, timeout=60)
            except Exception:
                pass
        __import__("time").sleep(DONGU_ARALIK)
