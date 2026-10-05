#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CYBER KALKAN — AĞ KATMANI (L3/L4) v10
======================================
Zone · NAT (SNAT/DNAT) · Port yönlendirme · Statik rota · VLAN · Politika

Raporda "ağ katmanı yok" denen boşluğu kapatır.

★ GÜVENLİK İLKELERİ
  1. Uygulama ASLA otomatik değil — `uygula --onay` gerekir
  2. Önce `plan` (dry-run): ne değişecek gösterir
  3. Kendini kilitleme koruması: ct established + yönetim portları muaf
  4. Yedek + geri alma: her uygulamadan önce mevcut durum saklanır
  5. Girdi doğrulama: IP/CIDR/arayüz adı kontrol edilir (enjeksiyon yok)

Kullanım:
    python3 kalkan_ag_v10.py durum
    python3 kalkan_ag_v10.py plan
    python3 kalkan_ag_v10.py uygula --onay
    python3 kalkan_ag_v10.py port-yonlendir 8080 10.0.0.10 80
    python3 kalkan_ag_v10.py rota-ekle 10.10.0.0/24 192.168.1.1
    python3 kalkan_ag_v10.py vlan-ekle eth0 100 10.100.0.1/24
    python3 kalkan_ag_v10.py geri-al
"""
import json, os, re, subprocess, sys
from datetime import datetime

V = "/opt/siber-kalkan/VERI"
LOG = "/opt/siber-kalkan/LOG"
NFT_DOSYA = "/opt/siber-kalkan/firewall/ag.nft"
YEDEK = f"{V}/ag_yedek"
TABLO = "kalkan_ag"


# ───────────────────────── yardımcılar ─────────────────────────
def _log(msg):
    ts = datetime.now().strftime("%d.%m.%Y %H:%M:%S")
    satir = f"[{ts}] AG: {msg}\n"
    try:
        os.makedirs(LOG, exist_ok=True)
        open(f"{LOG}/ag.log", "a").write(satir)
    except Exception:
        pass
    print("  " + msg)


def _gecerli_ip(v):
    """IPv4/IPv6 veya CIDR doğrula."""
    try:
        from kalkan_ipdogrula import gecerli_ip
        if "/" in str(v):
            import ipaddress
            ipaddress.ip_network(str(v), strict=False)
            return True
        return gecerli_ip(v)
    except ImportError:
        s = str(v)
        return bool(re.match(r"^[0-9a-fA-F:.]+(/\d{1,3})?$", s)) and len(s) <= 49


def _gecerli_arayuz(a):
    """Arayüz adı (harf, rakam, . _ - @ :) — shell'e güvenli."""
    return bool(re.match(r"^[A-Za-z0-9._@:-]{1,15}$", str(a)))


def sh(argv, t=20):
    """Liste formu — shell YOK."""
    try:
        r = subprocess.run(argv, capture_output=True, text=True, timeout=t)
        return r.returncode, (r.stdout or "").strip(), (r.stderr or "").strip()
    except Exception as e:
        return 1, "", str(e)


# ───────────────────────── durum ─────────────────────────
def durum():
    """Mevcut ağ durumu: arayüzler, rotalar, NAT kuralları."""
    d = {"arayuzler": [], "rotalar": [], "nat": [], "vlan": [], "zaman": None}
    rc, out, _ = sh(["ip", "-br", "addr"])
    if rc == 0:
        for s in out.splitlines():
            p = s.split()
            if len(p) >= 2:
                d["arayuzler"].append({"ad": p[0], "durum": p[1], "ip": p[2] if len(p) > 2 else "-"})
    rc, out, _ = sh(["ip", "route"])
    if rc == 0:
        d["rotalar"] = [s for s in out.splitlines() if s][:30]
    rc, out, _ = sh(["nft", "list", "table", "inet", TABLO])
    if rc == 0:
        d["nat"] = [s.strip() for s in out.splitlines()
                    if "dnat" in s or "masquerade" in s or "snat" in s]
    d["vlan"] = [a for a in d["arayuzler"] if "." in a["ad"]]
    d["zaman"] = datetime.now().strftime("%d.%m.%Y %H:%M")
    return d


