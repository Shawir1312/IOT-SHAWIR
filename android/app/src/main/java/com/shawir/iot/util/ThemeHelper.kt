package com.shawir.iot.util

import android.content.Context
import androidx.appcompat.app.AppCompatDelegate
import com.shawir.iot.data.repository.SessionManager

object ThemeHelper {

    fun initTheme(context: Context) {
        val sessionManager = SessionManager(context)
        applyTheme(sessionManager.isDarkMode)
    }

    fun applyTheme(isDarkMode: Boolean) {
        val mode = if (isDarkMode) {
            AppCompatDelegate.MODE_NIGHT_YES
        } else {
            AppCompatDelegate.MODE_NIGHT_NO
        }
        AppCompatDelegate.setDefaultNightMode(mode)
    }

    fun toggleTheme(context: Context): Boolean {
        val sessionManager = SessionManager(context)
        val newMode = !sessionManager.isDarkMode
        sessionManager.isDarkMode = newMode
        applyTheme(newMode)
        return newMode
    }
}
