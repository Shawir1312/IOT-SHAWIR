package com.shawir.iot.ui.devices

import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.content.Intent
import android.graphics.Color
import android.graphics.drawable.ColorDrawable
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.widget.ArrayAdapter
import android.widget.Toast
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import com.shawir.iot.R
import com.shawir.iot.data.api.ApiClient
import com.shawir.iot.data.model.Device
import com.shawir.iot.data.repository.SessionManager
import com.shawir.iot.databinding.ActivityDeviceListBinding
import com.shawir.iot.databinding.DialogAddDeviceBinding
import com.shawir.iot.ui.auth.LoginActivity
import com.shawir.iot.ui.dashboard.DeviceDashboardActivity
import com.shawir.iot.util.ThemeHelper
import kotlinx.coroutines.launch

class DeviceListActivity : AppCompatActivity() {

    private lateinit var binding: ActivityDeviceListBinding
    private lateinit var sessionManager: SessionManager
    private lateinit var adapter: DeviceAdapter

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityDeviceListBinding.inflate(layoutInflater)
        setContentView(binding.root)

        sessionManager = SessionManager(this)

        setupUI()
        setupRecyclerView()
        loadDevices()
    }

    override fun onResume() {
        super.onResume()
        loadDevices(silent = true)
    }

    private fun setupUI() {
        val user = sessionManager.currentUser
        binding.tvWelcomeName.text = "Halo, ${user?.name ?: "User"}"
        val plan = user?.planName ?: "Free"
        val credits = user?.credits ?: 0
        binding.tvPlanBadge.text = "Paket: $plan · $credits Kredit"

        updateThemeIcon()

        binding.btnThemeToggle.setOnClickListener {
            val isDark = ThemeHelper.toggleTheme(this)
            updateThemeIcon(isDark)
        }

        binding.swipeRefresh.setOnRefreshListener {
            loadDevices(silent = true)
        }

        binding.btnLogout.setOnClickListener {
            showLogoutDialog()
        }

        binding.fabAddDevice.setOnClickListener {
            showAddDeviceDialog()
        }
    }

    private fun updateThemeIcon(isDark: Boolean = sessionManager.isDarkMode) {
        val iconRes = if (isDark) R.drawable.ic_sun else R.drawable.ic_moon
        binding.btnThemeToggle.setImageResource(iconRes)
    }

    private fun setupRecyclerView() {
        adapter = DeviceAdapter(
            onDeviceClick = { device ->
                val intent = Intent(this, DeviceDashboardActivity::class.java).apply {
                    putExtra("DEVICE_ID", device.id)
                    putExtra("DEVICE_NAME", device.name)
                    putExtra("DEVICE_HARDWARE", device.hardware)
                    putExtra("DEVICE_TOKEN", device.token)
                    putExtra("DEVICE_ONLINE", device.isOnline)
                }
                startActivity(intent)
            },
            onCopyToken = { device ->
                copyTokenToClipboard(device.token)
            },
            onDeleteDevice = { device ->
                showDeleteDeviceDialog(device)
            }
        )

        binding.rvDevices.layoutManager = LinearLayoutManager(this)
        binding.rvDevices.adapter = adapter
    }

    private fun copyTokenToClipboard(token: String) {
        val clipboard = getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
        val clip = ClipData.newPlainText("Device Token", token)
        clipboard.setPrimaryClip(clip)
        Toast.makeText(this, "Token perangkat berhasil disalin ke clipboard!", Toast.LENGTH_SHORT).show()
    }

    private fun showDeleteDeviceDialog(device: Device) {
        AlertDialog.Builder(this)
            .setTitle("Hapus Perangkat")
            .setMessage("Apakah Anda yakin ingin menghapus perangkat \"${device.name}\"?")
            .setPositiveButton("Hapus") { _, _ ->
                deleteDevice(device.id)
            }
            .setNegativeButton("Batal", null)
            .show()
    }

    private fun deleteDevice(deviceId: Int) {
        lifecycleScope.launch {
            try {
                val apiService = ApiClient.getService(this@DeviceListActivity)
                val response = apiService.deleteDevice(deviceId)
                if (response.success) {
                    Toast.makeText(this@DeviceListActivity, "Perangkat berhasil dihapus.", Toast.LENGTH_SHORT).show()
                    loadDevices(silent = true)
                } else {
                    Toast.makeText(this@DeviceListActivity, response.message ?: "Gagal menghapus", Toast.LENGTH_SHORT).show()
                }
            } catch (e: Exception) {
                Toast.makeText(this@DeviceListActivity, "Error: ${e.localizedMessage}", Toast.LENGTH_SHORT).show()
            }
        }
    }

    private fun showAddDeviceDialog() {
        val dialogBinding = DialogAddDeviceBinding.inflate(LayoutInflater.from(this))

        val hardwareList = listOf("ESP32", "ESP8266", "Arduino", "Raspberry Pi", "STM32", "Custom")
        val connList = listOf("WiFi", "Ethernet", "GSM / Cellular", "Bluetooth")

        val hwAdapter = ArrayAdapter(this, android.R.layout.simple_spinner_dropdown_item, hardwareList)
        val connAdapter = ArrayAdapter(this, android.R.layout.simple_spinner_dropdown_item, connList)

        dialogBinding.spHardware.adapter = hwAdapter
        dialogBinding.spConnection.adapter = connAdapter

        val dialog = AlertDialog.Builder(this)
            .setView(dialogBinding.root)
            .create()

        dialog.window?.setBackgroundDrawable(ColorDrawable(Color.TRANSPARENT))

        dialogBinding.btnCancel.setOnClickListener {
            dialog.dismiss()
        }

        dialogBinding.btnSave.setOnClickListener {
            val name = dialogBinding.etDeviceName.text.toString().trim()
            if (name.isBlank()) {
                dialogBinding.etDeviceName.error = "Nama perangkat harus diisi"
                return@setOnClickListener
            }

            val hardware = dialogBinding.spHardware.selectedItem.toString()
            val conn = dialogBinding.spConnection.selectedItem.toString()
            val desc = dialogBinding.etDescription.text.toString().trim()

            dialogBinding.btnSave.isEnabled = false
            dialogBinding.btnSave.text = "Menyimpan..."

            lifecycleScope.launch {
                try {
                    val apiService = ApiClient.getService(this@DeviceListActivity)
                    val response = apiService.addDevice(name, hardware, conn, desc)
                    dialog.dismiss()

                    if (response.success) {
                        Toast.makeText(this@DeviceListActivity, "Perangkat \"$name\" berhasil ditambahkan!", Toast.LENGTH_SHORT).show()
                        loadDevices(silent = true)
                    } else {
                        Toast.makeText(this@DeviceListActivity, response.message ?: "Gagal menambahkan perangkat", Toast.LENGTH_LONG).show()
                    }
                } catch (e: Exception) {
                    dialogBinding.btnSave.isEnabled = true
                    dialogBinding.btnSave.text = "Simpan Perangkat"
                    Toast.makeText(this@DeviceListActivity, "Error: ${e.localizedMessage}", Toast.LENGTH_LONG).show()
                }
            }
        }

        dialog.show()
    }

    private fun loadDevices(silent: Boolean = false) {
        if (!silent) {
            binding.loadingIndicator.visibility = View.VISIBLE
        }

        lifecycleScope.launch {
            try {
                val apiService = ApiClient.getService(this@DeviceListActivity)
                val response = apiService.getDevices()

                binding.loadingIndicator.visibility = View.GONE
                binding.swipeRefresh.isRefreshing = false

                if (response.success && response.data != null) {
                    val list = response.data
                    adapter.submitList(list)

                    binding.tvDeviceCount.text = "${list.size} Device"
                    if (list.isEmpty()) {
                        binding.emptyState.visibility = View.VISIBLE
                        binding.rvDevices.visibility = View.GONE
                    } else {
                        binding.emptyState.visibility = View.GONE
                        binding.rvDevices.visibility = View.VISIBLE
                    }
                } else {
                    Toast.makeText(this@DeviceListActivity, response.message ?: "Gagal memuat perangkat", Toast.LENGTH_SHORT).show()
                }
            } catch (e: Exception) {
                binding.loadingIndicator.visibility = View.GONE
                binding.swipeRefresh.isRefreshing = false
                Toast.makeText(this@DeviceListActivity, "Error: ${e.localizedMessage}", Toast.LENGTH_SHORT).show()
            }
        }
    }

    private fun showLogoutDialog() {
        AlertDialog.Builder(this)
            .setTitle("Konfirmasi Keluar")
            .setMessage("Apakah Anda yakin ingin keluar dari akun ShawirIOT?")
            .setPositiveButton("Ya, Keluar") { _, _ ->
                performLogout()
            }
            .setNegativeButton("Batal", null)
            .show()
    }

    private fun performLogout() {
        lifecycleScope.launch {
            try {
                ApiClient.getService(this@DeviceListActivity).logout()
            } catch (_: Exception) {}
        }
        sessionManager.clearSession()
        val intent = Intent(this, LoginActivity::class.java)
        intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
        startActivity(intent)
        finish()
    }
}