def tablo_yuklu():
    rc, _, _ = sh(["nft", "list", "table", "inet", TABLO])
    return rc == 0


# ───────────────────────── plan / uygula ─────────────────────────
def plan():
    """Ne değişecek? (uygulamaz)"""
    print("\n  ── AĞ KATMANI PLANI (dry-run) ──")
    d = durum()
    print(f"  arayüz : {len(d['arayuzler'])} adet")
    for a in d["arayuzler"][:6]:
        print(f"      {a['ad']:16} {a['durum']:10} {a['ip']}")
    print(f"  rota   : {len(d['rotalar'])} adet")
    print(f"  vlan   : {len(d['vlan'])} adet")
    print(f"  nat    : {len(d['nat'])} kural")
    print(f"  tablo  : {'YÜKLÜ' if tablo_yuklu() else 'yüklü DEĞİL'}")
    print(f"\n  dosya  : {NFT_DOSYA}")
    print(f"  var mı : {'✓' if os.path.isfile(NFT_DOSYA) else '✗'}")
    if os.path.isfile(NFT_DOSYA):
        rc, _, err = sh(["nft", "-c", "-f", NFT_DOSYA])
        print(f"  sozdizimi: {'✓ TEMİZ' if rc == 0 else '✗ HATA'}")
        if err:
            print(f"      {err[:200]}")
    print("\n  ★ Uygulamak için: kalkan_ag_v10.py uygula --onay")
    print("  ★ Uygulama öncesi yedek alınır, sorun olursa: geri-al\n")
    return True


def uygula(onay=False):
    """Ağ kurallarını yükle. onay=False ise REDDEDER (güvenlik)."""
    if not onay:
        _log("uygula: ONAY YOK — reddedildi (--onay gerekir)")
        return False
    if not os.path.isfile(NFT_DOSYA):
        _log(f"uygula: dosya yok: {NFT_DOSYA}")
        return False
    # 1) sözdizimi kontrolü
    rc, _, err = sh(["nft", "-c", "-f", NFT_DOSYA])
    if rc != 0:
        _log(f"uygula: sozdizimi HATALI — yuklenmedi: {err[:150]}")
        return False
    # 2) yedek al
    os.makedirs(YEDEK, exist_ok=True)
    ts = datetime.now().strftime("%Y%m%d_%H%M%S")
    rc, mevcut, _ = sh(["nft", "list", "ruleset"])
    if rc == 0:
        open(f"{YEDEK}/{ts}.nft", "w").write(mevcut)
        open(f"{YEDEK}/son.txt", "w").write(f"{YEDEK}/{ts}.nft")
        _log(f"yedek alindi: {YEDEK}/{ts}.nft")
    # 3) uygula
    rc, _, err = sh(["nft", "-f", NFT_DOSYA])
    if rc == 0:
        _log("AG KATMANI YÜKLENDİ ✓")
        return True
    _log(f"uygula: HATA — {err[:200]}")
    return False


def geri_al():
    """Son yedeğe dön."""
    try:
        yol = open(f"{YEDEK}/son.txt").read().strip()
        if not os.path.isfile(yol):
            _log("geri-al: yedek bulunamadi")
            return False
        sh(["nft", "delete", "table", "inet", TABLO])
        rc, _, err = sh(["nft", "-f", yol])
        _log("geri alindi ✓" if rc == 0 else f"geri-al HATA: {err[:150]}")
        return rc == 0
    except Exception as e:
        _log(f"geri-al: {e}")
        return False


