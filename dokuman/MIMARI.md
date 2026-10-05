# 🏛️ CYBER KALKAN — Sistem Mimarisi

> Tek sunucuda çalışan, kendi kendini koruyan **bütünleşik siber güvenlik platformu**.
> 42 modül · 24 otomatik görev · 7 servis · harici veritabanı yok.

---

## 1. GENEL BAKIŞ

CYBER KALKAN, bir sunucuyu **dıştan ve içten** gelen tehditlere karşı korumak için tasarlanmış
katmanlı bir savunma sistemidir. Felsefesi: *her katman bir sonrakine güvenmez* (derinlemesine savunma).

```
                    İNTERNET
                       │
        ┌──────────────▼──────────────┐
        │   CLOUDFLARE (opsiyonel)    │  ← DDoS, WAF ön katman
        └──────────────┬──────────────┘
                       │
        ┌──────────────▼──────────────┐
        │      1. WAF (PHP)           │  111 imza · 5 katman çözümleme
        │   anomali skor + hız sınırı │  → 403 / engelle
        └──────────────┬──────────────┘
                       │
        ┌──────────────▼──────────────┐
        │    2. FIREWALL (nftables)   │  kalıcı kara liste (@kara seti)
        └──────────────┬──────────────┘
                       │
        ┌──────────────▼──────────────┐
        │      3. IDS (Suricata)      │  imza tabanlı trafik analizi
        └──────────────┬──────────────┘
                       │
        ┌──────────────▼──────────────┐
        │   4. SUNUCU KAYNAKLARI      │  panel · web · servisler
        └─────────────────────────────┘
                       ▲
                       │  koruma
        ┌──────────────┴──────────────┐
        │   5. KALKAN MOTOR (Python)  │  korelasyon · karar · engelleme
        │   + 41 yardımcı modül       │  FIM · UEBA · SCA · AV · yedek...
        └─────────────────────────────┘
```

---

## 2. KATMANLI MİMARİ (5 KATMAN)

### Katman 1 — GİRDİ (Perimeter)
Dışarıdan gelen her şey burada karşılanır.

| Bileşen | Teknoloji | Görev |
|---|---|---|
| **WAF** | PHP (kendi motoru) | 111 imza (20 kategori) · 5 katman çözümleme · anomali skor · hız sınırı |
| **Firewall** | nftables | Kalıcı kara liste, tüm portlardan kesme |
| **IDS** | Suricata | Gerçek zamanlı imza tespiti |

### Katman 2 — ANALİZ (Detection)
Gelen veriyi anlamlandırır, tehdit arar.

| Bileşen | Teknoloji | Görev |
|---|---|---|
| **Motor** | Python | Kural eşleştirme, korelasyon, karar |
| **UEBA** | Python | Davranış profili, anomali tespiti |
| **FIM** | Python | Dosya bütünlüğü (SHA-256) |
| **Indexer** | Python | 6 log kaynağından olay çıkarma |
| **Coğrafya** | Python | IP → ülke/ASN/risk |
| **Bulut** | Python | AWS/GCP/Azure denetimi + EVTX |

### Katman 3 — SAVUNMA (Prevention)
Saldırıyı durdurur ve yanıltır.

| Bileşen | Teknoloji | Görev |
|---|---|---|
| **Aktif Savunma** | Python + socket | 16 honeypot portu |
| **Honeyfile** | Python | 10 tuzak dosya |
| **Kimlik** | Python + PHP | RBAC · 2FA · oturum güvenliği |
| **Koruma** | Python | Kritik dosya bütünlüğü + onarım |

### Katman 4 — TARAMA (Assessment)
Sistemin kendi zayıflıklarını bulur.

| Bileşen | Teknoloji | Görev |
|---|---|---|
| **SCA** | Python | 48 CIS kontrolü |
| **Zafiyet** | Python | 20 CVE · CVSS skorlama |
| **AV** | Python | 20 imza · YARA · karantina |

### Katman 5 — UYUMLULUK (Governance)
Kayıt, yedek ve denetim.

| Bileşen | Teknoloji | Görev |
|---|---|---|
| **Yedek** | Python + tar | Immutable yedek · SHA-256 |
| **Denetim** | Python | ISO 27001 · NIST · GDPR · PCI · KVKK |

---

## 3. VERİ AKIŞI (İstek → Karar)

Bir saldırı isteğinin izlediği yol:

```
1. İSTEK GELİR
   nginx → waf.php

2. WAF ÇÖZÜMLER (5 katman)
   ham veri → URL çöz → HTML çöz → JS çöz → Base64 çöz → yorum kır
   sonuç: normalize edilmiş metin

3. İMZA EŞLEŞTİRME (111 imza / 20 kategori)
   her eşleşme PUAN verir (bot=5, genel=1-3)

4. ANOMALİ SKORU
   toplam skor ≥ eşik mi?

   ├── EVET → 403 döndür
   │          IP'yi engel.json'a yaz
   │          olaylar.json'a OLAY kaydet (ENGELLENDİ)
   │          ↓
   └── HAYIR → isteği geçir

5. MOTOR (her dakika)
   engel.json okunur → firewall_engelle(ip)
   ↓
   nftables @kara setine IP eklenir
   ↓
   kilic.nft yazılır (kalıcılık)
   ↓
   IP artık TÜM portlardan kesintili

6. SONRAKİ GÜN / YENİDEN BAŞLATMA
   kalkan-fw-yukle.service → kilic.nft yükler
   → engeller KALICI (ölümsüz)
```

---

## 4. BİLEŞENLER ARASI HABERLEŞME

