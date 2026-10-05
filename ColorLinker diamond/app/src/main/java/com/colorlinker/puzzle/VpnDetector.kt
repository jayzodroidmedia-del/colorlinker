package com.colorlinker.puzzle

import android.content.Context
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.os.Build
import java.net.NetworkInterface
import java.util.Collections

object VpnDetector {
    fun isVpnActive(context: Context): Boolean {
        return isVpnByNetworkCapabilities(context) || isVpnByNetworkInterfaces() || isProxyActive()
    }

    private fun isProxyActive(): Boolean {
        try {
            val proxyHost = System.getProperty("http.proxyHost")
            val proxyPort = System.getProperty("http.proxyPort")
            if (!proxyHost.isNullOrEmpty() && !proxyPort.isNullOrEmpty()) {
                return true
            }
        } catch (e: Exception) {}
        return false
    }

    private fun isVpnByNetworkCapabilities(context: Context): Boolean {
        val connectivityManager = context.getSystemService(Context.CONNECTIVITY_SERVICE) as? ConnectivityManager
            ?: return false
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            val activeNetwork = connectivityManager.activeNetwork ?: return false
            val capabilities = connectivityManager.getNetworkCapabilities(activeNetwork) ?: return false
            return capabilities.hasTransport(NetworkCapabilities.TRANSPORT_VPN)
        } else {
            @Suppress("DEPRECATION")
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
                    if (name.contains("tun") || name.contains("ppp") || name.contains("tap") || name.contains("vpn")) {
                        return true
                    }
                }
            }
        } catch (e: Exception) {}
        return false
    }
}
