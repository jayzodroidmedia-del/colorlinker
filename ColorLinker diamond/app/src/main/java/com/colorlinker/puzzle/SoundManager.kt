package com.colorlinker.puzzle

import android.content.Context
import android.media.AudioAttributes
import android.media.AudioFocusRequest
import android.media.AudioManager
import android.media.MediaPlayer
import android.media.SoundPool
import android.os.Build
import android.util.Log
import com.colorlinker.puzzle.R

object SoundManager {
    private var soundPool: SoundPool? = null
    private var mediaPlayer: MediaPlayer? = null
    private var correctSoundId: Int = 0
    private var wrongSoundId: Int = 0
    private var isSfxEnabled = true
    private var isMusicEnabled = true
    private var isClickSoundEnabled = true
    private var isVibrationEnabled = true
    private var prefs: android.content.SharedPreferences? = null
    private var appContext: Context? = null
    @Volatile
    var isAppPaused = false
    private var volume = 1.0f
    private const val TAG = "SoundManager"
    var hasEnteredHomeScreen = false

    fun init(context: Context) {
        Log.d(TAG, "Init called")
        val contextApp = context.applicationContext
        appContext = contextApp
        prefs = contextApp.getSharedPreferences("user_settings", Context.MODE_PRIVATE)
        isVibrationEnabled = prefs?.getBoolean("vibration_enabled", true) ?: true
        isSfxEnabled = prefs?.getBoolean("sfx_enabled", true) ?: true
        isMusicEnabled = prefs?.getBoolean("music_enabled", true) ?: true
        volume = prefs?.getFloat("volume", 1.0f) ?: 1.0f
        
        if (soundPool != null) return

        val audioManager = contextApp.getSystemService(Context.AUDIO_SERVICE) as AudioManager
        val currentVol = audioManager.getStreamVolume(AudioManager.STREAM_MUSIC)
        val maxVol = audioManager.getStreamMaxVolume(AudioManager.STREAM_MUSIC)
        Log.d(TAG, "System MUSIC volume: $currentVol / $maxVol")

        val audioAttributes = AudioAttributes.Builder()
            .setUsage(AudioAttributes.USAGE_GAME)
            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
            .build()

        soundPool = SoundPool.Builder()
            .setMaxStreams(10)
            .setAudioAttributes(audioAttributes)
            .build()

        soundPool?.setOnLoadCompleteListener { _, sampleId, status ->
            Log.d(TAG, "SoundPool sample $sampleId loaded with status $status")
        }

        loadResources(contextApp)
    }

    private fun loadResources(context: Context) {
        try {
            // SFX - direct compile-time references to prevent resource shrinker from stripping them in release builds
            correctSoundId = soundPool?.load(context, R.raw.correctnew, 1) ?: 0
            Log.i(TAG, "Loading correct sound, ID: $correctSoundId")

            wrongSoundId = soundPool?.load(context, R.raw.wrongarrow, 1) ?: 0
            Log.i(TAG, "Loading wrong sound, ID: $wrongSoundId")

            arrowSwipeSoundId = soundPool?.load(context, R.raw.arrrowswipe, 1) ?: 0
            Log.i(TAG, "Loading arrow swipe sound, ID: $arrowSwipeSoundId")

            clickSoundId = soundPool?.load(context, R.raw.click_candy_pop, 1) ?: 0
            Log.i(TAG, "Loading candy pop click sound, ID: $clickSoundId")

            switchSoundId = soundPool?.load(context, R.raw.switch_tactile_snap, 1) ?: 0
            Log.i(TAG, "Loading tactile switch sound, ID: $switchSoundId")

            pipeConnectSoundId = soundPool?.load(context, R.raw.linker_connect, 1) ?: 0
            Log.i(TAG, "Loading connect sound, ID: $pipeConnectSoundId")

            pipeBreakSoundId = soundPool?.load(context, R.raw.linker_break, 1) ?: 0
            Log.i(TAG, "Loading break sound, ID: $pipeBreakSoundId")

            levelWinSoundId = soundPool?.load(context, R.raw.level_win, 1) ?: 0
            Log.i(TAG, "Loading level win sound, ID: $levelWinSoundId")
        } catch (e: Exception) {
            Log.e(TAG, "Error loading resources", e)
        }
    }

    private var arrowSwipeSoundId: Int = 0
    private var clickSoundId: Int = 0
    private var switchSoundId: Int = 0
    private var pipeConnectSoundId: Int = 0
    private var pipeBreakSoundId: Int = 0
    private var levelWinSoundId: Int = 0

    fun playPipeConnectSound() {
        if (isAppPaused || !isSfxEnabled) return
        val context = appContext
        if (soundPool == null && context != null) {
            init(context)
        }
        val effVol = if (volume > 0f) volume else 1.0f
        if (pipeConnectSoundId != 0) {
            soundPool?.play(pipeConnectSoundId, effVol, effVol, 1, 0, 1.0f)
        }
    }

    fun playPipeBreakSound() {
        if (isAppPaused || !isSfxEnabled) return
        val context = appContext
        if (soundPool == null && context != null) {
            init(context)
        }
        val effVol = (if (volume > 0f) volume else 1.0f) * 0.85f
        if (pipeBreakSoundId != 0) {
            soundPool?.play(pipeBreakSoundId, effVol, effVol, 1, 0, 1.0f)
        }
    }

