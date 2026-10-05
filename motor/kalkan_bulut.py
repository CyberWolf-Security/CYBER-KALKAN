#!/usr/bin/env python3
# CYBER KALKAN - BULUT LOGLARI (AWS/GCP) + EVTX (Windows) — CYBER KALKAN'ta var, bizde de olsun
import json, os, subprocess, glob
from datetime import datetime
V = "/opt/siber-kalkan/VERI"
def simdi(): return datetime.now().strftime("%d.%m.%Y %H:%M")
def oku(y, d=None):
    try: return json.load(open(y, encoding="utf-8"))
    except Exception: return d if d is not None else {}
def yaz(y, v):
    try: json.dump(v, open(y, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    except Exception: pass
def sh(c, t=90):
    try: return subprocess.run(c, shell=True, capture_output=True, text=True, timeout=t).stdout
    except Exception: return ""

# ---------- 1) BULUT LOG TOPLAYICI (AWS CloudTrail / GCP Audit) ----------
def bulut_tara():
    bulgular = []
    # AWS CLI var mi?
    if sh("which aws").strip():
        olay = sh("aws cloudtrail lookup-events --max-results 20 --query 'Events[*].[EventName,Username,EventTime]' --output text 2>/dev/null")
        if olay.strip():
            for s in olay.strip().split("\n")[:20]:
                p = s.split("\t")
                if len(p) >= 3:
                    tehlikeli = any(x in p[0] for x in ["DeleteTrail", "StopLogging", "CreateUser",
                                                        "AttachUserPolicy", "PutBucketPolicy", "DeleteBucket"])
                    bulgular.append({"kaynak": "AWS", "olay": p[0], "kullanici": p[1],
                                     "zaman": p[2], "tehlikeli": tehlikeli})
    # GCP gcloud var mi?
    if sh("which gcloud").strip():
        g = sh("gcloud logging read 'severity>=WARNING' --limit=20 --format='value(protoPayload.methodName)' 2>/dev/null")
        for s in [x for x in g.strip().split("\n") if x][:20]:
            bulgular.append({"kaynak": "GCP", "olay": s, "tehlikeli": False})
    # GCP service account json var mi?
    sa = glob.glob("/root/*.json") + glob.glob("/root/Masaüstü/*.json")
    sa = [x for x in sa if "service" in x.lower() or "gcp" in x.lower()]
    yaz(f"{V}/bulut.json", {"tarih": simdi(), "bulgular": bulgular, "toplam": len(bulgular),
                            "aws_cli": bool(sh("which aws").strip()), "gcp_cli": bool(sh("which gcloud").strip()),
                            "service_account": len(sa)})
    return len(bulgular)

# ---------- 2) EVTX (Windows olay loglari) ----------
def evtx_tara():
    # evtx dosyalari var mi?
    dosyalar = glob.glob("/root/**/*.evtx", recursive=True) + glob.glob("/var/log/**/*.evtx", recursive=True)
    bulgular = []
    for f in dosyalar[:10]:
        # python-evtx var mi?
        try:
            from Evtx.Evtx import Evtx
            with Evtx(f) as log:
                for i, kayit in enumerate(log.records()):
                    if i >= 200: break
                    x = kayit.xml()
                    for anahtar in ["4624", "4625", "4672", "4720", "7045", "1102"]:
                        if f"EventID>{anahtar}<" in x:
                            bulgular.append({"dosya": os.path.basename(f), "event_id": anahtar})
                            break
        except ImportError:
            bulgular.append({"dosya": os.path.basename(f), "not": "python-evtx kurulu degil (pip install python-evtx)"})
        except Exception as e:
            bulgular.append({"dosya": os.path.basename(f), "hata": str(e)[:80]})
    yaz(f"{V}/evtx.json", {"tarih": simdi(), "dosya_sayisi": len(dosyalar),
                           "bulgular": bulgular[:50], "toplam": len(bulgular)})
    return len(dosyalar)

# ---------- 3) COGRAFI HARITA VERISI (SVG icin) ----------
def harita_verisi():
    c = oku(f"{V}/cografya.json", {"ulkeler": [], "ip_ulke": {}})
    e = oku(f"{V}/engel.json", {"liste": []})
    # ulke -> engelli IP sayisi
    ulke_engel = {}
    for k in e.get("liste", []):
        v = c.get("ip_ulke", {}).get(k.get("ip", ""), "??")
        u = v.get("ulke") if isinstance(v, dict) else (v or "??")
        if u and u != "??": ulke_engel[u] = ulke_engel.get(u, 0) + 1
    sirali = sorted(ulke_engel.items(), key=lambda x: -x[1])
    yaz(f"{V}/harita.json", {"tarih": simdi(), "ulkeler": sirali, "toplam_ulke": len(sirali),
                             "en_yuksek": sirali[0][0] if sirali else "-"})
    return len(sirali)

if __name__ == "__main__":
    print("BULUT (AWS/GCP) olay:", bulut_tara())
    print("EVTX dosya:", evtx_tara())
    print("HARITA ulke:", harita_verisi())
    b = oku(f"{V}/bulut.json", {})
    print(f"  AWS CLI: {b.get('aws_cli')} | GCP CLI: {b.get('gcp_cli')} | service account: {b.get('service_account')}")
    h = oku(f"{V}/harita.json", {})
    print("  En cok saldiri:", h.get("en_yuksek"), "| ulke:", h.get("toplam_ulke"))
