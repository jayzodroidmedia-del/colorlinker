# Add project specific ProGuard rules here.
# You can control the set of applied configuration files using the
# proguardFiles setting in build.gradle.
#
# For more details, see
#   http://developer.android.com/guide/developing/tools/proguard.html

# Uncomment this to preserve the line number information for debugging stack traces.
-keepattributes SourceFile,LineNumberTable

# Keep attributes required for JSON serialization/deserialization and reflection
-keepattributes Signature, *Annotation*, InnerClasses, EnclosingMethod

# ====================================================================
# 1. Custom Model Keep Rules (Prevents Gson crash on ApiResponse)
# ====================================================================
-keep class com.colorlinker.puzzle.** { *; }
-keepclassmembers class com.colorlinker.puzzle.** { *; }

-keepclassmembers class * {
    @com.google.gson.annotations.SerializedName <fields>;
}

# ====================================================================
# 2. Gson & OkHttp Specific Rules
# ====================================================================
-keep class com.google.gson.** { *; }
-keep class okhttp3.** { *; }
-dontwarn okhttp3.**

# ====================================================================
# 3. WorkManager, Room Database & Startup Keep Rules (Fixes Startup Crashes)
# ====================================================================
-keep class androidx.work.** { *; }
-dontwarn androidx.work.**

-keep class androidx.startup.** { *; }
-dontwarn androidx.startup.**

-keep class * extends androidx.room.RoomDatabase { *; }
-dontwarn androidx.room.**

# ====================================================================
# 4. Anythink (TopOn) Ad SDK & Mediation Rules
# ====================================================================
-keep class com.anythink.** { *; }
-dontwarn com.anythink.**
-keep class com.anythink.network.** { *; }
-dontwarn com.anythink.network.**
-keep class com.tramini.** { *; }
-dontwarn com.tramini.**
-keep class com.secmtp.sdk.** { *; }
-dontwarn com.secmtp.sdk.**

# ====================================================================
# 5. Individual Ad Network Rules (AdMob, Unity, Facebook, AppLovin, etc.)
# ====================================================================
# AdMob / Google Play Services
-keep class com.google.android.gms.ads.** { *; }
-keep class com.google.android.gms.common.** { *; }

# AppLovin
-keep class com.applovin.** { *; }
-dontwarn com.applovin.**

# Unity Ads
-keep class com.unity3d.ads.** { *; }
-dontwarn com.unity3d.ads.**

# Facebook / Audience Network
-keep class com.facebook.ads.** { *; }
-dontwarn com.facebook.ads.**

# InMobi
-keep class com.inmobi.** { *; }
-dontwarn com.inmobi.**

# Mintegral
-keep class com.mbridge.msdk.** { *; }
-dontwarn com.mbridge.msdk.**

# Vungle
-keep class com.vungle.ads.** { *; }
-dontwarn com.vungle.ads.**

# IronSource
-keep class com.ironsource.** { *; }
-dontwarn com.ironsource.**

# ====================================================================
# 6. Additional SDKs (OneSignal, UMP, SmartDigiMktTech)
# ====================================================================
# OneSignal
-keep class com.onesignal.** { *; }
-dontwarn com.onesignal.**

# Google User Messaging Platform (UMP)
-keep class com.google.android.ump.** { *; }
-dontwarn com.google.android.ump.**

# SmartDigiMktTech
-keep class com.smartdigimkttech.** { *; }
-dontwarn com.smartdigimkttech.**

# ====================================================================
# 7. General Anythink Reflection/Mediation Keep Rules
# ====================================================================
-keep class * implements com.anythink.core.api.ATBaseAdAdapter { *; }
-keep class * implements com.anythink.core.api.ATAdAdapter { *; }
-keep class * implements com.anythink.core.api.ATCustomLoadListener { *; }
-keep class * implements com.anythink.core.api.ATCustomShowListener { *; }
-keep class * implements com.anythink.core.api.ATCustomBidListener { *; }
-keep class * implements com.anythink.core.api.ATCustomRewardListener { *; }
-keep class * implements com.anythink.core.api.ATCustomTrackListener { *; }
-keep class * implements com.anythink.core.api.IITNLoadListener { *; }
-keep class * implements com.anythink.core.api.IITNShowListener { *; }
-dontwarn com.anythink.core.api.**

-keep class * implements com.secmtp.sdk.core.api.ATBaseAdAdapter { *; }
-keep class * implements com.secmtp.sdk.core.api.ATAdAdapter { *; }
-keep class * implements com.secmtp.sdk.core.api.ATCustomLoadListener { *; }
-keep class * implements com.secmtp.sdk.core.api.ATCustomShowListener { *; }
-keep class * implements com.secmtp.sdk.core.api.ATCustomBidListener { *; }
-keep class * implements com.secmtp.sdk.core.api.ATCustomRewardListener { *; }
-keep class * implements com.secmtp.sdk.core.api.ATCustomTrackListener { *; }
-keep class * implements com.secmtp.sdk.core.api.IITNLoadListener { *; }
-keep class * implements com.secmtp.sdk.core.api.IITNShowListener { *; }
-dontwarn com.secmtp.sdk.core.api.**

# ====================================================================
# 8. Strip Debug Logs in Release Builds
# ====================================================================
-assumenosideeffects class android.util.Log {
    public static boolean isLoggable(java.lang.String, int);
    public static int v(...);
    public static int d(...);
    public static int i(...);
}