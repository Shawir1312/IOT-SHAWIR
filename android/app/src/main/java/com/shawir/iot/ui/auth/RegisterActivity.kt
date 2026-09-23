package com.shawir.iot.ui.auth

import android.content.Intent
import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.shawir.iot.R
import com.shawir.iot.data.api.ApiClient
import com.shawir.iot.data.repository.SessionManager
import com.shawir.iot.databinding.ActivityRegisterBinding
import com.shawir.iot.ui.devices.DeviceListActivity
import com.shawir.iot.util.ThemeHelper
import kotlinx.coroutines.launch

class RegisterActivity : AppCompatActivity() {

    private lateinit var binding: ActivityRegisterBinding
    private lateinit var sessionManager: SessionManager

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityRegisterBinding.inflate(layoutInflater)
        setContentView(binding.root)

        sessionManager = SessionManager(this)
        updateThemeIcon()

        binding.btnThemeToggle.setOnClickListener {
            val isDark = ThemeHelper.toggleTheme(this)
            updateThemeIcon(isDark)
        }

        binding.tvToLogin.setOnClickListener {
            finish()
        }

        binding.btnRegister.setOnClickListener {
            performRegister()
        }
    }

    private fun updateThemeIcon(isDark: Boolean = sessionManager.isDarkMode) {
        val iconRes = if (isDark) R.drawable.ic_sun else R.drawable.ic_moon
        binding.btnThemeToggle.setImageResource(iconRes)
    }

    private fun performRegister() {
        val name = binding.etName.text.toString().trim()
        val email = binding.etEmail.text.toString().trim()
        val password = binding.etPassword.text.toString().trim()

        if (name.length < 2) {
            binding.etName.error = "Nama minimal 2 karakter"
            return
        }
        if (email.isBlank() || !android.util.Patterns.EMAIL_ADDRESS.matcher(email).matches()) {
            binding.etEmail.error = "Format email tidak valid"
            return
        }
        if (password.length < 6) {
            binding.etPassword.error = "Kata sandi minimal 6 karakter"
            return
        }

        setLoading(true)

        lifecycleScope.launch {
            try {
                val apiService = ApiClient.getService(this@RegisterActivity)
                val response = apiService.register(name, email, password, "Android Native")

                setLoading(false)

                if (response.success && response.data != null) {
                    val regData = response.data
                    if (regData.requireVerify) {
                        Toast.makeText(this@RegisterActivity, response.message ?: "Kode verifikasi telah dikirim ke email.", Toast.LENGTH_LONG).show()
                        val verifyIntent = Intent(this@RegisterActivity, VerifyOtpActivity::class.java).apply {
                            putExtra(VerifyOtpActivity.EXTRA_EMAIL, email)
                        }
                        startActivity(verifyIntent)
                        finish()
                    } else if (regData.token != null && regData.user != null) {
                        sessionManager.saveSession(regData.token, regData.user)
                        Toast.makeText(this@RegisterActivity, "Pendaftaran berhasil!", Toast.LENGTH_SHORT).show()

                        val intent = Intent(this@RegisterActivity, DeviceListActivity::class.java).apply {
                            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
                        }
                        startActivity(intent)
                        finish()
                    } else {
                        Toast.makeText(this@RegisterActivity, response.message ?: "Pendaftaran berhasil, silakan login.", Toast.LENGTH_LONG).show()
                        finish()
                    }
                } else {
                    Toast.makeText(this@RegisterActivity, response.message ?: "Registrasi gagal", Toast.LENGTH_LONG).show()
                }
            } catch (e: Exception) {
                setLoading(false)
                Toast.makeText(this@RegisterActivity, "Error: ${e.localizedMessage ?: "Cek jaringan"}", Toast.LENGTH_LONG).show()
            }
        }
    }

    private fun setLoading(isLoading: Boolean) {
        binding.btnRegister.isEnabled = !isLoading
        binding.btnRegister.text = if (isLoading) "" else "Daftar Akun Baru"
        binding.loadingBar.visibility = if (isLoading) View.VISIBLE else View.GONE
    }
}
