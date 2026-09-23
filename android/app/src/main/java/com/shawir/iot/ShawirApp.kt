package com.shawir.iot

import android.app.Application
import com.shawir.iot.util.ThemeHelper

/**
 * Application class for ShawirIOT Android
 */
class ShawirApp : Application() {
    override fun onCreate() {
        super.onCreate()
        ThemeHelper.initTheme(this)
    }
}
