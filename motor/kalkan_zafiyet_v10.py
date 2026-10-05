#!/usr/bin/env python3
"""CYBER KALKAN — Zafiyet Tarayici v10
CVE beslemesi · CVSS skorlama · paket bazli risk · sanal yama kontrolu"""
import json, os, re, subprocess, urllib.request
from datetime import datetime

V = "/opt/siber-kalkan/VERI"
LOG = "/opt/siber-kalkan/LOG"
CVE_ONBELLEK = f"{V}/cve_onbellek.json"

# ── Bilinen kritik CVE'ler (paket · surum · cve · cvss · baslik) ──
BILINEN = [
    ("openssl", "<3.0.7", "CVE-2022-3602", 9.8, "OpenSSL X.509 buffer overflow"),
    ("openssh", "<9.3",   "CVE-2023-38408", 9.8, "OpenSSH ssh-agent Uzak Kod"),
    ("sudo",    "<1.9.13","CVE-2023-22809", 7.8, "sudo EDITOR yetki yukseltme"),
    ("polkit",  "<121",   "CVE-2021-4034", 7.8, "PwnKit yerel yetki yukseltme"),
    ("glibc",   "<2.36",  "CVE-2021-3999", 7.4, "glibc getcwd buffer overflow"),
    ("bash",    "<5.2",   "CVE-2022-3715", 7.8, "bash heap overflow"),
    ("curl",    "<7.86",  "CVE-2022-32221", 9.8, "curl cookie sizintisi"),
    ("nginx",   "<1.23.3","CVE-2022-41741", 7.8, "nginx mp4 bellek bozulmasi"),
    ("apache2", "<2.4.55", "CVE-2023-25690", 9.8, "Apache mod_proxy istek kacakciligi"),
    ("php",     "<8.1.17","CVE-2023-0662", 7.5, "PHP DoS (boundary)"),
    ("python3", "<3.11.3","CVE-2023-24329", 7.5, "Python urllib URL filtresi"),
    ("mysql",   "<8.0.32","CVE-2023-21980", 7.1, "MySQL istemci kod enjeksiyonu"),
    ("kernel",  "<6.1.20","CVE-2023-0386", 7.8, "Linux kernel overlayfs yetki"),
    ("libxml2", "<2.10.3","CVE-2022-40303", 7.5, "libxml2 tamsayi tasması"),
    ("zlib",    "<1.2.13","CVE-2022-37434", 9.8, "zlib heap buffer overflow"),
    ("systemd", "<252.7", "CVE-2022-45873", 5.5, "systemd bellek sizintisi"),
    ("vim",     "<9.0.1837","CVE-2023-4733",7.8, "vim modeline komut calistirma"),
    ("unzip",   "<6.0",   "CVE-2014-9636", 7.5, "unzip out-of-bounds yazma"),
    ("libssh",  "<0.10.4","CVE-2023-1667", 5.3, "libssh NULL dereference"),
    ("docker",  "<23.0.3", "CVE-2023-28842", 5.3, "Docker IP maskeleme hatasi"),
]

# ── Paket yoneticileri ──
def paketler():
    p = {}
    for cmd, isim in [
        ("dpkg-query -W -f='${Package} ${Version}\\n'", "dpkg"),
        ("rpm -qa --qf '%{NAME} %{VERSION}\\n'", "rpm"),
        ("pacman -Q", "pacman"),
    ]:
        try:
            r = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=45)
            if r.returncode == 0 and r.stdout.strip():
                for satir in r.stdout.splitlines():
                    par = satir.split()
                    if len(par) >= 2:
                        p[par[0].lower()] = par[1]
                if p:
                    return p, isim
        except Exception:
            continue
    return p, "yok"

def surum_kiyasla(mevcut, kosul):
    """<X.Y.Z kosulunu kontrol et"""
    m = re.match(r"<\s*([\d.]+)", kosul)
    if not m:
        return False
    def parcalar(s):
        return [int(x) for x in re.findall(r"\d+", s.split("-")[0].split("+")[0])][:4]
    try:
        a, b = parcalar(mevcut), parcalar(m.group(1))
        while len(a) < len(b): a.append(0)
        while len(b) < len(a): b.append(0)
        return a < b
    except Exception:
        return False

