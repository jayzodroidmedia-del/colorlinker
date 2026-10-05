package com.colorlinker.puzzle

import android.content.Context
import android.os.Handler
import android.os.Looper
import android.provider.Settings
import android.util.Base64
import android.util.Log
import okhttp3.*
import org.json.JSONObject
import java.io.IOException
import java.security.KeyFactory
import java.security.spec.X509EncodedKeySpec
import javax.crypto.Cipher
import javax.crypto.Mac
import javax.crypto.spec.IvParameterSpec
import javax.crypto.spec.SecretKeySpec
import java.util.Random

object GameSecurity {
    private const val TAG = "GameSecurity"
    private val mainHandler = Handler(Looper.getMainLooper())

    private const val HARDCODED_MAIN_KEY = "sxguGsnhZ0qVHWWZuRscY0rBA23kxA4M"
    private const val HARDCODED_MASTER_SECRET = "9pMuRqBvNnNlKQATwhV2oWAQazjlsEiP"

    private const val ENCODED_REGISTER_URL = "aHR0cHM6Ly9jb2xvcmxpbmtlci5nYW1lZG9zdC5jb20vcmVnaXN0ZXIucGhw"
    private const val ENCODED_GATEWAY_URL = "aHR0cHM6Ly9jb2xvcmxpbmtlci5nYW1lZG9zdC5jb20vaW5kZXgucGhw"

    private val client = OkHttpClient()
    private var isRegistering = false
    private val registrationCallbacks = mutableListOf<(Boolean) -> Unit>()

    private const val INDEX_LIST = "*?**?v*?^*?N*??*?K*?=*?!*?+*?~*K@*Kw*K#*Kx*K**Kv*K^*KN*K?*KK*K=*K!*K+*K~*=@*=w*=#*=x*=**=v*=^*=N*=?*=K*==*=!*=+*=~*!@*!w*!#*!x*!**!v*!^*!N*!?*!K*!=*!!*!+*!~*+@*+w*+#*+x*+**+v*+^*+N*+?*+K*+=*+!*++*+~*~@*~w*~#*~x*~**~v*~^*~N*~?*~K*~=*~!*~+*~~v@@v@wv@#v@xv@*v@vv@^v@Nv@?v@Kv@=v@!v@+v@~vw@vwwvw#vwxvw*vwv"
    private const val MY_ALL_STRING = "ABCDEFGHIJKLMNOPQRSTUVWXZabcdefghijklmnopqrstuvwxyz0123456789\";?:={}() +-*/.!_'&|$,[]"

    private fun getIndexCode(index: Int): String {
        val pos = index * 3
        if (pos < 0 || pos + 3 > INDEX_LIST.length) return INDEX_LIST.substring(0, 3)
        return INDEX_LIST.substring(pos, pos + 3)
    }

    // Custom noise string obfuscation matching the PHP decryptMyString and C# EncryptMyString
    fun encryptMyString(input: String): String {
        if (input.isEmpty()) return ""
        val sb = StringBuilder()
        val random = Random()
        for (c in input) {
            val ci = MY_ALL_STRING.indexOf(c)
            if (ci == -1) continue
            val ts = ci + 1
            
            val maxLimit = Math.min(7, ts)
            var fv = if (maxLimit > 1) random.nextInt(maxLimit) + 1 else 1
            if (fv >= ts) fv = 1
            val sv = ts - fv
            
            sb.append(getIndexCode(fv))
            for (j in 1..9) {
                if (j == fv) {
                    sb.append(getIndexCode(fv))
                } else if (j == fv + 1) {
                    sb.append(getIndexCode(sv))
                } else {
                    sb.append(getIndexCode(0))
                }
            }
        }
        return sb.toString()
    }

    private fun deriveFieldKey(fieldName: String, masterSecret: String): String {
        val mac = Mac.getInstance("HmacSHA256")
        val secretKey = SecretKeySpec(masterSecret.toByteArray(Charsets.UTF_8), "HmacSHA256")
        mac.init(secretKey)
        val hashBytes = mac.doFinal(fieldName.toByteArray(Charsets.UTF_8))
        val hexString = hashBytes.joinToString("") { String.format("%02x", it) }
        return hexString.substring(0, 32)
    }

