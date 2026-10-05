# ⚡ CYBER KALKAN — Özellikler

Sistemin tüm yetenekleri, modül modül ve özellik özellik.

---

## 🛡️ 1. WAF — Web Uygulama Güvenlik Duvarı

**Dosya:** `motor/waf.php` · **Kural:** 1002 · **Satır:** 344

### Saldırı Tespiti
| Kategori | Yakalanan |
|---|---|
| SQL Injection | `UNION SELECT`, `OR 1=1`, `'; DROP`, `SLEEP()`, `BENCHMARK()` |
| XSS | `<script>`, `onerror=`, `javascript:`, `<iframe>`, `<svg/onload>` |
| LFI / RFI | `../../../etc/passwd`, `php://filter`, `data://`, `expect://` |
| Komut Enjeksiyonu | `; cat /etc/passwd`, `|whoami`, `$(id)`, `` `ls` ``, `&&` |
| Şablon Enjeksiyonu | `{{7*7}}`, `${7*7}`, `<%= %>`, `#{7*7}` |
| NoSQL | `$ne`, `$gt`, `$where`, `$regex` |
| Dosya Yükleme | `.php.jpg`, `%00.php`, çift uzantı, `.phtml`, `.phar` |
| Yol Geçişi | `..%2f`, `%252e%252e`, Unicode kaçış |
| SSRF | `169.254.169.254` (bulut metadata), `file://`, `gopher://` |
| Başlık Enjeksiyonu | `\r\n` CRLF, `%0d%0a` |

### CVE Sanal Yama (imza tabanlı, yama olmadan korur)
```
Log4Shell      (CVE-2021-44228)   ${jndi:ldap://...}
Spring4Shell   (CVE-2022-22965)   class.module.classLoader
ProxyLogon     (CVE-2021-26855)   /ecp/, /owa/auth/
ThinkPHP RCE   (CVE-2018-20062)   /index.php?s=...
Laravel        (CVE-2021-3129)    _ignition/execute-solution
Shellshock     (CVE-2014-6271)    () { :; };
```

### 5 Katmanlı Çözümleme (Normalize)
Saldırganlar atlatmak için kodlarlar — WAF hepsini çözer:
```
Katman 1: URL kod çözme      %27%20UNION → ' UNION
Katman 2: HTML varlık         &#x27; &#39; → '
Katman 3: JS kaçış            \\u0027 \\x27 → '
Katman 4: Base64              VU5JT04 → UNION
Katman 5: Yorum kırma         UN/**/ION → UNION
```

### Anomali Skorlama
Her kural farklı puan verir, eşik aşılırsa engellenir:
```
Bot imzası (sqlmap/nuclei/nmap/nikto)  = 5 puan  (tek başına yeter)
Kritik saldırı (RCE, SQLi)             = 3 puan
Orta (XSS, LFI)                        = 2 puan
Zayıf (şüpheli desen)                  = 1 puan
```

### Ek Koruma
- **Hız sınırı:** 100 istek / 30 saniye / IP → aşılırsa geçici blok
- **DoS koruması:** eşzamanlı bağlantı sınırı
- **Boyut sınırı:** 4 KB üzeri şüpheli URL engellenir
- **Bot tespiti:** bilinen tarayıcı user-agent'ları
- **Gerçek IP:** `CF-Connecting-IP` başlığından (Cloudflare arkasında)
- **Beyaz liste:** muaf IP ve yollar (localhost, panel statikleri)

### Test Sonucu
```
38 / 38 saldırı vektörü ENGELLENDİ · 0 kaçak
```

---

## 🔥 2. Firewall — nftables

**Dosya:** `motor/kalkan_fw_v10.py` · **Kalıcılık:** evet

- **Kara liste seti:** `@kara` — engellenen tüm IP'ler
- **Kural:** `meta l4proto tcp ip saddr @kara reject` + `ip daddr @kara drop`
- Tüm portlardan keser (yalnızca web değil)
- **Kalıcı:** `kilic.nft` dosyasına yazılır, açılışta `kalkan-fw-yukle.service` yükler
- Sunucu yeniden başlasa bile engeller durur ("ölümsüz")
- Panelden manuel IP ekleme/çıkarma

---

## 🕵️ 3. IDS — Suricata Entegrasyonu

**Dosya:** `motor/kalkan_ids.py` · **Çalışma:** her 2 dakika

