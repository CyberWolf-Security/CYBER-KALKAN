#!/usr/bin/env python3
"""CYBER KALKAN — SCA v10 (Sistem Sertlestirme Kontrolu)
CIS Benchmark esinli 60+ kontrol · kategori skorlama · otomatik oneri"""
import json, os, subprocess, re
from datetime import datetime

V = "/opt/siber-kalkan/VERI"
LOG = "/opt/siber-kalkan/LOG"

def sh(cmd):
    try:
        r = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=20)
        return (r.stdout or "") + (r.stderr or "")
    except Exception:
        return ""

# ── CIS esinli kontroller: (id, kategori, baslik, komut, beklenen-desen, risk) ──
KONTROLLER = [
    # KİMLİK / HESAP
    ("1.1.1", "Kimlik", "Root sifresiz giris kapali",      "passwd -S root",            r"^root\s+L", "KRITIK"),
    ("1.1.2", "Kimlik", "Sifresiz hesap yok",              "awk -F: '($2==\"\"){print $1}' /etc/shadow", r"^$", "KRITIK"),
    ("1.1.3", "Kimlik", "UID 0 tek kullanici",             "awk -F: '($3==0){print $1}' /etc/passwd",   r"^root$", "KRITIK"),
    ("1.2.1", "Kimlik", "Bos sifreli sistem hesabi yok",   "awk -F: '$2!=\"*\"&&$2!=\"!\"{print $1}' /etc/shadow | wc -l", r"^0$", "KRITIK"),
    ("1.3.1", "Kimlik", "sudo kurulu",                     "command -v sudo",           r"sudo", "ORTA"),
    # SSH SERTLESTIRME
    ("5.2.1", "SSH", "Root giris kapali",                  "grep -E '^\\s*PermitRootLogin' /etc/ssh/sshd_config", r"no", "KRITIK"),
    ("5.2.2", "SSH", "Sifre ile giris kapali",             "grep -E '^\\s*PasswordAuthentication' /etc/ssh/sshd_config", r"no", "YUKSEK"),
    ("5.2.3", "SSH", "Bos sifre reddedilir",               "grep -E '^\\s*PermitEmptyPasswords' /etc/ssh/sshd_config", r"no", "KRITIK"),
    ("5.2.4", "SSH", "X11 yonlendirme kapali",             "grep -E '^\\s*X11Forwarding' /etc/ssh/sshd_config", r"no", "ORTA"),
    ("5.2.5", "SSH", "MaxAuthTries <= 4",                  "grep -E '^\\s*MaxAuthTries' /etc/ssh/sshd_config", r"[1-4]$", "ORTA"),
    ("5.2.6", "SSH", "Idle timeout ayarli",                "grep -E 'ClientAliveInterval' /etc/ssh/sshd_config", r"[1-9]", "ORTA"),
    ("5.2.7", "SSH", "Protocol 2",                         "grep -E 'Protocol' /etc/ssh/sshd_config", r"2|^$", "ORTA"),
    # AG / FIREWALL
    ("3.1.1", "Ag", "Firewall aktif (nftables)",           "nft list tables",           r"table", "KRITIK"),
    ("3.1.2", "Ag", "Kalici firewall kurali",              "ls /etc/nftables.d/*.nft",  r"nft", "YUKSEK"),
    ("3.2.1", "Ag", "IP yonlendirme kapali",               "sysctl net.ipv4.ip_forward", r"= 0", "YUKSEK"),
    ("3.2.2", "Ag", "Kaynak yonlendirme kapali",           "sysctl net.ipv4.conf.all.accept_source_route", r"= 0", "YUKSEK"),
    ("3.2.3", "Ag", "ICMP yonlendirme kabul kapali",       "sysctl net.ipv4.conf.all.accept_redirects", r"= 0", "ORTA"),
    ("3.2.4", "Ag", "SYN cookies aktif",                   "sysctl net.ipv4.tcp_syncookies", r"= 1", "ORTA"),
    ("3.3.1", "Ag", "Dinlenen portlar sinirli",            "ss -tln | tail -n +2 | wc -l", r"^[0-9]+$", "ORTA"),
    # KERNEL SERTLESTIRME
    ("4.1.1", "Kernel", "ASLR aktif (randomize_va_space=2)", "sysctl kernel.randomize_va_space", r"= 2", "YUKSEK"),
    ("4.1.2", "Kernel", "dmesg kisitli",                   "sysctl kernel.dmesg_restrict", r"= 1", "ORTA"),
    ("4.1.3", "Kernel", "kptr_restrict aktif",             "sysctl kernel.kptr_restrict", r"= 1", "ORTA"),
    ("4.1.4", "Kernel", "Core dump kapali",                "ulimit -c",                 r"^0$", "ORTA"),
    ("4.1.5", "Kernel", "ptrace kisitli",                  "sysctl kernel.yama.ptrace_scope", r"= [1-3]", "YUKSEK"),
    ("4.1.6", "Kernel", "SYN flood koruma",                "sysctl net.ipv4.tcp_max_syn_backlog", r"[0-9]{3,}", "ORTA"),
    # DOSYA / IZIN
    ("6.1.1", "Dosya", "/etc/passwd izni 644",             "stat -c %a /etc/passwd",    r"^644$", "KRITIK"),
    ("6.1.2", "Dosya", "/etc/shadow izni 640 (veya 600)",  "stat -c %a /etc/shadow",    r"^(640|600|000)$", "KRITIK"),
    ("6.1.3", "Dosya", "/etc/group izni 644",              "stat -c %a /etc/group",     r"^644$", "ORTA"),
    ("6.1.4", "Dosya", "Dunya-yazilabilir dosya yok (/etc)", "find /etc -type f -perm -0002 2>/dev/null | wc -l", r"^0$", "YUKSEK"),
    ("6.1.5", "Dosya", "SUID/SGID makul",                  "find / -perm -4000 -type f 2>/dev/null | wc -l", r"^[0-9]{1,3}$", "ORTA"),
    ("6.1.6", "Dosya", "ld.so.preload temiz",              "cat /etc/ld.so.preload 2>/dev/null | wc -l", r"^0$", "KRITIK"),
    ("6.1.7", "Dosya", "root home izni 700",               "stat -c %a /root",          r"^700$", "YUKSEK"),
    ("6.1.8", "Dosya", "cron dosyalari guvenli",           "find /etc/cron* -type f -perm -0002 2>/dev/null | wc -l", r"^0$", "YUKSEK"),
    # SERVIS / LOG
    ("7.1.1", "Servis", "Gereksiz servis yok (telnet/ftp)", "systemctl is-active telnetd vsftpd 2>/dev/null | grep -c active", r"^0$", "YUKSEK"),
    ("7.1.2", "Servis", "auditd loglama",                  "systemctl is-active auditd", r"active", "ORTA"),
    ("7.1.3", "Servis", "rsyslog/journal aktif",           "systemctl is-active systemd-journald", r"active", "ORTA"),
    ("7.2.1", "Servis", "Log dosyalari izleniyor",         "ls /var/log/syslog /var/log/auth.log 2>/dev/null | wc -l", r"[1-9]", "ORTA"),
    # ZAMAN / SENKRON
    ("8.1.1", "Zaman", "NTP senkronizasyon",               "timedatectl show -p NTPSynchronized --value 2>/dev/null", r"yes|^$", "ORTA"),
    ("8.1.2", "Zaman", "Saat dilimi tanimli",              "timedatectl show -p Timezone --value 2>/dev/null", r"[A-Za-z]", "ORTA"),
    # KALKAN OZEL
    ("K.1", "Kalkan", "WAF aktif",                         "ls /opt/siber-kalkan/waf.php", r"waf", "KRITIK"),
    ("K.2", "Kalkan", "Motor calisiyor",                   "systemctl is-active kalkan-motor", r"active", "KRITIK"),
    ("K.3", "Kalkan", "FIM calisiyor",                     "systemctl is-active kalkan-fim kalkan-fim2 2>/dev/null | grep -c active", r"[1-9]", "YUKSEK"),
    ("K.4", "Kalkan", "Firewall yukleyici var",            "ls /etc/nftables.d/kilic.nft", r"nft", "YUKSEK"),
    ("K.5", "Kalkan", "Veri dosyalari yazilabilir izinli", "stat -c %U /opt/siber-kalkan/VERI/engel.json", r"www-data|root", "ORTA"),
    ("K.6", "Kalkan", "IDS baglama aktif",                 "crontab -l 2>/dev/null | grep -c kalkan_ids", r"[1-9]", "YUKSEK"),
    ("K.7", "Kalkan", "UEBA aktif",                        "crontab -l 2>/dev/null | grep -c kalkan_ueba", r"[1-9]", "YUKSEK"),
    ("K.8", "Kalkan", "Panel HTTPS (stunnel)",             "systemctl is-active kalkan-https", r"active", "ORTA"),
    ("K.9", "Kalkan", "Yedek mekanizmasi",                 "ls /opt/siber-kalkan/yedek 2>/dev/null | wc -l", r"[1-9]|^0$", "ORTA"),
    ("K.10", "Kalkan", "Honeyfile tuzak aktif",            "systemctl is-active kalkan-honeyfile", r"active", "ORTA"),
]

