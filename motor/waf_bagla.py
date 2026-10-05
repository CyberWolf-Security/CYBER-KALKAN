#!/usr/bin/env python3
"""CYBER KALKAN — WAF baglama (panel + hedef sayfalara)
Kullanim: python3 waf_bagla.py /var/www/kalkan-panel/ortak.php /var/www/lab-hedef/index.php
"""
import re, sys, os

WAF = os.environ.get("KALKAN_WAF_YOL", "/opt/siber-kalkan/waf.php")
EK = "\nrequire_once '" + WAF + "';  // CYBER KALKAN WAF"

def bagla(p):
    if not os.path.exists(p):
        return f"  - atlandi (yok): {p}"
    s = open(p, encoding="utf-8").read()
    if "siber-kalkan/waf.php" in s:
        return f"  = zaten bagli: {p}"
    m = re.search(r'(declare\s*\([^;]+\)\s*;)', s)
    if m:
        s = s.replace(m.group(1), m.group(1) + EK, 1)
    else:
        s = s.replace("<?php", "<?php" + EK, 1)
    open(p, "w", encoding="utf-8").write(s)
    return f"  + WAF baglandi: {p}"

if __name__ == "__main__":
    hedefler = sys.argv[1:] or ["/var/www/kalkan-panel/ortak.php", "/var/www/lab-hedef/index.php"]
    for h in hedefler:
        print(bagla(h))
