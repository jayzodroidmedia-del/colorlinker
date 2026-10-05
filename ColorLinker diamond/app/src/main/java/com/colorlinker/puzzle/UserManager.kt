package com.colorlinker.puzzle

import android.content.Context
import android.provider.Settings
import android.util.Log
import okhttp3.*
import org.json.JSONObject
import java.io.IOException

object UserManager {
    private const val TAG = "UserManager"

    private const val PREF_SYNC = "sync_prefs"
    private const val KEY_PENDING_LEVELS = "pending_levels"
    private const val KEY_LAST_SYNC_TIME = "last_sync_time"
    private const val KEY_MAX_SYNCED_LEVEL = "max_synced_level"

    fun syncLevel(context: Context, level: Int, onComplete: (Boolean) -> Unit = {}) {
        val prefs = context.getSharedPreferences(PREF_SYNC, Context.MODE_PRIVATE)
        val maxSynced = prefs.getInt(KEY_MAX_SYNCED_LEVEL, 0)
        
        // Anti-Cheat: No level decrease
        if (level <= maxSynced) {
            onComplete(true)
            return
        }
        
        // Add to pending
        val pending = prefs.getStringSet(KEY_PENDING_LEVELS, emptySet())?.toMutableSet() ?: mutableSetOf()
        pending.add(level.toString())
        prefs.edit().putStringSet(KEY_PENDING_LEVELS, pending).apply()

        performSync(context, level, onComplete)
    }

    private fun performSync(context: Context, level: Int, onComplete: (Boolean) -> Unit = {}) {
        Log.d(TAG, "Syncing Level: $level")

        val params = mapOf("current_level" to level.toString())
        GameSecurity.postSecureRequest(context, "save_user", params) { success, body ->
            if (success) {
                val prefs = context.getSharedPreferences(PREF_SYNC, Context.MODE_PRIVATE)
                val pending = prefs.getStringSet(KEY_PENDING_LEVELS, emptySet())?.toMutableSet() ?: mutableSetOf()
                pending.remove(level.toString())
                
                val maxSynced = prefs.getInt(KEY_MAX_SYNCED_LEVEL, 0)
                val newMax = if (level > maxSynced) level else maxSynced
                
                prefs.edit()
                    .putStringSet(KEY_PENDING_LEVELS, pending)
                    .putInt(KEY_MAX_SYNCED_LEVEL, newMax)
                    .putString(KEY_LAST_SYNC_TIME, System.currentTimeMillis().toString())
                    .apply()
                
                Log.d(TAG, "Level $level synced successfully")
                onComplete(true)
            } else {
                Log.e(TAG, "Level sync failed for $level: $body")
                onComplete(false)
            }
        }
    }

    fun syncPendingLevels(context: Context) {
        val prefs = context.getSharedPreferences(PREF_SYNC, Context.MODE_PRIVATE)
        val pending = prefs.getStringSet(KEY_PENDING_LEVELS, emptySet()) ?: emptySet()
        
        if (pending.isNotEmpty()) {
            Log.d(TAG, "Retrying sync for pending levels: $pending")
            pending.forEach { levelStr ->
                performSync(context, levelStr.toInt())
            }
        }
    }

    fun registerDevice(context: Context) {
        Log.d(TAG, "Registering device...")
        GameSecurity.registerDevice(context) { success ->
            Log.d(TAG, "Device registration status: $success")
        }
    }

    fun claimReward(
        context: Context,
        method: String,
        account: String,
        amount: Int,
        onResponse: (String) -> Unit
    ) {
        val params = mapOf(
            "method" to method,
            "account" to account,
            "amount" to amount.toString()
        )

        GameSecurity.postSecureRequest(context, "claim_reward", params) { success, body ->
            if (success && body != null) {
                try {
                    val json = JSONObject(body)
                    onResponse(json.optString("status", "error"))
                } catch (e: Exception) {
                    onResponse("error")
                }
            } else {
                onResponse("error")
            }
        }
    }

    fun getRewardHistory(
        context: Context,
        onResponse: (List<RewardHistoryItem>) -> Unit
    ) {
        GameSecurity.postSecureRequest(context, "get_reward_history", emptyMap()) { success, body ->
            if (success && body != null) {
                try {
                    val json = JSONObject(body)
                    val historyArr = json.optJSONArray("history") ?: json.optJSONArray("claims")
                    val items = mutableListOf<RewardHistoryItem>()
                    if (historyArr != null) {
                        for (i in 0 until historyArr.length()) {
                            val obj = historyArr.getJSONObject(i)
                            val methodVal = if (obj.has("method") && !obj.isNull("method") && obj.optString("method").isNotBlank()) {
                                obj.optString("method")
                            } else {
                                obj.optString("payment_method", "UPI")
                            }
                            val accountVal = if (obj.has("account") && !obj.isNull("account") && obj.optString("account").isNotBlank()) {
                                obj.optString("account")
                            } else {
                                obj.optString("account_details", "")
                            }
                            items.add(
                                RewardHistoryItem(
                                    id = obj.optInt("id"),
                                    type = obj.optString("type", "tester"),
                                    cycle = obj.optInt("cycle", 1),
                                    method = methodVal,
                                    account = accountVal,
                                    amount = obj.optInt("amount", 0),
                                    voucherCode = if (obj.has("voucher_code") && !obj.isNull("voucher_code") && obj.optString("voucher_code").isNotBlank()) obj.optString("voucher_code") else null,
                                    adminNotes = if (obj.has("admin_notes") && !obj.isNull("admin_notes") && obj.optString("admin_notes").isNotBlank()) obj.optString("admin_notes") else null,
                                    status = obj.optString("status", "pending"),
                                    createdAt = obj.optString("created_at", "")
                                )
                            )
                        }
                    }
                    Log.d(TAG, "Reward history loaded: ${items.size} items")
                    onResponse(items)
                } catch (e: Exception) {
                    Log.e(TAG, "Error parsing reward history: ${e.message}", e)
                    onResponse(emptyList())
                }
            } else {
                Log.e(TAG, "Failed to get reward history: $body")
                onResponse(emptyList())
            }
        }
    }
}

data class RewardHistoryItem(
    val id: Int,
    val type: String = "tester",
    val cycle: Int = 1,
    val method: String,
    val account: String,
    val amount: Int,
    val voucherCode: String? = null,
    val adminNotes: String? = null,
    val status: String,
    val createdAt: String
)


