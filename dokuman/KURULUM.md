# ⚙️ CYBER KALKAN — Kurulum

Sunucuya kurulum adımları. Ortalama süre: **~5 dakika**.

---

## 1. GEREKSİNİMLER

| Bileşen | Asgari |
|---|---|
| İşletim sistemi | Debian 12 / Ubuntu 22.04+ (Kali de uyumlu) |
| PHP | 8.0+ (nginx veya apache) |
| Python | 3.9+ |
| Web sunucu | nginx (önerilen) |
| Çekirdek | nftables destekli |
| RAM | 1 GB+ |
| Disk | 5 GB+ |

---

## 2. KLASÖR YAPISI OLUŞTUR

```bash
mkdir -p /srv/calkam/{panel,motor,veri,firewall,servis}
```

---

## 3. DOSYALARI KOPYALA

```bash
cp -r panel/*   /srv/calkam/panel/
cp -r motor/*   /srv/calkam/motor/
cp -r veri/*    /srv/calkam/veri/
cp -r firewall/* /srv/calkam/firewall/
cp -r servis/*  /etc/systemd/system/
```

---

## 4. İZİNLERİ AYARLA

```bash
chown -R www-data:www-data /srv/calkam/panel
chown -R www-data:www-data /srv/calkam/veri
chmod 664 /srv/calkam/veri/*.json
chmod 600 /srv/calkam/veri/mail_ayar.json   # varsa
```

> **Önemli:** `veri/*.json` dosyaları www-data tarafından **yazılabilir** olmalı,
> yoksa WAF olayları ve engeller kaydedilemez.

---

## 5. WAF YAPILANDIRMASI

WAF, web sunucusunun istekleri geçirmeden önce çalışması gerekir.

### nginx örneği
```nginx
server {
    listen 80;
    root /srv/calkam/panel;
    index index.php;

    # WAF'ı önce çalıştır
    location / {
        try_files $uri /waf-router.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        include fastcgi_params;
    }
}
```

---

## 6. FIREWALL (nftables)

```bash
# nftables kuralını yükle
cp firewall/kilic.nft /etc/nftables.d/
systemctl enable kalkan-fw-yukle.service
systemctl start kalkan-fw-yukle.service
```

---

## 7. SERVİSLERİ BAŞLAT

```bash
systemctl daemon-reload
for s in kalkan-panel kalkan-motor kalkan-fim kalkan-aktif kalkan-honeyfile; do
    systemctl enable $s
    systemctl start $s
done
```

**Kontrol:**
```bash
systemctl status kalkan-panel kalkan-motor
```

---

## 8. OTOMATİK GÖREVLER (cron)

```bash
crontab -e
```

Örnek satırlar:
```cron
* * * * *   /usr/bin/python3 /srv/calkam/motor/kalkan_motor.py     >> /srv/calkam/log/motor.log 2>&1
*/2 * * * * /usr/bin/python3 /srv/calkam/motor/kalkan_ids.py       >> /srv/calkam/log/ids.log 2>&1
*/5 * * * * /usr/bin/python3 /srv/calkam/motor/kalkan_fim_v10.py   >> /srv/calkam/log/fim.log 2>&1
*/15 * * * * /usr/bin/python3 /srv/calkam/motor/kalkan_ueba.py     >> /srv/calkam/log/ueba.log 2>&1
0 * * * *   /usr/bin/python3 /srv/calkam/motor/kalkan_sca_v10.py   >> /srv/calkam/log/sca.log 2>&1
```

---

## 9. İLK GİRİŞ

```
Adres : http://sunucu-ip:8890
Kullanıcı : admin
Şifre     : kalkan
```

> ⚠️ **İlk girişten sonra şifreyi HEMEN değiştir.** `kalkan` yalnızca kurulum
> içindir; canlıda bcrypt ile değiştirilmelidir.

---

## 10. İLK YAPILANDIRMA

### E-posta bildirimleri (opsiyonel)
`veri/mail_ayar.json` dosyasını doldur:
```json
{
  "brevo_api": "BURAYA_API_ANAHTARI",
  "smtp_sunucu": "mail.ornek.com",
  "smtp_port": 587,
  "smtp_kullanici": "bildirim@ornek.com",
  "smtp_sifre": "BURAYA_SIFRE"
}
```

### Otomatik engelleme
Panel → **Ayarlar** → "Otomatik Engel" tikini işaretle.

---

## 11. DOĞRULAMA TESTLERİ

```bash
# WAF çalışıyor mu? (403 beklenir)
curl -s -o /dev/null -w "%{http_code}" "http://localhost/?id=1' UNION SELECT"

# Panel ayakta mı?
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8890/

# Firewall kara liste
nft list set inet kilic kara
```

Beklenen: WAF `403` · Panel `302`/`200` · Firewall seti görünür.

---

## 12. SORUN GİDERME

| Sorun | Çözüm |
|---|---|
| WAF engellemiyor | nginx'te `waf-router.php` yönlendirmesi eksik |
| Olaylar kaydedilmiyor | `veri/*.json` izni www-data olmalı |
| Servis başlamıyor | `journalctl -u kalkan-motor` incele |
| Engeller kalıcı değil | `kalkan-fw-yukle.service` aktif mi? |
| Panel açılmıyor | PHP-FPM çalışıyor mu, soket yolu doğru mu? |

---

## 13. GÜNCELLEME

```bash
# Yedek al
python3 motor/kalkan_yedek_v10.py

# Dosyaları güncelle
cp -r yeni/panel/* /srv/calkam/panel/

# Koruma hash'ini yenile
python3 motor/kalkan_koruma.py --kaydet
python3 motor/kalkan_koruma.py --senkron

# Servisleri yeniden başlat
systemctl restart kalkan-panel kalkan-motor
```

---

## 14. KALDIRMA

```bash
for s in kalkan-panel kalkan-motor kalkan-fim kalkan-aktif kalkan-honeyfile; do
    systemctl stop $s; systemctl disable $s
done
rm -rf /srv/calkam
crontab -l | grep -v kalkan | crontab -
```

---

*CYBER KALKAN v1.0 · Kurulum Belgesi · 🐺 CYBERWOLF SECURITY*