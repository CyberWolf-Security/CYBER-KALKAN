#!/bin/bash
B=/opt/siber-kalkan; V=$B/VERI; mkdir -p $B/LOG $V
MAKINE=$(hostname)
OLAY=$(tail -200 /var/log/nginx/access.log 2>/dev/null | grep -icE "(union.*select|<script|\.\./\.\.|/\.env|wp-login|sqlmap)" | head -1)
OLAY=${OLAY:-0}
BAG=$(ss -tn state established 2>/dev/null | awk 'NR>1{print $5}' | cut -d: -f1 | sort -u | grep -vcE '^(127\.|::|10\.|192\.168\.)')
cat > $V/ajan_${MAKINE}.json <<EOF
{
 "makine": "${MAKINE}",
 "zaman": "$(date '+%d.%m.%Y %H:%M:%S')",
 "baglanti_ips": ${BAG:-0},
 "suspicious_events": ${OLAY},
 "uptime": "$(uptime -p 2>/dev/null | head -c 40)",
 "yuk": "$(cut -d' ' -f1-3 /proc/loadavg)"
}
EOF
echo "[$(date '+%d.%m.%Y %H:%M:%S')] ajan: $MAKINE - $OLAY olay" >> $B/LOG/ajan.log
