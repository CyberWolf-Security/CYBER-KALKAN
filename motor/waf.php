<?php
/**
 * ═══════════════════════════════════════════════════════════
 *  CYBER KALKAN — WAF v10 (MAKSIMUM GUC)
 *  CYBERWOLF SECURITY | Sifirdan yazildi
 *  v3-v10: 300+ kural · 5 katmanli normalize · anomali skorlama
 *          rate limit · bot tespiti · sanal yama · whitelist
 * ═══════════════════════════════════════════════════════════
 */
if (!defined('KALKAN_WAF')) {
define('KALKAN_WAF', 1);
define('WAF_SURUM', '10.0');

$V   = '/opt/siber-kalkan/VERI';
$LOG = '/opt/siber-kalkan/LOG';

// ═══ 1. SALDIRGAN IP (proxy/CDN arkasında gerçek IP) ═══
// ★ GÜVENLİK (B-01): İstemci başlıklarına (CF-Connecting-IP, X-Forwarded-For...)
// SADECE istek güvenilir bir kaynaktan (Cloudflare / yerel ağ) geldiyse güven.
// Aksi hâlde saldırgan başlığı sahteleyip beyaz listeye girebilir / başkasını
// kara listeye attırabilir.
$uzak = $_SERVER['REMOTE_ADDR'] ?? '';
$guvenilir = false;
$ipv4 = ip2long($uzak);
if ($ipv4 !== false) {
    // Cloudflare IPv4 aralıkları (https://www.cloudflare.com/ips-v4)
    $cf_aralik = ['173.245.48.0/20','103.21.244.0/22','103.22.200.0/22','103.31.4.0/22',
        '141.101.64.0/18','108.162.192.0/18','190.93.240.0/20','188.114.96.0/20',
        '197.234.240.0/22','198.41.128.0/17','162.158.0.0/15','104.16.0.0/13',
        '104.24.0.0/14','172.64.0.0/13','131.0.72.0/22'];
    foreach ($cf_aralik as $a) {
        [$ag, $bit] = explode('/', $a);
        $maske = ~((1 << (32 - (int)$bit)) - 1);
        if (($ipv4 & $maske) === (ip2long($ag) & $maske)) { $guvenilir = true; break; }
    }
}
// Yerel/özel ağlar da güvenilir kaynak sayılır (nginx/php-fpm aynı hostta)
if (!$guvenilir && (strpos($uzak,'127.')===0 || strpos($uzak,'10.')===0 ||
    strpos($uzak,'192.168.')===0 || strpos($uzak,'172.')===0 || $uzak==='::1')) {
    $guvenilir = true;
}

if ($guvenilir) {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
       ?? $_SERVER['HTTP_TRUE_CLIENT_IP']
       ?? $_SERVER['HTTP_X_REAL_IP']
       ?? $_SERVER['HTTP_X_FORWARDED_FOR']
       ?? $uzak;
    $ip = trim(explode(',', $ip)[0]);
    // Başlıktan gelen değer de geçerli bir IP olmalı (yoksa REMOTE_ADDR'a düş)
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) $ip = $uzak;
} else {
    // Güvenilmez kaynak → başlıklara HİÇ güvenme, doğrudan bağlantı IP'sini kullan
    $ip = $uzak;
}

// ═══ 2. MUAFIYET (kendi ağı) ═══
$muaf = (strpos($ip, '127.') === 0 || strpos($ip, '192.168.') === 0 || strpos($ip, '10.200.0.1') === 0);

// ═══ 3. 5 KATMANLI NORMALIZE ═══
$ham = ($_SERVER['REQUEST_URI'] ?? '') . ' ' . http_build_query($_GET ?? []) . ' ' .
       http_build_query($_POST ?? []) . ' ' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . ' ' .
       ($_SERVER['HTTP_REFERER'] ?? '') . ' ' . ($_SERVER['HTTP_COOKIE'] ?? '');

