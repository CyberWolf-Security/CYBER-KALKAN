#!/usr/bin/env python3
# CYBER KALKAN - COGRAFYA (saldiri kaynagi ulke tespiti - toplu sorgu)
import json, urllib.request, time
V = "/opt/siber-kalkan/VERI"
def oku(y):
    try: return json.load(open(y, encoding="utf-8"))
    except: return {}
def yaz(y, v):
    json.dump(v, open(y, "w", encoding="utf-8"), ensure_ascii=False, indent=1)

e = oku(f"{V}/engel.json")
geo = oku(f"{V}/cografya.json")
cache = geo.get("ip_ulke", {})
ips = [k["ip"] for k in e.get("liste", [])][:300]
eksik = [ip for ip in ips if ip not in cache]
print(f"toplam {len(ips)} IP, {len(eksik)} yeni sorgulanacak")

# ip-api.com toplu sorgu (100 IP/istek, ucretsiz)
sayac = {}
for i in range(0, min(len(eksik), 200), 100):
    parca = eksik[i:i+100]
    try:
        req = urllib.request.Request("http://ip-api.com/batch?fields=query,country,countryCode",
                                     data=json.dumps(parca).encode(), headers={"Content-Type": "application/json"})
        for x in json.loads(urllib.request.urlopen(req, timeout=20).read()):
            if x.get("countryCode"):
                cache[x["query"]] = {"ulke": x["country"], "kod": x["countryCode"]}
    except Exception as ex:
        print("sorgu hatasi:", ex)
        break
    time.sleep(1.5)

for ip, v in cache.items():
    k = v.get("kod", "?")
    sayac[k] = sayac.get(k, 0) + 1
sirali = sorted(sayac.items(), key=lambda x: -x[1])[:15]
yaz(f"{V}/cografya.json", {"guncelleme": time.strftime("%d.%m.%Y %H:%M"), "ip_ulke": cache, "ulkeler": sirali})
print("ulkeler:", sirali[:8])
