#!/usr/bin/env python3
"""CYBER KALKAN — UEBA (User & Entity Behavior Analytics) v10
Davranis profili cikarir, anormal IP/kullanici hareketlerini tespit eder."""
import json, os, time, statistics
from datetime import datetime, timedelta
from collections import defaultdict, Counter

V = "/opt/siber-kalkan/VERI"
LOG = "/opt/siber-kalkan/LOG"

# ── Esikler (v10) ──
ESIK = {
    "yeni_ip_agirlik": 3,        # ilk kez gorulen IP
    "yuksek_hacim": 50,          # 1 saatte 50+ olay
    "gece_aktivite": 2,          # 00:00-06:00 arasi olay
    "cok_kural": 5,              # farkli kural sayisi
    "yeni_ulke": 2,              # ilk kez gorulen ulke
    "anomali_skor": 6,           # toplam esik
    "min_olay": 3,               # analiz icin minimum olay
}

def oku(ad, varsa=None):
    try:
        return json.load(open(f"{V}/{ad}.json"))
    except Exception:
        return varsa if varsa is not None else {}

def yaz(ad, veri):
    json.dump(veri, open(f"{V}/{ad}.json", "w"), ensure_ascii=False, indent=1)

def profil():
    """Gecmis olaylardan referans profil olustur (baseline)."""
    olay = oku("olaylar", {"olaylar": []}).get("olaylar", [])
    engel = oku("engel", {"liste": []}).get("liste", [])

    # IP basina kural ve zaman dagilimi
    ip_kural = defaultdict(set)
    ip_zaman = defaultdict(list)
    ip_saat = defaultdict(int)
    kural_say = Counter()

    for o in olay:
        ip = o.get("ip", "")
        if not ip:
            continue
        k = str(o.get("kural", "?"))
        ip_kural[ip].add(k)
        kural_say[k] += 1
        z = o.get("zaman", "")
        try:
            t = datetime.strptime(z, "%d.%m.%Y %H:%M:%S")
            ip_zaman[ip].append(t)
            if 0 <= t.hour < 6:
                ip_saat[ip] += 1
        except Exception:
            pass

    # Ulke haritasi
    ulke = {}
    try:
        cg = oku("cografya", {})
        for ip, v in (cg.get("ip_ulke", {}) or {}).items():
            ulke[ip] = v.get("ulke") if isinstance(v, dict) else v
    except Exception:
        pass

    return {
        "olay": olay, "engel": engel, "ip_kural": ip_kural, "ip_zaman": ip_zaman,
        "ip_saat": ip_saat, "kural_say": kural_say, "ulke": ulke,
        "bilinen_ip": set(engel_ip["ip"] for engel_ip in engel if engel_ip.get("ip")),
    }

def analiz():
    p = profil()
    skorlar = {}
    bulgular = []

    # 1. Her IP icin anomali skoru
    for ip, kurallar in p["ip_kural"].items():
        skor = 0; sebep = []
        zamanlar = p["ip_zaman"].get(ip, [])

        if len(zamanlar) >= ESIK["min_olay"]:
            # farkli kural cesitliligi
            if len(kurallar) >= ESIK["cok_kural"]:
                skor += ESIK["cok_kural"]; sebep.append(f"{len(kurallar)} farkli kural")
            # yogunluk (son 1 saat)
            bir_saat_once = datetime.now() - timedelta(hours=1)
            son_saat = [t for t in zamanlar if t >= bir_saat_once]
            if len(son_saat) >= ESIK["yuksek_hacim"]:
                skor += 3; sebep.append(f"1 saatte {len(son_saat)} olay")
            # gece aktivitesi
            if p["ip_saat"].get(ip, 0) >= ESIK["gece_aktivite"]:
                skor += ESIK["gece_aktivite"]; sebep.append(f"{p['ip_saat'][ip]}x gece (00-06)")
            # duzenli aralik (bot/beacon tespiti)
            if len(zamanlar) >= 5:
                sn = sorted(t.timestamp() for t in zamanlar)
                aralik = [sn[i+1]-sn[i] for i in range(len(sn)-1)]
                if aralik and statistics.mean(aralik) > 0:
                    sapma = statistics.pstdev(aralik) if len(aralik) > 1 else 0
                    if sapma < 30 and statistics.mean(aralik) < 600:
                        skor += 3; sebep.append(f"duzenli beacon (~{int(statistics.mean(aralik))}sn)")
        if skor:
            skorlar[ip] = skor
            if skor >= ESIK["anomali_skor"]:
                bulgular.append({"ip": ip, "skor": skor, "sebep": " · ".join(sebep),
                                 "kural_sayisi": len(kurallar)})

    # 2. Anormal kural yogunlasmasi (yeni saldiri tipi)
    toplam = sum(p["kural_say"].values()) or 1
    yeni_trendler = []
    for k, n in p["kural_say"].most_common(5):
        oran = n / toplam * 100
        if oran > 25:
            yeni_trendler.append({"kural": k, "adet": n, "oran": round(oran, 1)})

    # 3. Sonuc kaydet
    sonuc = {
        "zaman": datetime.now().strftime("%d.%m.%Y %H:%M:%S"),
        "analiz_edilen_ip": len(p["ip_kural"]),
        "anormal_ip": len(bulgular),
        "yuksek_riskli": sorted(bulgular, key=lambda x: -x["skor"])[:20],
        "trendler": yeni_trendler,
        "skorlar": dict(sorted(skorlar.items(), key=lambda x: -x[1])[:50]),
        "esikler": ESIK,
    }
    yaz("ueba", sonuc)

    # Log
    os.makedirs(LOG, exist_ok=True)
    with open(f"{LOG}/ueba.log", "a") as f:
        f.write(f"[{sonuc['zaman']}] {len(p['ip_kural'])} IP analiz, {len(bulgular)} anormal\n")

    return sonuc

if __name__ == "__main__":
    r = analiz()
    print(f"[{r['zaman']}] UEBA: {r['analiz_edilen_ip']} IP analiz edildi, {r['anormal_ip']} anormal")
    for b in r["yuksek_riskli"][:5]:
        print(f"  ⚠ {b['ip']:18s} skor={b['skor']:2d}  {b['sebep']}")