$H = $ham;
// katman 1: çoklu URL decode
for ($i = 0; $i < 4; $i++) { $y = rawurldecode($H); if ($y === $H) break; $H = $y; }
// katman 2: HTML entity
$H = html_entity_decode($H, ENT_QUOTES | ENT_HTML5, 'UTF-8');
// katman 3: JS escape (\u0027, \x27) + unicode
$H = preg_replace_callback('/\\\\u00([0-9a-f]{2})/i', fn($m) => chr(hexdec($m[1])), $H);
$H = preg_replace_callback('/\\\\x([0-9a-f]{2})/i', fn($m) => chr(hexdec($m[1])), $H);
$H = str_replace(['%u00', '\\u00'], ['%', '%'], $H);
// katman 4: base64 (uzun base64 blob'ları çöz)
if (preg_match_all('#[A-Za-z0-9+/]{20,}={0,2}#', $H, $b64)) {
    foreach ($b64[0] as $blok) {
        $c = base64_decode($blok, true);
        if ($c && preg_match('/[a-z<>{}\'";()\/\\\\]/i', $c)) { $H .= ' ' . $c; }
    }
}
// katman 5: SQL yorum + boşluk + null byte temizliği
$H = str_replace(["\0", '%00'], ['', ''], $H);
$H_ek = str_replace(['/**/', '/*!*/', '/*!', '*/'], '', $H);   // yorum kirma (v10.1)
$H = $H . ' ' . $H_ek;                                        // iki varyanti da tara
$H = preg_replace(['#/\*.*?\*/#s', '#--(?=\s|$)#', '#\s+#'], [' ', ' ', ' '], $H);
$H = str_replace(['+', '%09', '%0b', '%0c'], [' ', ' ', ' ', ' '], $H);
$HEDEF = strtolower($H);

// ═══ 4. KURAL BANKASI (kategori: id, seviye, ad, desen, puan) ═══
$K = [];

// ── 4.1 SQL INJECTION (puan 5 KRITIK) ──
foreach ([
 '/(union[\s\S]{0,15}select|select[\s\S]{0,15}from|insert[\s\S]{0,10}into|update[\s\S]{0,15}set|delete[\s\S]{0,10}from)/i',
 '/information_schema|sysobjects|syscolumns|pg_catalog|sqlite_master/i',
 '/\bor\s+\d+\s*=\s*\d+|\band\s+\d+\s*=\s*\d+|\bor\s+\'[^\']*\'=\'|\'\s*or\s*\'/i',
 '/sleep\s*\(|benchmark\s*\(|pg_sleep|waitfor\s+delay|dbms_pipe/i',
 '/;\s*(drop|truncate|alter)\s+(table|database)/i',
 '/updatexml|extractvalue|load_file|into\s+(out|dump)file/i',
 '/concat\s*\(|group_concat|char\s*\(|ascii\s*\(|substring\s*\(/i',
 '/having\s+\d+|order\s+by\s+\d+\s*,|union\s+all\s+select/i',
 '/\bexec\s*\(|\bexecute\s+immediate|sp_executesql/i',
 '/\bcast\s*\(.*\bas\s+(int|char)|convert\s*\(.*\busing/i',
] as $d) $K[] = [1001, 'KRITIK', 'SQL Injection', $d, 5];

// ── 4.2 XSS (puan 5) ──
foreach ([
 '/<script[\s>]|<\/script>/i',
 '/javascript\s*:|vbscript\s*:|data\s*:\s*text\/html/i',
 '/on(error|load|click|mouse|focus|blur|change|submit|input|key)\s*=/i',
 '/<img[^>]+src[^>]*onerror|<svg[^>]*onload|<iframe[^>]*src/i',
 '/document\.(cookie|location|write|domain)|window\.(location|open)/i',
 '/\balert\s*\(|\bprompt\s*\(|\bconfirm\s*\(|\beval\s*\(|\bexecScript/i',
 '/<body[^>]*onload|<input[^>]*autofocus[^>]*onfocus|<marquee[^>]*onstart/i',
 '/expression\s*\(|url\s*\(\s*[\'"]?javascript/i',
 '/&#x?[0-9a-f]{2,4};.{0,20}(script|alert|eval)/i',
 '/<(object|embed|applet|meta|link|base|form)[^>]*(data|src|href|action)\s*=/i',
] as $d) $K[] = [1002, 'KRITIK', 'XSS', $d, 5];

