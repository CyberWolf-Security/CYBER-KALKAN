#!/bin/bash
# CYBER KALKAN — Suricata IPS kurulum (Docker, izole)
# ★ Sisteme 'apt install suricata' YAPILMAZ; her sey konteynerde.
set -e
D="$(cd "$(dirname "$0")/.." && pwd)"
S="$D/suricata"

echo "=== 1. dizinler ==="
mkdir -p "$S/rules" "$S/logs"

echo "=== 2. kurallari indir (ET Open) ==="
if [ ! -f "$S/rules/suricata.rules" ]; then
  for U in \
    "https://rules.emergingthreats.net/open/suricata-7.0.3/emerging.rules.tar.gz" \
    "https://rules.emergingthreats.net/open/suricata-6.0/emerging.rules.tar.gz" ; do
    if curl -sfL --max-time 120 -o /tmp/et.tar.gz "$U"; then
      tar xzf /tmp/et.tar.gz -C /tmp/ 2>/dev/null
      cat /tmp/rules/*.rules > "$S/rules/suricata.rules" 2>/dev/null
      rm -rf /tmp/rules /tmp/et.tar.gz
      break
    fi
  done
fi
N=$(grep -c "^alert\|^drop" "$S/rules/suricata.rules" 2>/dev/null || echo 0)
echo "  kural sayisi: $N"

echo "=== 3. imaj ==="
docker pull jasonish/suricata:latest >/dev/null 2>&1 && echo "  ✓ suricata imaji hazir" || echo "  ! imaj cekilemedi"

echo "=== 4. konteyner (nfqueue, IDS baslangic) ==="
docker rm -f kalkan-suricata >/dev/null 2>&1 || true

cat > "$S/docker-compose.ips.yml" <<'YML'
services:
  suricata:
    image: jasonish/suricata:latest
    container_name: kalkan-suricata
    network_mode: host
    cap_add: [NET_ADMIN, NET_RAW, SYS_NICE]
    command: ["-q", "0:1", "-v"]
    volumes:
      # ★ DUZELTME: ':ro' KALDIRILDI — Suricata acilista config'e chown yapar,
      # read-only mount'ta "chown: Read-only file system" verip restart dongusune girer.
      - ./suricata.yaml:/etc/suricata/suricata.yaml
      - ./rules:/var/lib/suricata/rules
      - ./logs:/var/log/suricata
    restart: unless-stopped
YML

cd "$S"
docker compose -f docker-compose.ips.yml up -d 2>&1 | tail -3 | sed 's/^/  /'

echo ""
echo "=== 5. durum ==="
sleep 5
docker ps --filter name=kalkan-suricata --format "  {{.Names}} {{.Status}}"

echo ""
echo "BITTI"
echo "  IPS tablosunu YUKLEMEK icin : python3 motor/kalkan_ips_v10.py baslat --onay"
echo "  Durdurmak icin            : python3 motor/kalkan_ips_v10.py durdur"
