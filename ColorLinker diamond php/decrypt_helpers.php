<?php
function deriveFieldKey($fieldName, $masterSecret = null) {
    if (!$masterSecret) $masterSecret = HARDCODED_MASTER_SECRET;
    return substr(hash_hmac('sha256', $fieldName, $masterSecret), 0, 32);
}

function aesDecrypt($encrypted, $secretKey) {
    if (strlen($secretKey) != 32) throw new Exception("Key must be 32 chars");
    $d = openssl_decrypt($encrypted, 'aes-256-cbc', $secretKey, 0, substr($secretKey, 0, 16));
    if ($d === false) throw new Exception('AES decrypt failed');
    return $d;
}

$indexList   = "*?**?v*?^*?N*??*?K*?=*?!*?+*?~*K@*Kw*K#*Kx*K**Kv*K^*KN*K?*KK*K=*K!*K+*K~*=@*=w*=#*=x*=**=v*=^*=N*=?*=K*==*=!*=+*=~*!@*!w*!#*!x*!**!v*!^*!N*!?*!K*!=*!!*!+*!~*+@*+w*+#*+x*+**+v*+^*+N*+?*+K*+=*+!*++*+~*~@*~w*~#*~x*~**~v*~^*~N*~?*~K*~=*~!*~+*~~v@@v@wv@#v@xv@*v@vv@^v@Nv@?v@Kv@=v@!v@+v@~vw@vwwvw#vwxvw*vwv";
$myAllString = "ABCDEFGHIJKLMNOPQRSTUVWXZabcdefghijklmnopqrstuvwxyz0123456789\";?:={}() +-*/.!_'&|$,[]";

function decryptMyString($code) {
    global $indexList, $myAllString;
    $code = trim($code); $out = '';
    for ($i = 0; $i < strlen($code); $i += 3) {
        $chunk = substr($code, $i, 3);
        $pos = strpos($indexList, $chunk);
        if ($pos === false) continue;
        $val = $pos / 3;
        if ($val < 8) {
            $sum = 0;
            for ($j = 1; $j < 10; $j++) {
                if ($j >= $val && $j < $val + 3) {
                    $sp = strpos($indexList, substr($code, $i + ($j * 3), 3));
                    if ($sp !== false) $sum += $sp / 3;
                }
            }
            if ($sum > 0 && isset($myAllString[$sum - 1])) $out .= $myAllString[$sum - 1];
            $i += 27;
        }
    }
    return $out;
}

function getDeviceKeys($deviceId) {
    $s = getDB()->prepare("SELECT * FROM device_keys WHERE device_id = ? AND is_active = 1 AND is_registered = 1");
    $s->execute([$deviceId]);
    return $s->fetch(PDO::FETCH_ASSOC);
}

function decryptPayload($data, $mainKey, $masterSecret, $aesOnlyFields = []) {
    $json = aesDecrypt($data, $mainKey);
    $fields = json_decode($json, true);
    if (!$fields) throw new Exception('JSON parse failed');
    
    // First, let's decrypt all fields to their AES-decrypted state
    $decryptedRaw = [];
    foreach ($fields as $fn => $ev) {
        try {
            $decryptedRaw[$fn] = aesDecrypt($ev, deriveFieldKey($fn, $masterSecret));
        } catch (Exception $e) { $decryptedRaw[$fn] = null; }
    }
    
    // Check if rsa_enabled was sent and set to '1'
    $rsaEnabled = false;
    if (isset($decryptedRaw['rsa_enabled']) && $decryptedRaw['rsa_enabled'] !== null) {
        $rsaEnabledDec = decryptMyString($decryptedRaw['rsa_enabled']);
        if ($rsaEnabledDec === '1') {
            $rsaEnabled = true;
        }
    }
    
    $result = [];
    foreach ($decryptedRaw as $fn => $ce) {
        if ($ce === null) {
            $result[$fn] = null;
            continue;
        }
        
        $isRsaField = $rsaEnabled && ($fn === 'device_id' || $fn === 'key_id' || $fn === 'milisecond');
        
        if (in_array($fn, $aesOnlyFields) || $isRsaField) {
            $result[$fn] = $ce;
        } else {
            $result[$fn] = decryptMyString($ce);
        }
    }

    return $result;
}
?>
