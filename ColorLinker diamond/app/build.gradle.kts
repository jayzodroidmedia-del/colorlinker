import java.io.ByteArrayOutputStream
import java.io.DataOutputStream
import java.io.File
import java.io.FileOutputStream

plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.compose.compiler)
}

android {
    namespace = "com.colorlinker.puzzle"
    compileSdk = 36

    defaultConfig {
        applicationId = "com.colorlinker.puzzle"
        minSdk = 26
        targetSdk = 36
        versionCode = 3
        versionName = "1.3"

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
        ndk {
            abiFilters.addAll(listOf("arm64-v8a", "armeabi-v7a"))
        }
    }

    signingConfigs {
        create("release") {
            val keystoreFile = file("/Users/vijaykumar/AndroidDevelopment/Keystore/balaji.keystore")
            if (keystoreFile.exists()) {
                storeFile = keystoreFile
                storePassword = "android"
                keyAlias = "androidkey"
                keyPassword = "android"
            }
        }
    }

    buildTypes {
        release {
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
            val releaseSigning = signingConfigs.getByName("release")
            if (releaseSigning.storeFile != null && releaseSigning.storeFile!!.exists()) {
                signingConfig = releaseSigning
            } else {
                signingConfig = signingConfigs.getByName("debug")
            }
        }
    }
    buildFeatures {
        compose = true
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
    }
}

dependencies {
    implementation(libs.androidx.activity.ktx)
    implementation(libs.androidx.appcompat)
    implementation(libs.androidx.constraintlayout)
    implementation(libs.androidx.core.ktx)
    implementation(libs.material)
    implementation(platform(libs.androidx.compose.bom))
    implementation(libs.androidx.ui)
    implementation(libs.androidx.ui.graphics)
    implementation(libs.androidx.ui.tooling.preview)
    implementation(libs.androidx.material3)
    implementation(libs.androidx.activity.compose)
    implementation(libs.androidx.compose.foundation)
    implementation(libs.androidx.compose.animation)
    implementation(libs.androidx.lifecycle.runtime.compose)
    implementation(libs.androidx.material.icons.extended)
    implementation(libs.androidx.compose.ui.text.google.fonts)
    implementation("com.airbnb.android:lottie-compose:6.0.0")
    // Anythink (Necessary)
    implementation("com.anythink.sdk:core-tpn:6.6.20.1")

    // Androidx (Necessary)
    implementation("androidx.appcompat:appcompat:1.6.1")
    implementation("androidx.browser:browser:1.4.0")

    // Vungle
    implementation("com.anythink.sdk:adapter-tpn-vungle:7.6.1.1.1")
    implementation("com.vungle:vungle-ads:7.6.1")
    implementation("com.google.android.gms:play-services-basement:18.1.0")
    implementation("com.google.android.gms:play-services-ads-identifier:18.0.1")

    // UnityAds
    implementation("com.anythink.sdk:adapter-tpn-unityads:4.17.0.1.1")
    implementation("com.unity3d.ads:unity-ads:4.17.0")

    // Ironsource
    implementation("com.anythink.sdk:adapter-tpn-ironsource:9.2.0.1.1")
    implementation("com.unity3d.ads-mediation:mediation-sdk:9.2.0")
    implementation("com.google.android.gms:play-services-appset:16.0.2")

    // Facebook
    implementation("com.anythink.sdk:adapter-tpn-facebook:6.21.0.1.1")
    implementation("com.facebook.android:audience-network-sdk:6.21.0")
    implementation("androidx.annotation:annotation:1.0.0")

    // Inmobi
    implementation("com.anythink.sdk:adapter-tpn-inmobi:11.1.1.1.1")
    implementation("com.inmobi.monetization:inmobi-ads-kotlin:11.1.1")

    // Anythink Adx SDK(Necessary)
    implementation("com.anythink.sdk:adapter-tpn-sdm:6.5.72.1.0")
    implementation("com.smartdigimkttech.sdk:smartdigimkttech-sdk:6.5.72")

    // AppLovin
    implementation("com.anythink.sdk:adapter-tpn-applovin:13.6.0.1.1")
    implementation("com.applovin:applovin-sdk:13.6.0")

    // Mintegral
    implementation("com.anythink.sdk:adapter-tpn-mintegral:17.0.91.1.0")
    implementation("com.mbridge.msdk.oversea:mbridge_android_sdk:17.0.91")
    implementation("androidx.recyclerview:recyclerview:1.1.0")

    // Tramini
    implementation("com.anythink.sdk:tramini-plugin-tpn:6.6.20")

    // AdMob / Google Ad Manager
    implementation("com.anythink.sdk:adapter-tpn-admob:25.0.0.1.0")
    implementation("com.google.android.gms:play-services-ads:23.0.0")

    implementation(libs.okhttp)
    implementation(libs.gson)
    implementation(libs.onesignal)
    implementation(libs.ump)
    implementation(libs.installreferrer)
    testImplementation(libs.junit)
    androidTestImplementation(libs.androidx.espresso.core)
    androidTestImplementation(libs.androidx.junit)
    androidTestImplementation(platform(libs.androidx.compose.bom))
}

tasks.register("packLevels") {
    doLast {
        val levelsDir = file("src/main/assets/levels")
        val outFile = file("src/main/assets/levels.bin")
        val totalLevels = 11178
        
        val dataBaos = ByteArrayOutputStream()
        val dataDos = DataOutputStream(dataBaos)
        val offsets = IntArray(totalLevels)

        for (i in 1..totalLevels) {
            val f = File(levelsDir, "level_$i.json")
            offsets[i - 1] = dataBaos.size()
            if (f.exists()) {
                val text = f.readText()
                val xMatch = Regex("\"XSize\"\\s*:\\s*(\\d+)").find(text)?.groupValues?.get(1)?.toShort() ?: 0
                val yMatch = Regex("\"YSize\"\\s*:\\s*(\\d+)").find(text)?.groupValues?.get(1)?.toShort() ?: 0
                val shapeMatch = Regex("\"ShapeType\"\\s*:\\s*(\\d+)").find(text)?.groupValues?.get(1)?.toShort() ?: 0
                
                val pointMatches = Regex("\"Points\"\\s*:\\s*\\[([^\\]]*)\\]").findAll(text).toList()
                
                dataDos.writeShort(xMatch.toInt())
                dataDos.writeShort(yMatch.toInt())
                dataDos.writeShort(shapeMatch.toInt())
                dataDos.writeShort(pointMatches.size)
                
                for (pm in pointMatches) {
                    val rawPts = pm.groupValues[1].split(",").mapNotNull { it.trim().toIntOrNull() }
                    dataDos.writeShort(rawPts.size)
                    for (p in rawPts) {
                        dataDos.writeShort(p)
                    }
                }
            } else {
                dataDos.writeShort(0)
                dataDos.writeShort(0)
                dataDos.writeShort(0)
                dataDos.writeShort(0)
            }
        }
        dataDos.flush()
        
        val fos = FileOutputStream(outFile)
        val dos = DataOutputStream(fos)
        dos.writeInt(totalLevels)
        for (offset in offsets) {
            dos.writeInt(offset)
        }
        dos.write(dataBaos.toByteArray())
        dos.flush()
        fos.close()
        
        println("SUCCESS: levels.bin created! Size: " + (outFile.length() / 1024 / 1024) + " MB (" + outFile.length() + " bytes)")
    }
}