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

        // Default: Mengarah langsung ke VPS production
        const val DEFAULT_SERVER_URL = "https://iot.shawir.id"
    }

    /**
     * Server Base URL (e.g. "http://192.168.1.15/IOT-SHAWIR" or "https://iot.shawir.com")
     */
    var serverUrl: String
        get() {
            var url = prefs.getString(KEY_SERVER_URL, DEFAULT_SERVER_URL) ?: DEFAULT_SERVER_URL
            if (!url.endsWith("/")) {
                url += "/"
            }
            return url
        }
        set(value) {
            var formatted = value.trim()
            if (!formatted.startsWith("http://") && !formatted.startsWith("https://")) {
                formatted = "http://$formatted"
            }
            if (!formatted.endsWith("/")) {
                formatted += "/"
            }
            prefs.edit().putString(KEY_SERVER_URL, formatted).apply()
        }

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
