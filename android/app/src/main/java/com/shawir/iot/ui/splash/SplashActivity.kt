package com.shawir.iot.ui.splash

import android.annotation.SuppressLint
import android.content.Intent
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import androidx.appcompat.app.AppCompatActivity
import com.shawir.iot.data.repository.SessionManager
import com.shawir.iot.databinding.ActivitySplashBinding
import com.shawir.iot.ui.auth.LoginActivity
import com.shawir.iot.ui.devices.DeviceListActivity

@SuppressLint("CustomSplashScreen")
class SplashActivity : AppCompatActivity() {

    private lateinit var binding: ActivitySplashBinding
    private lateinit var sessionManager: SessionManager

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivitySplashBinding.inflate(layoutInflater)
        setContentView(binding.root)

        sessionManager = SessionManager(this)

        Handler(Looper.getMainLooper()).postDelayed({
            checkAuthAndNavigate()
        }, 1200)
    }

    private fun checkAuthAndNavigate() {
        if (sessionManager.isLoggedIn) {
            startActivity(Intent(this, DeviceListActivity::class.java))
        } else {
            startActivity(Intent(this, LoginActivity::class.java))
        }
        finish()
    }
}