- Suricata `eve.json` çıktısını okur
- Alarm üreten IP'leri otomatik kara listeye alır
- Muafiyet: yerel ağ (`127.`, `192.168.`, `10.`, `172.16.`)
- Gerçek zamanlı imza tabanlı tespit

---

## 🧠 4. Motor — Korelasyon Motoru

**Dosya:** `motor/kalkan_motor.py` · **Çalışma:** sürekli döngü

- Tüm modüllerden gelen olayları toplar
- **14 fonksiyon, 182 desen** ile kural eşleştirme
- Risk skoru hesaplar: `f(tekrar sayısı, ciddiyet, IP itibarı, saat)`
- Engel kararı verir: `otomatik_engel` (panelden aç/kapa)
- Yanlış pozitif azaltma (tek seferlik olaylar engellemez)

### Karar Çıktıları
| Karar | Anlam |
|---|---|
| **ENGELLENDİ** | IP kara listeye alındı |
| **İZLENİYOR** | Şüpheli ama eşik altı |
| **TEMİZ** | Sorun yok |

---

## 👁️ 5. UEBA — Davranış Analizi

**Dosya:** `motor/kalkan_ueba.py` · **Çalışma:** her 15 dakika

Her IP/kullanıcı için davranış profili çıkarır:

| Anomali | Tespit Yöntemi |
|---|---|
| **Beacon** | Düzenli aralıklı bağlantı (sapma < %15) → C2 haberleşmesi |
| **Aşırı istek** | 24 saatte 500+ istek |
| **Gece aktivitesi** | 02:00-05:00 arası olağandışı hareket |
| **Yeni IP** | İlk kez görülen kaynak |
| **Port taraması** | 10+ farklı porta erişim |
| **Yüksek hata** | %40+ 404/403 oranı (keşif taraması) |

---

## 📁 6. FIM — Dosya Bütünlüğü İzleme

**Dosya:** `motor/kalkan_fim_v10.py` · **İzlenen:** 31 dosya · **Çalışma:** her 5 dakika

- Her kritik dosyanın **SHA-256** özetini saklar (baseline)
- Değişiklik olursa **risk seviyesine göre** sınıflar:
  ```
  KRİTİK → panel PHP + motor PY + waf.php
  ORTA   → ayar dosyaları
  DÜŞÜK  → log, önbellek
  ```
- **Otomatik yedek:** değişen dosyanın kopyasını alır
- Değişimin kim/ne zaman/nasıl olduğunu raporlar
- Web korsanlığı (webshell) tespiti için kritik

---

## 🔍 7. SCA — Sistem Sertleştirme Denetimi

**Dosya:** `motor/kalkan_sca_v10.py` · **Kontrol:** 48 · **Çalışma:** her 1 saat

CIS Benchmark esinli, 6 kategoride denetim:

| Kategori | Kontroller |
|---|---|
| Dosya izinleri | Kritik dosya modları, dünya-yazılabilir dosyalar |
| Ağ ayarları | IP yönlendirme, SYN çerezleri, ICMP |
| Kimlik doğrulama | Boş parolalar, kök erişimi, PAM |
| Servis yönetimi | Gereksiz servisler, otomatik başlatma |
| Çekirdek parametreleri | ASLR, çekirdek koruması, bellek |
| Loglama/denetim | auditd, log rotasyonu, NTP |

**Çıktı:** kategori bazlı yüzde skor + düzeltme önerileri.

---

## 🔬 8. Zafiyet Tarayıcı

**Dosya:** `motor/kalkan_zafiyet_v10.py` · **CVE:** 20 · **Çalışma:** her 6 saat

- `dpkg -l` ile kurulu paket listesi (3000+)
- Bilinen zafiyetli sürüm aralıklarıyla karşılaştırma
- **CVSS skoru** ile önceliklendirme
- **Sanal yama kontrolü:** WAF'ta bu zafiyet için kural var mı?
- Yama öncesi riski gösterir

---

## 🦠 9. Antivirüs

**Dosya:** `motor/kalkan_av_v10.py` · **İmza:** 20 + YARA · **Çalışma:** her 6 saat

- **İmza taraması:** regex tabanlı zararlı desenler
- **YARA desteği:** özel kural dosyaları
- **Hash itibar:** bilinen kötü hash listesi
- **Karantina:** şüpheli dosyayı `.karantina` uzantısıyla taşır
- **Güvenli tarama alanı:** `/tmp`, `/var/tmp`, `/dev/shm`
  *(Güvenlik kodları kendi imzalarını içerir → yanlış pozitif önlenir)*

