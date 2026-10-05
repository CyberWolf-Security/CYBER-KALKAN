#!/usr/bin/env python3
"""CYBER KALKAN — PAKET B: DOCKER/K8S + MERKEZI CONFIG + UPGRADE"""
import json, os, subprocess, re, glob
from datetime import datetime
B="/opt/siber-kalkan"; V=f"{B}/VERI"
KAYIT=[]

# ===== 1) DOCKER RUNTIME IZLEME =====
print("[1] Docker izleme")
def docker_tara():
    olaylar=[]
    try:
        # Konteynerler
        r=subprocess.run(["docker","ps","--format","{{.ID}}|{{.Image}}|{{.Status}}|{{.Names}}"],
                         capture_output=True,text=True,timeout=10)
        for line in r.stdout.strip().split("\n"):
            if not line: continue
            p=line.split("|")
            if len(p)>=4:
                olaylar.append({"tip":"konteyner","id":p[0],"imaj":p[1],"durum":p[2],"ad":p[3]})
        # Image'lar
        r=subprocess.run(["docker","images","--format","{{.Repository}}:{{.Tag}}|{{.Size}}"],
                         capture_output=True,text=True,timeout=10)
        imajlar=[l for l in r.stdout.strip().split("\n") if l]
        # Anomali: latest tag prod'da
        for im in imajlar:
            if ":latest" in im:
                olaylar.append({"tip":"uyari","mesaj":"latest tag kullaniliyor","imaj":im})
        # privileged konteyner
        r=subprocess.run(["docker","ps","-q"],capture_output=True,text=True,timeout=10)
        for cid in r.stdout.strip().split("\n"):
            if not cid: continue
            r2=subprocess.run(["docker","inspect","--format","{{.HostConfig.Privileged}}",cid],
                              capture_output=True,text=True,timeout=5)
            if "true" in r2.stdout.lower():
                olaylar.append({"tip":"KRITIK","mesaj":"privileged konteyner!","id":cid[:12]})
        return olaylar, len(imajlar)
    except Exception as e:
        return [{"tip":"hata","mesaj":str(e)}], 0

do, imaj_sayisi = docker_tara()
json.dump({"guncelleme":datetime.now().strftime("%d.%m.%Y %H:%M"),"konteynerler":do,"imaj":imaj_sayisi},
          open(f"{V}/docker.json","w",encoding="utf-8"), indent=1, ensure_ascii=False)
KAYIT.append(("docker", f"{len(do)} kayit, {imaj_sayisi} imaj"))
print(f"  konteyner kayit: {len(do)} | imaj: {imaj_sayisi}")

# ===== 2) KUBERNETES IZLEME =====
print("[2] K8s izleme")
def k8s_tara():
    olaylar=[]
    if not os.path.exists("/var/run/secrets/kubernetes.io") and not subprocess.run(["which","kubectl"],capture_output=True).returncode==0:
        return [{"tip":"bilgi","mesaj":"K8s ortami yok"}], 0
    try:
        r=subprocess.run(["kubectl","get","pods","-A","-o","json"],capture_output=True,text=True,timeout=15)
        if r.returncode==0:
            d=json.loads(r.stdout)
            for pod in d.get("items",[]):
                ad=pod["metadata"]["name"]; ns=pod["metadata"]["namespace"]
                spec=pod.get("spec",{})
                if spec.get("hostNetwork"): olaylar.append({"tip":"UYARI","mesaj":"hostNetwork","pod":ad})
                if spec.get("hostPID"): olaylar.append({"tip":"KRITIK","mesaj":"hostPID","pod":ad})
                for c in spec.get("containers",[]):
                    sc=c.get("securityContext",{})
                    if sc.get("privileged"): olaylar.append({"tip":"KRITIK","mesaj":"privileged pod","pod":ad})
        return olaylar, 1
    except Exception as e:
        return [{"tip":"hata","mesaj":str(e)}], 0

ko, k8s_var = k8s_tara()
json.dump({"guncelleme":datetime.now().strftime("%d.%m.%Y %H:%M"),"bulgular":ko,"k8s":bool(k8s_var)},
          open(f"{V}/k8s.json","w",encoding="utf-8"), indent=1, ensure_ascii=False)
KAYIT.append(("k8s", f"{len(ko)} bulgu"))
print(f"  K8s bulgu: {len(ko)}")

