# Android App Feature Integration Guide (Ad Manager, VPN, UMP CMP, CPA Postback, & Storage-Based Install Tasks)

This document contains a complete integration guide for database schemas, backend APIs, and client-side (Android Kotlin with Jetpack Compose) configurations. It is designed to be fully generic. You can feed this guide along with the PHP files to any AI model to automatically implement these systems in any new Android app.

---

## 1. Database Schema Configurations (`database.sql`)
Ensure the remote MySQL database is updated with these generic table definitions:

```sql
-- 1. Screen Ad Control Configuration
CREATE TABLE IF NOT EXISTS `ad_control` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `screen_name`  VARCHAR(100) NOT NULL UNIQUE,
    `ad_type`      VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Ad Placements (Must use Google Ad Manager format '/network_code/ad_unit_code')
INSERT INTO `ad_control` (`screen_name`, `ad_type`) VALUES 
('MainActivity', 'native'),
('GamePlay', 'interstitial'),
('ClaimReward', 'rewarded'),
('UnlockLevel', 'interstitial')
ON DUPLICATE KEY UPDATE screen_name=screen_name;

-- 2. CPA Postback & Campaign Tracking Tables
CREATE TABLE IF NOT EXISTS `campaign_clicks` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `offer_id`       VARCHAR(255) NOT NULL,
    `rewardbro_uid`  VARCHAR(255) NOT NULL,
    `event_id`       VARCHAR(255) NOT NULL,
    `ip_address`     VARCHAR(45) NOT NULL,
    `user_agent`     VARCHAR(255) DEFAULT NULL,
    `status`         VARCHAR(50) DEFAULT 'pending',
    `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ip` (`ip_address`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_campaigns` (
    `device_id`      VARCHAR(255) NOT NULL,
    `rewardbro_uid`  VARCHAR(255) NOT NULL,
    `offer_id`       VARCHAR(255) NOT NULL,
    `event_id`       VARCHAR(255) NOT NULL,
    `status`         VARCHAR(50) DEFAULT 'active',
    `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`device_id`, `offer_id`),
    FOREIGN KEY (`device_id`) REFERENCES `users`(`device_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `postback_settings` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `secret_key`     VARCHAR(255) NOT NULL,
    `target_level`   INT DEFAULT 5,
    `event_id`       VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `postback_history` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `device_id`       VARCHAR(255) NOT NULL,
    `rewardbro_uid`   VARCHAR(255) NOT NULL,
    `offer_id`        VARCHAR(255) NOT NULL,
    `event_id`        VARCHAR(255) NOT NULL,
    `level`           INT NOT NULL,
    `postback_url`    TEXT NOT NULL,
    `response_status` INT DEFAULT NULL,
    `response_body`   TEXT DEFAULT NULL,
    `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Install Tasks Table (Storage checking for app installs)
CREATE TABLE IF NOT EXISTS `install_tasks` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `level`       INT NOT NULL UNIQUE,
    `required_mb` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default install task requirements
INSERT INTO `install_tasks` (`level`, `required_mb`) VALUES 
(5, 10)
ON DUPLICATE KEY UPDATE required_mb=VALUES(required_mb);
```

---

## 2. Kotlin Client-Side Model Updates (`AppSettings.kt`)
Update the settings schemas model to include general configurations, ad placements, and install tasks:

```kotlin
package com.yourpackage.app.model

import com.google.gson.annotations.SerializedName

data class ApiResponse(
    @SerializedName("status") val status: String,
    @SerializedName("data") val data: AppSettingsData
)

data class AppSettingsData(
    @SerializedName("ad_settings") val adSettings: AdSettings,
    @SerializedName("ad_control") val adControl: List<AdControl>,
    @SerializedName("reward_settings") val rewardSettings: List<RewardSetting>,
    @SerializedName("daily_reset") val dailyReset: Int = 0,
    @SerializedName("install_tasks") val installTasks: List<InstallTask> = emptyList()
)

data class AdSettings(
    @SerializedName("id") val id: Int,
    @SerializedName("interstitial_id") val interstitialId: String,
    @SerializedName("native_id") val nativeId: String,
    @SerializedName("rewarded_id") val rewardedId: String,
    @SerializedName("onesignal_app_id") val onesignalAppId: String? = null,
    @SerializedName("native_enabled") val nativeEnabled: Int = 0
)

data class AdControl(
    @SerializedName("screen_name") val screenName: String,
    @SerializedName("ad_type") val adType: String
)

data class InstallTask(
    @SerializedName("level") val level: Int,
    @SerializedName("required_mb") val requiredMb: Int
)

data class RewardSetting(
    @SerializedName("required_level") val requiredLevel: Int,
    @SerializedName("reward_amount") val rewardAmount: Int,
    @SerializedName("message") val message: String,
    @SerializedName("status") val status: Int = 1
)
```

---

## 3. Settings Manager Integration (`SettingsManager.kt`)
Include helper methods to retrieve the install task configurations:

```kotlin
package com.yourpackage.app

import android.content.Context
import com.yourpackage.app.model.AppSettingsData
import com.yourpackage.app.model.InstallTask

object SettingsManager {
    private var settings: AppSettingsData? = null

    fun getInstallTaskForLevel(level: Int): InstallTask? {
        return settings?.installTasks?.find { it.level == level }
    }

    fun getAdId(type: String): String? {
        return when (type) {
            "interstitial" -> settings?.adSettings?.interstitialId
            "rewarded" -> settings?.adSettings?.rewardedId
            "native" -> settings?.adSettings?.nativeId
            else -> null
        }
    }

    fun getAdTypeForScreen(screenName: String): String? {
        return settings?.adControl?.find { it.screenName == screenName }?.adType
    }

    fun getSettings() = settings
}
```

---

## 4. Google Ad Manager Integration (`AdManager.kt`)
Use Google Ad Manager (GAM) classes for loading and displaying advertisements. Ensure you pass `AdManagerAdRequest` payloads:

```kotlin
package com.yourpackage.app

import android.app.Activity
import android.content.Context
import android.util.Log
import com.google.android.gms.ads.*
import com.google.android.gms.ads.admanager.AdManagerAdRequest
import com.google.android.gms.ads.admanager.AdManagerInterstitialAd
import com.google.android.gms.ads.admanager.AdManagerInterstitialAdLoadCallback
import com.google.android.gms.ads.rewarded.RewardedAd
import com.google.android.gms.ads.rewarded.RewardedAdLoadCallback

object AdManager {
    private var interstitialAd: AdManagerInterstitialAd? = null
    private var rewardedAd: RewardedAd? = null
    private var isInterstitialLoading = false
    private var isRewardedLoading = false
    private const val TAG = "AdManager"

    fun loadInterstitialAd(context: Context) {
        if (interstitialAd != null || isInterstitialLoading) return
        isInterstitialLoading = true
        val adRequest = AdManagerAdRequest.Builder().build()
        val adId = SettingsManager.getAdId("interstitial") ?: "/6499/example/interstitial"
        
        AdManagerInterstitialAd.load(context.applicationContext, adId, adRequest, object : AdManagerInterstitialAdLoadCallback() {
            override fun onAdFailedToLoad(adError: LoadAdError) {
                isInterstitialLoading = false
                interstitialAd = null
                Log.e(TAG, "Interstitial Failed: ${adError.message}")
            }

            override fun onAdLoaded(ad: AdManagerInterstitialAd) {
                isInterstitialLoading = false
                interstitialAd = ad
                Log.d(TAG, "Interstitial Loaded Successfully")
            }
        })
    }

    fun showInterstitialAd(activity: Activity, onAdDismissed: () -> Unit) {
        if (interstitialAd != null) {
            interstitialAd?.fullScreenContentCallback = object : FullScreenContentCallback() {
                override fun onAdDismissedFullScreenContent() {
                    interstitialAd = null
                    loadInterstitialAd(activity)
                    onAdDismissed()
                }
                override fun onAdFailedToShowFullScreenContent(adError: AdError) {
                    interstitialAd = null
                    loadInterstitialAd(activity)
                    onAdDismissed()
                }
            }
            interstitialAd?.show(activity)
        } else {
            loadInterstitialAd(activity)
            onAdDismissed()
        }
    }

    fun loadRewardedAd(context: Context) {
        if (rewardedAd != null || isRewardedLoading) return
        isRewardedLoading = true
        val adRequest = AdManagerAdRequest.Builder().build()
        val adId = SettingsManager.getAdId("rewarded") ?: "/6499/example/rewarded"

        RewardedAd.load(context.applicationContext, adId, adRequest, object : RewardedAdLoadCallback() {
            override fun onAdFailedToLoad(adError: LoadAdError) {
                isRewardedLoading = false
                rewardedAd = null
            }
            override fun onAdLoaded(ad: RewardedAd) {
                isRewardedLoading = false
                rewardedAd = ad
            }
        })
    }

    fun showRewardedAd(activity: Activity, onRewardEarned: () -> Unit, onAdClosed: () -> Unit) {
        var earned = false
        if (rewardedAd != null) {
            rewardedAd?.fullScreenContentCallback = object : FullScreenContentCallback() {
                override fun onAdDismissedFullScreenContent() {
                    rewardedAd = null
                    loadRewardedAd(activity)
                    if (earned) onRewardEarned()
                    onAdClosed()
                }
                override fun onAdFailedToShowFullScreenContent(adError: AdError) {
                    rewardedAd = null
                    loadRewardedAd(activity)
                    onAdClosed()
                }
            }
            rewardedAd?.show(activity) { earned = true }
        } else {
            loadRewardedAd(activity)
            onRewardEarned()
            onAdClosed()
        }
    }
}
```

---

## 5. VPN Protection System

### 5.1 Detector Utility (`VpnDetector.kt`)
Combines system network capability checking and active network interface scanning:

```kotlin
package com.yourpackage.app

import android.content.Context
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.os.Build
import java.net.NetworkInterface
import java.util.Collections

object VpnDetector {
    fun isVpnActive(context: Context): Boolean {
        return isVpnByNetworkCapabilities(context) || isVpnByNetworkInterfaces()
    }

    private fun isVpnByNetworkCapabilities(context: Context): Boolean {
        val connectivityManager = context.getSystemService(Context.CONNECTIVITY_SERVICE) as? ConnectivityManager
            ?: return false
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            val activeNetwork = connectivityManager.activeNetwork ?: return false
            val capabilities = connectivityManager.getNetworkCapabilities(activeNetwork) ?: return false
            return capabilities.hasTransport(NetworkCapabilities.TRANSPORT_VPN)
        } else {
            val networks = connectivityManager.allNetworks
            for (network in networks) {
                val capabilities = connectivityManager.getNetworkCapabilities(network)
                if (capabilities != null && capabilities.hasTransport(NetworkCapabilities.TRANSPORT_VPN)) {
                    return true
                }
            }
        }
        return false
    }

    private fun isVpnByNetworkInterfaces(): Boolean {
        try {
            val interfaces = Collections.list(NetworkInterface.getNetworkInterfaces())
            for (networkInterface in interfaces) {
                if (networkInterface.isUp) {
                    val name = networkInterface.name.lowercase()
                    if (name.contains("tun") || name.contains("ppp") || name.contains("p2p") || name.contains("tap") || name.contains("vpn")) {
                        return true
                    }
                }
            }
        } catch (e: Exception) {}
        return false
    }
}
```

### 5.2 Early Startup Check (`MainActivity.kt`)
Perform a check inside `onCreate` before initializing any server connections or settings:
```kotlin
override fun onCreate(savedInstanceState: Bundle?) {
    super.onCreate(savedInstanceState)
    if (VpnDetector.isVpnActive(this)) {
        setContent { AppMainNavigation() }
        return
    }
    // Proceed to sync settings and initialize the application...
}
```

### 5.3 UI Background Checking & VPN Blocked Screen (Jetpack Compose)
Run a background routine checking VPN status and display a blocking screen using standard Material components:

```kotlin
// 1. Navigation state enum
enum class AppScreen { Splash, Home, VpnBlocked }

// 2. State loop in Main Compose Container
var currentScreen by remember { 
    mutableStateOf(if (VpnDetector.isVpnActive(context)) AppScreen.VpnBlocked else AppScreen.Splash) 
}

LaunchedEffect(currentScreen) {
    if (currentScreen != AppScreen.VpnBlocked) {
        while (true) {
            if (VpnDetector.isVpnActive(context)) {
                currentScreen = AppScreen.VpnBlocked
                break
            }
            delay(2000)
        }
    }
}

// 3. UI rendering block
when (currentScreen) {
    AppScreen.VpnBlocked -> {
        GenericVpnBlockedScreen(onRetry = {
            if (!VpnDetector.isVpnActive(context)) {
                currentScreen = AppScreen.Splash
            }
        })
    }
    // other screens...
}

// 4. Generic Block Screen Composable
@Composable
fun GenericVpnBlockedScreen(onRetry: () -> Unit) {
    Box(modifier = Modifier.fillMaxSize().background(Color(0xFF121212)), contentAlignment = Alignment.Center) {
        Column(
            modifier = Modifier.padding(32.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            Icon(
                imageVector = Icons.Default.Warning,
                contentDescription = "VPN Warning",
                tint = Color.Red,
                modifier = Modifier.size(72.dp)
            )
            Spacer(Modifier.height(24.dp))
            Text("VPN DETECTED!", color = Color.White, fontSize = 28.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(12.dp))
            Text(
                text = "Please disable your VPN, proxy, or virtual private network to continue using this application.",
                color = Color.LightGray,
                fontSize = 16.sp,
                textAlign = TextAlign.Center
            )
            Spacer(Modifier.height(32.dp))
            Button(
                onClick = onRetry,
                colors = ButtonDefaults.buttonColors(containerColor = Color.Red)
            ) {
                Text("RETRY", color = Color.White, fontWeight = FontWeight.Bold)
            }
        }
    }
}
```

---

## 6. UMP CMP Consent Form Debug Configuration (`ConsentManager.kt`)
Add EEA debug geography settings so consent forms can be easily triggered and verified in any country:

```kotlin
import com.google.android.ump.ConsentDebugSettings

fun requestConsent(activity: Activity, onComplete: () -> Unit) {
    val debugSettings = ConsentDebugSettings.Builder(activity)
        .setDebugGeography(ConsentDebugSettings.DebugGeography.DEBUG_GEOGRAPHY_EEA)
        .build()

    val params = ConsentRequestParameters.Builder()
        .setConsentDebugSettings(debugSettings)
        .setTagForUnderAgeOfConsent(false)
        .build()
        // ...
}
```

---

## 7. Dynamic Install Tasks & Space Difference Check (Generic Compose)

To verify that the user installed an app after viewing an ad, we check the device's free storage space before and after the ad click event.

### 7.1 Completion Interceptor
When a level, page, or target action is completed, intercept the flow if a task exists:
```kotlin
val installTask = SettingsManager.getInstallTaskForLevel(completedLevel)
if (installTask != null) {
    showImportantTaskPopup = true
} else {
    // Proceed with standard unlock/progression flow
}
```

### 7.2 Storage Space Calculation Helper
Uses Android's `StatFs` api to compute available device storage space in Megabytes:
```kotlin
import android.os.Environment
import android.os.StatFs

fun getFreeInternalStorageMB(): Long {
    return try {
        val path = Environment.getDataDirectory()
        val stat = StatFs(path.path)
        val blockSize = stat.blockSizeLong
        val availableBlocks = stat.availableBlocksLong
        (availableBlocks * blockSize) / (1024L * 1024L)
    } catch (e: Exception) { 
        0L 
    }
}
```

### 7.3 App Resume Check (Inside Activity Lifecycle Observer)
Check if a task is in progress when the app resumes. If the user installed the app, the free space decreases (meaning `storageBefore - storageAfter` increases).

```kotlin
// Inside Lifecycle ON_RESUME:
val hasActiveTask = prefs.getBoolean("install_task_active", false)
if (hasActiveTask) {
    // Clear flag immediately
    prefs.edit().putBoolean("install_task_active", false).apply()
    
    val storageBefore = prefs.getLong("install_task_storage_before", 0L)
    val requiredMb = prefs.getInt("install_task_required_mb", 0)
    val taskLevel = prefs.getInt("install_task_level", 0)
    
    val storageAfter = getFreeInternalStorageMB()
    val usedMbDifference = storageBefore - storageAfter
    
    if (usedMbDifference >= requiredMb) {
        // Success: Unlock progression and proceed
        onTaskSuccess(taskLevel)
    } else {
        // Fail: Show failure dialog
        showTaskFailedDialog = true
    }
}
```

### 7.4 Generic Popup Composables
Generic dialogs using standard Compose Material components that can run in any app configuration:

```kotlin
@Composable
fun GenericImportantTaskPopup(
    requiredMb: Int,
    onInstallClick: () -> Unit,
    onCancel: () -> Unit
) {
    AlertDialog(
        onDismissRequest = {},
        icon = { Icon(Icons.Default.Info, contentDescription = null, tint = Color(0xFFFF9800), modifier = Modifier.size(48.dp)) },
        title = { Text("IMPORTANT TASK", fontWeight = FontWeight.Bold) },
        text = { Text("To proceed, you must install the promoted app from the following advertisement. (Requires at least $requiredMb MB of free space)") },
        confirmButton = {
            Button(
                onClick = onInstallClick,
                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF4CAF50))
            ) {
                Text("Install & Unlock 📥", color = Color.White)
            }
        },
        dismissButton = {
            TextButton(onClick = onCancel) {
                Text("Cancel")
            }
        }
    )
}

@Composable
fun GenericTaskFailedPopup(
    requiredMb: Int,
    onOkay: () -> Unit
) {
    AlertDialog(
        onDismissRequest = {},
        icon = { Icon(Icons.Default.Close, contentDescription = null, tint = Color.Red, modifier = Modifier.size(48.dp)) },
        title = { Text("TASK FAILED!", fontWeight = FontWeight.Bold, color = Color.Red) },
        text = { Text("The app was not installed or does not meet the required $requiredMb MB size threshold. Please try again to unlock.") },
        confirmButton = {
            Button(
                onClick = onOkay,
                colors = ButtonDefaults.buttonColors(containerColor = Color.Red)
            ) {
                Text("Okay", color = Color.White)
            }
        }
    )
}
```

### 7.5 Triggering the Ad with Stored Storage Check
When the user clicks "Install & Unlock":

```kotlin
val storageBefore = getFreeInternalStorageMB()
prefs.edit()
    .putBoolean("install_task_active", true)
    .putLong("install_task_storage_before", storageBefore)
    .putInt("install_task_required_mb", requiredMb)
    .putInt("install_task_level", currentLevel)
    .apply()

// Dynamically fetch configured ad type (interstitial or rewarded)
val adType = SettingsManager.getAdTypeForScreen("UnlockLevel")
if (adType == "rewarded") {
    AdManager.showRewardedAd(activity, {}, {})
} else {
    AdManager.showInterstitialAd(activity) {}
}
```

---

## 8. Cryptographic Security & Device Integrity Gateway

Communications are secured with AES-256-CBC encryption and device integrity checks to block virtual emulators and request tampering.

### 8.1 Request Encryption & Cryptography Setup (AES + RSA)

Communications use a hybrid encryption architecture combining AES symmetric encryption for request bodies and RSA asymmetric encryption for high-security parameters.

1. **Outer Parameter (AES-256-CBC)**:
   - All parameters are wrapped inside a single encrypted `spoint` POST parameter:
     `spoint = BASE64(AES_ENCRYPT(json_payload))`
   - The device sends its unique ID as `did` (plain text) so the server can look up that device's session keys (`device_main_key` and `device_master_secret` stored in `device_keys` table) to decrypt `spoint`.

2. **Inner High-Security Parameter Protection (RSA-2048)**:
   - When the client payload contains `"rsa_enabled" = "1"`, high-security fields (including `device_id`, `key_id`, and `milisecond`) are individually encrypted on the device using the RSA public key.
   - On the server, after decrypting the outer AES `spoint` envelope, the PHP gateway checks if `$rsa === true`.
   - If enabled, the server fetches the private key (`rsa1_private_key`) from `device_keys` and decrypts these sensitive fields using `openssl_private_decrypt()`.
   - This ensures that even if the AES session keys are compromised, critical authentication tokens and timestamps remain completely secure under RSA-2048 protection.

3. **Replay Protection**:
   - The payload contains a `milisecond` timestamp parameter. The server decrypts it and compares it with the server's time. If the difference is greater than 60 seconds (`REQUEST_EXPIRY_MS`), the request is rejected as expired.

### 8.2 Device Integrity Verification
* **Signature checking**: The server verifies that the application package name equals the configured bundle id and that the signature SHA-256 matches your release signing certificate.
* **Anti-Emulator checks**: The server checks build metrics (`device_info` payload) for virtualized drivers (e.g. `goldfish`, `qemu`, `vbox`, `nox`) and blocks execution if emulator indicators are present.
