package com.colorlinker.puzzle

import android.app.Activity
import android.content.Context
import android.util.Log
import com.secmtp.sdk.core.api.ATSDK
import com.secmtp.sdk.core.api.AdError
import com.secmtp.sdk.interstitial.api.ATInterstitial
import com.secmtp.sdk.interstitial.api.ATInterstitialListener
import com.secmtp.sdk.rewardvideo.api.ATRewardVideoAd
import com.secmtp.sdk.rewardvideo.api.ATRewardVideoListener
import com.secmtp.sdk.splashad.api.ATSplashAd
import com.secmtp.sdk.splashad.api.ATSplashAdListener
import com.secmtp.sdk.splashad.api.ATSplashAdExtraInfo
import com.secmtp.sdk.nativead.api.ATNative
import com.secmtp.sdk.nativead.api.NativeAd
import com.secmtp.sdk.nativead.api.ATNativeNetworkListener

object AdManager {      
    private const val TAG = "AdManager"

    // TopOn Production Credentials (Default Fallback)
    const val TOPON_APP_ID = "h6abf939ee7d84"
    const val TOPON_APP_KEY = "a174dbc40be220647879d701c773493f7" 

    private var splashAd: ATSplashAd? = null
    private var isSplashLoading = false
    private var onSplashDismissed: (() -> Unit)? = null

    private var interstitialAd: ATInterstitial? = null
    private var rewardedAd: ATRewardVideoAd? = null
    private var isInterstitialLoading = false
    private var isRewardedLoading = false

    private var onRewardedLoadedShow: (() -> Unit)? = null
    private var onRewardedFailedShow: ((String) -> Unit)? = null

    private var onInterstitialLoadedShow: (() -> Unit)? = null
    private var onInterstitialFailedShow: ((String) -> Unit)? = null

    private fun getSplashId() = SettingsManager.getAdId("splash") ?: ""
    private fun getInterstitialId() = SettingsManager.getAdId("interstitial") ?: ""
    private fun getRewardedId() = SettingsManager.getAdId("rewarded") ?: ""
    private fun getNativeId() = SettingsManager.getAdId("native") ?: ""

    fun loadSplashAd(context: Context, onLoaded: (() -> Unit)? = null) {
        val adId = getSplashId()
        if (adId.isEmpty() || isSplashLoading) return

        isSplashLoading = true
        val listener = object : ATSplashAdListener {
            override fun onAdLoaded(isTimeout: Boolean) {
                isSplashLoading = false
                Log.d(TAG, "TopOn Splash Ad Loaded successfully (isTimeout=$isTimeout)")
                onLoaded?.invoke()
            }

            override fun onAdLoadTimeout() {
                isSplashLoading = false
                Log.w(TAG, "TopOn Splash Ad Load Timeout")
            }

            override fun onNoAdError(adError: AdError) {
                isSplashLoading = false
                splashAd = null
                Log.e(TAG, "TopOn Splash Load Failed: ${adError.desc} (Code: ${adError.code})")
            }

            override fun onAdShow(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                Log.d(TAG, "TopOn Splash Ad Showed")
            }

            override fun onAdClick(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                Log.d(TAG, "TopOn Splash Ad Clicked")
            }

            override fun onAdDismiss(adInfo: com.secmtp.sdk.core.api.ATAdInfo, splashAdExtraInfo: ATSplashAdExtraInfo?) {
                Log.d(TAG, "TopOn Splash Ad Dismissed")
                splashAd = null
                val cb = onSplashDismissed
                onSplashDismissed = null
                cb?.invoke()
            }
        }

        splashAd = ATSplashAd(context, adId, listener)
        splashAd?.loadAd()
    }

    fun showSplashAd(activity: Activity, onAdDismissed: () -> Unit) {
        val ad = splashAd
        if (ad != null && ad.isAdReady) {
            onSplashDismissed = onAdDismissed
            val container = activity.findViewById<android.view.ViewGroup>(android.R.id.content)
            if (container != null) {
                ad.show(activity, container)
            } else {
                onAdDismissed()
            }
        } else {
            onAdDismissed()
        }
    }