    private fun aesEncrypt(plainText: String, key: String): String {
        val keyBytes = key.toByteArray(Charsets.UTF_8)
        val ivBytes = ByteArray(16)
        System.arraycopy(keyBytes, 0, ivBytes, 0, 16)
        
        val secretKey = SecretKeySpec(keyBytes, "AES")
        val ivSpec = IvParameterSpec(ivBytes)
        
        val cipher = Cipher.getInstance("AES/CBC/PKCS5Padding")
        cipher.init(Cipher.ENCRYPT_MODE, secretKey, ivSpec)
        val encryptedBytes = cipher.doFinal(plainText.toByteArray(Charsets.UTF_8))
        return Base64.encodeToString(encryptedBytes, Base64.NO_WRAP)
    }

    private fun encryptPayload(
        payload: Map<String, String>,
        mainKey: String,
        masterSecret: String,
        aesOnlyFields: Set<String>,
        serverRsa1PublicKeyBase64: String? = null
    ): String {
        val encryptedFields = JSONObject()
        val rsaEnabled = !serverRsa1PublicKeyBase64.isNullOrEmpty()
        
        for ((name, value) in payload) {
            val isRsaField = rsaEnabled && (name == "device_id" || name == "key_id" || name == "milisecond")
            
            val valueToProcess = if (isRsaField && serverRsa1PublicKeyBase64 != null) {
                KeyStoreHelper.encryptWithRSA1(value, serverRsa1PublicKeyBase64)
            } else {
                value
            }
            
            // RSA encrypted fields skip encryptMyString (already encrypted)
            // Non-RSA fields need encryptMyString for obfuscation
            val valueToEncrypt = when {
                aesOnlyFields.contains(name) -> valueToProcess
                isRsaField -> valueToProcess  // RSA encrypted - no obfuscation needed
                else -> encryptMyString(valueToProcess)
            }
            
            val fieldKey = deriveFieldKey(name, masterSecret)
            val encryptedVal = aesEncrypt(valueToEncrypt, fieldKey)
            encryptedFields.put(name, encryptedVal)
        }
        
        val jsonString = encryptedFields.toString()
        return aesEncrypt(jsonString, mainKey)
    }

    private fun deliverResponse(onResponse: (Boolean, String?) -> Unit, success: Boolean, result: String?) {
        if (Looper.myLooper() == Looper.getMainLooper()) {
            try {
                onResponse(success, result)
            } catch (e: Exception) {
                Log.e(TAG, "Error in onResponse: ${e.message}")
            }
        } else {
            mainHandler.post {
                try {
                    onResponse(success, result)
                } catch (e: Exception) {
                    Log.e(TAG, "Error in onResponse: ${e.message}")
                }
            }
        }
    }

    private fun finishRegistration(success: Boolean) {
        val callbacks = synchronized(this) {
            val list = ArrayList(registrationCallbacks)
            registrationCallbacks.clear()
            isRegistering = false
            list
        }
        mainHandler.post {
            for (cb in callbacks) {
                try {
                    cb(success)
                } catch (e: Exception) {
                    Log.e(TAG, "Error in registration callback: ${e.message}")
                }
            }
        }
    }