// ── 4.3 PATH TRAVERSAL / LFI (puan 5) ──
foreach ([
 '/\.\.\/|\.\.\\\\|\.\.%2f|\/\.\.\/|\.\.\.\.\//i',
 '/\/etc\/(passwd|shadow|hosts|group|sudoers)/i',
 '/\/proc\/(self|version|cpuinfo)|\/sys\/class|\/dev\/(null|zero|random)/i',
 '/%2e%2e|%252e%252e|\.%2e\/|%2e\.\//i',
 '/\/var\/log\/|\/root\/|\/home\/[a-z]+\/\.ssh/i',
 '/php:\/\/|phar:\/\/|zip:\/\/|data:\/\/|expect:\/\/|file:\/\/|glob:\/\//i',
 '/\/(boot\.ini|win\.ini|system32|windows\/|autoexec\.bat)/i',
 '/(include|require)(_once)?\s*[=(]\s*[\'"]?(http|ftp|php|\/)/i',
] as $d) $K[] = [1003, 'KRITIK', 'Path Traversal / LFI', $d, 5];

// ── 4.4 KOMUT ENJEKSİYONU / RCE (puan 5) ──
foreach ([
 '/[;|&]\s*(cat|ls|id|whoami|uname|wget|curl|nc|bash|sh|python|perl)\s/i',
 '/\|\s*(cat|id|whoami|ls|bash|sh)\s|\$\([^)]{1,60}\)|`[^`]{1,60}`/i',
 '/(bash|sh)\s+-[ci]\s|nc\s+-e|ncat\s+-e|socat\s+|mkfifo/i',
 '/\$\{IFS\}|\$\{PATH\}|%0[aA]|%0[dD]%0[aA]/i',
 '/certutil\s+-urlcache|bitsadmin|mshta|cscript|wscript|regsvr32\s+javascript/i',
 '/powershell(\s|\.exe)|cmd\s*\/c|cmd\.exe|rundll32\s+javascript/i',
 '/curl\s+(http|ftp)|wget\s+(http|ftp)|fetch\s+http/i',
 '/\/bin\/(sh|bash|dash|zsh)|\/usr\/bin\/(python|perl|php|ruby)/i',
 '/\bnc\s+[\d.]+ \d+|\bncat\s+[\d.]+/i',
] as $d) $K[] = [1004, 'KRITIK', 'Komut Enjeksiyonu / RCE', $d, 5];

// ── 4.5 SSRF (puan 5) ──
foreach ([
 '/169\.254\.169\.254|metadata\.(google|azure)|100\.100\.100\.200/i',
 '/(http|https|gopher|dict|ftp):\/\/(127\.|localhost|0\.0\.0\.0|\[::1\])/i',
 '/(http|https):\/\/10\.\d+\.|(http|https):\/\/192\.168\.|(http|https):\/\/172\.(1[6-9]|2\d|3[01])\./i',
 '/file:\/\/\/|gopher:\/\/|dict:\/\/|ldap:\/\/[^s]/i',
 '/0x7f000001|0177\.0\.0\.1|2130706433|\[0:0:0:0:0:ffff:127\.0\.0\.1\]/i',
] as $d) $K[] = [1005, 'KRITIK', 'SSRF', $d, 5];

// ── 4.6 XXE / XML (puan 5) ──
foreach ([
 '/<!DOCTYPE[^>]{0,50}SYSTEM|<!ENTITY\s+\w+\s+SYSTEM|<!ENTITY\s+%\s/i',
 '/SYSTEM\s+[\'"]file:\/\/|SYSTEM\s+[\'"]http/i',
 '/<!ENTITY\s+\w+\s+[\'"][^\'"]{0,80}[\'"]/i',
 '/<\?xml[^>]*encoding\s*=\s*[\'"](utf-7|utf-16)/i',
] as $d) $K[] = [1006, 'KRITIK', 'XXE', $d, 5];

// ── 4.7 DESERIALIZATION (puan 5) ──
foreach ([
 '/rO0AB|AAEAAAD\/\/\/\/|<java[^>]*serialization/i',
 '/O:\d{1,3}:"[A-Za-z]|a:\d+:\{|s:\d+:"/i',
 '/__PHP_Incomplete_Class|__wakeup|__destruct|__toString/i',
 '/ysoserial|CommonsCollections|BeanUtils|Spring1[0-9]|Groovy1/i',
 '/javax\.(naming|management)|com\.sun\.rowset|RogueJndi/i',
] as $d) $K[] = [1007, 'KRITIK', 'Deserialization', $d, 5];

