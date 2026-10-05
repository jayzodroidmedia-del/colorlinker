<?php
// Installer verification whitelist
$ALLOWED_INSTALLERS = [
    'com.android.vending', 'com.huawei.appmarket', 'com.sec.android.app.samsungapps',
    'com.xiaomi.market', 'com.oppo.market', 'com.heytap.market', 'com.bbk.appstore',
    'com.android.shell', 'com.android.packageinstaller', 'com.google.android.packageinstaller', 'unknown' // Added 'unknown' and 'com.google.android.packageinstaller' to allow local debugging and sideloading
];

function isRealDevice($rawJson, $expectedPkg, $deviceId, &$reason) {
    global $ALLOWED_INSTALLERS;
    $d = json_decode($rawJson, true);
    if (!$d) { $reason = "Invalid data"; return false; }

    $pkg     = $d['p'] ?? null;
    $process = $d['pn'] ?? null;
    $dataPath = $d['dp'] ?? '';
    $androidId = $d['aid'] ?? null;
    $fp      = strtolower($d['fp'] ?? '');
    $brand   = strtolower($d['br'] ?? '');
    $model   = strtolower($d['md'] ?? '');
    $mfr     = strtolower($d['mf'] ?? '');
    $product = strtolower($d['pd'] ?? '');
    $hardware = strtolower($d['hw'] ?? '');
    $board   = strtolower($d['bd'] ?? '');
    $bootloader = strtolower($d['bl'] ?? '');
    $tags    = strtolower($d['tg'] ?? '');
    $abis    = $d['ab'] ?? [];
    $bt      = isset($d['bt']) ? floatval($d['bt']) : null;
    $installer = $d['ins'] ?? 'unknown';
    $filesDir = $d['fd'] ?? '';
    $sensors = intval($d['sc'] ?? 0);
    $props   = $d['gp'] ?? [];

    $debuggable = $props['debuggable'] ?? '';
    $secure   = $props['secure'] ?? '';
    $qemu     = $props['qemu'] ?? '';
    $qemuHw   = $props['mainkeys'] ?? '';
    $qemud    = $props['qemud'] ?? '';
    $baseband = $props['baseband'] ?? '';
    $chipname = $props['chipname'] ?? '';
    $gmsVer   = $props['gmsversion'] ?? '';
    $chars    = $props['characteristics'] ?? '';

    // Check 1: Android ID match
    if ($deviceId !== $androidId) { $reason = "A1 - ID Mismatch"; return false; }
    
    // Check 2: Package Name match
    if (empty($pkg) || $pkg !== $expectedPkg) { $reason = "A2 - Package Mismatch"; return false; }
    
    // Check 3: Process Name match
    if ($process !== $expectedPkg) { $reason = "A3 - Process Mismatch"; return false; }

    // Check 4: Data Directory structure check
    if (!empty($dataPath)) {
        $ok = false;
        foreach (["/data/user/0/{$expectedPkg}/","/data/user_de/0/{$expectedPkg}/","/data/data/{$expectedPkg}/"] as $vp)
            if (strpos($dataPath, $vp) === 0) { $ok = true; break; }
        if (!$ok && strpos($dataPath, "/mnt/expand/") === 0 && strpos($dataPath, "/user/0/{$expectedPkg}/") !== false) $ok = true;
        if (!$ok) { $reason = "A4 - Invalid Data Path"; return false; }
    }
    
    // Check 5: Multi-user files directory path verification
    if (!empty($filesDir) && strpos($filesDir, "/user/") !== false && strpos($filesDir, "/user/0/") === false)
    { $reason = "A5 - Sandbox check failed"; return false; }

    // Check 6: Root signature & developer mode checks
    $rs = 0;
    if (strpos($tags, 'test-keys') !== false) $rs += 2;
    if ($debuggable === '1') $rs += 2;
    if ($secure === '0') $rs += 2;
    if ($rs >= 3) { $reason = "B1 - Root/Debug detected"; return false; }

    // Check 7: Emulator characteristics checks
    $es = 0;
    if (!empty($qemu) && $qemu !== '0') $es += 3;
    if (!empty($qemuHw)) $es += 2;
    if (!empty($qemud)) $es += 2;
    if (empty($baseband)) $es += 1;
    if (empty($chipname)) $es += 1;
    if (empty($gmsVer)) $es += 1;
    if ($es >= 3) { $reason = "C1 - QEMU detected"; return false; }

    // Check 8: Virtual machines / common emulators fingerprint string matching
    foreach (['generic','vbox','sdk','emulator','goldfish','ranchu'] as $b)
        if (strpos($fp, $b) !== false) { $reason = "C2 - VM fingerprint"; return false; }
    if (in_array($brand, ['generic','unknown','alps','vbox'])) { $reason = "C3 - VM brand"; return false; }
    foreach (['goldfish','ranchu','vbox86','nox'] as $b)
        if (strpos($hardware, $b) !== false) { $reason = "C4 - VM hardware"; return false; }
    foreach (['sdk','emulator','android sdk','droid4x'] as $b)
        if (strpos($model, $b) !== false) { $reason = "C5 - VM model"; return false; }
    foreach (['genymotion','genymobile'] as $b)
        if (strpos($mfr, $b) !== false) { $reason = "C6 - VM manufacturer"; return false; }
    foreach (['sdk','emulator','vbox','nox'] as $b)
        if (strpos($product, $b) !== false) { $reason = "C7 - VM product"; return false; }

    // Check 9: CPU architecture (Emulators are mostly x86)
    if (is_array($abis)) foreach ($abis as $abi) if (stripos($abi, 'x86') !== false) { $reason = "D1 - x86 architecture"; return false; }
    
    // Check 10: Bootloader and Board verification (Relaxed for real devices)
    // if (empty($bootloader) || $bootloader === 'unknown') { $reason = "D2 - Empty bootloader"; return false; }
    // if (empty($board) || $board === 'unknown') { $reason = "D3 - Empty board"; return false; }
    
    // Check 11: Sensor count verification (emulators have almost zero sensors)
    if ($sensors < 3) { $reason = "D4 - Low sensor count"; return false; }
    
    // Check 12: Installer source verification
    if (!in_array($installer, $ALLOWED_INSTALLERS)) { $reason = "E1 - Disallowed Installer: " . $installer; return false; }
    
    // Check 13: Emulator characteristics property
    if (strpos($chars, 'emulator') !== false) { $reason = "E2 - Characteristics check"; return false; }

    // Check 14: Sensor & Hardware consistency
    $ss = 0;
    if ($bt !== null && ($bt == 0.0 || $bt == 25.0)) $ss++;
    if ($sensors < 5) $ss++;
    if (empty($baseband)) $ss++;
    if ($ss >= 3) { $reason = "F1 - Inconsistent sensors/hw"; return false; }

    return true;
}
?>