    fun registerDevice(context: Context, onComplete: (Boolean) -> Unit) {
        synchronized(this) {
            if (isRegistering) {
                registrationCallbacks.add(onComplete)
                return
            }
            isRegistering = true
            registrationCallbacks.add(onComplete)
        }

        try {
            if (KeyStoreHelper.rsa2KeyExists()) {
                KeyStoreHelper.deleteRSA2Key()
            }
            val rsa2PublicKeyPem = KeyStoreHelper.generateRSA2KeyPair()
            if (rsa2PublicKeyPem.startsWith("ERROR")) {
                Log.e(TAG, "Failed to generate RSA2 KeyPair: $rsa2PublicKeyPem")
                finishRegistration(false)
                return
            }
            
            val deviceId = Settings.Secure.getString(context.contentResolver, Settings.Secure.ANDROID_ID) ?: "unknown"
            val deviceInfoJson = DeviceInfoHelper.getDeviceInfoJson(context)
            
            val prefs = context.getSharedPreferences("app_prefs", Context.MODE_PRIVATE)
            val referrer = prefs.getString("referrer", "") ?: ""
            val gid = prefs.getString("gid", "") ?: ""

            val payload = mutableMapOf(
                "device_id" to deviceId,
                "action" to "register",
                "rsa2_public_key" to rsa2PublicKeyPem,
                "device_info" to deviceInfoJson,
                "pkg" to context.packageName,
                "milisecond" to System.currentTimeMillis().toString()
            )
            if (referrer.isNotEmpty()) payload["referrer"] = referrer
            if (gid.isNotEmpty()) payload["gid"] = gid
            
            val spoint = encryptPayload(payload, HARDCODED_MAIN_KEY, HARDCODED_MASTER_SECRET, setOf("rsa2_public_key", "device_info"))
            val registerUrl = NetworkUtils.decodeUrl(ENCODED_REGISTER_URL)
            
            val formBody = FormBody.Builder()
                .add("spoint", spoint)
                .add("did", deviceId)
                .build()
                
            val request = Request.Builder()
                .url(registerUrl)
                .post(formBody)
                .build()
                
            client.newCall(request).enqueue(object : Callback {
                override fun onFailure(call: Call, e: IOException) {
                    Log.e(TAG, "Registration failed: ${e.message}")
                    finishRegistration(false)
                }

                override fun onResponse(call: Call, response: Response) {
                    response.use {
                        if (it.isSuccessful) {
                            val bodyStr = it.body?.string() ?: ""
                            try {
                                val json = JSONObject(bodyStr)
                                if (json.optBoolean("success", false)) {
                                    val encryptedData = json.getString("encrypted_data")
                                    val keyId = json.getString("key_id")
                                    
                                    val decrypted = KeyStoreHelper.decryptWithRSA2(encryptedData)
                                    if (decrypted.startsWith("ERROR")) {
                                        Log.e(TAG, "Failed to decrypt server response: $decrypted")
                                        finishRegistration(false)
                                        return
                                    }
                                    
                                    val parts = decrypted.split("|")
                                    if (parts.size >= 4) {
                                        val serverRsa1PubKey = parts[0] // Kept in base64 format for encryptWithRSA1
                                        val mainKey = parts[1]
                                        val masterSecret = parts[2]
                                        val returnedKeyId = parts[3]
                                        
                                        SecureStorage.saveRegistrationData(
                                            context,
                                            serverRsa1PubKey,
                                            mainKey,
                                            masterSecret,
                                            returnedKeyId
                                        )
                                        
                                        Log.d(TAG, "Device registration successful. Key ID: $returnedKeyId")
                                        finishRegistration(true)
                                    } else {
                                        Log.e(TAG, "Malformed registration payload decrypted: $decrypted")
                                        finishRegistration(false)
                                    }
                                } else {
                                    Log.e(TAG, "Server registration error: ${json.optString("error")}")
                                    finishRegistration(false)
                                }
                            } catch (e: Exception) {
                                Log.e(TAG, "Parse failure on register response: ${e.message}, body: $bodyStr")
                                finishRegistration(false)
                            }
                        } else {
                            Log.e(TAG, "HTTP register error: ${it.code}")
                            finishRegistration(false)
                        }
                    }
                }
            })
        } catch (e: Exception) {
            Log.e(TAG, "Fatal register error: ${e.message}")
            finishRegistration(false)
        }
    }

