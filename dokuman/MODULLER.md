# 📚 CYBER KALKAN — Modül Detayları

42 modülün teknik açıklaması. Her modül bağımsız çalışır, JSON ile haberleşir.

---

## 1. WAF — Web Uygulama Güvenlik Duvarı

**Dosya:** `motor/waf.php` · **Satır:** 344 · **Kural:** 1002

### Çalışma
Her HTTP isteğini 5 katmanda çözümler ve 1002 imzayla karşılaştırır.
Skor eşiği aşılırsa `403` döner ve IP'yi kara listeye alır.

### 5 Katmanlı Çözümleme
```
1. URL kod çözme        %27%20 → ' '
2. HTML varlık çözme    &#x27; &#39; → '
3. JS kaçış çözme       \\u0027 → '
4. Base64 çözme         dGVzdA== → test
5. Yorum kırma          UN/**/ION → UNION  (sonra birleştirir)
```

### Kural Grupları
| Grup | Örnek |
|---|---|
| SQL Injection | `UNION SELECT`, `OR 1=1`, `'; DROP` |
| XSS | `<script>`, `onerror=`, `javascript:` |
| LFI/RFI | `../../../etc/passwd`, `php://filter` |
| RCE | `; cat`, `|whoami`, `$(id)`, backtick |
| Şablon (SSTI) | `{{7*7}}`, `${7*7}`, `<%= %>` |
| NoSQL | `$ne`, `$gt`, `$where` |
| CVE sanal yama | Log4Shell, Spring4Shell, ProxyLogon, ThinkPHP, Laravel, Shellshock |
| Bot tespiti | sqlmap, nuclei, nmap, nikto, masscan |
| Dosya yükleme | `.php.jpg`, `%00.php`, çift uzantı |

### Ek Koruma
- **Anomali skorlama:** her kural farklı puan (bot = 5, genel = 1-3)
- **Hız sınırı:** 100 istek / 30 saniye / IP
- **DoS koruması:** eşzamanlı bağlantı sınırı
- **Beyaz liste:** muaf IP ve yollar (localhost, panel statikleri)
- **Gerçek IP:** `CF-Connecting-IP` başlığından okunur

### Test Sonucu
```
38 / 38 saldırı vektörü ENGELLENDİ · 0 kaçak
```

---

## 2. Motor — Kural Motoru ve Korelasyon

**Dosya:** `motor/kalkan_motor.py` · **Satır:** ~500 · **Çalışma:** sürekli döngü

### Görev
Tüm modüllerden gelen olayları toplar, korele eder, risk skoru hesaplar ve engel kararı verir.

### Karar Akışı
```
olaylar.json okunur
   ↓
kural eşleşmesi (14 fonksiyon, 182 desen)
   ↓
risk skoru = f(tekrar, ciddiyet, IP itibarı, saat)
   ↓
eşik aşıldı mı?
   ├─ evet + otomatik_engel=True → firewall_engelle(ip)
   └─ hayır → sadece kaydet (ENGELLENDİ/İZLENİYOR)
```

### Ayarlar
`veri/ayarlar.json` → `otomatik_engel` (True/False) panelden yönetilir.

---

## 3. UEBA — Davranış Analizi

**Dosya:** `motor/kalkan_ueba.py` · **Çalışma:** her 15 dk

Her IP/kullanıcı için davranış profili çıkarır:

| Anomali | Eşik |
|---|---|
| Beacon (düzenli aralık) | sapma < %15 |
| Aşırı istek | 24 saatte > 500 |
| Gece aktivitesi | 02:00-05:00 arası |
| Yeni IP | ilk kez görülen |
| Port taraması | 10+ farklı port |
| Yüksek hata oranı | > %40 (404/403) |

---

## 4. FIM — Dosya Bütünlüğü İzleme

**Dosya:** `motor/kalkan_fim_v10.py` · **İzlenen:** 31 kritik dosya · **Çalışma:** her 5 dk

