package com.shawir.iot.data.api

import com.shawir.iot.data.model.*
import retrofit2.http.*

/**
 * Retrofit REST API interface for ShawirIOT Mobile Backend
 */
interface ShawirApiService {

    @GET("api/mobile.php?action=server_info")
    suspend fun getServerInfo(): ApiResponse<ServerInfo>

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
    ): ApiResponse<AuthData>

    @POST("api/mobile.php?action=logout")
    suspend fun logout(): ApiResponse<Any>

    @GET("api/mobile.php?action=me")
    suspend fun getProfile(): ApiResponse<User>

    @GET("api/mobile.php?action=devices")
    suspend fun getDevices(): ApiResponse<List<Device>>

    @GET("api/mobile.php?action=device_dashboard")
    suspend fun getDeviceDashboard(
        @Query("device_id") deviceId: Int
    ): ApiResponse<DashboardResponse>

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
