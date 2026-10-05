#!/usr/bin/env python3
"""CYBER KALKAN — PAKET D: CLUSTER/HA + INDEXER (SQLite)"""
import json, os, sqlite3, subprocess, socket, time
from datetime import datetime
B="/opt/siber-kalkan"; V=f"{B}/VERI"
KAYIT=[]

# ===== 1) CLUSTER / HA (coklu merkez) =====
print("[1] Cluster / HA")
def yerel_ip():
    try:
        s=socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8",80)); ip=s.getsockname()[0]; s.close(); return ip
    except Exception: return "127.0.0.1"

CLUSTER = {
 "guncelleme": datetime.now().strftime("%d.%m.%Y %H:%M"),
 "mod": "tek-merkez",  # tek-merkez | master | worker
 "dugumler": [
   {"ad":"kalkan-merkez","rol":"MASTER","ip":yerel_ip(),"port":8890,
    "durum":"aktif","son_gorulme":datetime.now().strftime("%d.%m.%Y %H:%M:%S")}
 ],
 "heartbeat_sn": 30,
 "failover": "manuel",  # manuel | otomatik
 "senkron_hedef": [],   # worker IP'leri
 "yuk_denge": "yok"
}
json.dump(CLUSTER, open(f"{V}/cluster.json","w",encoding="utf-8"), indent=1, ensure_ascii=False)

# HA izleme scripti (dugum sagligi)
HA_SCRIPT = '''#!/usr/bin/env python3
# CYBER KALKAN - HA IZLEME (dugum sagligi + failover)
import json, subprocess, socket, time
from datetime import datetime
V="/opt/siber-kalkan/VERI"
def oku(n,d=None):
    try: return json.load(open(f"{V}/{n}.json",encoding="utf-8"))
    except Exception: return d or {}
def yaz(n,v): json.dump(v,open(f"{V}/{n}.json","w",encoding="utf-8"),indent=1,ensure_ascii=False)

c=oku("cluster",{"dugumler":[]})
for d in c.get("dugumler",[]):
    ip=d.get("ip"); port=d.get("port",8890)
    try:
        s=socket.socket(); s.settimeout(3); s.connect((ip,port)); s.close()
        d["durum"]="aktif"; d["son_gorulme"]=datetime.now().strftime("%d.%m.%Y %H:%M:%S")
    except Exception:
        if d.get("rol")=="MASTER":
            d["durum"]="KRITIK (merkez erisilemez)"
        else:
            d["durum"]="erisilemez"
c["guncelleme"]=datetime.now().strftime("%d.%m.%Y %H:%M")
yaz("cluster",c)

# Yerel servis sagligi
saglik={}
for svc in ["kalkan-panel","kalkan-motor","kalkan-fim","kalkan-aktif","suricata"]:
    r=subprocess.run(["systemctl","is-active",svc],capture_output=True,text=True)
    saglik[svc]=r.stdout.strip()
yaz("ha_saglik",{"guncelleme":datetime.now().strftime("%d.%m.%Y %H:%M"),"servisler":saglik})
'''
open(f"{B}/MOTOR/kalkan_ha.py","w",encoding="utf-8").write(HA_SCRIPT)
os.chmod(f"{B}/MOTOR/kalkan_ha.py",0o755)
KAYIT.append(("cluster","aktif (tek-merkez modu, HA izleme hazir)"))
print(f"  merkez IP: {yerel_ip()} | HA izleme: hazir")

# ===== 2) INDEXER (SQLite — hizli arama, olcek) =====
print("[2] Indexer (SQLite)")
DB=f"{B}/VERI/kalkan.db"
con=sqlite3.connect(DB)
cur=con.cursor()
# Tablolar
cur.execute("""CREATE TABLE IF NOT EXISTS olaylar(
  id INTEGER PRIMARY KEY AUTOINCREMENT, zaman TEXT, ip TEXT, kural INTEGER,
  seviye TEXT, ad TEXT, mitre TEXT, puan INTEGER, kaynak TEXT)""")
cur.execute("""CREATE TABLE IF NOT EXISTS engel(
  id INTEGER PRIMARY KEY AUTOINCREMENT, ip TEXT UNIQUE, puan INTEGER,
  kaynak TEXT, zaman TEXT)""")
