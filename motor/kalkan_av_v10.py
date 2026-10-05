#!/usr/bin/env python3
"""CYBER KALKAN — Antivirus v10
Imza tabanli tarama · YARA destegi · karantina · hash itibar · davranis"""
import hashlib, json, os, re, subprocess, shutil
from datetime import datetime

V = "/opt/siber-kalkan/VERI"
LOG = "/opt/siber-kalkan/LOG"
KARANTINA = "/opt/siber-kalkan/karantina"

# ── Kotu amacli imza kaliplari (dosya icinde aranir) ──
IMZALAR = [
    (r"eval\s*\(\s*base64_decode\s*\(", "PHP webshell (base64 eval)", "KRITIK"),
    (r"\$_(POST|GET|REQUEST|COOKIE)\s*\[[^\]]+\]\s*;.*?(system|exec|shell_exec|passthru)", "PHP RCE", "KRITIK"),
    (r"(system|shell_exec|passthru|popen|proc_open)\s*\(\s*\$_", "PHP komut calistirma", "KRITIK"),
    (r"preg_replace\s*\(\s*[\"'].*?/e[\"']", "PHP preg_replace /e RCE", "KRITIK"),
    (r"(bash|sh)\s+-i\s*>&\s*/dev/tcp/", "Ters kabuk (bash)", "KRITIK"),
    (r"nc\s+-e\s+/bin/(sh|bash)", "Ters kabuk (nc)", "KRITIK"),
    (r"python\s+-c\s*[\"'].*socket.*connect", "Ters kabuk (python)", "KRITIK"),
    (r"powershell.*-e(ncodedcommand)?.*FromBase64String", "PowerShell base64 yuk", "KRITIK"),
    (r"IEX\s*\(New-Object\s+Net\.WebClient\)", "PowerShell indirme", "YUKSEK"),
    (r"xmrig|stratum\+tcp|minerd|cryptonight", "Kripto madenci", "KRITIK"),
    (r"HOW_TO_DECRYPT|YOUR_FILES_ARE_ENCRYPTED|DECRYPT_INSTRUCTIONS", "Ransomware notu", "KRITIK"),
    (r"(chmod\s+[0-7]{3,4}\s+.*&&\s*\./)|(curl|wget)\s+http[^\s]+\s*\|\s*(sh|bash)", "Indir-calistir", "KRITIK"),
    (r"stratum\+tcp://|pool\.minexmr|pool\.supportxmr", "Madencilik havuzu", "KRITIK"),
    (r"/dev/tcp/[0-9.]+/[0-9]+", "Ters kabuk (bash tcp)", "KRITIK"),
    (r"c99shell|r57shell|b374k|WSO\s+[0-9.]+", "Bilinen webshell", "KRITIK"),
    (r"LD_PRELOAD|ld\.so\.preload", "Preload kancasi (rootkit)", "YUKSEK"),
    (r"insmod\s+.*\.ko|modprobe\s+.*rootkit", "Kernel modul yukleme", "YUKSEK"),
    (r"curl\s+.*-d\s*.*@/etc/(passwd|shadow)", "Veri sizdirma", "KRITIK"),
    (r"base64\s+-d\s*\|.*(sh|bash|python)", "Encode edilmis kabuk", "KRITIK"),
    (r"antivirus|defender|selinux|apparmor.*(stop|disable)", "Guvenlik devre disi", "YUKSEK"),
]

# ── Taranacak dizinler ──
DIZINLER = ["/tmp", "/var/tmp", "/dev/shm"]

# ── HARIÇ TUTULAN yollar (guvenlik kodlari kendi imzalarini icerir — kendini silmesin) ──
HARIC = (
    "/opt/siber-kalkan",        # KALKAN motoru + WAF
    "/var/www/kalkan-panel",    # panel
    "/root/.hermes",            # skill/agent dosyalari
    "/root/Masaustu", "/root/Masaüstü",  # yedekler + paketler
    "/opt/siber-kalkan/karantina",
)

def haric_mi(yol):
    import fnmatch
    if any(yol.startswith(h) for h in HARIC) or "/karantina/" in yol:
        return True
    # gecici dosyalar (python -c, heredoc) + kendi imza dosyalari
    if fnmatch.fnmatch(yol, "/tmp/tmp*") or fnmatch.fnmatch(yol, "/var/tmp/tmp*"):
        return True
    return False

def sha256(yol):
    try:
        h = hashlib.sha256()
        with open(yol, "rb") as f:
            for b in iter(lambda: f.read(131072), b""):
                h.update(b)
        return h.hexdigest()
    except Exception:
        return None

def tara_dosya(yol):
    bulgular = []
    try:
        if os.path.getsize(yol) > 8 * 1024 * 1024:   # 8MB üstü atla
            return []
        with open(yol, "rb") as f:
            icerik = f.read()
        metin = icerik.decode("utf-8", "ignore")
        for desen, ad, risk in IMZALAR:
            if re.search(desen, metin, re.I):
                bulgular.append({"desen": ad, "risk": risk})
    except Exception:
        pass
    return bulgular

