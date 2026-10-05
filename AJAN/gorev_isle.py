#!/usr/bin/env python3
"""CYBER KALKAN AJAN — gorev isleyici
stdin: merkez yaniti (JSON)   stdout: gorev sonuclari (JSON dizisi)
Guvenli: komutlar merkezin izin listesinden (gorev_tip) gelir, 180 sn timeout."""
import sys, json, subprocess, datetime

LOG = "/var/log/kalkan-ajan.log"


def logla(m):
    try:
        with open(LOG, "a") as f:
            f.write(f"[{datetime.datetime.now():%d.%m.%Y %H:%M:%S}] {m}\n")
    except Exception:
        pass


def main():
    try:
        d = json.load(sys.stdin)
    except Exception:
        print("[]")
        return
    gorevler = d.get("gorevler") or []
    sonuc = []
    for g in gorevler[:5]:
        komut = (g.get("komut") or "").strip()
        gid = g.get("id")
        tip = g.get("tip") or ""
        if not komut:
            continue
        logla(f"GOREV #{gid} ({tip}) basliyor")
        try:
            r = subprocess.run(["bash", "-c", komut], capture_output=True,
                               text=True, timeout=180)
            cikti = (r.stdout or "") + (r.stderr or "")
            durum = "tamam" if r.returncode == 0 else "hata"
            logla(f"GOREV #{gid} bitti ({durum}, {len(cikti)} B)")
        except subprocess.TimeoutExpired:
            cikti, durum = "zaman asimi (180 sn)", "hata"
            logla(f"GOREV #{gid} ZAMAN ASIMI")
        except Exception as e:
            cikti, durum = str(e)[:500], "hata"
            logla(f"GOREV #{gid} HATA: {e}")
        sonuc.append({
            "id": gid, "durum": durum, "sonuc": tip,
            "cikti": cikti[:3500],
        })
    print(json.dumps(sonuc, ensure_ascii=False))


if __name__ == "__main__":
    main()