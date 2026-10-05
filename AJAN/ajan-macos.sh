#!/bin/bash
# CYBER KALKAN — macOS AJAN (launchd)
MERKEZ="${1:-http://192.168.1.4:8890}"
TOKEN="${2:-cyber-kalkan-2026}"
DIZIN="/usr/local/cyber-kalkan"
mkdir -p $DIZIN
cat > $DIZIN/ajan.sh <<'AJ'
#!/bin/bash
M="$1"; T="$2"
HOST=$(hostname); IP=$(ipconfig getifaddr en0 2>/dev/null || echo "0.0.0.0")
MODEL=$(sysctl -n hw.model 2>/dev/null)
OS=$(sw_vers -productVersion 2>/dev/null)
LOG=""
# Guvenlik olaylari
LOG+=$(log show --predicate 'eventMessage CONTAINS "failed" OR eventMessage CONTAINS "denied"' --last 2m 2>/dev/null | tail -50)
# Sistem
UP=$(uptime | sed 's/.*up //;s/,.*//')
LOAD=$(sysctl -n vm.loadavg 2>/dev/null)
DATA=$(python3 - <<PY
import json
print(json.dumps({"host":"$HOST","ip":"$IP","os":"macOS $OS","model":"$MODEL","uptime":"$UP","load":"$LOAD","log":"""$LOG"""[:2000]}))
PY
)
curl -s -X POST "$M/alici.php" -H "Content-Type: application/json" -H "X-Kalkan-Token: $T" -d "$DATA" >/dev/null 2>&1
# Config cek
curl -s "$M/ajan_config.php?token=$T" > $DIZIN/last_config.json 2>/dev/null
AJ
chmod +x $DIZIN/ajan.sh
cat > /Library/LaunchDaemons/com.cyberwolf.kalkan.plist <<PL
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
<key>Label</key><string>com.cyberwolf.kalkan</string>
<key>ProgramArguments</key><array><string>$DIZIN/ajan.sh</string><string>$MERKEZ</string><string>$TOKEN</string></array>
<key>StartInterval</key><integer>60</integer>
<key>RunAtLoad</key><true/>
</dict></plist>
PL
launchctl load /Library/LaunchDaemons/com.cyberwolf.kalkan.plist 2>/dev/null
echo "✓ macOS ajan kuruldu: $DIZIN/ajan.sh (60sn aralik)"