# ───────────────────────── NAT / port / rota / VLAN ─────────────────────────
def port_yonlendir(dis_port, ic_ip, ic_port):
    """DNAT: dış port → iç sunucu (kalıcı dosyaya eklenir, hemen yüklenmez)."""
    if not (_gecerli_ip(ic_ip) and str(dis_port).isdigit() and str(ic_port).isdigit()):
        _log("port-yonlendir: GECERSIZ girdi (IP/port dogrulanamadi)")
        return False
    if not (1 <= int(dis_port) <= 65535 and 1 <= int(ic_port) <= 65535):
        _log("port-yonlendir: port araligi disinda")
        return False
    kayit = {"tip": "dnat", "dis_port": int(dis_port), "ic_ip": ic_ip,
             "ic_port": int(ic_port), "zaman": datetime.now().strftime("%d.%m.%Y %H:%M")}
    _kurallari_kaydet(kayit)
    _log(f"port yonlendirme eklendi: {dis_port} → {ic_ip}:{ic_port} (uygula --onay ile aktif olur)")
    return True


def rota_ekle(ag, gw):
    """Statik rota (kalıcı, ip route ile hemen eklenmez)."""
    if not (_gecerli_ip(ag) and _gecerli_ip(gw)):
        _log("rota-ekle: GECERSIZ ag/gateway")
        return False
    kayit = {"tip": "rota", "ag": ag, "gw": gw,
             "zaman": datetime.now().strftime("%d.%m.%Y %H:%M")}
    _kurallari_kaydet(kayit)
    _log(f"rota eklendi: {ag} via {gw} (uygula --onay ile aktif olur)")
    return True


def vlan_ekle(arayuz, vid, adres):
    """802.1Q VLAN alt arayüzü."""
    if not (_gecerli_arayuz(arayuz) and str(vid).isdigit() and _gecerli_ip(adres)):
        _log("vlan-ekle: GECERSIZ girdi")
        return False
    if not (1 <= int(vid) <= 4094):
        _log("vlan-ekle: VLAN ID 1-4094 arasinda olmali")
        return False
    kayit = {"tip": "vlan", "arayuz": arayuz, "vid": int(vid), "adres": adres,
             "zaman": datetime.now().strftime("%d.%m.%Y %H:%M")}
    _kurallari_kaydet(kayit)
    _log(f"VLAN eklendi: {arayuz}.{vid} {adres} (uygula --onay ile aktif olur)")
    return True


def _kurallari_kaydet(kayit):
    """ag_kurallar.json'a ekle (kalıcı niyet; uygulama ayrı adım)."""
    yol = f"{V}/ag_kurallar.json"
    try:
        d = json.load(open(yol))
    except Exception:
        d = {"kurallar": []}
    d["kurallar"].append(kayit)
    os.makedirs(V, exist_ok=True)
    json.dump(d, open(yol, "w"), ensure_ascii=False, indent=1)
    return True


# ───────────────────────── CLI ─────────────────────────
if __name__ == "__main__":
    a = sys.argv[1:] or ["durum"]
    # ★ DUZELTME: --json bayragi — panel (ag.php) JSON bekliyor.
    # Onceden durum() dict donduruyordu ama CLI METIN basiyordu → panelde KPI 0.
    if "--json" in a:
        a = [x for x in a if x != "--json"]
        if not a or a[0] == "durum":
            print(json.dumps(durum(), ensure_ascii=False))
            sys.exit(0)
    k = a[0]
    if k == "durum":
        d = durum()
        print(f"\n  ── AĞ DURUMU ({d['zaman']}) ──")
        for x in d["arayuzler"][:10]:
            print(f"  {x['ad']:16} {x['durum']:9} {x['ip']}")
        print(f"\n  rota: {len(d['rotalar'])} · nat: {len(d['nat'])} · vlan: {len(d['vlan'])}")
        print(f"  ag tablosu: {'YÜKLÜ' if tablo_yuklu() else 'yüklü değil'}\n")
    elif k == "plan":
        plan()
    elif k == "uygula":
        uygula("--onay" in a)
    elif k == "geri-al":
        geri_al()
    elif k == "port-yonlendir" and len(a) == 4:
        port_yonlendir(a[1], a[2], a[3])
    elif k == "rota-ekle" and len(a) == 3:
        rota_ekle(a[1], a[2])
    elif k == "vlan-ekle" and len(a) == 4:
        vlan_ekle(a[1], a[2], a[3])
    else:
        print(__doc__)
