#!/usr/bin/env python3
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
