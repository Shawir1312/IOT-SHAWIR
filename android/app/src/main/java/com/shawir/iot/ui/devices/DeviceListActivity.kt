package com.shawir.iot.ui.devices

import android.content.Intent
import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import com.shawir.iot.data.api.ApiClient
import com.shawir.iot.data.model.Device
import com.shawir.iot.data.repository.SessionManager
import com.shawir.iot.databinding.ActivityDeviceListBinding
import com.shawir.iot.ui.auth.LoginActivity
import com.shawir.iot.ui.dashboard.DeviceDashboardActivity
import com.shawir.iot.ui.server.ServerConfigDialog
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
        // Refresh silently when returning from dashboard
        loadDevices(silent = true)
    }

    private fun setupUI() {
        val user = sessionManager.currentUser
        binding.tvWelcomeName.text = "Halo, ${user?.name ?: "User"}"
        val plan = user?.planName ?: "Free"
        val credits = user?.credits ?: 0
        binding.tvPlanBadge.text = "Paket: $plan · $credits Kredit"

        binding.swipeRefresh.setOnRefreshListener {
            loadDevices(silent = true)
        }

        binding.btnSettings.setOnClickListener {
            ServerConfigDialog.show(this) {
                loadDevices()
            }
        }

        binding.btnLogout.setOnClickListener {
            showLogoutDialog()
        }
    }

    private fun setupRecyclerView() {
        adapter = DeviceAdapter { device ->
            val intent = Intent(this, DeviceDashboardActivity::class.java).apply {
                putExtra("DEVICE_ID", device.id)
                putExtra("DEVICE_NAME", device.name)
                putExtra("DEVICE_HARDWARE", device.hardware)
                putExtra("DEVICE_TOKEN", device.token)
                putExtra("DEVICE_ONLINE", device.isOnline)
            }
            startActivity(intent)
        }

        binding.rvDevices.layoutManager = LinearLayoutManager(this)
        binding.rvDevices.adapter = adapter
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
