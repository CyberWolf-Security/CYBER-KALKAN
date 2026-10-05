#!/usr/bin/env python3
"""AG DURUM — nftables/arayuz durumunu JSON'a yazar (ROOT calisir). Panel sadece okur."""
import json, os, subprocess, datetime

V = "/opt/siber-kalkan/VERI"

def sh(c, t=15):
    try:
        return subprocess.run(c, shell=True, capture_output=True, text=True, timeout=t).stdout.strip()
    except Exception:
        return ""

d = {"zaman": datetime.datetime.now().strftime("%d.%m.%Y %H:%M:%S")}

# arayuzler (ip -j)
try:
    a = json.loads(sh("ip -j addr") or "[]")
    d["arayuzler"] = [{"ad": x.get("ifname",""), "durum": (x.get("operstate","") or "").upper(),
                       "ip": next((f.get("local","")+"/"+str(f.get("prefixlen","")) for f in x.get("addr_info",[]) if f.get("family")=="inet"), "")}
                      for x in a]
except Exception:
    d["arayuzler"] = []

# rotalar
d["rotalar"] = len([l for l in sh("ip route").splitlines() if l.strip()])

# NAT (nftables)
nn = sh("nft list table ip nat 2>/dev/null")
d["nat"] = len([l for l in nn.splitlines() if "dnat" in l or "snat" in l or "masquerade" in l])

# VLAN arayuzleri
d["vlan"] = len([l for l in sh("ip -o link show").splitlines() if "@" in l and "." in l.split(":")[1] if ":" in l]) if False else len([x["ad"] for x in d["arayuzler"] if "." in x["ad"]])

# kalkan_ag tablosu yuklu mu
ag = sh("nft list table inet kalkan_ag 2>/dev/null")
d["zone"] = "kalkan_ag" in ag
d["zone_kural"] = len([l for l in ag.splitlines() if l.strip().startswith(("tcp","udp","ip ","meta","counter"))])
d["nat_toplam"] = 0

# suricata/ips bilgisi (kart icin)
d["ips_tablo"] = "kalkan_ips" in sh("nft list tables 2>/dev/null")

for k, v in d.items():
    if isinstance(v, str) and len(v) > 200:
        d[k] = v[:200]

open(os.path.join(V, "ag_durum.json"), "w", encoding="utf-8").write(
    json.dumps(d, ensure_ascii=False, indent=1))
print(json.dumps(d, ensure_ascii=False)[:300])
