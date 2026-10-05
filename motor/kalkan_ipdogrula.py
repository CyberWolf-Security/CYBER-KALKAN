#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CYBER KALKAN — IP DOGRULAMA (guvenlik yardimcisi)
==================================================
★ B-02 DUZELTMESI: Komut enjeksiyonunu onler.
Disariden (engel.json, WAF, log, API) gelen IP/deger shell komutuna
girmeden ONCE buradan gecirilmelidir.

Kullanim:
    from kalkan_ipdogrula import gecerli_ip, guvenli_arg
    if not gecerli_ip(ip): return          # IP degilse isleme alma
    subprocess.run(["nft", "add", "element", ..., ip, "}"])   # liste formu
"""
import ipaddress
import re

# Shell'de ozel anlamli karakterler (liste-argumanda da savunma amacli yasak)
TEHLIKELI = re.compile(r"[;&|`$(){}<>\n\r\t\"'\\*?!~\[\]#]")


def gecerli_ip(deger):
    """Gecerli IPv4/IPv6 adresi mi? (True/False)"""
    if not deger or not isinstance(deger, str):
        return False
    d = deger.strip()
    if len(d) > 45:                     # IPv6 max 45 karakter
        return False
    try:
        ipaddress.ip_address(d)
    except ValueError:
        return False
    # ek savunma: tehlikeli karakter hic olmamali
    return not TEHLIKELI.search(d)


# ★ B-22 DUZELTMESI: RFC-5737 belgeleme (test) adresleri ve varsayilan/dokumantasyon
# araliklari engel listesine GIRMEMELI — gercek dunyada yonlendirilemez,
# listeyi kirletir ve "canli tespit" yanilsamasi yaratir.
_BELGELEME = [
    "203.0.113.", "198.51.100.", "192.0.2.",      # RFC 5737 (TEST-NET-1/2/3)
    "192.0.0.", "198.18.", "198.19.",               # RFC 6890 ozel kullanim
]


def belgeleme_ip(ip):
    """RFC-5737 belgeleme / ozel kullanim adresi mi (engellenmemeli)"""
    s = str(ip).strip()
    for p in _BELGELEME:
        if s.startswith(p):
            return True
    return False


def ozel_ip(deger):
    """Ozel/ayrilmis IP mi (localhost, RFC1918, link-local)? — engellenmemeli"""
    try:
        a = ipaddress.ip_address(deger.strip())
    except ValueError:
        return True                     # gecersizse guvenli tarafta kal
    return (a.is_loopback or a.is_private or a.is_link_local or
            a.is_multicast or a.is_reserved or a.is_unspecified)


def engellenebilir(deger, beyaz=None):
    """Engel listesine EKLENEBILIR mi? (gecerli + ozel degil + belgeleme degil + beyaz degil)"""
    if not gecerli_ip(deger):
        return False, "gecersiz IP"
    if ozel_ip(deger):
        return False, "ozel/ayrilmis IP"
    # ★ B-22: RFC-5737 belgeleme / ozel kullanim adresleri engellenemez
    if belgeleme_ip(deger):
        return False, "belgeleme adresi"
    if beyaz and deger.strip() in beyaz:
        return False, "beyaz listede"
    return True, "ok"


def guvenli_arg(deger):
    """Shell'e arguman olarak verilecek degeri temizle (son savunma hatti)."""
    return TEHLIKELI.sub("", str(deger)).strip()


if __name__ == "__main__":
    import sys
    for x in (sys.argv[1:] or ["1.2.3.4", "127.0.0.1", "8.8.8.8; rm -rf /", "abc"]):
        print(f"  {x:28} gecerli={gecerli_ip(x)}  ozel={ozel_ip(x)}  engellenebilir={engellenebilir(x)[0]}")
