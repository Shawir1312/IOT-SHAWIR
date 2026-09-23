package com.shawir.iot.ui.auth

import android.content.Intent
import android.os.Bundle
import android.os.CountDownTimer
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.shawir.iot.R
import com.shawir.iot.data.api.ApiClient
import com.shawir.iot.data.repository.SessionManager
import com.shawir.iot.databinding.ActivityVerifyOtpBinding
import com.shawir.iot.ui.devices.DeviceListActivity
import com.shawir.iot.util.ThemeHelper
import kotlinx.coroutines.launch

class VerifyOtpActivity : AppCompatActivity() {

    private lateinit var binding: ActivityVerifyOtpBinding
    private lateinit var sessionManager: SessionManager
    private var targetEmail: String = ""
    private var resendTimer: CountDownTimer? = null

    companion object {
        const val EXTRA_EMAIL = "EXTRA_EMAIL"
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityVerifyOtpBinding.inflate(layoutInflater)
        setContentView(binding.root)

        sessionManager = SessionManager(this)
        targetEmail = intent.getStringExtra(EXTRA_EMAIL) ?: ""
        binding.tvEmailTarget.text = targetEmail

        updateThemeIcon()

        binding.btnThemeToggle.setOnClickListener {
            val isDark = ThemeHelper.toggleTheme(this)
            updateThemeIcon(isDark)
        }

        binding.btnVerify.setOnClickListener {
            performVerification()
        }

        binding.tvResendOtp.setOnClickListener {
            performResendOtp()
        }

        binding.tvBackToLogin.setOnClickListener {
            finish()
        }

        startResendCooldown(60)
    }

    override fun onDestroy() {
        super.onDestroy()
        resendTimer?.cancel()
    }

    private fun updateThemeIcon(isDark: Boolean = sessionManager.isDarkMode) {
        val iconRes = if (isDark) R.drawable.ic_sun else R.drawable.ic_moon
        binding.btnThemeToggle.setImageResource(iconRes)
    }

    private fun performVerification() {
        val otpCode = binding.etOtpCode.text.toString().trim()

        if (otpCode.length != 6) {
            binding.etOtpCode.error = "Masukkan 6-digit kode OTP"
            return
        }

        setLoading(true)

        lifecycleScope.launch {
            try {
                val apiService = ApiClient.getService(this@VerifyOtpActivity)
                val response = apiService.verifyEmailOtp(targetEmail, otpCode, "Android Native")

                setLoading(false)

                if (response.success && response.data != null) {
                    val authData = response.data
                    sessionManager.saveSession(authData.token, authData.user)
                    Toast.makeText(this@VerifyOtpActivity, "Email berhasil diverifikasi! Selamat datang.", Toast.LENGTH_SHORT).show()

                    val intent = Intent(this@VerifyOtpActivity, DeviceListActivity::class.java).apply {
                        flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
                    }
                    startActivity(intent)
                    finish()
                } else {
                    Toast.makeText(this@VerifyOtpActivity, response.message ?: "Verifikasi gagal", Toast.LENGTH_LONG).show()
                }
            } catch (e: Exception) {
                setLoading(false)
                Toast.makeText(this@VerifyOtpActivity, "Gagal verifikasi: ${e.localizedMessage ?: "Cek jaringan"}", Toast.LENGTH_LONG).show()
            }
        }
    }

    private fun performResendOtp() {
        binding.tvResendOtp.isEnabled = false

        lifecycleScope.launch {
            try {
                val apiService = ApiClient.getService(this@VerifyOtpActivity)
                val response = apiService.resendEmailOtp(targetEmail)

                if (response.success) {
                    Toast.makeText(this@VerifyOtpActivity, "Kode OTP baru telah dikirim ke email.", Toast.LENGTH_SHORT).show()
                    startResendCooldown(60)
                } else {
                    binding.tvResendOtp.isEnabled = true
                    Toast.makeText(this@VerifyOtpActivity, response.message ?: "Gagal kirim ulang", Toast.LENGTH_LONG).show()
                }
            } catch (e: Exception) {
                binding.tvResendOtp.isEnabled = true
                Toast.makeText(this@VerifyOtpActivity, "Koneksi error: ${e.localizedMessage ?: "Cek jaringan"}", Toast.LENGTH_SHORT).show()
            }
        }
    }

    private fun startResendCooldown(seconds: Int) {
        binding.tvResendOtp.isEnabled = false
        resendTimer?.cancel()
        resendTimer = object : CountDownTimer(seconds * 1000L, 1000L) {
            override fun onTick(millisUntilFinished: Long) {
                val sec = millisUntilFinished / 1000
                binding.tvResendOtp.text = "Kirim ulang kode dalam $sec detik"
            }

            override fun onFinish() {
                binding.tvResendOtp.isEnabled = true
                binding.tvResendOtp.text = "Belum menerima kode? Kirim Ulang"
            }
        }.start()
    }

    private fun setLoading(isLoading: Boolean) {
        binding.btnVerify.isEnabled = !isLoading
        binding.btnVerify.text = if (isLoading) "" else "Verifikasi Sekarang"
        binding.loadingBar.visibility = if (isLoading) View.VISIBLE else View.GONE
    }
}