### Yöntem
```
1. Baseline: her dosyanın SHA-256'sı kaydedilir (fim.json)
2. Kontrol: mevcut hash ≠ baseline → DEĞİŞİM
3. Sınıflandırma:
   KRITIK → panel php + motor py + waf.php
   ORTA   → ayar dosyaları
   DÜŞÜK  → log, cache
4. Otomatik yedek: değişen kritik dosyanın kopyası alınır
```

---

## 5. SCA — Sistem Sertleştirme Denetimi

**Dosya:** `motor/kalkan_sca_v10.py` · **Kontrol:** 48 · **Çalışma:** her 1 saat

CIS Benchmark esinli kontroller, 6 kategori:

| Kategori | Kontrol sayısı |
|---|---|
| Dosya izinleri | 12 |
| Ağ ayarları | 9 |
| Kimlik doğrulama | 8 |
| Servis yönetimi | 7 |
| Çekirdek parametreleri | 7 |
| Loglama/denetim | 5 |

Sonuç: `veri/sca.json` → kategori bazlı yüzde + öneri listesi.

---

## 6. Zafiyet Tarayıcı

**Dosya:** `motor/kalkan_zafiyet_v10.py` · **CVE:** 20 · **Çalışma:** her 6 saat

```
1. dpkg -l ile kurulu paket listesi (3000+)
2. Bilinen zafiyetli sürüm aralıklarıyla karşılaştırma
3. CVSS skoru ile önceliklendirme
4. NVD sanal yama kontrolü (WAF kuralı var mı?)
```

---

## 7. Antivirüs

**Dosya:** `motor/kalkan_av_v10.py` · **İmza:** 20 + YARA · **Çalışma:** her 6 saat

### Taranan dizinler (v10 güvenli)
```
/ tmp · /var/tmp · /dev/shm       ← sadece geçici alanlar
```
**Hariç:** `/opt/siber-kalkan`, panel, `/root/.hermes`, masaüstü, karantina.
*(v10 dersi: güvenlik kodları kendi imzalarını içerir → taranırsa kendini siler.)*

### Yöntem
- İmza eşleşmesi (regex)
- Hash itibar kontrolü
- Şüpheli bulunan dosya → `karantina/` (`.karantina` uzantısıyla)

---

## 8. Coğrafya — IP İtibar

**Dosya:** `motor/kalkan_cografya_v10.py` · **Çalışma:** her 15 dk

```
IP → ülke (whois/geoip) + ASN + risk skoru
Risk kaynağı: Tor çıkış · VPN · veri merkezi · bilinen kötü IP aralığı
Sonuç: harita verisi (veri/cografya.json)
```

---

## 9. Aktif Savunma

**Dosya:** `motor/kalkan_aktif_v10.py` · **Honeypot:** 16 port · **Çalışma:** her 5 dk

```
Sahte servisler dinler: 22, 23, 445, 3389, 1433, 3306, 5900, 6379...
Bir bağlantı gelirse → kaynak IP DERHAL engellenir + "kılıç" logu
```

---

## 10. Honeyfile — Tuzak Dosya

**Dosya:** `motor/kalkan_honeyfile_v10.py` · **Tuzak:** 10 · **Çalışma:** her 10 dk

Sistemde zararsız ama "cazip" isimli dosyalar:
```
~/sifreler.txt · ~/.ssh/config_yedek · /tmp/veritabani_yedek.sql
/var/backups/musteri_listesi.csv ...
```
Okunursa (atime değişirse) → kim okudu (`lsof`) → alarm + engel.

---

## 11. Kimlik ve Erişim

**Dosya:** `motor/kalkan_kimlik_v10.py` · **Çalışma:** panel ile entegre

| Özellik | Detay |
|---|---|
| Rol tabanlı erişim | ADMIN / İZLEYİCİ |
| 2FA | TOTP desteği |
| Oturum güvenliği | 15 dk hareketsizlik → çıkış |
| Parola politikası | min 8 karakter, karmaşıklık |
| Deneme kilidi | 5 hatalı giriş → 15 dk kilit |
| Denetim izi | her giriş/çıkış kaydedilir |