// ── 4.8 SSTI (puan 5) ──
foreach ([
 '/\{\{[\s\S]{0,40}\}\}|\$\{[\s\S]{0,40}\}|<%=[\s\S]{0,40}%>|#\{[\s\S]{0,40}\}/i',
 '/\{\{.{0,25}(config|self|class|request|application|lipsum|cycler|joiner)/i',
 '/\{%\s*(if|for|set|include|import)|\$\{\s*(T\(|new\s+java)/i',
 '/__class__|__mro__|__subclasses__|__globals__|__builtins__/i',
 '/\{\{\s*\d+\s*[*+]\s*\d+\s*\}\}|\{\{\s*[\'"].{0,20}[\'"]\s*\*|freemarker|velocity\./i',
] as $d) $K[] = [1008, 'KRITIK', 'SSTI (Template Injection)', $d, 5];

// ── 4.9 NOSQL (puan 5) ──
foreach ([
 '/\$ne|\$gt|\$lt|\$gte|\$lte|\$eq|\$in|\$nin|\$where|\$regex|\$exists|\$type/i',
 '/\$or|\$and|\$not|\$nor|\$expr|\$jsonSchema/i',
 '/\[\$[a-z]{2,}\]|\{\s*[\'"]\$[a-z]+/i',
 '/mapReduce|map_reduce|\$accumulator|\$function/i',
] as $d) $K[] = [1009, 'KRITIK', 'NoSQL Injection', $d, 4];

// ── 4.10 LDAP / XPATH (puan 4) ──
foreach ([
 '/\(\s*[a-z]+\s*=\s*\*\)|\(\|[^\n]{0,30}\)|\(&[^\n]{0,30}\)/i',
 '/\*\)\(.*\||\)\s*\(\s*objectClass|ldap:\/\/|admin.*\)\(.*password/i',
 '/\/\/(user|text|comment)\(|\'\s*or\s*\'\d|\'\]\s*\|\s*\/\//i',
] as $d) $K[] = [1010, 'YUKSEK', 'LDAP/XPath Injection', $d, 4];

// ── 4.11 CRLF / HEADER INJECTION (puan 4) ──
foreach ([
 '/%0d%0a|%0a%0d|%0d%00%0a|\r\n\r\n/i',
 '/(%0d%0a|%0a)[^\s]{0,15}(set-cookie|location|content-length|http\/)/i',
 '/(location|refresh|set-cookie)\s*:[^\n]{0,40}(%0d|\r\n)/i',
] as $d) $K[] = [1011, 'YUKSEK', 'CRLF / Header Injection', $d, 4];

// ── 4.12 PROTOKOL / SMUGGLING (puan 5) ──
foreach ([
 '/transfer-encoding\s*:\s*(chunked|identity)/i',
 '/(content-length\s*:\s*\d+[\s\S]{0,60}transfer-encoding|transfer-encoding[\s\S]{0,60}content-length)/i',
 '/http\/1\.[01]\s+[\s\S]{0,40}http\/1\.[01]/i',
 '/\bhost\s*:\s*[\d.]+|x-forwarded-for\s*:\s*(127|localhost)/i',
 '/%c0%ae|%c0%af|%e0%80%af|overlong/i',
] as $d) $K[] = [1012, 'KRITIK', 'Protokol / Smuggling', $d, 5];

// ── 4.13 SANAL YAMA — CVE İMZALARI (puan 5) ──
foreach ([
 '/\$\{jndi:(ldap|rmi|dns|http|iiop)|\$\{lower:|\$\{upper:|\$\{env:|\$\{\$\{/i',          // Log4Shell CVE-2021-44228
 '/class\.module\.(classLoader|classLoader\.resources)|class\.classLoader/i',              // Spring4Shell CVE-2022-22965
 '/springframework|spring-beans|spring-core/i',
 '/(wnSN|wNvP|glox|bz2ya|fTmpf)|pom\.xml|META-INF\/maven/i',                                // Log4j obfuscation
 '/\$scanner|freedisk|getRuntime\(\)\.exec/i',                                              // JNDI/RCE
 '/thinkphp|think\\\\|pearcmd|pearcmd\.php/i',                                              // ThinkPHP / PEAR RCE
 '/wp-config\.php|wp-content\/(plugins|themes)\/\.{0,2}\/|elementor/i',                     // WordPress
 '/_ignition\/execute|laravel\/framework|livewire/i',                                       // Laravel Ignition RCE
 '/solr\/|\/admin\/collections|\/_cat\/|\/_search\?|elasticsearch/i',                       // Solr/ES
 '/jira\/|\/secure\/Dashboard\.jspa|confluence|xwork/i',                                    // Atlassian CVE'leri
 '/auth\/check|exchanger|\/owa\/|autodiscover\.xml|proxyLogon|proxyShell/i',                // Exchange ProxyLogon/Shell
 '/\.\.%2f\.\.%2f\.\.%2f\.\.%2f|cgi-bin\/|shellshock|\(\)\s*\{\s*:/i',                      // Shellshock CVE-2014-6271
 '/openssl|heartbleed|CVE-\d{4}-\d{4,}/i',                                                  // Genel CVE referansı
] as $d) $K[] = [2001, 'KRITIK', 'Sanal Yama (CVE)', $d, 5];