    fun playLevelWinSound() {
        if (isAppPaused || !isSfxEnabled) return
        val context = appContext
        if (soundPool == null && context != null) {
            init(context)
        }
        val effVol = if (volume > 0f) volume else 1.0f
        if (levelWinSoundId != 0) {
            soundPool?.play(levelWinSoundId, effVol, effVol, 1, 0, 1.0f)
        } else {
            playCorrectSound()
        }
    }

    fun playArrowSwipeSound() {
        if (isAppPaused) return
        val context = appContext
        if (soundPool == null && context != null) {
            init(context)
        }
        val effVol = if (volume > 0f) volume else 1.0f
        val streamId = if (isSfxEnabled && arrowSwipeSoundId != 0) {
            soundPool?.play(arrowSwipeSoundId, effVol, effVol, 1, 0, 1f) ?: 0
        } else 0
        Log.i(TAG, "Playing Arrow Swipe Sound. Enabled: $isSfxEnabled, ID: $arrowSwipeSoundId, StreamId: $streamId, Vol: $effVol")
    }

    fun playCorrectSound() {
        if (isAppPaused) return
        val context = appContext
        if (soundPool == null && context != null) {
            init(context)
        }
        val effVol = if (volume > 0f) volume else 1.0f
        val streamId = if (isSfxEnabled && correctSoundId != 0) {
            soundPool?.play(correctSoundId, effVol, effVol, 1, 0, 1f) ?: 0
        } else 0
        Log.i(TAG, "Playing Correct Sound. Enabled: $isSfxEnabled, ID: $correctSoundId, StreamId: $streamId, Vol: $effVol")
    }

    fun playWrongSound() {
        if (isAppPaused) return
        val context = appContext
        if (soundPool == null && context != null) {
            init(context)
        }
        val effVol = if (volume > 0f) volume else 1.0f
        val streamId = if (isSfxEnabled && wrongSoundId != 0) {
            soundPool?.play(wrongSoundId, effVol, effVol, 1, 0, 1f) ?: 0
        } else 0
        Log.i(TAG, "Playing Wrong Sound. Enabled: $isSfxEnabled, ID: $wrongSoundId, StreamId: $streamId, Vol: $effVol")
    }

    private var shouldPlayMusic = false

    fun playClickSound() {
        if (isAppPaused || !isSfxEnabled) return
        val context = appContext
        if (soundPool == null && context != null) {
            init(context)
        }
        val effVol = if (volume > 0f) volume else 1.0f
        if (clickSoundId != 0) {
            soundPool?.play(clickSoundId, effVol, effVol, 1, 0, 1.0f)
        } else if (arrowSwipeSoundId != 0) {
            soundPool?.play(arrowSwipeSoundId, effVol, effVol, 1, 0, 1.4f)
        }
    }

    fun playSwitchSound() {
        if (isAppPaused || !isSfxEnabled) return
        val context = appContext
        if (soundPool == null && context != null) {
            init(context)
        }
        val effVol = if (volume > 0f) volume else 1.0f
        if (switchSoundId != 0) {
            soundPool?.play(switchSoundId, effVol, effVol, 1, 0, 1.0f)
        } else {
            playClickSound()
        }
    }

    fun startMusic() {
        shouldPlayMusic = true
        if (isAppPaused || !isMusicEnabled) return
        playMusicActual()
    }

    fun stopMusic() {
        shouldPlayMusic = false
        pauseMusicActual()
    }

    private fun playMusicActual() {
        // Disabled as per user request to remove background music
    }

    private fun pauseMusicActual() {
        try {
            if (mediaPlayer?.isPlaying == true) {
                mediaPlayer?.pause()
            }
            Log.d(TAG, "Music paused successfully")
        } catch (e: Exception) {
            Log.e(TAG, "Error pausing music", e)
        }
    }

    fun onAppPause() {
        isAppPaused = true
        soundPool?.autoPause()
        pauseMusicActual()
    }

    fun onAppResume() {
        isAppPaused = false
        soundPool?.autoResume()
        if (isMusicEnabled && shouldPlayMusic) {
            playMusicActual()
        }
    }

    fun setSfxEnabled(enabled: Boolean) {
        isSfxEnabled = enabled
        prefs?.edit()?.putBoolean("sfx_enabled", enabled)?.apply()
    }

    fun isSfxEnabled() = isSfxEnabled

    fun setMusicEnabled(enabled: Boolean) {
        isMusicEnabled = enabled
        prefs?.edit()?.putBoolean("music_enabled", enabled)?.apply()
        if (enabled) {
            if (shouldPlayMusic) {
                playMusicActual()
            }
        } else {
            pauseMusicActual()
        }
    }

    fun isMusicEnabled() = isMusicEnabled

    fun setClickSoundEnabled(enabled: Boolean) {
        isClickSoundEnabled = enabled
    }

    fun isClickSoundEnabled() = isClickSoundEnabled

    fun setVibrationEnabled(enabled: Boolean) {
        isVibrationEnabled = enabled
        prefs?.edit()?.putBoolean("vibration_enabled", enabled)?.apply()
    }

    fun isVibrationEnabled() = isVibrationEnabled

    fun setVolume(v: Float) {
        volume = v.coerceIn(0f, 1f)
        mediaPlayer?.let { mp ->
            val musicVol = volume * 0.4f
            mp.setVolume(musicVol, musicVol)
        }
        prefs?.edit()?.putFloat("volume", volume)?.apply()
        Log.d(TAG, "Volume set to $volume")
    }

    fun getVolume() = volume

    fun release() {
        soundPool?.release()
        soundPool = null
        try {
            mediaPlayer?.stop()
            mediaPlayer?.release()
        } catch (e: Exception) {}
        mediaPlayer = null
    }
}
