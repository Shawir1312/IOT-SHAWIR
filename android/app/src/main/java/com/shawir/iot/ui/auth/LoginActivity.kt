package com.shawir.iot.ui.auth

import android.content.Intent
import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.shawir.iot.data.api.ApiClient
import com.shawir.iot.data.repository.SessionManager
import com.shawir.iot.databinding.ActivityLoginBinding
import com.shawir.iot.ui.devices.DeviceListActivity
import com.shawir.iot.ui.server.ServerConfigDialog
import kotlinx.coroutines.launch

class LoginActivity : AppCompatActivity() {

    private lateinit var binding: ActivityLoginBinding
    private lateinit var sessionManager: SessionManager

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityLoginBinding.inflate(layoutInflater)
        setContentView(binding.root)

        sessionManager = SessionManager(this)
        updateServerLabel()

        binding.btnServerConfig.setOnClickListener {
            ServerConfigDialog.show(this) {
                updateServerLabel()
            }
        }

        binding.tvToRegister.setOnClickListener {
            startActivity(Intent(this, RegisterActivity::class.java))
        }

        binding.btnLogin.setOnClickListener {
            performLogin()
        }
    }

    private fun updateServerLabel() {
        binding.tvCurrentServer.text = "Server: ${sessionManager.serverUrl}"
    }

    private fun performLogin() {
        val email = binding.etEmail.text.toString().trim()
        val password = binding.etPassword.text.toString().trim()

        if (email.isBlank()) {
            binding.etEmail.error = "Email tidak boleh kosong"
            return
        }
        if (password.isBlank()) {
            binding.etPassword.error = "Kata sandi tidak boleh kosong"
            return
        }

        setLoading(true)

        lifecycleScope.launch {
            try {
                val apiService = ApiClient.getService(this@LoginActivity)
                val response = apiService.login(email, password, "Android Native")

                setLoading(false)

                if (response.success && response.data != null) {
                    val authData = response.data
                    sessionManager.saveSession(authData.token, authData.user)
                    Toast.makeText(this@LoginActivity, "Selamat datang, ${authData.user.name}!", Toast.LENGTH_SHORT).show()

                    val intent = Intent(this@LoginActivity, DeviceListActivity::class.java)
                    intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
                    startActivity(intent)
                    finish()
                } else {
                    Toast.makeText(this@LoginActivity, response.message ?: "Login gagal", Toast.LENGTH_LONG).show()
                }
            } catch (e: Exception) {
                setLoading(false)
                Toast.makeText(this@LoginActivity, "Koneksi error: ${e.localizedMessage ?: "Cek jaringan / URL server"}", Toast.LENGTH_LONG).show()
            }
        }
    }

    private fun setLoading(isLoading: Boolean) {
        binding.btnLogin.isEnabled = !isLoading
        binding.btnLogin.text = if (isLoading) "" else "Masuk"
        binding.loadingBar.visibility = if (isLoading) View.VISIBLE else View.GONE
    }
}