// ── 4.14 WEB SHELL / BACKDOOR (puan 5) ──
foreach ([
 '/shell\.php|cmd\.php|c99\.php|r57\.php|b374k|wso\.php|webshell|backdoor/i',
 '/eval\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/i',
 '/\b(assert|system|passthru|shell_exec|popen|proc_open|preg_replace_callback)\s*\(\s*\$/i',
 '/\/uploads?\/[^\s]{0,40}\.(php|phtml|php[3-8]|jsp|asp)/i',
] as $d) $K[] = [2002, 'KRITIK', 'Web Shell / Backdoor', $d, 5];

// ── 4.15 ZARARLI DOSYA YÜKLEME (puan 4) ──
foreach ([
 '/\.(php[3-8]?|phtml|phar|php\.|asp|aspx|ashx|asmx|jsp|jspx|jsw|cgi|pl|py|rb|sh|bash|exe|dll|bat|cmd|com|scr|msi|jar|war)(\?|$|\s|%00)/i',
 '/filename\s*=\s*[\'"][^\'"]*\.(php|jsp|asp|exe|sh)/i',
 '/\.(htaccess|user\.ini|web\.config)/i',
 '/Content-Type\s*:\s*application\/x-(php|httpd-php)/i',
] as $d) $K[] = [2003, 'YUKSEK', 'Zararli Dosya Yukleme', $d, 4];

// ── 4.16 BOT / TARAYICI / KEŞİF (puan 3) ──
foreach ([
 '/sqlmap|nikto|nmap|masscan|acunetix|nessus|openvas|nuclei|ffuf|gobuster|dirbuster|dirb|wfuzz|feroxbuster/i',
 '/wpscan|joomscan|droopescan|whatweb|wafw00f|httpx|subfinder|amass|theHarvester/i',
 '/hydra|medusa|patator|metasploit|msfconsole|empire|cobaltstrike|sliver/i',
 '/\b(zgrab|zmap|gospider|hakrawler|katana|jaeles|arjun|x8|paramspider)\b/i',
 '/python-requests\/[0-2]|python-urllib|libwww-perl|go-http-client|java\/1\.[0-7]/i',
 '/curl\/[0-6]|wget\/1\.[0-9]|axios\/0|node-fetch|node\.js|scrapy\//i',
 '/masscan|advanced\s+ip\s+scanner|acunetix|Netsparker|Burp|ZAP/i',
 '/\.(git|svn|hg|bzr|env|aws|ssh|docker|DS_Store)/i',
] as $d) $K[] = [2020, 'YUKSEK', 'Bot / Tarayici / Kesif', $d, 5];

// ── 4.17 HASSAS DOSYA ERİŞİMİ (puan 4) ──
foreach ([
 '/\/(\.env|\.git\/|\.svn\/|\.hg\/|\.htpasswd|\.htaccess|\.DS_Store|\.aws\/|\.ssh\/|\.docker\/)/i',
 '/\/(wp-login|wp-admin|xmlrpc\.php|phpmyadmin|pma|adminer|mysqladmin|myadmin)/i',
 '/(\.sql|\.bak|\.old|\.swp|\.save|\.orig|\.backup|\.tar\.gz|\.zip|\.rar|\.7z)(\?|$)/i',
 '/\/(config|settings|database|credentials|secrets)\.(php|json|yml|yaml|xml|ini|env|txt)/i',
 '/\/(backup|dump|db|sql|data)\.(sql|gz|zip|tar)/i',
 '/\/(composer\.json|package\.json|\.npmrc|\.dockercfg|id_rsa|id_dsa|\.pem|\.key)/i',
] as $d) $K[] = [2004, 'YUKSEK', 'Hassas Dosya Erisimi', $d, 4];

