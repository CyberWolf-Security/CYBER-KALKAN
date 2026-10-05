<?php
/**
 * CYBER KALKAN — WAF ROUTER (v2: debug ile)
 */
error_log("[ROUTER] istek: " . ($_SERVER['REQUEST_URI'] ?? '?') . " ip=" . ($_SERVER['REMOTE_ADDR'] ?? '?'));
$r = require '/opt/siber-kalkan/waf.php';
error_log("[ROUTER] waf sonrasi devam edildi");
return false;
