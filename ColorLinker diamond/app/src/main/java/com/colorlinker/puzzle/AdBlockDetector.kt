package com.colorlinker.puzzle

import android.content.Context
import java.net.InetAddress
import java.net.UnknownHostException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

object AdBlockDetector {
    private const val AD_DOMAIN = "pagead2.googlesyndication.com"

    /**
     * Checks if a DNS-based or Host-based ad blocker is active.
     * Requires active internet access to avoid false positives.
     */
    suspend fun isAdBlockerActive(context: Context): Boolean {
        return withContext(Dispatchers.IO) {
            if (NetworkUtils.isInternetAvailable(context) && NetworkUtils.hasActualInternetAccess()) {
                try {
                    val address = InetAddress.getByName(AD_DOMAIN)
                    // If it resolves to a loopback address or 0.0.0.0, ads are blocked
                    address.isLoopbackAddress || address.hostAddress == "0.0.0.0"
                } catch (e: UnknownHostException) {
                    // UnknownHostException under active internet indicates DNS blocking
                    true
                } catch (e: Exception) {
                    false
                }
            } else {
                false
            }
        }
    }
}
