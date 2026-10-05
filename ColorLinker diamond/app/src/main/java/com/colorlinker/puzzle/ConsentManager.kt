package com.colorlinker.puzzle

import android.app.Activity
import android.util.Log
import com.google.android.ump.ConsentDebugSettings
import com.google.android.ump.ConsentInformation
import com.google.android.ump.ConsentRequestParameters
import com.google.android.ump.UserMessagingPlatform

object ConsentManager {
    private const val TAG = "ConsentManager"
    private lateinit var consentInformation: ConsentInformation

    /**
     * Helper variable to determine if the app can request ads.
     */
    val canRequestAds: Boolean
        get() = if (::consentInformation.isInitialized) consentInformation.canRequestAds() else false

    fun requestConsent(activity: Activity, onComplete: () -> Unit) {
        val debugSettings = ConsentDebugSettings.Builder(activity)
            .setDebugGeography(ConsentDebugSettings.DebugGeography.DEBUG_GEOGRAPHY_EEA)
            .build()

        val params = ConsentRequestParameters.Builder()
            .setConsentDebugSettings(debugSettings)
            .setTagForUnderAgeOfConsent(false)
            .build()


        consentInformation = UserMessagingPlatform.getConsentInformation(activity)
        consentInformation.requestConsentInfoUpdate(
            activity,
            params,
            {
                UserMessagingPlatform.loadAndShowConsentFormIfRequired(activity) { formError ->
                    if (formError != null) {
                        Log.e(TAG, "${formError.errorCode}: ${formError.message}")
                    }
                    onComplete()
                }
            },
            { requestConsentError ->
                Log.e(TAG, "${requestConsentError.errorCode}: ${requestConsentError.message}")
                onComplete()
            }
        )
    }
}
