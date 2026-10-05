package com.colorlinker.puzzle

import android.content.Context
import android.util.Log
import com.google.gson.Gson
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import com.colorlinker.puzzle.model.ApiResponse
import com.colorlinker.puzzle.model.AppSettingsData
import com.colorlinker.puzzle.model.InstallTask
import okhttp3.*
import java.io.IOException

object SettingsManager {
    private const val PREFS_NAME = "app_settings_prefs"
    private const val KEY_SETTINGS = "cached_settings"
    private const val TAG = "SettingsManager"

    var settings by mutableStateOf<AppSettingsData?>(null)
        private set
        
    var rawJsonResponse by mutableStateOf<String?>(null)
        private set

    var apiStatus by mutableStateOf("INITIALIZING")
        private set

    var isServerAvailable by mutableStateOf(false)

    private val gson = Gson()

    fun getInstallTaskForLevel(level: Int): InstallTask? {
        return settings?.installTasks?.find { it.level == level }
    }

    fun init(context: Context, onComplete: () -> Unit = {}) {
        apiStatus = "LOADING_CACHE"
        // Load from cache first to have something immediately
        val cached = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .getString(KEY_SETTINGS, null)
        
        if (cached != null) {
            rawJsonResponse = "CACHED:\n$cached"
            settings = getCachedSettings(context)
        }
        
        // Fetch from API to update
        fetchSettings(context, onComplete)
    }

    private fun fetchSettings(context: Context, onComplete: () -> Unit) {
        apiStatus = "FETCHING_API"
        GameSecurity.postSecureRequest(context, "get_settings", emptyMap()) { success, body ->
            if (success && body != null) {
                try {
                    val apiResponse = gson.fromJson(body, ApiResponse::class.java)
                    if (apiResponse.status == "success") {
                        settings = apiResponse.data
                        rawJsonResponse = "FRESH_API:\n$body"
                        saveSettingsToCache(context, body)
                        apiStatus = "SUCCESS"
                        isServerAvailable = true
                        Log.d(TAG, "Settings successfully updated from API. Raw Body: $body")
                    } else {
                        apiStatus = "API_ERROR: status=${apiResponse.status}"
                        isServerAvailable = false
                    }
                } catch (e: Exception) {
                    apiStatus = "PARSE_ERROR: ${e.message}"
                    isServerAvailable = false
                    Log.e(TAG, "Error parsing API JSON: ${e.message}")
                }
            } else {
                apiStatus = "ERROR: $body"
                isServerAvailable = false
                Log.e(TAG, "Failed to fetch settings: $body")
            }
            onComplete()
        }
    }


    private fun saveSettingsToCache(context: Context, json: String) {
        context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .edit()
            .putString(KEY_SETTINGS, json)
            .apply()
    }

    private fun getCachedSettings(context: Context): AppSettingsData? {
        val json = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .getString(KEY_SETTINGS, null) ?: return null
        return try {
            val apiResponse = gson.fromJson(json, ApiResponse::class.java)
            apiResponse.data
        } catch (e: Exception) {
            null
        }
    }

    fun getAdId(type: String): String? {
        return when (type) {
            "splash", "app_open" -> settings?.adSettings?.toponSplashId ?: "b6a6dcd4790001"
            "interstitial" -> settings?.adSettings?.toponInterstitialId ?: settings?.adSettings?.interstitialId ?: "n6abf9442e8673"
            "rewarded" -> settings?.adSettings?.toponRewardedId ?: settings?.adSettings?.rewardedId ?: "n6abf9427b3f3f"
            "native" -> settings?.adSettings?.toponNativeId ?: settings?.adSettings?.nativeId ?: "n6abf93fa45aa7"
            else -> null
        }
    }

    fun getAdTypeForScreen(screenName: String): String {
        val mappedKey = when (screenName) {
            "next_level", "tester_next_level", "level_completed" -> "level_completed_interstitial"
            "restart", "level_restart" -> "level_restart_interstitial"
            "splash_open", "splash" -> "app_open"
            "game_over_resume", "resume_lives", "free_lives" -> "free_lives_rewarded"
            "hint", "free_hint" -> "free_hint_rewarded"
            else -> screenName
        }
        val type = settings?.adControl?.find { it.screenName == mappedKey || it.screenName == screenName }?.adType
        Log.d(TAG, "getAdTypeForScreen: screenName='$screenName', mapped='$mappedKey', resolvedType='$type', allControls=${settings?.adControl}")
        return type ?: if (mappedKey.contains("interstitial") || mappedKey == "next_level") "interstitial" else if (mappedKey.contains("rewarded") || mappedKey.contains("lives") || mappedKey.contains("hint")) "rewarded" else "none"
    }

    fun isNativeEnabled(screenName: String): Boolean {
        val globalEnabled = settings?.adSettings?.nativeEnabled == 1
        val mappedScreenName = if (screenName == "quiz") "MainActivity" else screenName
        val screenAdType = getAdTypeForScreen(mappedScreenName)
        val finalResult = globalEnabled && screenAdType == "native"
        Log.d(TAG, "isNativeEnabled: screenName='$screenName', mapped='$mappedScreenName', globalEnabled=$globalEnabled, screenAdType='$screenAdType', finalResult=$finalResult")
        return finalResult
    }

