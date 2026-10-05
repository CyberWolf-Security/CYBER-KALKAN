# 🐺 CYBER KALKAN — DEĞİŞİKLİK GÜNLÜĞÜ (CHANGELOG)

Bu dosya, canlı sistemde (`/opt/siber-kalkan` + `/var/www/kalkan-panel`) ve depoda
yapılan **tüm önemli değişiklikleri** kronolojik olarak kaydeder.

> Kural: Her düzeltme hem **depoda** hem **canlıda** olmalı; ikisi `cmp`/`md5sum` ile doğrulanır.

---

## [1.0] — 2026-10-06

### 🔴 GÜVENLİK — Gözcü Kod İnceleme Bulguları (133 bulgu → hepsi kapalı)

**Kritik (6/6 kapalı):**
| # | Bulgu | Düzeltme |
|---|---|---|
| B-01 | `waf.php` CF-Connecting-IP doğrulanmadan gerçek adres sayılıyor | Cloudflare IP aralığı kontrolü; güvenilmez kaynak YOK SAYILIR |
| B-02 | IP doğrulanmadan `shell=True` komutuna giriyor (CWE-78) | `engellenebilir()` doğrulaması + argüman listesi |
| B-03 | Panel oturum çerezi HttpOnly/SameSite eksik | `session_set_cookie_params` sertleştirildi |
| B-04 | CSRF koruması tutarsız | `kalkan_csrf()` + `kalkan_csrf_dogrula()` tüm formlara |
| B-05 | Log dosyası dışarıdan okunabilir | İzinler 600 + dizin 700 |
| B-06 | Zayıf parola kabulü (sha256 fallback) | Yalnız bcrypt/argon2 (`$2y$`/`$2a$`/`argon`) |

**Yüksek (7/7 kapalı):** B-07 ajan komut allowlist · B-08 … B-13
(ajan_kayit.php token doğrulama + komut allowlist; allowlist dışı komut ajanlara GÖNDERİLMEZ;
eski `kalkan_fim.py` devre dışı — `kalkan_fim_v10.py` aktif)

**Orta (120 kapalı — öne çıkanlar):**
- B-10: "1002 kural" iddiası → gerçek **111 imza (20 kategori)**
- B-14: honeyfile `durum`/`dosyalar` şema uyuşmazlığı (0 dosya izliyordu) → düzeltildi
- B-16: IDS kalıcı offset (`ids_offset.json`) — aynı alarmlar tekrar sayılmıyor
- B-17: giriş yalnız `admin` değil, girilen kullanıcı adı denenir + `session_regenerate_id(true)`
- B-18: WAF rate limit sayacı `flock LOCK_EX` ile **atomik**
- B-19/21: doküman iddiaları gerçek değerlere çekildi (42 modül, 38/38, immutable, 10.000 cihaz)
- B-22: RFC-5737 belgeleme adresleri (203.0.113.x vb.) engellenemez

### 🟠 ÇALIŞTIRMA TESTİNDE BULUNAN SESSİZ BOZUKLUKLAR (8 gerçek hata)

| # | Bulgu | Düzeltme |
|---|---|---|
| 1 | `entegrasyon.php` = API kodu (menüde JSON döküyordu) | Panel sayfası olarak yeniden yazıldı |
| 2 | `kalkan_csrf_uret()` TANIMSIZ ama 5 yerde çağrılıyordu → FATAL | `kalkan_csrf()` alias eklendi |
| 3 | `ortak.php` mail From boş (`$gonderen` bir satır sonra tanımlı) | Sıra düzeltildi ("sender rejected" çözüldü) |
| 4 | `kalkan_motor.py` eşiği ölü kod (sabit 100/40) | `e_tek`/`e_toplam`/`esik` ayarlardan okunur |
| 5 | Menü+footer hiç basılmıyordu (`return` eden fonksiyonlar `<?php X(); ?>` ile) | `<?= X() ?>` (6 dosya) |
| 6 | `kalkan_aktif_savunma.py` tarpit `bind()` try/except'siz → port meşgulse çöküyor | try/except + temiz çıkış |
| 7 | `kalkan_ag_v10.py` düz metin basıyor, panel JSON bekliyor → KPI 0 | `--json` bayrağı + `sys.exit(0)` |
| 8 | `entegrasyon.php` + `ag.php` `kalkan_menu()` kullanıyor → ÜST BANT yok | `kalkan_ustbilgi()` |
| 9 | `mb_strtoupper()` → **mbstring yok** → FATAL (kartlar boş) | `strtoupper()` + `kalkan_kacis()` |