    fun postSecureRequest(
        context: Context,
        action: String,
        additionalParams: Map<String, String>,
        onResponse: (Boolean, String?) -> Unit
    ) {
        val isRegistered = SecureStorage.isRegistered(context)
        if (!isRegistered) {
            Log.d(TAG, "Device unregistered. Triggering auto-registration...")
            registerDevice(context) { success ->
                if (success) {
                    postSecureRequest(context, action, additionalParams, onResponse)
                } else {
                    deliverResponse(onResponse, false, "Device registration failed")
                }
            }
            return
        }
        
        try {
            val mainKey = SecureStorage.getMainKey(context) ?: throw Exception("Missing main key")
            val masterSecret = SecureStorage.getMasterSecret(context) ?: throw Exception("Missing master secret")
            val serverRsa1Pub = SecureStorage.getRSA1PublicKey(context) ?: throw Exception("Missing server RSA public key")
            val keyId = SecureStorage.getKeyId(context) ?: throw Exception("Missing key ID")
            
            val deviceId = Settings.Secure.getString(context.contentResolver, Settings.Secure.ANDROID_ID) ?: "unknown"
            
            val prefs = context.getSharedPreferences("app_prefs", Context.MODE_PRIVATE)
            val referrer = prefs.getString("referrer", "") ?: ""
            val gid = prefs.getString("gid", "") ?: ""

            val payload = mutableMapOf<String, String>()
            payload["action"] = action
            payload["device_id"] = deviceId
            payload["key_id"] = keyId
            payload["milisecond"] = System.currentTimeMillis().toString()
            payload["rsa_enabled"] = "1"
            payload["device_info"] = DeviceInfoHelper.getDeviceInfoJson(context)
            payload["pkg"] = context.packageName
            if (referrer.isNotEmpty()) payload["referrer"] = referrer
            if (gid.isNotEmpty()) payload["gid"] = gid
            
            payload.putAll(additionalParams)
            
            val aesFields = setOf("device_info", "rsa2_public_key", "email", "account", "account_details", "feedback_text", "review_text", "feedback", "name", "mobile", "method", "detail")
            val spoint = encryptPayload(payload, mainKey, masterSecret, aesFields, serverRsa1Pub)
            val gatewayUrl = NetworkUtils.decodeUrl(ENCODED_GATEWAY_URL)
            
            val formBody = FormBody.Builder()
                .add("spoint", spoint)
                .add("did", deviceId)
                .build()
                
            val request = Request.Builder()
                .url(gatewayUrl)
                .post(formBody)
                .build()
                
            client.newCall(request).enqueue(object : Callback {
                override fun onFailure(call: Call, e: IOException) {
                    Log.e(TAG, "Request error: ${e.message}")
                    deliverResponse(onResponse, false, e.message)
                }

                override fun onResponse(call: Call, response: Response) {
                    response.use {
                        val bodyStr = it.body?.string() ?: ""
                        if (it.isSuccessful) {
                            try {
                                val json = JSONObject(bodyStr)
                                val success = json.optBoolean("success", false)
                                if (success) {
                                    deliverResponse(onResponse, true, bodyStr)
                                } else {
                                    val errorMsg = json.optString("error", "Unknown error")
                                    Log.e(TAG, "API error: $errorMsg")
                                    if (errorMsg.contains("Decrypt failed", ignoreCase = true) || errorMsg.contains("No keys found", ignoreCase = true)) {
                                        Log.w(TAG, "Server session keys invalid. Clearing local keys...")
                                        SecureStorage.deleteAll(context)
                                    }
                                    deliverResponse(onResponse, false, errorMsg)
                                }
                            } catch (e: Exception) {
                                Log.e(TAG, "JSON Parse error: ${e.message}, body: $bodyStr")
                                deliverResponse(onResponse, false, "Parse error")
                            }
                        } else {
                            Log.e(TAG, "HTTP error: ${it.code}, body: $bodyStr")
                            deliverResponse(onResponse, false, "HTTP ${it.code}")
                        }
                    }
                }
            })
        } catch (e: Exception) {
            Log.e(TAG, "Fatal secure request setup: ${e.message}")
            deliverResponse(onResponse, false, e.message)
        }
    }
}
