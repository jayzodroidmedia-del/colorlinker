package com.colorlinker.puzzle.model

import com.google.gson.annotations.SerializedName

data class ApiResponse(
    @SerializedName("status") val status: String,
    @SerializedName("data") val data: AppSettingsData
)

data class AppSettingsData(
    @SerializedName("ad_settings") val adSettings: AdSettings,
    @SerializedName("ad_control") val adControl: List<AdControl>,
    @SerializedName("reward_settings") val rewardSettings: List<RewardSetting>? = null,
    @SerializedName("daily_reset") val dailyReset: Int = 0,
    @SerializedName("install_tasks") val installTasks: List<InstallTask> = emptyList(),
    @SerializedName("user_level") val userLevel: Int = 1,
    @SerializedName("rewards_enabled") val rewardsEnabled: Int = 1,
    @SerializedName("banners") val banners: List<BannerItem> = emptyList(),
    @SerializedName("tester_program") val testerProgram: TesterProgramSettings? = null,
    @SerializedName("payout_ticker") val payoutTicker: PayoutTickerConfig? = null
)

data class PayoutTickerItem(
    @SerializedName("name") val name: String = "Tester",
    @SerializedName("amount") val amount: Int = 150,
    @SerializedName("method") val method: String = "UPI",
    @SerializedName("time") val time: String = "Just now"
)

data class PayoutTickerConfig(
    @SerializedName("enabled") val enabled: Boolean = true,
    @SerializedName("show_home") val showHome: Boolean = true,
    @SerializedName("show_register") val showRegister: Boolean = true,
    @SerializedName("show_dashboard") val showDashboard: Boolean = true,
    @SerializedName("speed") val speed: Int = 25,
    @SerializedName("items") val items: List<PayoutTickerItem> = emptyList()
)

data class AdSettings(
    @SerializedName("id") val id: Int,
    @SerializedName("interstitial_id") val interstitialId: String,
    @SerializedName("native_id") val nativeId: String,
    @SerializedName("rewarded_id") val rewardedId: String,
    @SerializedName("topon_app_id") val toponAppId: String? = null,
    @SerializedName("topon_app_key") val toponAppKey: String? = null,
    @SerializedName("topon_splash_id") val toponSplashId: String? = null,
    @SerializedName("topon_interstitial_id") val toponInterstitialId: String? = null,
    @SerializedName("topon_rewarded_id") val toponRewardedId: String? = null,
    @SerializedName("topon_native_id") val toponNativeId: String? = null,
    @SerializedName("onesignal_app_id") val onesignalAppId: String? = null,
    @SerializedName("native_enabled") val nativeEnabled: Int = 0,
    @SerializedName("rewards_enabled") val rewardsEnabled: Int = 1,
    @SerializedName("update_url") val updateUrl: String? = null,
    @SerializedName("update_version") val updateVersion: String? = null,
    @SerializedName("update_status") val updateStatus: Int = 0
)

data class BannerItem(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("title") val title: String = "",
    @SerializedName("description") val description: String = "",
    @SerializedName("image_url") val imageUrl: String = "",
    @SerializedName("action_type") val actionType: String = "tester_program", // "tester_program", "reward_center", "open_url", "external_url"
    @SerializedName("action_value") val actionValue: String = "",
    @SerializedName("button_text") val buttonText: String = ""
)

data class TesterProgramSettings(
    @SerializedName("is_active") val isActive: Int = 1,
    @SerializedName("total_days") val totalDays: Int = 7,
    @SerializedName("reward_amount") val rewardAmount: Int = 150,
    @SerializedName("instant_approval") val instantApproval: Int = 1,
    @SerializedName("badge_text") val badgeText: String = "EARN ₹150 GUARANTEED",
    @SerializedName("badge_color") val badgeColor: String = "#06D6A0",
    @SerializedName("title") val title: String = "Join the 7-Day Testing Team",
    @SerializedName("description") val description: String = "Play 10 puzzle levels every day for 7 days, submit short daily bug reviews, and claim direct UPI/Paytm payout on Day 7!",
    @SerializedName("perk1_title") val perk1Title: String = "10 Levels/Day",
    @SerializedName("perk1_subtitle") val perk1Subtitle: String = "Daily Target",
    @SerializedName("perk2_title") val perk2Title: String = "Bug Reports",
    @SerializedName("perk2_subtitle") val perk2Subtitle: String = "Quick Review",
    @SerializedName("perk3_title") val perk3Title: String = "Instant Cash",
    @SerializedName("perk3_subtitle") val perk3Subtitle: String = "₹150 Reward"
)