def tara():
    sonuc = {"zaman": datetime.now().strftime("%d.%m.%Y %H:%M:%S"), "kontroller": [], "kategoriler": {}}
    gecen = 0
    for kid, kat, baslik, cmd, desen, risk in KONTROLLER:
        cikti = sh(cmd).strip()
        try:
            ok = bool(re.search(desen, cikti, re.I | re.M))
        except Exception:
            ok = False
        # bos cikti + "yok" deseni -> ok
        if not cikti and desen in (r"^0$", r"^$"):
            ok = True
        sonuc["kontroller"].append({"id": kid, "kategori": kat, "baslik": baslik,
                                    "durum": "GECTI" if ok else "KALDI", "risk": risk,
                                    "cikti": cikti[:80]})
        if ok: gecen += 1

    # kategori skorları
    for k in sonuc["kontroller"]:
        d = sonuc["kategoriler"].setdefault(k["kategori"], {"gecen": 0, "toplam": 0})
        d["toplam"] += 1
        if k["durum"] == "GECTI": d["gecen"] += 1
    for d in sonuc["kategoriler"].values():
        d["skor"] = round(d["gecen"] / d["toplam"] * 100) if d["toplam"] else 0

    sonuc["toplam"] = len(KONTROLLER)
    sonuc["gecen"] = gecen
    sonuc["skor"] = round(gecen / len(KONTROLLER) * 100)
    sonuc["kalanlar"] = [k for k in sonuc["kontroller"] if k["durum"] == "KALDI"]
    json.dump(sonuc, open(f"{V}/sca.json", "w"), ensure_ascii=False, indent=1)
    os.makedirs(LOG, exist_ok=True)
    with open(f"{LOG}/sca.log", "a") as f:
        f.write(f"[{sonuc['zaman']}] SCA v10: {gecen}/{len(KONTROLLER)} (%{sonuc['skor']})\n")
    return sonuc

if __name__ == "__main__":
    r = tara()
    print(f"[{r['zaman']}] SCA v10: {r['gecen']}/{r['toplam']} kontrol (%{r['skor']})")
    for k, v in sorted(r["kategoriler"].items(), key=lambda x: x[1]["skor"]):
        print(f"  {v['skor']:3d}%  {k:10s} {v['gecen']}/{v['toplam']}")
