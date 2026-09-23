package com.shawir.iot.data.repository

import android.content.Context
import android.content.SharedPreferences
import com.google.gson.Gson
import com.shawir.iot.data.model.User

/**
 * Manages user session, authentication token, and configurable server URL.
 */
class SessionManager(context: Context) {

    private val prefs: SharedPreferences =
        context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE)
    private val gson = Gson()

    companion object {
        private const val PREF_NAME = "shawir_iot_session"
        private const val KEY_SERVER_URL = "server_url"
        private const val KEY_AUTH_TOKEN = "auth_token"
        private const val KEY_USER_JSON = "user_json"
        private const val KEY_DARK_MODE = "is_dark_mode"

        // Server terpusat aman langsung ke VPS production
        const val DEFAULT_SERVER_URL = "https://iot.shawir.id/"
    }

    /**
     * Preferensi Mode Gelap (Dark Mode) atau Terang (Light Mode)
     */
    var isDarkMode: Boolean
        get() = prefs.getBoolean(KEY_DARK_MODE, true)
        set(value) = prefs.edit().putBoolean(KEY_DARK_MODE, value).apply()

    /**
     * Server Base URL - Terkunci aman mengarah ke VPS production
     */
    val serverUrl: String
        get() = DEFAULT_SERVER_URL

    /**
     * Auth token returned from backend
     */
    var authToken: String?
        get() = prefs.getString(KEY_AUTH_TOKEN, null)
        set(value) = prefs.edit().putString(KEY_AUTH_TOKEN, value).apply()

    /**
     * Logged-in user data
     */
    var currentUser: User?
        get() {
            val json = prefs.getString(KEY_USER_JSON, null) ?: return null
            return try {
                gson.fromJson(json, User::class.java)
            } catch (e: Exception) {
                null
            }
        }
        set(value) {
            val json = if (value != null) gson.toJson(value) else null
            prefs.edit().putString(KEY_USER_JSON, json).apply()
        }

    val isLoggedIn: Boolean
        get() = !authToken.isNullOrBlank()

    fun saveSession(token: String, user: User) {
        prefs.edit()
            .putString(KEY_AUTH_TOKEN, token)
            .putString(KEY_USER_JSON, gson.toJson(user))
            .apply()
    }

    fun clearSession() {
        prefs.edit()
            .remove(KEY_AUTH_TOKEN)
            .remove(KEY_USER_JSON)
            .apply()
    }
}