cur.execute("""CREATE TABLE IF NOT EXISTS fim(
  id INTEGER PRIMARY KEY AUTOINCREMENT, zaman TEXT, dosya TEXT, olay TEXT, ozet TEXT)""")
cur.execute("""CREATE TABLE IF NOT EXISTS waf(
  id INTEGER PRIMARY KEY AUTOINCREMENT, zaman TEXT, ip TEXT, kural INTEGER, yol TEXT)""")
# Indeksler (hizli arama)
cur.execute("CREATE INDEX IF NOT EXISTS ix_olay_ip ON olaylar(ip)")
cur.execute("CREATE INDEX IF NOT EXISTS ix_olay_zaman ON olaylar(zaman)")
cur.execute("CREATE INDEX IF NOT EXISTS ix_olay_seviye ON olaylar(seviye)")
cur.execute("CREATE INDEX IF NOT EXISTS ix_engel_ip ON engel(ip)")
cur.execute("CREATE INDEX IF NOT EXISTS ix_fim_dosya ON fim(dosya)")
con.commit()
print(f"  tablolar: 4 + 5 indeks")

# Mevcut JSON verilerini index'e aktar
def aktar():
    n={"olay":0,"engel":0,"fim":0}
    # olaylar
    try:
        o=json.load(open(f"{V}/olaylar.json"))
        for x in o.get("olaylar",[]):
            try:
                cur.execute("INSERT INTO olaylar(zaman,ip,kural,seviye,ad,mitre,puan,kaynak) VALUES(?,?,?,?,?,?,?,?)",
                  (x.get("zaman",""),x.get("ip",""),x.get("kural"),x.get("seviye"),x.get("ad",""),
                   x.get("mitre",""),x.get("puan"),x.get("kaynak","")))
                n["olay"]+=1
            except Exception: pass
    except Exception: pass
    # engel
    try:
        e=json.load(open(f"{V}/engel.json"))
        for x in e.get("liste",[]):
            try:
                cur.execute("INSERT OR IGNORE INTO engel(ip,puan,kaynak,zaman) VALUES(?,?,?,?)",
                  (x.get("ip"),x.get("puan"),x.get("kaynak"),x.get("zaman","")))
                n["engel"]+=1
            except Exception: pass
    except Exception: pass
    # fim
    try:
        f=json.load(open(f"{V}/fim.json"))
        for x in f.get("degisimler",[]):
            cur.execute("INSERT INTO fim(zaman,dosya,olay,ozet) VALUES(?,?,?,?)",
              (x.get("zaman",""),x.get("dosya",""),x.get("olay",""),str(x.get("ozet",""))[:200]))
            n["fim"]+=1
    except Exception: pass
    con.commit()
    return n

n=aktar()
# Istatistik
cur.execute("SELECT COUNT(*) FROM olaylar"); olay_s=cur.fetchone()[0]
cur.execute("SELECT COUNT(*) FROM engel"); engel_s=cur.fetchone()[0]
cur.execute("SELECT COUNT(*) FROM fim"); fim_s=cur.fetchone()[0]
con.close()
KAYIT.append(("indexer", f"olay={olay_s} engel={engel_s} fim={fim_s}"))
print(f"  index: olay={olay_s} engel={engel_s} fim={fim_s}")
print(f"  DB boyut: {os.path.getsize(DB)//1024} KB")

