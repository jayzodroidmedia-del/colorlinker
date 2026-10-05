package com.colorlinker.puzzle

import android.content.Context
import android.content.pm.PackageManager
import android.content.pm.ApplicationInfo
import android.os.BatteryManager
import android.os.Build
import android.provider.Settings
import android.util.DisplayMetrics
import android.hardware.SensorManager
import android.hardware.Sensor
import android.content.Intent
import android.content.IntentFilter
import org.json.JSONObject
import org.json.JSONArray
import java.io.BufferedReader
import java.io.File
import java.io.FileInputStream
import java.io.InputStreamReader

object DeviceInfoHelper {

    fun getDeviceInfoJson(context: Context): String {
        return try {
            val info = JSONObject()
            val pkg = context.packageName

            info.put("p", pkg)
            info.put("pn", getProcName())
            info.put("aid", Settings.Secure.getString(context.contentResolver, Settings.Secure.ANDROID_ID))

            try {
                val ai = context.packageManager.getApplicationInfo(pkg, 0)
                info.put("dp", ai.dataDir + "/")
                info.put("sp", ai.sourceDir)
            } catch (e: Exception) {}

            info.put("fp", Build.FINGERPRINT)
            info.put("br", Build.BRAND)
            info.put("md", Build.MODEL)
            info.put("mf", Build.MANUFACTURER)
            info.put("dv", Build.DEVICE)
            info.put("pd", Build.PRODUCT)
            info.put("hw", Build.HARDWARE)
            info.put("bd", Build.BOARD)
            info.put("bl", Build.BOOTLOADER)
            info.put("tg", Build.TAGS ?: "")
            info.put("tp", Build.TYPE)
            info.put("hs", Build.HOST)
            info.put("ds", Build.DISPLAY)
            info.put("ri", Build.ID)
            info.put("sv", Build.VERSION.SDK_INT)
            info.put("rl", Build.VERSION.RELEASE)
            info.put("sr", Build.VERSION.SECURITY_PATCH ?: "")

            val abis = JSONArray()
            for (abi in Build.SUPPORTED_ABIS) abis.put(abi)
            info.put("ab", abis)

            try {
                val f = IntentFilter(Intent.ACTION_BATTERY_CHANGED)
                val b = context.registerReceiver(null, f)
                if (b != null) {
                    info.put("bt", b.getIntExtra(BatteryManager.EXTRA_TEMPERATURE, 0) / 10.0f)
                    info.put("bv", b.getIntExtra(BatteryManager.EXTRA_VOLTAGE, 0))
                    info.put("bs", b.getIntExtra(BatteryManager.EXTRA_STATUS, 0))
                }
            } catch (e: Exception) {}

            info.put("ins", getInstaller(context))

            val dm = context.resources.displayMetrics
            info.put("sw", dm.widthPixels)
            info.put("sh", dm.heightPixels)
            info.put("sd", dm.densityDpi)

            try {
                val sm = context.getSystemService(Context.SENSOR_SERVICE) as SensorManager
                info.put("sc", sm.getSensorList(Sensor.TYPE_ALL).size)
            } catch (e: Exception) {
                info.put("sc", 0)
            }

            info.put("fd", context.filesDir.absolutePath)
            info.put("gp", getAllProps())

            info.toString()
        } catch (e: Exception) {
            "{\"error\":\"${e.message}\"}"
        }
    }

    private fun getAllProps(): JSONObject {
        val props = JSONObject()
        val keys = arrayOf(
            "ro.build.flavor", "ro.build.display.id", "ro.build.version.sdk",
            "ro.build.type", "ro.hardware", "ro.product.model", "ro.product.brand",
            "ro.product.device", "ro.product.board", "ro.product.cpu.abi",
            "ro.product.manufacturer", "ro.product.locale", "ro.product.first_api_level",
            "ro.debuggable", "ro.secure", "ro.build.characteristics",
            "ro.kernel.qemu", "ro.boot.hardware", "ro.hardware.chipname",
            "ro.board.platform", "gsm.version.baseband", "persist.sys.language",
            "persist.sys.timezone", "qemu.hw.mainkeys", "init.svc.qemud",
            "ro.setupwizard.mode", "ro.com.google.gmsversion"
        )
        for (key in keys) {
            val valStr = getProp(key)
            if (valStr.isNotEmpty()) {
                val shortKey = key.substring(key.lastIndexOf('.') + 1)
                try {
                    props.put(shortKey, valStr)
                } catch (e: Exception) {}
            }
        }
        return props
    }

    private fun getProp(key: String): String {
        return try {
            val p = Runtime.getRuntime().exec("getprop $key")
            val r = BufferedReader(InputStreamReader(p.inputStream))
            val line = r.readLine()
            p.destroy()
            line?.trim() ?: ""
        } catch (e: Exception) {
            ""
        }
    }

    private fun getInstaller(context: Context): String {
        return try {
            val pm = context.packageManager
            var ins: String? = null
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                try {
                    ins = pm.getInstallSourceInfo(context.packageName).installingPackageName
                } catch (e: Exception) {}
            }
            if (ins == null) {
                @Suppress("DEPRECATION")
                ins = pm.getInstallerPackageName(context.packageName)
            }
            ins ?: "unknown"
        } catch (e: Exception) {
            "unknown"
        }
    }

    private fun getProcName(): String {
        try {
            val f = File("/proc/self/cmdline")
            if (f.exists()) {
                val r = BufferedReader(InputStreamReader(FileInputStream(f)))
                val n = r.readLine()
                r.close()
                if (n != null) return n.replace("\u0000", "").trim()
            }
        } catch (e: Exception) {}
        return "unknown"
    }
}