### 🟡 IPS (Suricata + nftables NFQUEUE) — AKTİF + KALICI

- `table inet kalkan_ips` (3 kuyruk + beyaz liste) — fail-open
- Suricata konteyneri: **52.601 kural · 0 hata · 0 atlanan · 46.771 L7**
- Kurulumda çıkan 4 tuzak düzeltildi: (1) önce kuyruk sonra konteyner, (2) `:ro` mount kaldırıldı,
  (3) IPv6 adresler `ipaddress` ile atlanır, (4) `ORACLE_PORTS: "1521"` eklendi
- Kalıcılık: `kalkan-ips.service` (oneshot, RemainAfterExit, `Requires=docker.service`) — enabled
- **Yönetim portları (22/2083/2087/8083) muaf · `--onay` zorunlu · `nft -c` ön kontrol**

### 🟢 ARAYÜZ (PANEL) İYİLEŞTİRMELERİ

- **132 sayfa aynı CSS yoluna hizalandı:** `assets/panel.css?v=1.7` (stil.css YOK)
- **Tüm modül başlıkları → animasyonlu hero** (`assets/hero.css`, 6 keyframe):
  gradient akışı · neon nabız · ikon parlaması · ışık süpürme · rozet yüzmesi · neon çizgi
  (`kalkan_baslik()` fonksiyonu değiştirildi → 19+ sayfa otomatik kazandı)
- **KPI kartları** ağ modülü standardına çekildi (`.kartlar`/`.kart`/`.etiket`/`.deger`/`.alt`)
  — `kpi-izgara`/`kpi` panel.css'de YOK (ag.php + entegrasyon.php'de yanlış kullanılmıştı)
- **CANLI bandı** (`canli-serit`) tüm modüllere eklendi — tek satır yazılmalı
  (inline-flex + satır kırılması = boş flex item → bant bozulur)
- Kart metni taşma önleme: `clamp(19px, 2.05vw, 31px)` + `overflow-wrap:anywhere`
- **WAF sayfası:** imza sayımı düzeltildi (300+ → gerçek **135**), rate limit kartı eklendi,
  hero `.cy-hero` yapısına çevrildi, tablolardaki rakamlar tutarlı
- Ağ tablosu renkleri `!important` (UP yeşil / DOWN kırmızı / UNKNOWN gri)

### 🔵 GİRİŞ (B-17 REGRESYONU)

- B-17 "kullanıcıya özel hash" + B-06 "sha256 fallback kaldırıldı" → `password_verify()` **her şeyi reddetti** (kimse giremiyordu)
- Düzeltme: kullanıcı hash'i **yalnız `$2y$`/`$2a$`/`argon` ise** kullanılır, değilse global ayar hash'ine düşer

### 📦 DEPO ↔ CANLI SENKRON

Canlı kod ile depo farkı **0** (motor + panel birebir aynı).
Depoya eklenenler: `AJAN/` (4) · `ARAC/` (1) · `DECODER/` (1) · `suricata/docker-compose.ips.yml` · `VERI/` (66 yapılandırma)
`.gitignore` ile korunanlar: `LOG/` · `TLS/` · `CDB/` (150 MB) · `suricata/rules/` (49 MB) · `suricata/logs/` · `VERI/eposta.json` · `VERI/mail_ayar.json`
**Güvenlik:** public repoda `sifre_hash` (bcrypt) ve tokenlar → `$KURULUMDA_OLUSTURULUR$`

### 🗄️ KURAL TABANI

NVD 401.263 CVE · ET Open 52.561 · YARA 12.909 · kara liste 41.861 IP · CIS 160 · WAF **135 imza (20 kategori)** · CPE 702 (250 kritik)

---

## Sürüm Notları

- **v1.0** (2026-10-06) — İlk kararlı sürüm: 16 modül, hibrit mimari
  (nftables + Suricata IPS host'ta; panel + motor + WAF konteynerde), fail-open, onay zorunlu.

🐺 **CYBERWOLF SECURITY** · CYBER KALKAN V1.0