Harici veritabanı **yok** — tüm modüller JSON dosyaları üzerinden konuşur.
Bu, sistemin tek dosya kopyalamayla taşınabilmesini ve bağımlılıksız çalışmasını sağlar.

```
                    ┌──────────────────┐
                    │  VERI/ (JSON)    │
                    │  ───────────────  │
                    │  kurallar.json   │ 111 WAF imzası
                    │  olaylar.json    │ tespit edilen olaylar
                    │  engel.json      │ engellenen IP'ler
                    │  ayarlar.json    │ sistem ayarları
                    │  dil.json        │ TR/EN çeviri
                    └────────┬─────────┘
                             │
      ┌──────────┬───────────┼───────────┬──────────┐
      ▼          ▼           ▼           ▼          ▼
   ┌──────┐  ┌──────┐   ┌──────┐   ┌──────┐  ┌──────┐
   │ WAF  │  │Motor │   │ FIM  │   │UEBA  │  │ ...  │
   │ PHP  │  │Python│   │Python│   │Python│  │      │
   └──────┘  └──────┘   └──────┘   └──────┘  └──────┘
```

**Kilit tasarım kararı:** her modül bağımsız çalışır. Biri çökse diğerleri etkilenmez.

---

## 5. TEKNOLOJİ YIĞINI

| Katman | Teknoloji | Neden |
|---|---|---|
| Web panel | PHP 8 (framework'süz) | Hafif, her sunucuda çalışır |
| Motor/modüller | Python 3 | Zengin kütüphane, hızlı geliştirme |
| Veri | JSON dosyaları | Bağımlılıksız, taşınabilir |
| Web sunucu | nginx | Performans |
| Firewall | nftables | Modern, çekirdek düzeyi |
| IDS | Suricata | Endüstri standardı |
| Servis | systemd | Otomatik başlatma, izleme |
| TLS | stunnel / Cloudflare | Şifreli erişim |

**Toplam bağımlılık:** PHP 8, Python 3, nginx, nftables, Suricata.
Harici servis/veritabanı hesabı **gerekmez**.

---

## 6. DAĞITIM MODELİ

```
/srv/calkam/                    ← kök klasör
├── panel/                      Web arayüzü (nginx root)
│   ├── *.php                   34 sayfa
│   └── assets/                 CSS · JS · ikon
├── motor/                      42 Python modül
│   ├── waf.php                 WAF çekirdeği
│   ├── kalkan_motor.py         Ana motor
│   └── kalkan_*_v10.py         Yardımcı modüller
├── veri/                       JSON veri deposu
├── firewall/                   nftables kuralları
└── servis/                     systemd birimleri
```

### Çalışan Servisler (7)
```
kalkan-panel.service       Web paneli      (127.0.0.1:8890)
kalkan-motor.service       Ana motor       (sürekli döngü)
kalkan-fim.service         Dosya izleme
kalkan-aktif.service       Aktif savunma
kalkan-honeyfile.service   Tuzak dosyalar
kalkan-https.service       TLS tüneli       (8443)
suricata.service           IDS
```

### Otomatik Görevler (24 cron)
```
her dakika → WAF log, engel senkronu
her 2 dk   → IDS → engelleme
her 5 dk   → FIM, aktif savunma
her 10 dk  → motor korelasyon, honeyfile
her 15 dk  → UEBA, coğrafya
her 30 dk  → koruma senkronu
her 1 saat → SCA, indexer, bulut
her 6 saat → zafiyet, AV
günlük     → yedek, denetim
```

---

## 7. TASARIM İLKELERİ

### 1. Derinlemesine Savunma
Tek bir katman aşılsa bile sonraki katman durdurur. WAF atlatılsa firewall, firewall atlatılsa IDS.

### 2. Bağımsız Modüller
Her modül kendi çalışır. Biri güncellenirken diğerleri durmaz, biri çökse sistem ayakta kalır.

### 3. Sıfır Dış Bağımlılık
Veritabanı sunucusu, dış API, bulut hesabı gerekmez. Tek sunucu, kendi kendine yeter.

### 4. Kalıcılık
Engeller diskte tutulur. Sunucu kapansa da açılışta geri yüklenir.

### 5. Görünürlük
Her karar panelde görünür: kim, ne, neden engellendi. Karanlık kutu yok.

### 6. Kendi Kendini Koruması
Koruma modülü, sistemin kendi dosyalarını izler; değişirse uyarır ve onarır.

---

## 8. OLÇEKLENME VE SINIRLAR

| Konu | Durum |
|---|---|
| Sunucu sayısı | 1 (tek sunucu için tasarlandı) |
| Eşzamanlı kullanıcı | Düşük (panel, yönetim amaçlı) |
| Trafik hacmi | Orta (Küçük/orta siteler) |
| Yedek | Günlük otomatik |
| Taşınabilirlik | Yüksek (tar.gz kopyala-taşı) |

**Not:** Sistem bir *yönetim paneli + koruma katmanı* olarak tasarlanmıştır;
yüksek trafikli servis (yüz binlerce eşzamanlı) için önüne CDN/dengeleyici önerilir.

---

## 9. GÜVENLİK SINIRLARI (Nerede Durur)

CYBER KALKAN **savunma** sistemidir:
```
✓ Kendi sunucunu korur
✓ Saldırıyı tespit eder ve engeller
✓ Kayıt ve rapor üretir
✓ Uyumluluk denetimi yapar

✘ Başkasının sistemine saldırmaz
✘ Veri toplamaz (yalnızca kendi logunu)
✘ Dışarıya bilgi göndermez (Cloudflare hariç, opsiyonel)
```

---

*CYBER KALKAN v1.0 · Mimari Belgesi · 🐺 CYBERWOLF SECURITY*