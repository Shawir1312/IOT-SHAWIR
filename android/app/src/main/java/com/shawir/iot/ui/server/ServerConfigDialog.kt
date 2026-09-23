package com.shawir.iot.ui.server

import android.content.Context
import android.graphics.Color
import android.graphics.drawable.ColorDrawable
import android.view.LayoutInflater
import android.view.View
import androidx.appcompat.app.AlertDialog
import com.shawir.iot.R
import com.shawir.iot.data.api.ApiClient
import com.shawir.iot.data.repository.SessionManager
import com.shawir.iot.databinding.DialogServerConfigBinding
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

object ServerConfigDialog {

    fun show(context: Context, onServerChanged: (() -> Unit)? = null) {
        val sessionManager = SessionManager(context)
        val binding = DialogServerConfigBinding.inflate(LayoutInflater.from(context))

        binding.etServerUrl.setText(sessionManager.serverUrl)

        val dialog = AlertDialog.Builder(context)
            .setView(binding.root)
            .create()

        dialog.window?.setBackgroundDrawable(ColorDrawable(Color.TRANSPARENT))

        binding.btnCancel.setOnClickListener {
            dialog.dismiss()
        }

        binding.btnTestConnection.setOnClickListener {
            val inputUrl = binding.etServerUrl.text.toString().trim()
            if (inputUrl.isBlank()) {
                binding.tvTestStatus.visibility = View.VISIBLE
                binding.tvTestStatus.setTextColor(Color.parseColor("#EF4444"))
                binding.tvTestStatus.text = "Masukkan URL server terlebih dahulu."
                return@setOnClickListener
            }

            binding.tvTestStatus.visibility = View.VISIBLE
            binding.tvTestStatus.setTextColor(Color.parseColor("#94A3B8"))
            binding.tvTestStatus.text = "Menghubungkan ke server..."
            binding.btnTestConnection.isEnabled = false

            // Temporarily test with the new URL
            val oldUrl = sessionManager.serverUrl
            sessionManager.serverUrl = inputUrl
            ApiClient.resetClient()

            CoroutineScope(Dispatchers.IO).launch {
                try {
                    val response = ApiClient.getService(context).getServerInfo()
                    withContext(Dispatchers.Main) {
                        binding.btnTestConnection.isEnabled = true
                        if (response.success) {
                            binding.tvTestStatus.setTextColor(Color.parseColor("#10B981"))
                            binding.tvTestStatus.text = "Berhasil terhubung ke ${response.data?.platform ?: "ShawirIOT"}!"
                        } else {
                            binding.tvTestStatus.setTextColor(Color.parseColor("#EF4444"))
                            binding.tvTestStatus.text = response.message ?: "Gagal terhubung."
                        }
                    }
                } catch (e: Exception) {
                    withContext(Dispatchers.Main) {
                        binding.btnTestConnection.isEnabled = true
                        binding.tvTestStatus.setTextColor(Color.parseColor("#EF4444"))
                        binding.tvTestStatus.text = "Koneksi gagal: ${e.localizedMessage ?: "Cek URL / Jaringan WiFi"}"
                    }
                } finally {
                    // Restore until user clicks Save
                    sessionManager.serverUrl = oldUrl
                    ApiClient.resetClient()
                }
            }
        }

        binding.btnSave.setOnClickListener {
            val newUrl = binding.etServerUrl.text.toString().trim()
            if (newUrl.isNotBlank()) {
                sessionManager.serverUrl = newUrl
                ApiClient.resetClient()
                onServerChanged?.invoke()
            }
            dialog.dismiss()
        }

        dialog.show()
    }
}