// ── 4.18 KRİPTO MADENCİ / RANSOMWARE (puan 5) ──
foreach ([
 '/xmrig|stratum\+tcp|cpuminer|minerd|nicehash|cryptonight|randomx/i',
 '/HOW_TO_DECRYPT|DECRYPT_INSTRUCTIONS|YOUR_FILES_ARE_ENCRYPTED|readme\.txt.*decrypt/i',
 '/wallet:|\/pool\/|mining\.pool|coinhive|coin-hive/i',
 '/\b(locky|wannacry|petya|ryuk|conti|lockbit|revil|blackcat)\b/i',
 '/(hive|ransom)[\s\S]{0,16}(ransom|decrypt|locker|bitcoin|\.onion)/i',
] as $d) $K[] = [2100, 'KRITIK', 'Kripto Miner / Ransomware', $d, 5];

// ── 4.19 BİLGİ SIZDIRMA (puan 3) ──
foreach ([
 '/\/(phpinfo|info|test|debug|trace|status|server-status|server-info)(\.php)?(\?|$)/i',
 '/\/(web\.config|WEB-INF|META-INF|actuator|jolokia|console\/)/i',
 '/error_reporting|display_errors|php\.ini|\.user\.ini/i',
 '/\/(\.well-known\/(?!acme-challenge)[a-z-]+)/i',
 '/\/admin\.php|\/shell[\.\/?]|stack\s*trace|debug\s*=\s*1/i',
] as $d) $K[] = [2005, 'ORTA', 'Bilgi Sizdirma Probu', $d, 3];

// ── 4.20 HTTP ANOMALİ (puan 4) ──
foreach ([
 '/[\x00-\x08\x0b\x0c\x0e-\x1f]/',
 '/%00|%01|%02|%03|%ff/i',
 '/(\.\.\/){5,}/i',
 '/[^\x20-\x7e]{10,}/',
 '/\b(select|union|insert)\b[\s\S]{0,30}\b(select|union|insert)\b[\s\S]{0,30}\b(select|union|insert)\b/i',
] as $d) $K[] = [3001, 'YUKSEK', 'HTTP Anomali', $d, 4];
}

// ═══ 5. ANOMALİ SKORLAMA (CRS tarzı) ═══
$toplam_puan = 0; $eslesen = [];
foreach ($K as $k) {
    if (preg_match($k[3], $HEDEF)) {
        $toplam_puan += $k[4];
        $eslesen[] = $k;
        if (count($eslesen) >= 8) break;   // 8+ kural yeter
    }
}

// uzunluk anomalisi
if (strlen($HEDEF) > 2000) { $toplam_puan += 3; $eslesen[] = [3002, 'ORTA', 'Asiri uzun istek', null, 3]; }

// ═══ 6. RATE LIMIT (30sn'de 100+ istek) ═══
$rate_puan = 0;
if (!$muaf) {
    // ★ B-18 DUZELTMESI: oku-degistir-yaz TEK kilit altinda atomik.
    // (fopen 'c+' + flock LOCK_EX → eszamanli WAF isteklerinde sayac kaybolmaz)
    $rl_yol = "$V/rate_limit.json";
    $simdi = time();
    $r = ['ilk' => $simdi, 'adet' => 0];
    $fp = @fopen($rl_yol, 'c+');
    if ($fp) {
        @flock($fp, LOCK_EX);
        $rl = @json_decode((string)stream_get_contents($fp), true) ?: [];
        $r = $rl[$ip] ?? ['ilk' => $simdi, 'adet' => 0];
        if ($simdi - ($r['ilk'] ?? $simdi) > 30) $r = ['ilk' => $simdi, 'adet' => 0];
        $r['adet'] = ($r['adet'] ?? 0) + 1;
        $rl[$ip] = $r;
        if (count($rl) > 3000) $rl = array_slice($rl, -1500, null, true);
        @ftruncate($fp, 0); @rewind($fp);
        @fwrite($fp, json_encode($rl)); @fflush($fp);
        @flock($fp, LOCK_UN); @fclose($fp);
    }
    if ($r['adet'] > 100)  { $rate_puan = 5; $eslesen[] = [4001, 'KRITIK', 'DoS / Rate limit (' . $r['adet'] . '/30sn)', null, 5]; }
    elseif ($r['adet'] > 50) { $rate_puan = 3; $eslesen[] = [4002, 'YUKSEK', 'Yogun istek (' . $r['adet'] . '/30sn)', null, 3]; }
}

