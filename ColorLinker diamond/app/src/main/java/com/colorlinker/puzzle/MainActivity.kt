package com.colorlinker.puzzle

import android.os.Bundle
import android.util.Log
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.core.view.WindowCompat
import androidx.core.view.WindowInsetsCompat
import androidx.core.view.WindowInsetsControllerCompat
import com.secmtp.sdk.core.api.ATSDK

import com.onesignal.OneSignal
import com.onesignal.debug.LogLevel
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class MainActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        // 1. Enable Edge-to-Edge first
        enableEdgeToEdge()
        
        // 2. Hide System Bars properly
        hideSystemBars()

        if (VpnDetector.isVpnActive(this)) {
            setContent {
                ColorLinkerApp()
            }
            return
        }

        ReferrerTracker.initialize(this) {
            // Initialize Settings from API/Cache after ReferrerTracker finishes
            SettingsManager.init(this) {
                UserManager.registerDevice(this)
                runOnUiThread {
                    initializeOneSignal()
                    ConsentManager.requestConsent(this) {
                        initializeAds()
                    }
                }
            }
        }
        
        SoundManager.init(this)

        setContent {
            ColorLinkerApp()
        }
    }


    override fun onResume() {
        super.onResume()
        hideSystemBars()
        SoundManager.onAppResume()
    }

    override fun onPause() {
        super.onPause()
        SoundManager.onAppPause()
    }

    override fun onWindowFocusChanged(hasFocus: Boolean) {
        super.onWindowFocusChanged(hasFocus)
        if (hasFocus) {
            hideSystemBars()
        }
    }

    private fun hideSystemBars() {
        val windowInsetsController = WindowCompat.getInsetsController(window, window.decorView)
        windowInsetsController.systemBarsBehavior = WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
        windowInsetsController.hide(WindowInsetsCompat.Type.systemBars())
    }

    private fun initializeOneSignal() {
        val appId = SettingsManager.settings?.adSettings?.onesignalAppId
        if (!appId.isNullOrBlank()) {
            Log.d("MainActivity", "Initializing OneSignal with App ID: $appId")
            OneSignal.Debug.logLevel = LogLevel.WARN
            OneSignal.initWithContext(this, appId)
            CoroutineScope(Dispatchers.IO).launch {
                OneSignal.Notifications.requestPermission(false)
            }
        }
    }

    private fun initializeAds() {
        ATSDK.setNetworkLogDebug(false)
        val appId = SettingsManager.settings?.adSettings?.toponAppId ?: AdManager.TOPON_APP_ID
        val appKey = SettingsManager.settings?.adSettings?.toponAppKey ?: AdManager.TOPON_APP_KEY
        ATSDK.init(this, appId, appKey)
        AdManager.loadSplashAd(this)
        AdManager.loadInterstitialAd(this)
        AdManager.loadRewardedAd(this)
    }

    override fun onDestroy() {
        super.onDestroy()
        SoundManager.release()
    }
}
