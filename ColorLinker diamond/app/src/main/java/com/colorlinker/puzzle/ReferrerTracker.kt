package com.colorlinker.puzzle

import android.content.Context
import android.os.Handler
import android.os.Looper
import com.android.installreferrer.api.InstallReferrerClient
import com.android.installreferrer.api.InstallReferrerStateListener
import com.google.android.gms.ads.identifier.AdvertisingIdClient
import java.util.concurrent.atomic.AtomicBoolean
import kotlin.concurrent.thread

object ReferrerTracker {
    fun initialize(context: Context, onComplete: () -> Unit) {
        val prefs = context.getSharedPreferences("app_prefs", Context.MODE_PRIVATE)

        // 1. Fetch Google Advertising ID (GID) on a background thread
        if (prefs.getString("gid", "").isNullOrEmpty()) {
            thread {
                try {
                    val adInfo = AdvertisingIdClient.getAdvertisingIdInfo(context)
                    val gid = adInfo.id
                    if (!gid.isNullOrEmpty()) {
                        prefs.edit().putString("gid", gid).apply()
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                }
            }
        }

        // 2. Fetch Install Referrer (Only once on first install)
        if (!prefs.getBoolean("referrer_checked", false)) {
            val hasCompleted = AtomicBoolean(false)
            val handler = Handler(Looper.getMainLooper())
            
            // Timeout runner to guarantee we call onComplete within 5.0 seconds
            val timeoutRunnable = Runnable {
                if (hasCompleted.compareAndSet(false, true)) {
                    onComplete()
                }
            }
            handler.postDelayed(timeoutRunnable, 5000)

            try {
                val referrerClient = InstallReferrerClient.newBuilder(context).build()
                referrerClient.startConnection(object : InstallReferrerStateListener {
                    override fun onInstallReferrerSetupFinished(responseCode: Int) {
                        handler.removeCallbacks(timeoutRunnable)
                        if (hasCompleted.compareAndSet(false, true)) {
                            if (responseCode == 0) { // 0 matches InstallReferrerResponseCode.OK
                                try {
                                    val response = referrerClient.installReferrer
                                    val referrerUrl = response.installReferrer
                                    if (!referrerUrl.isNullOrEmpty() && referrerUrl.contains("referr=")) {
                                        val referrer = referrerUrl.substringAfter("referr=").substringBefore("&")
                                        prefs.edit().putString("referrer", referrer).apply()
                                    }
                                } catch (e: Exception) {
                                    e.printStackTrace()
                                } finally {
                                    prefs.edit().putBoolean("referrer_checked", true).apply()
                                    try {
                                        referrerClient.endConnection()
                                    } catch (ex: Exception) {}
                                    onComplete()
                                }
                            } else {
                                prefs.edit().putBoolean("referrer_checked", true).apply()
                                try {
                                    referrerClient.endConnection()
                                } catch (ex: Exception) {}
                                onComplete()
                            }
                        }
                    }

                    override fun onInstallReferrerServiceDisconnected() {
                        handler.removeCallbacks(timeoutRunnable)
                        if (hasCompleted.compareAndSet(false, true)) {
                            onComplete()
                        }
                    }
                })
            } catch (e: Exception) {
                handler.removeCallbacks(timeoutRunnable)
                if (hasCompleted.compareAndSet(false, true)) {
                    onComplete()
                }
            }
        } else {
            onComplete()
        }
    }
}