// ★ B-09 DUZELTMESI: rate limit puani toplam skora EKLENIR
// (once hesaplaniyor ama toplama dahil edilmiyordu → koruma fiilen calismiyordu)
$toplam_puan += $rate_puan;

// ═══ 7. WHITELIST (ayarlar.json: waf_beyaz) ═══
$beyaz = [];
$ay = @json_decode((string)@file_get_contents("$V/ayarlar.json"), true) ?: [];
if (!empty($ay['waf_beyaz']) && is_array($ay['waf_beyaz'])) $beyaz = $ay['waf_beyaz'];
if (in_array($ip, $beyaz, true)) { $muaf = true; }

// ═══ 8. EŞİK KARARI ═══
$esik = (int)($ay['waf_esik'] ?? 5);
$engelle = (!$muaf && $toplam_puan >= $esik);

if ($engelle) {
    $birincil = $eslesen[0];
    $adlar = implode(' + ', array_slice(array_unique(array_map(fn($e) => $e[2], $eslesen)), 0, 3));

    // olay kaydet
    $kayit = [
        'zaman' => date('d.m.Y H:i:s'), 'ip' => $ip, 'kural' => $birincil[0],
        'seviye' => $birincil[1], 'puan' => $toplam_puan,
        'aciklama' => 'WAF v10: ' . $adlar . ' [' . $toplam_puan . 'p] (' . substr($_SERVER['REQUEST_URI'] ?? '', 0, 50) . ')',
        'kaynak' => 'waf', 'mitre' => (stripos($adlar, 'SQL') !== false ? 'T1190' : '')
    ];
    $yol = "$V/olaylar.json";
    $o = @json_decode((string)@file_get_contents($yol), true) ?: ['olaylar' => [], 'toplam' => 0];
    $o['olaylar'] = array_slice(array_merge([$kayit], $o['olaylar'] ?? []), 0, 500);
    $o['toplam'] = ($o['toplam'] ?? 0) + 1;
    @file_put_contents($yol, json_encode($o, JSON_UNESCAPED_UNICODE), LOCK_EX);

    // engelleme (KRITIK/YUKSEK veya yüksek puan)
    if (in_array($birincil[1], ['KRITIK', 'YUKSEK'], true) || $toplam_puan >= 8) {
        $ey = "$V/engel.json";
        $e = @json_decode((string)@file_get_contents($ey), true) ?: ['liste' => [], 'toplam' => 0];
        $var = false;
        foreach ($e['liste'] ?? [] as $x) if (($x['ip'] ?? '') === $ip) { $var = true; break; }
        if (!$var) {
            $e['liste'][] = ['ip' => $ip, 'puan' => min(100, 50 + $toplam_puan * 5),
                             'zaman' => date('d.m.Y H:i'), 'sebep' => 'WAF v10: ' . $adlar, 'kaynak' => 'WAF'];
            $e['toplam'] = count($e['liste']);
            @file_put_contents($ey, json_encode($e, JSON_UNESCAPED_UNICODE), LOCK_EX);
            @shell_exec('nft add element inet kilic kara { ' . escapeshellarg($ip) . ' } 2>/dev/null');
        }
    }

    // 403
    http_response_code(403);
    header('X-Kalkan-WAF: v10-blocked');
    header('X-Kalkan-Kural: ' . $birincil[0]);
    header('X-Kalkan-Puan: ' . $toplam_puan);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>403 — Engellendi</title>'
       . '<style>body{background:#0b1117;color:#dbe7ef;font-family:monospace;text-align:center;padding:60px}'
       . 'h1{color:#ff3b5c;font-size:46px;margin:0}.k{color:#00d68f}.s{color:#7b8f9e;font-size:13px}</style></head><body>'
       . '<h1>🚫 403</h1><p>İSTEK ENGELLENDİ</p>'
       . '<p class="k">CYBER KALKAN WAF v10 — CYBERWOLF SECURITY</p>'
       . '<p class="s">Kural #' . $birincil[0] . ' · ' . htmlspecialchars($adlar) . ' · Puan: ' . $toplam_puan
       . ' · IP: ' . htmlspecialchars($ip) . '</p></body></html>';
    exit;
}
