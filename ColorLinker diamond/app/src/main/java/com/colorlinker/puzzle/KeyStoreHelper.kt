package com.colorlinker.puzzle

import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import java.security.KeyFactory
import java.security.KeyStore
import java.security.KeyPairGenerator
import java.security.PrivateKey
import java.security.PublicKey
import java.security.spec.X509EncodedKeySpec
import javax.crypto.Cipher

object KeyStoreHelper {
    private const val KEYSTORE_PROVIDER = "AndroidKeyStore"
    private const val KEY_ALIAS_RSA2 = "GameDeviceRSA2Key"

    fun generateRSA2KeyPair(): String {
        return try {
            val generator = KeyPairGenerator.getInstance(
                KeyProperties.KEY_ALGORITHM_RSA, KEYSTORE_PROVIDER
            )
            generator.initialize(
                KeyGenParameterSpec.Builder(
                    KEY_ALIAS_RSA2,
                    KeyProperties.PURPOSE_DECRYPT or KeyProperties.PURPOSE_ENCRYPT
                )
                    .setKeySize(2048)
                    .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_RSA_PKCS1)
                    .setDigests(KeyProperties.DIGEST_SHA256)
                    .build()
            )
            val keyPair = generator.generateKeyPair()
            Base64.encodeToString(keyPair.public.encoded, Base64.NO_WRAP)
        } catch (e: Exception) {
            "ERROR:${e.message}"
        }
    }

    fun rsa2KeyExists(): Boolean {
        return try {
            val ks = KeyStore.getInstance(KEYSTORE_PROVIDER)
            ks.load(null)
            ks.containsAlias(KEY_ALIAS_RSA2)
        } catch (e: Exception) {
            false
        }
    }

    fun getRSA2PublicKey(): String? {
        return try {
            val ks = KeyStore.getInstance(KEYSTORE_PROVIDER)
            ks.load(null)
            val entry = ks.getEntry(KEY_ALIAS_RSA2, null) ?: return null
            val pk = (entry as KeyStore.PrivateKeyEntry).certificate.publicKey
            Base64.encodeToString(pk.encoded, Base64.NO_WRAP)
        } catch (e: Exception) {
            null
        }
    }

    fun decryptWithRSA2(encryptedData: String): String {
        return try {
            val ks = KeyStore.getInstance(KEYSTORE_PROVIDER)
            ks.load(null)
            val entry = ks.getEntry(KEY_ALIAS_RSA2, null) ?: throw Exception("Key not found")
            val privateKey = (entry as KeyStore.PrivateKeyEntry).privateKey

            val chunks = encryptedData.split(".")
            val result = StringBuilder()

            for (chunk in chunks) {
                if (chunk.isEmpty()) continue
                val cipher = Cipher.getInstance("RSA/ECB/PKCS1Padding")
                cipher.init(Cipher.DECRYPT_MODE, privateKey)
                val decBytes = cipher.doFinal(Base64.decode(chunk, Base64.NO_WRAP))
                result.append(String(decBytes, Charsets.UTF_8))
            }
            result.toString()
        } catch (e: Exception) {
            "ERROR:${e.message}"
        }
    }

    fun encryptWithRSA1(plainText: String, rsa1PublicKeyBase64: String): String {
        return try {
            val pemBytes = Base64.decode(rsa1PublicKeyBase64, Base64.NO_WRAP)
            val pemString = String(pemBytes, Charsets.UTF_8)
            val clean = pemString
                .replace("-----BEGIN PUBLIC KEY-----", "")
                .replace("-----END PUBLIC KEY-----", "")
                .replace("\r", "")
                .replace("\n", "")
                .replace(" ", "")
                .trim()
            val keyBytes = Base64.decode(clean, Base64.NO_WRAP)
            val spec = X509EncodedKeySpec(keyBytes)
            val factory = KeyFactory.getInstance("RSA")
            val publicKey = factory.generatePublic(spec)
            val cipher = Cipher.getInstance("RSA/ECB/PKCS1Padding")
            cipher.init(Cipher.ENCRYPT_MODE, publicKey)
            val encBytes = cipher.doFinal(plainText.toByteArray(Charsets.UTF_8))
            Base64.encodeToString(encBytes, Base64.NO_WRAP)
        } catch (e: Exception) {
            "ERROR:${e.message}"
        }
    }

    fun deleteRSA2Key(): Boolean {
        return try {
            val ks = KeyStore.getInstance(KEYSTORE_PROVIDER)
            ks.load(null)
            ks.deleteEntry(KEY_ALIAS_RSA2)
            true
        } catch (e: Exception) {
            false
        }
    }
}
