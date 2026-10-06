#!/bin/bash
# ============================================================
#  CYBER KALKAN AJAN KURULUMU (tek satir)
#  Kullanim: curl -s "http://MERKEZ:8890/ajan_al.sh" | bash -s "http://MERKEZ:8890" "TOKEN"
# ============================================================
set -e
MERKEZ="${1:-}"
TOKEN="${2:-}"
if [ -z "$MERKEZ" ] || [ -z "$TOKEN" ]; then
  echo "kullanim: bash -s \"http://MERKEZ:8890\" \"TOKEN\""
  exit 1
fi
command -v python3 >/dev/null 2>&1 || { echo "python3 gerekli"; exit 1; }

mkdir -p /opt/kalkan-ajan
cat > /opt/kalkan-ajan/kalkan_ajan.sh <<'AJAN_BETIK_EOF'
#!/bin/bash
# ============================================================
#  CYBER KALKAN AJAN — uzak cihazlara kurulur
#  Görev: log topla → merkeze gönder → engelleme komutu uygula
#  Kurulum:  curl -s http://MERKEZ:8890/ajan_al.sh | bash -s MERKEZ TOKEN
# ============================================================
MERKEZ="${1:-http://127.0.0.1:8890}"
TOKEN="${2:-}"
MAKS_TUR="${3:-0}"        # 0 = sonsuz (servis), >0 = test icin N tur
AD="$(hostname)"
ARALIK=5
TUR=0
LOG=/var/log/kalkan-ajan.log

logla(){ echo "[$(date '+%d.%m.%Y %H:%M:%S')] $*" >> "$LOG" 2>/dev/null; }

# engelleme: nft varsa nft, yoksa iptables
engelle(){
  local ip="$1"
  [ -z "$ip" ] && return
  if command -v nft >/dev/null 2>&1; then
    nft add table inet kalkan 2>/dev/null
    nft add set inet kalkan kara { type ipv4_addr\; flags timeout\; timeout 24h\; } 2>/dev/null
    nft add chain inet kalkan gir { type filter hook input priority -10\; policy accept\; } 2>/dev/null
    nft add rule inet kalkan gir ip saddr @kara drop 2>/dev/null
    nft add element inet kalkan kara { $ip } 2>/dev/null
  elif command -v iptables >/dev/null 2>&1; then
    iptables -C INPUT -s "$ip" -j DROP 2>/dev/null || iptables -I INPUT -s "$ip" -j DROP
  fi
  logla "ENGELLENDI: $ip"
}
coz(){
  local ip="$1"
  [ -z "$ip" ] && return
  nft delete element inet kalkan kara { $ip } 2>/dev/null
  iptables -D INPUT -s "$ip" -j DROP 2>/dev/null
  logla "COZULDU: $ip"
}

# toplanacak log satirlari (son ARALIK saniyede eklenen)
log_satirlari(){
  for f in /var/log/auth.log /var/log/syslog /var/log/nginx/access.log /var/log/apache2/access.log; do
    [ -f "$f" ] && tail -n 120 "$f" 2>/dev/null
  done | tail -n 400
}

# sistem bilgisi
sistem_bilgi(){
  printf '{"cpu":"%s","yuk":"%s","ram_mb":"%s","disk":"%s"}' \
    "$(nproc 2>/dev/null)" "$(cut -d' ' -f1 /proc/loadavg 2>/dev/null)" \
    "$(free -m 2>/dev/null | awk 'NR==2{print $3}')" \
    "$(df -h / 2>/dev/null | awk 'NR==2{print $5}')"
}

logla "ajan basladi: $AD -> $MERKEZ (her ${ARALIK}sn)"
GOREV_SONUC="[]"   # sonraki turda gonderilecek gorev sonuclari

while true; do
  # 1) log satirlarini JSON kacisli diziye cevir
  LOGJSON=$(log_satirlari | python3 -c "
import sys, json
satirlar = [s.rstrip() for s in sys.stdin if s.strip()]
print(json.dumps(satirlar[-200:], ensure_ascii=False))
" 2>/dev/null)
  [ -z "$LOGJSON" ] && LOGJSON='[]'

  GOVDE=$(printf '{"ad":"%s","sistem":%s,"loglar":%s,"gorev_sonuclar":%s}' "$AD" "$(sistem_bilgi)" "$LOGJSON" "$GOREV_SONUC")
  GOREV_SONUC="[]"

  # 2) merkeze gonder, komutlari al
  YANIT=$(curl -s -m 20 -X POST "$MERKEZ/ajan_kayit.php" \
      -H "X-Kalkan-Token: $TOKEN" -H "Content-Type: application/json" \
      -d "$GOVDE" 2>/dev/null)

  # 3) komutlari uygula
  if [ -n "$YANIT" ]; then
    KOMUTLAR=$(printf '%s' "$YANIT" | python3 -c "
import sys, json
try:
    d = json.load(sys.stdin)
    for k in d.get('komutlar', []):
        print(k.get('tip',''), k.get('ip',''))
except Exception:
    pass
" 2>/dev/null)
    while read -r TIP IP; do
      case "$TIP" in
        engelle) engelle "$IP" ;;
        coz)     coz "$IP" ;;
      esac
    done <<< "$KOMUTLAR"

    # 4) gorevleri al, calistir, sonuclari bir sonraki turda gonder
    if [ -f /opt/kalkan-ajan/gorev_isle.py ] || [ -f "$(dirname "$0")/gorev_isle.py" ]; then
      COZUC="$(dirname "$0")/gorev_isle.py"
      [ -f /opt/kalkan-ajan/gorev_isle.py ] && COZUC=/opt/kalkan-ajan/gorev_isle.py
      GS=$(printf '%s' "$YANIT" | python3 "$COZUC" 2>/dev/null)
      if [ -n "$GS" ] && [ "$GS" != "[]" ]; then
        GOREV_SONUC="$GS"
        logla "gorev sonuclari hazir (sonraki turda gonderilecek)"
      fi
    fi
  fi

  sleep "$ARALIK"
  TUR=$((TUR + 1))
  if [ "$MAKS_TUR" -gt 0 ] && [ "$TUR" -ge "$MAKS_TUR" ]; then
    logla "test turu tamamlandi ($TUR tur)"
    exit 0
  fi
done

AJAN_BETIK_EOF
cat > /opt/kalkan-ajan/gorev_isle.py <<'GOREV_COZUCU_EOF'
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
GOREV_COZUCU_EOF
chmod +x /opt/kalkan-ajan/kalkan_ajan.sh /opt/kalkan-ajan/gorev_isle.py

cat > /etc/systemd/system/kalkan-ajan.service <<UNIT
[Unit]
Description=CYBER KALKAN Uzak Ajan
After=network-online.target

[Service]
Type=simple
ExecStart=/opt/kalkan-ajan/kalkan_ajan.sh "$MERKEZ" "$TOKEN"
Restart=always
RestartSec=10
Nice=10

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
systemctl enable --now kalkan-ajan >/dev/null 2>&1
sleep 1
echo "✅ CYBER KALKAN ajani kuruldu ve baslatildi"
echo "   cihaz : $(hostname)"
echo "   merkez: $MERKEZ"
echo "   durum : $(systemctl is-active kalkan-ajan)"
