package com.colorlinker.puzzle

import android.content.Context
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.util.Base64

object NetworkUtils {
    /**
     * Decodes a Base64 encoded URL string.
     */
    fun decodeUrl(encodedUrl: String): String {
        return try {
            val decodedBytes = Base64.decode(encodedUrl, Base64.DEFAULT)
            String(decodedBytes, Charsets.UTF_8)
        } catch (e: Exception) {
            ""
        }
    }

    /**
     * Checks if the internet connection is currently available.
     */
    fun isInternetAvailable(context: Context): Boolean {
        return try {
            val connectivityManager = context.getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
            val network = connectivityManager.activeNetwork ?: return false
            val activeNetwork = connectivityManager.getNetworkCapabilities(network) ?: return false
            activeNetwork.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET) &&
            (activeNetwork.hasTransport(NetworkCapabilities.TRANSPORT_WIFI) ||
             activeNetwork.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR) ||
             activeNetwork.hasTransport(NetworkCapabilities.TRANSPORT_ETHERNET))
        } catch (e: Exception) {
            false
        }
    }

    /**
     * Checks if the device has actual internet access by attempting a lightweight TCP connection to a reliable DNS server.
     */
    fun hasActualInternetAccess(): Boolean {
        return try {
            val timeoutMs = 1500
            val sock = java.net.Socket()
            val sockaddr = java.net.InetSocketAddress("8.8.8.8", 53)
            sock.connect(sockaddr, timeoutMs)
            sock.close()
            true
        } catch (e: Exception) {
            false
        }
    }
}
