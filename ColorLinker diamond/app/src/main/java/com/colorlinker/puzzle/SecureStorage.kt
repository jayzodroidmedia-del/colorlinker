package com.colorlinker.puzzle

import android.content.Context
import android.provider.Settings
import android.util.Log
import java.io.File
import java.security.MessageDigest
import javax.crypto.Cipher
import javax.crypto.spec.IvParameterSpec
import javax.crypto.spec.SecretKeySpec

object SecureStorage {
    private const val TAG = "SecureStorage"
    private const val SALT = "SecureSalt!2024"

    private fun getDeviceKey(context: Context): ByteArray {
        val deviceId = Settings.Secure.getString(context.contentResolver, Settings.Secure.ANDROID_ID) ?: "unknown"
        val pkg = context.packageName
        val raw = deviceId + pkg + SALT
        val digest = MessageDigest.getInstance("SHA-256")
        return digest.digest(raw.toByteArray(Charsets.UTF_8))
    }

    fun save(context: Context, key: String, value: String): Boolean {
        return try {
            val dk = getDeviceKey(context)
            val iv = ByteArray(16)
            System.arraycopy(dk, 0, iv, 0, 16)
            
            val secretKey = SecretKeySpec(dk, "AES")
            val ivSpec = IvParameterSpec(iv)
            
            val cipher = Cipher.getInstance("AES/CBC/PKCS5Padding")
            cipher.init(Cipher.ENCRYPT_MODE, secretKey, ivSpec)
            val encryptedBytes = cipher.doFinal(value.toByteArray(Charsets.UTF_8))
            
            val file = File(context.filesDir, ".sk_$key")
            file.writeBytes(encryptedBytes)
            true
        } catch (e: Exception) {
            Log.e(TAG, "Save [$key] failed: ${e.message}")
            false
        }
    }

    fun load(context: Context, key: String): String? {
        return try {
            val file = File(context.filesDir, ".sk_$key")
            if (!file.exists()) return null
            
            val dk = getDeviceKey(context)
            val iv = ByteArray(16)
            System.arraycopy(dk, 0, iv, 0, 16)
            
            val secretKey = SecretKeySpec(dk, "AES")
            val ivSpec = IvParameterSpec(iv)
            
            val cipher = Cipher.getInstance("AES/CBC/PKCS5Padding")
            cipher.init(Cipher.DECRYPT_MODE, secretKey, ivSpec)
            val encryptedBytes = file.readBytes()
            val decryptedBytes = cipher.doFinal(encryptedBytes)
            String(decryptedBytes, Charsets.UTF_8)
        } catch (e: Exception) {
            Log.e(TAG, "Load [$key] failed: ${e.message}")
            null
        }
    }

    fun delete(context: Context, key: String): Boolean {
        return try {
            val file = File(context.filesDir, ".sk_$key")
            if (file.exists()) {
                file.delete()
            }
            true
        } catch (e: Exception) {
            false
        }
    }

    fun deleteAll(context: Context) {
        delete(context, "rsa1_public_key")
        delete(context, "main_key")
        delete(context, "master_secret")
        delete(context, "key_id")
        delete(context, "registered")
    }

    fun saveRegistrationData(context: Context, rsa1PubKey: String, mainKey: String, masterSecret: String, keyId: String): Boolean {
        var ok = true
        ok = ok && save(context, "rsa1_public_key", rsa1PubKey)
        ok = ok && save(context, "main_key", mainKey)
        ok = ok && save(context, "master_secret", masterSecret)
        ok = ok && save(context, "key_id", keyId)
        ok = ok && save(context, "registered", "true")
        return ok
    }

    fun isRegistered(context: Context): Boolean = load(context, "registered") == "true"
    fun getRSA1PublicKey(context: Context): String? = load(context, "rsa1_public_key")
    fun getMainKey(context: Context): String? = load(context, "main_key")
    fun getMasterSecret(context: Context): String? = load(context, "master_secret")
    fun getKeyId(context: Context): String? = load(context, "key_id")
}