    fun loadInterstitialAd(context: Context) {
        if (interstitialAd != null || isInterstitialLoading) return
        val adId = getInterstitialId()
        if (adId.isEmpty()) return

        isInterstitialLoading = true
        val ad = ATInterstitial(context, adId)
        ad.setAdListener(object : ATInterstitialListener {
            override fun onInterstitialAdLoaded() {
                isInterstitialLoading = false
                interstitialAd = ad
                Log.d(TAG, "TopOn Interstitial Loaded Successfully")
                val show = onInterstitialLoadedShow
                onInterstitialLoadedShow = null
                onInterstitialFailedShow = null
                show?.invoke()
            }

            override fun onInterstitialAdLoadFail(adError: AdError) {
                isInterstitialLoading = false
                interstitialAd = null
                Log.e(TAG, "TopOn Interstitial Failed to Load: ${adError.desc} (Code: ${adError.code})")
                val fail = onInterstitialFailedShow
                onInterstitialLoadedShow = null
                onInterstitialFailedShow = null
                fail?.invoke("Load failed: ${adError.desc}")
            }

            override fun onInterstitialAdClicked(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                Log.d(TAG, "TopOn Interstitial Clicked")
            }

            override fun onInterstitialAdShow(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                Log.d(TAG, "TopOn Interstitial Showed")
            }

            override fun onInterstitialAdClose(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                Log.d(TAG, "TopOn Interstitial Closed")
            }

            override fun onInterstitialAdVideoStart(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
            override fun onInterstitialAdVideoEnd(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
            override fun onInterstitialAdVideoError(adError: AdError) {
                Log.e(TAG, "TopOn Interstitial Video Error: ${adError.desc}")
            }
        })
        ad.load()
    }

    fun showInterstitialAd(activity: Activity, onAdDismissed: () -> Unit) {
        val ad = interstitialAd
        if (ad != null && ad.isAdReady) {
            ad.setAdListener(object : ATInterstitialListener {
                override fun onInterstitialAdLoaded() {}
                override fun onInterstitialAdLoadFail(adError: AdError) {}
                override fun onInterstitialAdClicked(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
                override fun onInterstitialAdShow(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
                override fun onInterstitialAdClose(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                    interstitialAd = null
                    loadInterstitialAd(activity)
                    onAdDismissed()
                }
                override fun onInterstitialAdVideoStart(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
                override fun onInterstitialAdVideoEnd(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
                override fun onInterstitialAdVideoError(adError: AdError) {
                    interstitialAd = null
                    loadInterstitialAd(activity)
                    onAdDismissed()
                }
            })
            ad.show(activity)
        } else if (isInterstitialLoading) {
            onInterstitialLoadedShow = {
                val loadedAd = interstitialAd
                if (loadedAd != null && loadedAd.isAdReady) {
                    loadedAd.setAdListener(object : ATInterstitialListener {
                        override fun onInterstitialAdLoaded() {}
                        override fun onInterstitialAdLoadFail(adError: AdError) {}
                        override fun onInterstitialAdClicked(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
                        override fun onInterstitialAdShow(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
                        override fun onInterstitialAdClose(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                            interstitialAd = null
                            loadInterstitialAd(activity)
                            onAdDismissed()
                        }
                        override fun onInterstitialAdVideoStart(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
                        override fun onInterstitialAdVideoEnd(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
                        override fun onInterstitialAdVideoError(adError: AdError) {
                            interstitialAd = null
                            loadInterstitialAd(activity)
                            onAdDismissed()
                        }
                    })
                    loadedAd.show(activity)
                } else {
                    onAdDismissed()
                }
            }
            onInterstitialFailedShow = {
                onAdDismissed()
            }
        } else {
            onInterstitialLoadedShow = {
                val loadedAd = interstitialAd
                if (loadedAd != null && loadedAd.isAdReady) {
                    loadedAd.setAdListener(object : ATInterstitialListener {
                        override fun onInterstitialAdLoaded() {}
                        override fun onInterstitialAdLoadFail(adError: AdError) {}
                        override fun onInterstitialAdClicked(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
                        override fun onInterstitialAdShow(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
                        override fun onInterstitialAdClose(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                            interstitialAd = null
                            loadInterstitialAd(activity)
                            onAdDismissed()
                        }
                        override fun onInterstitialAdVideoStart(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
                        override fun onInterstitialAdVideoEnd(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
                        override fun onInterstitialAdVideoError(adError: AdError) {
                            interstitialAd = null
                            loadInterstitialAd(activity)
                            onAdDismissed()
                        }
                    })
                    loadedAd.show(activity)
                } else {
                    onAdDismissed()
                }
            }
            onInterstitialFailedShow = {
                onAdDismissed()
            }
            loadInterstitialAd(activity)
        }
    }

    fun loadRewardedAd(context: Context) {
        if (rewardedAd != null || isRewardedLoading) return
        val adId = getRewardedId()
        if (adId.isEmpty()) return

        isRewardedLoading = true
        val ad = ATRewardVideoAd(context, adId)
        ad.setAdListener(object : ATRewardVideoListener {
            override fun onRewardedVideoAdLoaded() {
                isRewardedLoading = false
                rewardedAd = ad
                Log.d(TAG, "TopOn Rewarded Loaded Successfully")
                val show = onRewardedLoadedShow
                onRewardedLoadedShow = null
                onRewardedFailedShow = null
                show?.invoke()
            }

            override fun onRewardedVideoAdFailed(adError: AdError) {
                isRewardedLoading = false
                rewardedAd = null
                Log.e(TAG, "TopOn Rewarded Failed to Load: ${adError.desc} (Code: ${adError.code})")
                val fail = onRewardedFailedShow
                onRewardedLoadedShow = null
                onRewardedFailedShow = null
                fail?.invoke("Load failed: ${adError.desc}")
            }

            override fun onRewardedVideoAdPlayStart(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
            override fun onRewardedVideoAdPlayEnd(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
            override fun onRewardedVideoAdPlayFailed(adError: AdError, adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                Log.e(TAG, "TopOn Rewarded Play Failed: ${adError.desc}")
            }
            override fun onRewardedVideoAdClosed(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
            override fun onRewardedVideoAdPlayClicked(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
            override fun onReward(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
        })
        ad.load()
    }

    fun showRewardedAd(activity: Activity, onRewardEarned: () -> Unit, onAdClosed: () -> Unit = {}) {
        val ad = rewardedAd
        if (ad != null && ad.isAdReady) {
            var rewardEarned = false
            ad.setAdListener(object : ATRewardVideoListener {
                override fun onRewardedVideoAdLoaded() {}
                override fun onRewardedVideoAdFailed(adError: AdError) {}
                override fun onRewardedVideoAdPlayStart(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
                override fun onRewardedVideoAdPlayEnd(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                    rewardEarned = true
                }
                override fun onRewardedVideoAdPlayFailed(adError: AdError, adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                    rewardedAd = null
                    loadRewardedAd(activity)
                    onAdClosed()
                }
                override fun onRewardedVideoAdClosed(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                    rewardedAd = null
                    loadRewardedAd(activity)
                    activity.window.decorView.postDelayed({
                        if (rewardEarned) {
                            onRewardEarned()
                        }
                        onAdClosed()
                    }, 250)
                }
                override fun onRewardedVideoAdPlayClicked(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
                override fun onReward(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                    rewardEarned = true
                }
            })
            ad.show(activity)
        } else if (isRewardedLoading) {
            onRewardedLoadedShow = {
                val loadedAd = rewardedAd
                if (loadedAd != null && loadedAd.isAdReady) {
                    var rewardEarned = false
                    loadedAd.setAdListener(object : ATRewardVideoListener {
                        override fun onRewardedVideoAdLoaded() {}
                        override fun onRewardedVideoAdFailed(adError: AdError) {}
                        override fun onRewardedVideoAdPlayStart(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
                        override fun onRewardedVideoAdPlayEnd(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                            rewardEarned = true
                        }
                        override fun onRewardedVideoAdPlayFailed(adError: AdError, adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                            rewardedAd = null
                            loadRewardedAd(activity)
                            onAdClosed()
                        }
                        override fun onRewardedVideoAdClosed(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                            rewardedAd = null
                            loadRewardedAd(activity)
                            activity.window.decorView.postDelayed({
                                if (rewardEarned) {
                                    onRewardEarned()
                                }
                                onAdClosed()
                            }, 250)
                        }
                        override fun onRewardedVideoAdPlayClicked(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
                        override fun onReward(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                            rewardEarned = true
                        }
                    })
                    loadedAd.show(activity)
                } else {
                    onAdClosed()
                }
            }
            onRewardedFailedShow = {
                onAdClosed()
            }
        } else {
            onRewardedLoadedShow = {
                val loadedAd = rewardedAd
                if (loadedAd != null && loadedAd.isAdReady) {
                    var rewardEarned = false
                    loadedAd.setAdListener(object : ATRewardVideoListener {
                        override fun onRewardedVideoAdLoaded() {}
                        override fun onRewardedVideoAdFailed(adError: AdError) {}
                        override fun onRewardedVideoAdPlayStart(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
                        override fun onRewardedVideoAdPlayEnd(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                            rewardEarned = true
                        }
                        override fun onRewardedVideoAdPlayFailed(adError: AdError, adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                            rewardedAd = null
                            loadRewardedAd(activity)
                            onAdClosed()
                        }
                        override fun onRewardedVideoAdClosed(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                            rewardedAd = null
                            loadRewardedAd(activity)
                            activity.window.decorView.postDelayed({
                                if (rewardEarned) {
                                    onRewardEarned()
                                }
                                onAdClosed()
                            }, 250)
                        }
                        override fun onRewardedVideoAdPlayClicked(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
                        override fun onReward(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                            rewardEarned = true
                        }
                    })
                    loadedAd.show(activity)
                } else {
                    onAdClosed()
                }
            }
            onRewardedFailedShow = {
                onAdClosed()
            }
            loadRewardedAd(activity)
        }
    }

    private fun showReadyRewardedAd(
        activity: Activity,
        ad: ATRewardVideoAd,
        onAdShowStart: () -> Unit,
        onAdComplete: () -> Unit,
        onAdFailed: (String) -> Unit
    ) {
        var rewardEarned = false
        var hasCompletedCalled = false
        ad.setAdListener(object : ATRewardVideoListener {
            override fun onRewardedVideoAdLoaded() {}
            override fun onRewardedVideoAdFailed(adError: AdError) {}
            override fun onRewardedVideoAdPlayStart(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                onAdShowStart()
            }
            override fun onRewardedVideoAdPlayEnd(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                rewardEarned = true
            }
            override fun onRewardedVideoAdPlayFailed(adError: AdError, adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                rewardedAd = null
                loadRewardedAd(activity)
                onAdFailed("Show failed: ${adError.desc}")
            }
            override fun onRewardedVideoAdClosed(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                rewardedAd = null
                loadRewardedAd(activity)
                activity.window.decorView.postDelayed({
                    if (rewardEarned) {
                        if (!hasCompletedCalled) {
                            hasCompletedCalled = true
                            onAdComplete()
                        }
                    } else {
                        if (!hasCompletedCalled) {
                            hasCompletedCalled = true
                            onAdFailed("Please watch the full ad to unlock!")
                        }
                    }
                }, 250)
            }
            override fun onRewardedVideoAdPlayClicked(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
            override fun onReward(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                rewardEarned = true
            }
        })
        ad.show(activity)
    }

    private fun showReadyInterstitialAd(
        activity: Activity,
        ad: ATInterstitial,
        onAdShowStart: () -> Unit,
        onAdComplete: () -> Unit,
        onAdFailed: (String) -> Unit
    ) {
        ad.setAdListener(object : ATInterstitialListener {
            override fun onInterstitialAdLoaded() {}
            override fun onInterstitialAdLoadFail(adError: AdError) {}
            override fun onInterstitialAdClicked(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
            override fun onInterstitialAdShow(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                onAdShowStart()
            }
            override fun onInterstitialAdClose(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {
                interstitialAd = null
                loadInterstitialAd(activity)
                onAdComplete()
            }
            override fun onInterstitialAdVideoStart(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
            override fun onInterstitialAdVideoEnd(adInfo: com.secmtp.sdk.core.api.ATAdInfo) {}
            override fun onInterstitialAdVideoError(adError: AdError) {
                interstitialAd = null
                loadInterstitialAd(activity)
                onAdFailed("Show failed: ${adError.desc}")
            }
        })
        ad.show(activity)
    }

    fun showAdWithLoading(
        activity: Activity,
        adType: String, // "interstitial" or "rewarded"
        onAdShowStart: () -> Unit,
        onAdComplete: () -> Unit,
        onAdFailed: (String) -> Unit
    ) {
        val adId = if (adType == "interstitial") getInterstitialId() else getRewardedId()
        if (adId.isEmpty()) {
            onAdShowStart()
            onAdComplete()
            return
        }

        if (adType == "interstitial") {
            val ad = interstitialAd
            if (ad != null && ad.isAdReady) {
                showReadyInterstitialAd(activity, ad, onAdShowStart, onAdComplete, onAdFailed)
            } else if (isInterstitialLoading) {
                onInterstitialLoadedShow = {
                    val loadedAd = interstitialAd
                    if (loadedAd != null && loadedAd.isAdReady) {
                        showReadyInterstitialAd(activity, loadedAd, onAdShowStart, onAdComplete, onAdFailed)
                    } else {
                        onAdFailed("Ad loaded but not ready")
                    }
                }
                onInterstitialFailedShow = { error ->
                    onAdFailed(error)
                }
            } else {
                onInterstitialLoadedShow = {
                    val loadedAd = interstitialAd
                    if (loadedAd != null && loadedAd.isAdReady) {
                        showReadyInterstitialAd(activity, loadedAd, onAdShowStart, onAdComplete, onAdFailed)
                    } else {
                        onAdFailed("Ad loaded but not ready")
                    }
                }
                onInterstitialFailedShow = { error ->
                    onAdFailed(error)
                }
                loadInterstitialAd(activity)
            }
        } else {
            val ad = rewardedAd
            if (ad != null && ad.isAdReady) {
                showReadyRewardedAd(activity, ad, onAdShowStart, onAdComplete, onAdFailed)
            } else if (isRewardedLoading) {
                onRewardedLoadedShow = {
                    val loadedAd = rewardedAd
                    if (loadedAd != null && loadedAd.isAdReady) {
                        showReadyRewardedAd(activity, loadedAd, onAdShowStart, onAdComplete, onAdFailed)
                    } else {
                        onAdFailed("Ad loaded but not ready")
                    }
                }
                onRewardedFailedShow = { error ->
                    onAdFailed(error)
                }
            } else {
                onRewardedLoadedShow = {
                    val loadedAd = rewardedAd
                    if (loadedAd != null && loadedAd.isAdReady) {
                        showReadyRewardedAd(activity, loadedAd, onAdShowStart, onAdComplete, onAdFailed)
                    } else {
                        onAdFailed("Ad loaded but not ready")
                    }
                }
                onRewardedFailedShow = { error ->
                    onAdFailed(error)
                }
                loadRewardedAd(activity)
            }
        }
    }

    fun loadNativeAd(context: Context, onAdLoaded: (NativeAd) -> Unit) {
        val adId = getNativeId()
        Log.d(TAG, "loadNativeAd: starting with adId='$adId'")
        if (adId.isEmpty()) {
            Log.e(TAG, "loadNativeAd: adId is EMPTY!")
            return
        }

        var atNative: ATNative? = null
        val listener = object : ATNativeNetworkListener {
            override fun onNativeAdLoaded() {
                Log.d(TAG, "onNativeAdLoaded: native ad successfully loaded")
                val nativeAd = atNative?.getNativeAd()
                if (nativeAd != null) {
                    onAdLoaded(nativeAd)
                } else {
                    Log.w(TAG, "TopOn Loaded Native Ad was null")
                }
            }

            override fun onNativeAdLoadFail(adError: AdError) {
                Log.e(TAG, "TopOn Native Load Failed: ${adError.desc} (Code: ${adError.code})")
            }
        }
        atNative = ATNative(context, adId, listener)
        atNative.makeAdRequest()
    }
}
