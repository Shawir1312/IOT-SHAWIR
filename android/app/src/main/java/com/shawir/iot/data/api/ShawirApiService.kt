package com.shawir.iot.data.api

import com.shawir.iot.data.model.*
import retrofit2.http.*

/**
 * Retrofit REST API interface for ShawirIOT Mobile Backend
 */
interface ShawirApiService {

    @GET("api/mobile.php?action=server_info")
    suspend fun getServerInfo(): ApiResponse<ServerInfo>

    // --- Authentication ---

    @FormUrlEncoded
    @POST("api/mobile.php?action=login")
    suspend fun login(
        @Field("email") email: String,
        @Field("password") password: String,
        @Field("device_name") deviceName: String = "Android Native"
    ): ApiResponse<AuthData>

    @FormUrlEncoded
    @POST("api/mobile.php?action=register")
    suspend fun register(
        @Field("name") name: String,
        @Field("email") email: String,
        @Field("password") password: String,
        @Field("device_name") deviceName: String = "Android Native"
    ): ApiResponse<RegisterData>

    @FormUrlEncoded
    @POST("api/mobile.php?action=verify_email_otp")
    suspend fun verifyEmailOtp(
        @Field("email") email: String,
        @Field("otp_code") otpCode: String,
        @Field("device_name") deviceName: String = "Android Native"
    ): ApiResponse<AuthData>

    @FormUrlEncoded
    @POST("api/mobile.php?action=resend_email_otp")
    suspend fun resendEmailOtp(
        @Field("email") email: String
    ): ApiResponse<Any>

    @POST("api/mobile.php?action=logout")
    suspend fun logout(): ApiResponse<Any>

    @GET("api/mobile.php?action=me")
    suspend fun getProfile(): ApiResponse<User>

    // --- Device Management ---

    @GET("api/mobile.php?action=devices")
    suspend fun getDevices(): ApiResponse<List<Device>>

    @FormUrlEncoded
    @POST("api/mobile.php?action=add_device")
    suspend fun addDevice(
        @Field("name") name: String,
        @Field("hardware") hardware: String,
        @Field("connection") connection: String,
        @Field("description") description: String = ""
    ): ApiResponse<Device>

    @FormUrlEncoded
    @POST("api/mobile.php?action=delete_device")
    suspend fun deleteDevice(
        @Field("device_id") deviceId: Int
    ): ApiResponse<Any>

    // --- Dashboard & Widgets ---

    @GET("api/mobile.php?action=device_dashboard")
    suspend fun getDeviceDashboard(
        @Query("device_id") deviceId: Int
    ): ApiResponse<DashboardResponse>

    @FormUrlEncoded
    @POST("api/mobile.php?action=add_widget")
    suspend fun addWidget(
        @Field("device_id") deviceId: Int,
        @Field("type") type: String,
        @Field("label") label: String,
        @Field("pin") pin: String,
        @Field("color") color: String = "#6366f1",
        @Field("min_value") minValue: Float = 0f,
        @Field("max_value") maxValue: Float = 100f,
        @Field("unit") unit: String = "",
        @Field("on_value") onValue: String = "1",
        @Field("off_value") offValue: String = "0"
    ): ApiResponse<Widget>

    @FormUrlEncoded
    @POST("api/mobile.php?action=update_widget")
    suspend fun updateWidget(
        @Field("widget_id") widgetId: Int,
        @Field("label") label: String,
        @Field("pin") pin: String,
        @Field("color") color: String = "#6366f1",
        @Field("min_value") minValue: Float = 0f,
        @Field("max_value") maxValue: Float = 100f,
        @Field("unit") unit: String = "",
        @Field("on_value") onValue: String = "1",
        @Field("off_value") offValue: String = "0"
    ): ApiResponse<Widget>

    @FormUrlEncoded
    @POST("api/mobile.php?action=delete_widget")
    suspend fun deleteWidget(
        @Field("widget_id") widgetId: Int
    ): ApiResponse<Any>

    // --- Real-time Pin & History ---

    @GET("api/mobile.php?action=pin_values")
    suspend fun getPinValues(
        @Query("device_id") deviceId: Int
    ): ApiResponse<PinValuesResponse>

    @FormUrlEncoded
    @POST("api/mobile.php?action=control_pin")
    suspend fun controlPin(
        @Field("device_id") deviceId: Int,
        @Field("pin") pin: String,
        @Field("value") value: String
    ): ApiResponse<Any>

    @GET("api/mobile.php?action=pin_history")
    suspend fun getPinHistory(
        @Query("device_id") deviceId: Int,
        @Query("pin") pin: String,
        @Query("n") limit: Int = 30
    ): ApiResponse<List<PinHistoryPoint>>
}