def yara_tara(yol):
    """YARA kuruluysa kurallarla tara"""
    try:
        r = subprocess.run(["which", "yara"], capture_output=True, text=True)
        if r.returncode != 0:
            return []
        kural_diz = "/opt/siber-kalkan/yara"
        if not os.path.isdir(kural_diz):
            return []
        out = []
        for kf in os.listdir(kural_diz):
            if kf.endswith((".yar", ".yara")):
                rr = subprocess.run(["yara", os.path.join(kural_diz, kf), yol],
                                    capture_output=True, text=True, timeout=15)
                if rr.stdout.strip():
                    out.append({"dosya": yol, "kural": rr.stdout.strip()[:120]})
        return out
    except Exception:
        return []

def karantina(yol, bulgular):
    os.makedirs(KARANTINA, exist_ok=True)
    ad = f"{os.path.basename(yol)}.{datetime.now().strftime('%d%m_%H%M%S')}.karantina"
    hedef = os.path.join(KARANTINA, ad)
    try:
        shutil.move(yol, hedef)
        with open(os.path.join(KARANTINA, "karantina.jsonl"), "a") as f:
            f.write(json.dumps({"zaman": datetime.now().strftime("%d.%m.%Y %H:%M:%S"),
                                "orijinal": yol, "karantina": hedef, "bulgular": bulgular},
                               ensure_ascii=False) + "\n")
        return hedef
    except Exception:
        return None

def tara():
    bulgular = []
    taranan = 0
    karantinaya = 0

    # 1. YARA kurallari
    yara_bulgular = []

    # 2. Dizin tarama
    for d in DIZINLER:
        if not os.path.isdir(d):
            continue
        for kok, _, dosyalar in os.walk(d):
            if "/karantina" in kok or "/.git" in kok or "node_modules" in kok:
                continue
            for fn in dosyalar[:400]:
                yol = os.path.join(kok, fn)
                if haric_mi(yol):        # guvenlik kodlari + yedekler haric
                    continue
                taranan += 1
                b = tara_dosya(yol)
                if b:
                    carpan = 2 if any(x["risk"] == "KRITIK" for x in b) else 1
                    kayit = {"zaman": datetime.now().strftime("%d.%m.%Y %H:%M:%S"),
                             "dosya": yol, "bulgular": b, "sha256": (sha256(yol) or "")[:16]}
                    # kritik -> karantina
                    if carpan == 2 and not yol.startswith(KARANTINA):
                        kq = karantina(yol, b)
                        if kq:
                            kayit["karantina"] = kq
                            karantinaya += 1
                    bulgular.append(kayit)
                # YARA (sadece kritik dizinler)
                if d in ("/tmp", "/var/tmp", "/dev/shm") and taranan % 50 == 0:
                    yara_bulgular += yara_tara(yol)

    # 3. ClamAV (kuruluysa)
    clam = ""
    try:
        r = subprocess.run(["which", "clamscan"], capture_output=True, text=True)
        if r.returncode == 0:
            rr = subprocess.run(["clamscan", "--no-summary", "-r", "-i", "/tmp", "/var/tmp", "/dev/shm"],
                                capture_output=True, text=True, timeout=120)
            clam = (rr.stdout or "")[:300]
    except Exception:
        pass

    durum = "TEMIZ" if not bulgular else ("KRITIK" if karantinaya else "SUPHELI")
    sonuc = {
        "zaman": datetime.now().strftime("%d.%m.%Y %H:%M:%S"),
        "taranan_dosya": taranan, "bulgu_sayisi": len(bulgular),
        "karantina": karantinaya, "durum": durum,
        "temiz": not bulgular, "bulgu": len(bulgular),
        "bulgular": bulgular[:100], "yara": yara_bulgular[:50],
        "imza_sayisi": len(IMZALAR), "clamav": clam,
    }
    json.dump(sonuc, open(f"{V}/av.json", "w"), ensure_ascii=False, indent=1)

    # olay
    if bulgular:
        try:
            o = json.load(open(f"{V}/olaylar.json"))
        except Exception:
            o = {"olaylar": [], "toplam": 0}
        for b in bulgular[:10]:
            o["olaylar"].insert(0, {"zaman": b["zaman"], "ip": "localhost", "kural": "AV",
                "seviye": "KRITIK" if any(x["risk"]=="KRITIK" for x in b["bulgular"]) else "YUKSEK",
                "aciklama": f"AV: {os.path.basename(b['dosya'])} · {b['bulgular'][0]['desen']}",
                "kaynak": "av", "mitre": "T1204"})
        o["olaylar"] = o["olaylar"][:500]
        o["toplam"] = o.get("toplam", 0) + len(bulgular)
        json.dump(o, open(f"{V}/olaylar.json", "w"), ensure_ascii=False)

    os.makedirs(LOG, exist_ok=True)
    with open(f"{LOG}/av.log", "a") as f:
        f.write(f"[{sonuc['zaman']}] AV v10: {taranan} dosya, {len(bulgular)} bulgu, {karantinaya} karantina\n")
    return sonuc

if __name__ == "__main__":
    r = tara()
    print(f"[{r['zaman']}] AV v10: {r['taranan_dosya']} dosya tarandi · {r['imza_sayisi']} imza · durum: {r['durum']}")
    print(f"  Bulgu: {r['bulgu_sayisi']} · Karanina: {r['karantina']}")
    for b in r["bulgular"][:5]:
        print(f"  ⚠ {b['dosya']}  →  {b['bulgular'][0]['desen']}")