---

## 12. Koruma Modülü

**Dosya:** `motor/kalkan_koruma.py` · **İzlenen:** 10 kritik dosya · **Çalışma:** her 30 dk

### İki komut
```bash
python3 kalkan_koruma.py --kaydet    # hash kaydet/yenile
python3 kalkan_koruma.py --kontrol   # bütünlük denetle + onar
python3 kalkan_koruma.py --senkron   # canlı → yedek kopyala
```

### Onarım güvenliği (v10.1)
```
dosya bozulmuş mu? → evet
   ↓
ÖNCE canlıyı sakla: dosya.php.onarim_oncesi
   ↓
yedekten geri yükle
```
Böylece yanlış teşhis durumunda veri kaybı olmaz.

---

## 13. Yedek ve Uyumluluk

**Dosya:** `motor/kalkan_yedek_v10.py` · **Çalışma:** günlük

### Yedek
```
1. tar.gz arşiv oluştur
2. SHA-256 manifest yaz
3. Doğrula (manifest ↔ arşiv)
4. Immutable işaretle (varsa)
5. Eski yedekleri sakla (döngüsel)
```

### Uyumluluk Denetimi
| Çerçeve | Kontrol edilen |
|---|---|
| ISO 27001 | 14 kontrol (A.5-A.18) |
| NIST CSF | 5 fonksiyon (Kimlik/Koru/Tespit/Müdahale/Kurtar) |
| GDPR | 6 madde (veri saklama, silme hakkı...) |
| PCI DSS | 12 gereksinim özet |
| KVKK | 5 ilke (aydınlatma, veri güvenliği...) |

---

## 14. Indexer — Log İndeksleme

**Dosya:** `motor/kalkan_indexer_v10.py` · **Kaynak:** 6 · **Çalışma:** her 1 saat

```
nginx/apache access → akış
auth.log           → giriş denemeleri
suricata eve.json  → IDS alarmları
waf.log            → WAF engelleri
panel.log          → kullanıcı işlemleri
sistem logu        → servis durumları
        ↓  regex parse + normalizasyon
     olaylar.json (korelasyon için)
```

---

## 15. Bulut ve EVTX

**Dosya:** `motor/kalkan_bulut_v10.py` · **Çalışma:** her 1 saat

### Bulut denetimi
```
AWS   → public S3, açık IAM, güvenlik grubu 0.0.0.0/0
GCP   → firewall kuralı, servis hesabı anahtarı
Azure → NSG kuralı, depolama public erişimi
```
*(Yalnızca yapılandırma denetimi — kimlik bilgisi gerekmez.)*

### EVTX
Windows olay logu (`.evtx`) dosya yapısını çözümler (kayıt başlığı + XML).

---

## 16. Firewall

**Dosya:** `motor/kalkan_fw_v10.py` · **Çalışma:** her 2 dk

```
engel.json → IP listesi
   ↓
nftables kılıç tablosu (set @kara)
   ↓
kilic.nft dosyasına yaz (kalıcılık)
   ↓
kalkan-fw-yukle.service → açılışta yükler
```

**Kural:**
```
meta l4proto tcp ip saddr @kara reject
ip daddr @kara drop
```

---

## 📊 VERİ DOSYALARI

| Dosya | İçerik |
|---|---|
| `kurallar.json` | 1002 WAF kuralı |
| `olaylar.json` | Tespit edilen olaylar |
| `engel.json` | Engellenen IP listesi |
| `ayarlar.json` | Sistem ayarları |
| `dil.json` | TR/EN çeviriler |
| `fim.json` | Dosya hash baseline |
| `ueba.json` | Davranış profilleri |
| `sca.json` | Sertleştirme skorları |
| `cografya.json` | IP ülke/risk verisi |

---

*CYBER KALKAN v1.0 · 🐺 CYBERWOLF SECURITY*