---

## 🗺️ 10. Coğrafya — IP İtibar

**Dosya:** `motor/kalkan_cografya_v10.py` · **Çalışma:** her 15 dakika

- IP → **ülke** (whois/geoip)
- **ASN** (otonom sistem numarası)
- **Risk skoru:**
  ```
  Tor çıkış düğümü      → yüksek risk
  VPN/bulut IP          → orta risk (AWS/GCP/Azure aralıkları)
  Veri merkezi          → işaretlenir
  Bilinen kötü aralık   → düşük itibar
  ```
- Panelde **harita görünümü**

---

## 🍯 11. Aktif Savunma

**Dosya:** `motor/kalkan_aktif_v10.py` · **Honeypot:** 16 port · **Çalışma:** her 5 dakika

Sistem dışarıya sahte servisler açar:
```
22 (SSH) · 23 (Telnet) · 21 (FTP) · 445 (SMB) · 3389 (RDP)
1433 (MSSQL) · 3306 (MySQL) · 5432 (PostgreSQL) · 5900 (VNC)
6379 (Redis) · 27017 (MongoDB) · 11211 (Memcached) · 8080 · 8443 ...
```
Gerçek servis **kapalı**, sahte dinleyici **açık** → bir bağlantı gelirse
kaynak IP **anında engellenir**. Meşru kullanıcı bu portlara dokunmaz.

---

## 🪤 12. Honeyfile — Tuzak Dosya

**Dosya:** `motor/kalkan_honeyfile_v10.py` · **Tuzak:** 10 · **Çalışma:** her 10 dakika

Sistemde "cazip" isimli sahte dosyalar:
```
~/sifreler.txt              ~/.env
~/.ssh/id_rsa               ~/yedek.sql
/var/www/.env               /tmp/veritabani_yedek.sql
~/musteri_listesi.xlsx      ~/kredi_kartlari.csv
~/api_anahtarlari.json      /var/backups/config_yedek.tar
```
Herhangi biri **okunursa** (atime değişirse):
1. `lsof` ile okuyan süreç bulunur
2. Alarm üretilir
3. Kaynak IP/kullanıcı engellenir

Sıradan kullanıcı bu dosyalara dokunmaz → dokunan saldırgan.

---

## 🔐 13. Kimlik ve Erişim

**Dosya:** `motor/kalkan_kimlik_v10.py`

| Özellik | Detay |
|---|---|
| **RBAC** | ADMIN (tam) / İZLEYİCİ (salt-okunur) rolleri |
| **2FA** | TOTP tabanlı iki faktörlü doğrulama |
| **Oturum güvenliği** | 15 dk hareketsizlik → otomatik çıkış |
| **Parola politikası** | min 8 karakter, karmaşıklık zorunlu |
| **Deneme kilidi** | 5 hatalı giriş → 15 dk hesap kilidi |
| **Denetim izi** | Her giriş/çıkış zaman damgalı kaydedilir |
| **Şifreleme** | Parolalar bcrypt ile saklanır (tuzlu) |

---

## 🛡️ 14. Koruma Modülü

**Dosya:** `motor/kalkan_koruma.py` · **İzlenen:** 10 kritik dosya · **Çalışma:** her 30 dakika

Sistemin **kendi kendini koruma** mekanizması:

```bash
--kaydet    → kritik dosyaların hash'ini kaydet
--kontrol   → bütünlüğü denetle, bozulmuşsa onar
--senkron   → canlı dosyaları yedeğe kopyala
```

### Onarım Güvenliği (v10.1)
```
Dosya bozulmuş mu?
  ↓ EVET
ÖNCE canlıyı sakla: dosya.php.onarim_oncesi   ← veri kaybı olmaz
  ↓
Yedekten geri yükle
```
Yanlış teşhis durumunda bile orijinal kaybolmaz.

---

## 💾 15. Yedek Sistemi

**Dosya:** `motor/kalkan_yedek_v10.py` · **Çalışma:** günlük

```
1. tar.gz arşivi oluştur
2. SHA-256 manifest yaz
3. Doğrula (manifest ↔ arşiv bütünlüğü)
4. Immutable işaretle (silinemez/değiştirilemez)
5. Döngüsel saklama (eski yedekler korunur)
```