data class TesterDayItem(
    @SerializedName("day_number") val dayNumber: Int = 1,
    @SerializedName("title") val title: String = "",
    @SerializedName("instructions") val instructions: String = "",
    @SerializedName("start_level") val startLevel: Int = 1,
    @SerializedName("end_level") val endLevel: Int = 10,
    @SerializedName("target_levels") val targetLevels: Int = 10,
    @SerializedName("levels_completed") val levelsCompleted: Int = 0,
    @SerializedName("status") val status: String = "locked", // "locked", "in_progress", "completed"
    @SerializedName("is_completed") val isCompleted: Boolean = false,
    @SerializedName("unlocks_tomorrow") val unlocksTomorrow: Boolean = false,
    @SerializedName("feedback_submitted") val feedbackSubmitted: Boolean = false,
    @SerializedName("feedback_text") val feedbackText: String = ""
)

data class TesterUserInfo(
    @SerializedName("name") val name: String = "",
    @SerializedName("mobile") val mobile: String = "",
    @SerializedName("email") val email: String = "",
    @SerializedName("registered_at") val registeredAt: String = ""
)

data class TesterPayoutMethodItem(
    @SerializedName("name") val name: String = "UPI",
    @SerializedName("input_placeholder") val inputPlaceholder: String = "Enter UPI ID (e.g. name@okhdfcbank)"
)

data class TesterStatusResponse(
    @SerializedName("registered") val registered: Boolean = false,
    @SerializedName("status") val status: String = "not_registered", // "not_registered", "pending", "active", "rejected", "completed"
    @SerializedName("is_finished") val isFinished: Boolean = false,
    @SerializedName("current_day") val currentDay: Int = 1,
    @SerializedName("current_cycle") val currentCycle: Int = 1,
    @SerializedName("is_today_completed") val isTodayCompleted: Boolean = false,
    @SerializedName("last_completed_day") val lastCompletedDay: Int = 0,
    @SerializedName("user") val user: TesterUserInfo? = null,
    @SerializedName("days") val days: List<TesterDayItem> = emptyList(),
    @SerializedName("payout_methods") val payoutMethods: List<TesterPayoutMethodItem> = emptyList(),
    @SerializedName("current_day_ready_for_review") val currentDayReadyForReview: Boolean = false,
    @SerializedName("can_claim") val canClaim: Boolean = false,
    @SerializedName("has_claimed") val hasClaimed: Boolean = false,
    @SerializedName("claim_info") val claimInfo: Map<String, Any>? = null,
    @SerializedName("reward_amount") val rewardAmount: Int = 150,
    @SerializedName("total_days") val totalDays: Int = 7,
    @SerializedName("payout_ticker") val payoutTicker: PayoutTickerConfig? = null
)

data class AdControl(
    @SerializedName("screen_name") val screenName: String,
    @SerializedName("ad_type") val adType: String
)

data class InstallTask(
    @SerializedName("level") val level: Int,
    @SerializedName("required_mb") val requiredMb: Int
)

data class RewardSetting(
    @SerializedName("required_level") val requiredLevel: Int,
    @SerializedName("reward_amount") val rewardAmount: Int,
    @SerializedName("message") val message: String,
    @SerializedName("status") val status: Int = 1
)

data class RewardHistoryItem(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("type") val type: String = "tester", // "tester" or "milestone"
    @SerializedName("cycle") val cycle: Int = 1,
    @SerializedName("amount") val amount: Int = 0,
    @SerializedName("method") val method: String = "UPI",
    @SerializedName("account") val account: String = "",
    @SerializedName("voucher_code") val voucherCode: String? = null,
    @SerializedName("admin_notes") val adminNotes: String? = null,
    @SerializedName("status") val status: String = "pending", // "pending", "sent", "completed", "rejected"
    @SerializedName("created_at") val createdAt: String = ""
)


