package com.shawir.iot.data.model

import com.google.gson.annotations.SerializedName

/**
 * Generic API Response wrapper from ShawirIOT Backend
 */
data class ApiResponse<T>(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String? = null,
    @SerializedName("data") val data: T? = null
)

/**
 * User account model
 */
data class User(
    @SerializedName("id") val id: Int,
    @SerializedName("name") val name: String,
    @SerializedName("email") val email: String,
    @SerializedName("role") val role: String? = "user",
    @SerializedName("credits") val credits: Int = 0,
    @SerializedName("plan_name") val planName: String? = "Free",
    @SerializedName("max_devices") val maxDevices: Int = 1,
    @SerializedName("avatar") val avatar: String? = null
)

/**
 * Auth data returned on login & register
 */
data class AuthData(
    @SerializedName("token") val token: String,
    @SerializedName("user") val user: User
)

/**
 * Registration result data (supports email OTP verification)
 */
data class RegisterData(
    @SerializedName("require_verify") val requireVerify: Boolean = false,
    @SerializedName("email") val email: String? = null,
    @SerializedName("token") val token: String? = null,
    @SerializedName("user") val user: User? = null
)

/**
 * IoT Device model
 */
data class Device(
    @SerializedName("id") val id: Int,
    @SerializedName("name") val name: String,
    @SerializedName("description") val description: String? = null,
    @SerializedName("token") val token: String,
    @SerializedName("hardware") val hardware: String? = "ESP8266",
    @SerializedName("connection") val connection: String? = "wifi",
    @SerializedName("is_online") var isOnline: Boolean = false,
    @SerializedName("last_seen") val lastSeen: String? = null,
    @SerializedName("last_ip") val lastIp: String? = null,
    @SerializedName("widget_count") val widgetCount: Int = 0,
    @SerializedName("last_seen_relative") val lastSeenRelative: String? = null
)

/**
 * Dashboard config
 */
data class Dashboard(
    @SerializedName("id") val id: Int,
    @SerializedName("title") val title: String? = "Dashboard"
)

/**
 * Widget model (Value Display, Switch, Button, Slider, Gauge, LED, Chart)
 */
data class Widget(
    @SerializedName("id") val id: Int,
    @SerializedName("dashboard_id") val dashboardId: Int = 0,
    @SerializedName("type") val type: String, // 'value_display', 'switch', 'button', 'slider', 'gauge', 'led', 'line_chart', 'bar_chart'
    @SerializedName("label") val label: String = "Widget",
    @SerializedName("pin") val pin: String? = "V0",
    @SerializedName("color") val color: String? = "#6366f1",
    @SerializedName("text_color") val textColor: String? = "#ffffff",
    @SerializedName("min_value") val minValue: Float = 0f,
    @SerializedName("max_value") val maxValue: Float = 100f,
    @SerializedName("unit") val unit: String? = "",
    @SerializedName("on_value") val onValue: String? = "1",
    @SerializedName("off_value") val offValue: String? = "0",
    @SerializedName("pos_x") val posX: Int = 0,
    @SerializedName("pos_y") val posY: Int = 0,
    @SerializedName("width") val width: Int = 3,
    @SerializedName("height") val height: Int = 2
)

/**
 * Response for device_dashboard action
 */
data class DashboardResponse(
    @SerializedName("device") val device: Device,
    @SerializedName("dashboard") val dashboard: Dashboard,
    @SerializedName("widgets") val widgets: List<Widget>,
    @SerializedName("pin_values") val pinValues: Map<String, String>? = emptyMap()
)

/**
 * Response for real-time polling pin_values action
 */
data class PinValuesResponse(
    @SerializedName("is_online") val isOnline: Boolean,
    @SerializedName("pin_values") val pinValues: Map<String, String>?,
    @SerializedName("timestamp") val timestamp: Long
)

/**
 * Single data point for line/bar charts
 */
data class PinHistoryPoint(
    @SerializedName("value") val value: Float,
    @SerializedName("recorded_at") val recordedAt: String,
    @SerializedName("time_label") val timeLabel: String
)

/**
 * Server info response
 */
data class ServerInfo(
    @SerializedName("platform") val platform: String,
    @SerializedName("version") val version: String,
    @SerializedName("status") val status: String
)