    fun isRewardsEnabled(): Boolean {
        return (settings?.rewardsEnabled ?: settings?.adSettings?.rewardsEnabled ?: 1) == 1
    }

    fun getRewardSettings(): List<com.colorlinker.puzzle.model.RewardSetting> {
        return settings?.rewardSettings ?: emptyList()
    }

    fun registerTester(
        context: Context,
        name: String,
        mobile: String,
        email: String,
        onResult: (Boolean, String?) -> Unit
    ) {
        val payload = mapOf(
            "name" to name,
            "mobile" to mobile,
            "email" to email
        )
        GameSecurity.postSecureRequest(context, "register_tester", payload) { success, body ->
            if (success && body != null) {
                onResult(true, body)
            } else {
                onResult(false, body ?: "Failed to register")
            }
        }
    }

    fun getTesterStatus(
        context: Context,
        onResult: (Boolean, com.colorlinker.puzzle.model.TesterStatusResponse?, String?) -> Unit
    ) {
        GameSecurity.postSecureRequest(context, "get_tester_status", emptyMap()) { success, body ->
            if (success && body != null) {
                try {
                    val statusRes = gson.fromJson(body, com.colorlinker.puzzle.model.TesterStatusResponse::class.java)
                    onResult(true, statusRes, null)
                } catch (e: Exception) {
                    onResult(false, null, e.message)
                }
            } else {
                onResult(false, null, body ?: "Failed to get tester status")
            }
        }
    }

    fun submitTesterReview(
        context: Context,
        dayNumber: Int,
        rating: Int,
        reviewText: String,
        onResult: (Boolean, String?) -> Unit
    ) {
        val payload = mapOf(
            "day_number" to dayNumber.toString(),
            "rating" to rating.toString(),
            "feedback_text" to reviewText
        )
        GameSecurity.postSecureRequest(context, "submit_tester_review", payload) { success, body ->
            if (success && body != null) {
                onResult(true, null)
            } else {
                onResult(false, body ?: "Failed to submit review")
            }
        }
    }

    fun submitTesterClaim(
        context: Context,
        method: String,
        account: String,
        onResult: (Boolean, String?) -> Unit
    ) {
        val payload = mapOf(
            "method" to method,
            "account" to account
        )
        GameSecurity.postSecureRequest(context, "submit_tester_reward_claim", payload) { success, body ->
            if (success && body != null) {
                onResult(true, null)
            } else {
                onResult(false, body ?: "Failed to submit claim")
            }
        }
    }

    fun syncTesterLevel(
        context: Context,
        dayNumber: Int,
        levelNumber: Int,
        onResult: (Boolean, Int, Int, Boolean, String?) -> Unit
    ) {
        val payload = mapOf(
            "day_number" to dayNumber.toString(),
            "level_number" to levelNumber.toString()
        )
        GameSecurity.postSecureRequest(context, "sync_tester_level", payload) { success, body ->
            if (success && body != null) {
                try {
                    val jsonObj = org.json.JSONObject(body)
                    val completed = jsonObj.optInt("levels_completed", 0)
                    val target = jsonObj.optInt("target_levels", 10)
                    val isDayDone = jsonObj.optBoolean("is_day_completed", completed >= target)
                    onResult(true, completed, target, isDayDone, null)
                } catch (e: Exception) {
                    onResult(true, 0, 0, false, null)
                }
            } else {
                onResult(false, 0, 0, false, body ?: "Failed to sync tester level")
            }
        }
    }

    fun resetTesterCycle(
        context: Context,
        onResult: (Boolean, String?) -> Unit
    ) {
        GameSecurity.postSecureRequest(context, "reset_tester_cycle", emptyMap()) { success, body ->
            if (success && body != null) {
                onResult(true, null)
            } else {
                onResult(false, body ?: "Failed to reset cycle")
            }
        }
    }

    fun endTesterProgram(
        context: Context,
        onResult: (Boolean, String?) -> Unit
    ) {
        GameSecurity.postSecureRequest(context, "end_tester_program", emptyMap()) { success, body ->
            if (success && body != null) {
                onResult(true, null)
            } else {
                onResult(false, body ?: "Failed to end tester program")
            }
        }
    }

    fun fetchRewardHistory(
        context: Context,
        onResult: (Boolean, List<com.colorlinker.puzzle.model.RewardHistoryItem>, String?) -> Unit
    ) {
        GameSecurity.postSecureRequest(context, "get_reward_history", emptyMap()) { success, body ->
            if (success && body != null) {
                try {
                    val jsonObj = org.json.JSONObject(body)
                    val historyArray = jsonObj.optJSONArray("history") ?: jsonObj.optJSONArray("claims")
                    val list = mutableListOf<com.colorlinker.puzzle.model.RewardHistoryItem>()
                    if (historyArray != null) {
                        for (i in 0 until historyArray.length()) {
                            val itemObj = historyArray.getJSONObject(i)
                            val item = gson.fromJson(itemObj.toString(), com.colorlinker.puzzle.model.RewardHistoryItem::class.java)
                            list.add(item)
                        }
                    }
                    onResult(true, list, null)
                } catch (e: Exception) {
                    onResult(false, emptyList(), e.message)
                }
            } else {
                onResult(false, emptyList(), body ?: "Failed to fetch reward history")
            }
        }
    }
}