# ===== 3) MERKEZI AJAN CONFIG (agent.conf push) =====
print("[3] Merkezi ajan config")
MERKEZ_CONFIG = {
  "surum":"1.0","guncelleme":datetime.now().strftime("%d.%m.%Y %H:%M"),
  "toplama_aralik_sn":60,
  "izlenecek_yollar":["/var/log/auth.log","/var/log/syslog","/etc/passwd","/etc/shadow","/etc/crontab"],
  "fim_aktif":True,"fim_aralik_sn":15,
  "sistem_bilgisi":["cpu","ram","disk","yuk","surecler","portlar"],
  "moduller":["log","fim","sistem","guvenlik","rootkit"],
  "log_limit_satir":500,
  "acil_durum":False
}
json.dump(MERKEZ_CONFIG, open(f"{V}/ajan_config.json","w",encoding="utf-8"), indent=1, ensure_ascii=False)
KAYIT.append(("ajan_config","aktif"))
print("  ajan_config.json olusturuldu")

# Ajan config dagitim endpointi (alici.php'ye eklenir)
AJAN_CFG_PHP = '''<?php
/* AJAN CONFIG DAGITIMI — ajanlar merkezi config ceker */
require __DIR__ . '/ortak.php';
header('Content-Type: application/json; charset=utf-8');
$ayar = kalkan_oku("ayarlar", []);
$token = $_GET["token"] ?? $_SERVER["HTTP_X_KALKAN_TOKEN"] ?? "";
if ($token !== ($ayar["api_token"] ?? "")) {
    http_response_code(403); exit('{"durum":"hata","mesaj":"token gecersiz"}');
}
$cfg = kalkan_oku("ajan_config", []);
echo json_encode($cfg, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
'''
open("/var/www/kalkan-panel/ajan_config.php","w",encoding="utf-8").write(AJAN_CFG_PHP)
KAYIT.append(("ajan_config_endpoint","/ajan_config.php"))

# ===== 4) AJAN UPGRADE (uzaktan guncelleme) =====
print("[4] Ajan upgrade")
UPGRADE_PHP = '''<?php
/* AJAN UPGRADE — merkezden surum kontrolu + indirme */
require __DIR__ . '/ortak.php';
header('Content-Type: application/json; charset=utf-8');
$ayar = kalkan_oku("ayarlar", []);
$token = $_GET["token"] ?? $_SERVER["HTTP_X_KALKAN_TOKEN"] ?? "";
if ($token !== ($ayar["api_token"] ?? "")) { http_response_code(403); exit('{"durum":"hata"}'); }
$ajan_surum = $_GET["surum"] ?? "0.0";
$merkez_surum = "1.0";
$guncelle = version_compare($merkez_surum, $ajan_surum, ">");
echo json_encode([
    "durum" => "aktif",
    "ajan_surum" => $ajan_surum,
    "merkez_surum" => $merkez_surum,
    "guncelleme_var" => $guncelle,
    "indirme" => $guncelle ? "/dosyalar/ajan-kur.sh" : null
], JSON_UNESCAPED_UNICODE);
'''
open("/var/www/kalkan-panel/ajan_upgrade.php","w",encoding="utf-8").write(UPGRADE_PHP)
KAYIT.append(("ajan_upgrade","/ajan_upgrade.php"))

# ===== 5) SURUM TAKIBI =====
print("[5] Ajan surum takibi")
try:
    ajanlar=kalkan_cfg=json.load(open(f"{V}/ajanlar.json")) if os.path.exists(f"{V}/ajanlar.json") else {}
except Exception:
    ajanlar={}
json.dump({"surum":"1.0","toplam":len(ajanlar) if isinstance(ajanlar,dict) else 0,
           "guncelleme":datetime.now().strftime("%d.%m.%Y %H:%M")},
          open(f"{V}/surum.json","w",encoding="utf-8"), indent=1)
KAYIT.append(("surum","1.0"))

json.dump({"guncelleme":datetime.now().strftime("%d.%m.%Y %H:%M"),"sonuc":KAYIT},
          open(f"{V}/paket_b.json","w",encoding="utf-8"), indent=1, ensure_ascii=False)
print("\n=== PAKET B TAMAM ===")
for k,v in KAYIT: print(f"  {k}: {v}")