- Fidye yazılıma karşı: yedek değiştirilemez
- Geri yükleme testini otomatik yapar

---

## 📋 16. Uyumluluk Denetimi

**Dosya:** `motor/kalkan_yedek_v10.py` · **Çerçeve:** 5

| Çerçeve | Denetlenen |
|---|---|
| **ISO 27001** | 14 kontrol (A.5–A.18) |
| **NIST CSF** | 5 fonksiyon (Kimlik/Koru/Tespit/Müdahale/Kurtar) |
| **GDPR** | 6 madde (veri saklama, silme hakkı, aydınlatma) |
| **PCI DSS** | 12 gereksinim özeti |
| **KVKK** | 5 ilke (aydınlatma, veri güvenliği, saklama) |

**Çıktı:** uyum yüzdesi + eksik kontroller.

---

## 📊 17. Indexer — Log İndeksleme

**Dosya:** `motor/kalkan_indexer_v10.py` · **Kaynak:** 6 · **Çalışma:** her 1 saat

| Kaynak | Çıkarılan |
|---|---|
| nginx/apache access | İstek akışı, hata oranı |
| auth.log | Giriş denemeleri, başarısız kimlik |
| suricata eve.json | IDS alarmları |
| waf.log | WAF engelleri |
| panel.log | Kullanıcı işlemleri |
| sistem logu | Servis durumları |

Regex ile ayrıştırır → normalize eder → `olaylar.json`'a yazar (korelasyon için).

---

## ☁️ 18. Bulut ve EVTX

**Dosya:** `motor/kalkan_bulut_v10.py` · **Çalışma:** her 1 saat

### Bulut Yapılandırma Denetimi
```
AWS   → Public S3, açık IAM politikası, 0.0.0.0/0 güvenlik grubu
GCP   → Firewall kuralları, servis hesabı anahtarı kontrolü
Azure → NSG kuralları, depolama public erişimi
```
*(Yalnızca yapılandırma denetimi — kimlik bilgisi gerekmez.)*

### EVTX (Windows Olay Logu)
`.evtx` dosya yapısını çözümler (kayıt başlığı + XML).

---

## 🖥️ 19. Panel Özellikleri

**34 PHP sayfa** · TR/EN çift dil

| Sayfa | Görev |
|---|---|
| Özet | Sistem durumu, KPI kartları |
| Olaylar | Tespit edilen olaylar listesi |
| Engeller | Engellenen IP'ler, manuel ekle/çıkar |
| **WAF** | Kural durumu, koruma özellikleri |
| Dosya Bütünlüğü | FIM sonuçları |
| Sertleştirme | SCA skorları |
| Zafiyetler | CVE listesi |
| Antivirüs | Tarama sonuçları |
| Harita | IP coğrafi görünümü |
| Honeyfile | Tuzak tetiklenmeleri |
| Yedekler | Yedek listesi, geri yükleme |
| Uyumluluk | 5 çerçeve skoru |
| Ayarlar | Otomatik engelleme, bildirimler |
| **WAF** sayfası | v10 kural grubu, koruma özeti |

### Arayüz Özellikleri
- Koyu tema + neon cyan vurgular
- Responsive (mobil uyumlu)
- KPI kartları, grafikler
- Türkçe / İngilizce geçiş

---

## 📈 20. Performans ve Kaynak Kullanımı

| Metrik | Değer |
|---|---|
| WAF gecikmesi | ~4 ms / istek |
| Bellek (motor) | ~40 MB |
| Bellek (panel) | ~20 MB |
| Disk (sistem) | ~5 MB (kod) + log |
| CPU (boşta) | < %2 |
| Kurulum süresi | ~5 dakika |

---

## ✅ ÖZET — NE YAPAR?

```
KORUR    → WAF · firewall · IDS · aktif savunma · honeyfile
TESPİT   → motor · UEBA · FIM · indexer · coğrafya
TARAR    → SCA · zafiyet · AV
ONARIR   → koruma modülü · yedek sistemi
DENETLER → uyumluluk (ISO/NIST/GDPR/PCI/KVKK)
GÖSTERİR → panel (34 sayfa, TR/EN)
```

---

*CYBER KALKAN v1.0 · Özellikler Belgesi · 🐺 CYBERWOLF SECURITY*