# Indexer API (hizli arama)
IDX_PHP = '''<?php
/* INDEXER API — SQLite hizli arama */
require __DIR__ . '/ortak.php';
kalkan_giris_gerekli();
header('Content-Type: application/json; charset=utf-8');
$db = new PDO("sqlite:/opt/siber-kalkan/VERI/kalkan.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$islem = $_GET["islem"] ?? "ara";
if ($islem === "ara") {
    $q = trim($_GET["q"] ?? ""); $sev = $_GET["seviye"] ?? ""; $limit = (int)($_GET["limit"] ?? 50);
    $sql = "SELECT * FROM olaylar WHERE 1=1"; $p = [];
    if ($q) { $sql .= " AND (ip LIKE ? OR ad LIKE ?)"; $p[] = "%$q%"; $p[] = "%$q%"; }
    if ($sev) { $sql .= " AND seviye = ?"; $p[] = $sev; }
    $sql .= " ORDER BY id DESC LIMIT $limit";
    $st = $db->prepare($sql); $st->execute($p);
    exit(json_encode(["durum"=>"ok","kayit"=>$st->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE));
}
if ($islem === "istatistik") {
    $r = [];
    foreach (["olaylar","engel","fim","waf"] as $t) {
        $r[$t] = (int)$db->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    }
    $r["seviye"] = $db->query("SELECT seviye, COUNT(*) c FROM olaylar GROUP BY seviye")->fetchAll(PDO::FETCH_KEY_PAIR);
    $r["top_ip"] = $db->query("SELECT ip, COUNT(*) c FROM olaylar GROUP BY ip ORDER BY c DESC LIMIT 10")->fetchAll(PDO::FETCH_KEY_PAIR);
    exit(json_encode(["durum"=>"ok","istatistik"=>$r], JSON_UNESCAPED_UNICODE));
}
if ($islem === "trend") {
    $r = $db->query("SELECT substr(zaman,1,10) gun, COUNT(*) c FROM olaylar GROUP BY gun ORDER BY gun DESC LIMIT 30")->fetchAll(PDO::FETCH_KEY_PAIR);
    exit(json_encode(["durum"=>"ok","trend"=>$r], JSON_UNESCAPED_UNICODE));
}
echo json_encode(["durum"=>"ok"]);
'''
# DÜZELTİLDİ: indexer.php HTML SAYFADIR, API kodu indexer_api.php'ye yazılır
open("/var/www/kalkan-panel/indexer_api.php","w",encoding="utf-8").write(IDX_PHP)
KAYIT.append(("indexer_api","/indexer.php"))

# ===== 3) SENKRON MODULU (JSON -> SQLite surekli) =====
print("[3] JSON->SQLite senkron")
SENK = '''#!/usr/bin/env python3
# JSON -> SQLite senkron (yeni kayitlari index'e ekle)
import json, sqlite3, os
V="/opt/siber-kalkan/VERI"; DB=f"{V}/kalkan.db"
con=sqlite3.connect(DB); cur=con.cursor()
def son_id(t):
    try: return cur.execute(f"SELECT MAX(id) FROM {t}").fetchone()[0] or 0
    except Exception: return 0
# Yeni olaylari ekle (son 200)
try:
    o=json.load(open(f"{V}/olaylar.json"))
    mevcut=cur.execute("SELECT COUNT(*) FROM olaylar").fetchone()[0]
    for x in o.get("olaylar",[])[mevcut:]:
        cur.execute("INSERT INTO olaylar(zaman,ip,kural,seviye,ad,mitre,puan,kaynak) VALUES(?,?,?,?,?,?,?,?)",
          (x.get("zaman",""),x.get("ip",""),x.get("kural"),x.get("seviye"),x.get("ad",""),
           x.get("mitre",""),x.get("puan"),x.get("kaynak","")))
    # engel
    e=json.load(open(f"{V}/engel.json"))
    for x in e.get("liste",[]):
        cur.execute("INSERT OR IGNORE INTO engel(ip,puan,kaynak,zaman) VALUES(?,?,?,?)",
          (x.get("ip"),x.get("puan"),x.get("kaynak"),x.get("zaman","")))
    # fim
    f=json.load(open(f"{V}/fim.json"))
    for x in f.get("degisimler",[])[mevcut:]:
        cur.execute("INSERT INTO fim(zaman,dosya,olay,ozet) VALUES(?,?,?,?)",
          (x.get("zaman",""),x.get("dosya",""),x.get("olay",""),str(x.get("ozet",""))[:200]))
    con.commit()
except Exception as e: pass
con.close()
'''
open(f"{B}/MOTOR/kalkan_indexer.py","w",encoding="utf-8").write(SENK)
KAYIT.append(("indexer_senkron","kalkan_indexer.py"))

json.dump({"guncelleme":datetime.now().strftime("%d.%m.%Y %H:%M"),"sonuc":KAYIT},
          open(f"{V}/paket_d.json","w",encoding="utf-8"), indent=1, ensure_ascii=False)
print("\n=== PAKET D TAMAM ===")
for k,v in KAYIT: print(f"  {k}: {v}")