# ── Guncel CVE beslemesi (NVD) — internet varsa ──
def nvd_besle(kelime):
    try:
        url = f"https://services.nvd.nist.gov/rest/json/cves/2.0?keywordSearch={kelime}&resultsPerPage=5"
        req = urllib.request.Request(url, headers={"User-Agent": "CYBER-KALKAN"})
        with urllib.request.urlopen(req, timeout=12) as r:
            d = json.loads(r.read())
        out = []
        for item in d.get("vulnerabilities", []):
            c = item.get("cve", {})
            skor = 0
            for m in c.get("metrics", {}).get("cvssMetricV31", []):
                skor = m.get("cvssData", {}).get("baseScore", 0); break
            out.append({"id": c.get("id"), "skor": skor,
                        "ozet": (c.get("descriptions", [{}])[0].get("value", "")[:110])})
        return out
    except Exception:
        return []

def tara(cevrimici=False):
    p, yonetici = paketler()
    bulgular = []
    sanal = []

    # 1. Bilinen kritik CVE eslesmesi
    for paket, kosul, cve, cvss, baslik in BILINEN:
        sur = p.get(paket)
        if sur and surum_kiyasla(sur, kosul):
            bulgular.append({"paket": paket, "surum": sur, "cve": cve, "cvss": cvss,
                             "baslik": baslik,
                             "risk": "KRITIK" if cvss >= 9 else ("YUKSEK" if cvss >= 7 else "ORTA")})

    # 2. Cevrimici NVD (opsiyonel)
    if cevrimici:
        for paket in list(p.keys())[:8]:
            for c in nvd_besle(paket)[:2]:
                if c.get("skor", 0) >= 7:
                    sanal.append(c)

    # 3. Kernel durumu
    kver = subprocess.run("uname -r", shell=True, capture_output=True, text=True).stdout.strip()

    # 4. Ortalama CVSS
    ort = round(sum(b["cvss"] for b in bulgular) / len(bulgular), 1) if bulgular else 0
    skor = max(0, 100 - sum(int(b["cvss"]) for b in bulgular) * 2)

    sonuc = {
        "zaman": datetime.now().strftime("%d.%m.%Y %H:%M:%S"),
        "yonetici": yonetici, "paket_sayisi": len(p),
        "kernel": kver, "bulgular": bulgular, "bulgu_sayisi": len(bulgular),
        "ortalama_cvss": ort, "sanal_yama": sanal,
        "skor": skor, "puan": skor,
        "kritik": sum(1 for b in bulgular if b["risk"] == "KRITIK"),
        "yuksek": sum(1 for b in bulgular if b["risk"] == "YUKSEK"),
        "kontrol_edilen_cve": len(BILINEN),
    }
    json.dump(sonuc, open(f"{V}/zafiyet.json", "w"), ensure_ascii=False, indent=1)

    # kritik bulgu -> olay
    if sonuc["kritik"]:
        try:
            o = json.load(open(f"{V}/olaylar.json"))
        except Exception:
            o = {"olaylar": [], "toplam": 0}
        for b in bulgular:
            if b["risk"] == "KRITIK":
                o["olaylar"].insert(0, {"zaman": sonuc["zaman"], "ip": "localhost",
                    "kural": "CVE", "seviye": "KRITIK",
                    "aciklama": f"ZAFA: {b['paket']} {b['surum']} · {b['cve']} (CVSS {b['cvss']})",
                    "kaynak": "zafiyet", "mitre": "T1190"})
        o["olaylar"] = o["olaylar"][:500]
        o["toplam"] = o.get("toplam", 0) + sonuc["kritik"]
        json.dump(o, open(f"{V}/olaylar.json", "w"), ensure_ascii=False)

    os.makedirs(LOG, exist_ok=True)
    with open(f"{LOG}/zafiyet.log", "a") as f:
        f.write(f"[{sonuc['zaman']}] Zafiyet v10: {len(p)} paket, {len(bulgular)} bulgu (K:{sonuc['kritik']} Y:{sonuc['yuksek']})\n")
    return sonuc

if __name__ == "__main__":
    r = tara("--cevrimici" in __import__("sys").argv)
    print(f"[{r['zaman']}] Zafiyet v10: {r['paket_sayisi']} paket · {r['kontrol_edilen_cve']} CVE kontrol")
    print(f"  Bulgular: {r['bulgu_sayisi']} (Kritik {r['kritik']} · Yuksek {r['yuksek']}) · ort CVSS {r['ortalama_cvss']} · skor {r['skor']}")
    for b in r["bulgular"][:6]:
        print(f"  {'⚠' if b['risk']=='KRITIK' else '·'} {b['paket']:12s} {b['surum']:14s} {b['cve']:16s} CVSS {b['cvss']}")
