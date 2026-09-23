package com.shawir.iot.ui.dashboard

import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.GridLayoutManager
import com.shawir.iot.R
import com.shawir.iot.data.api.ApiClient
import com.shawir.iot.data.model.PinHistoryPoint
import com.shawir.iot.databinding.ActivityDeviceDashboardBinding
import kotlinx.coroutines.*
import java.text.SimpleDateFormat
import java.util.*

class DeviceDashboardActivity : AppCompatActivity() {

    private lateinit var binding: ActivityDeviceDashboardBinding
    private lateinit var adapter: WidgetAdapter

    private var deviceId: Int = 0
    private var deviceName: String = "Device"
    private var deviceHardware: String = "ESP8266"
    private var deviceToken: String = ""
    private var isDeviceOnline: Boolean = false

    private var pollingJob: Job? = null
    private val timeFormat = SimpleDateFormat("HH:mm:ss", Locale.getDefault())

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityDeviceDashboardBinding.inflate(layoutInflater)
        setContentView(binding.root)

        deviceId = intent.getIntExtra("DEVICE_ID", 0)
        deviceName = intent.getStringExtra("DEVICE_NAME") ?: "Device"
        deviceHardware = intent.getStringExtra("DEVICE_HARDWARE") ?: "ESP8266"
        deviceToken = intent.getStringExtra("DEVICE_TOKEN") ?: ""
        isDeviceOnline = intent.getBooleanExtra("DEVICE_ONLINE", false)

        setupToolbar()
        setupRecyclerView()
        loadDashboardData()
    }

    override fun onResume() {
        super.onResume()
        startLivePolling()
    }

    override fun onPause() {
        super.onPause()
        stopLivePolling()
    }

    private fun setupToolbar() {
        binding.tvDeviceName.text = deviceName
        binding.tvHardwareInfo.text = "$deviceHardware · ID: #$deviceId"
        updateOnlineBadge(isDeviceOnline)

        binding.btnBack.setOnClickListener {
            finish()
        }

        binding.btnRefresh.setOnClickListener {
            loadDashboardData()
        }

        binding.swipeRefresh.setOnRefreshListener {
            loadDashboardData(silent = true)
        }
    }

    private fun updateOnlineBadge(online: Boolean) {
        isDeviceOnline = online
        if (online) {
            binding.tvDeviceStatus.setBackgroundResource(R.drawable.bg_badge_online)
            binding.tvDeviceStatus.setTextColor(ContextCompat.getColor(this, R.color.success))
            binding.tvDeviceStatus.text = getString(R.string.device_online)
            binding.dotPulse.setBackgroundResource(R.drawable.bg_badge_online)
        } else {
            binding.tvDeviceStatus.setBackgroundResource(R.drawable.bg_badge_offline)
            binding.tvDeviceStatus.setTextColor(ContextCompat.getColor(this, R.color.text_muted))
            binding.tvDeviceStatus.text = getString(R.string.device_offline)
            binding.dotPulse.setBackgroundResource(R.drawable.bg_badge_offline)
        }
    }

    private fun setupRecyclerView() {
        adapter = WidgetAdapter(
            onSendPinValue = { pin, value ->
                sendPinControl(pin, value)
            },
            onLoadChartHistory = { pin, callback ->
                loadChartHistory(pin, callback)
            }
        )

        // 2 columns for tablets or larger screens, 1 column for phones
        val spanCount = if (resources.configuration.screenWidthDp >= 600) 2 else 1
        binding.rvWidgets.layoutManager = GridLayoutManager(this, spanCount)
        binding.rvWidgets.adapter = adapter
    }

    private fun loadDashboardData(silent: Boolean = false) {
        if (!silent) {
            binding.loadingIndicator.visibility = View.VISIBLE
        }

        lifecycleScope.launch {
            try {
                val apiService = ApiClient.getService(this@DeviceDashboardActivity)
                val response = apiService.getDeviceDashboard(deviceId)

                binding.loadingIndicator.visibility = View.GONE
                binding.swipeRefresh.isRefreshing = false

                if (response.success && response.data != null) {
                    val data = response.data
                    updateOnlineBadge(data.device.isOnline)

                    val widgets = data.widgets
                    val pinValues = data.pinValues ?: emptyMap()

                    adapter.submitData(widgets, pinValues)

                    if (widgets.isEmpty()) {
                        binding.emptyWidgetsState.visibility = View.VISIBLE
                        binding.rvWidgets.visibility = View.GONE
                    } else {
                        binding.emptyWidgetsState.visibility = View.GONE
                        binding.rvWidgets.visibility = View.VISIBLE
                    }

                    binding.tvLastSync.text = "Pembaruan: ${timeFormat.format(Date())}"
                } else {
                    Toast.makeText(this@DeviceDashboardActivity, response.message ?: "Gagal memuat dashboard", Toast.LENGTH_SHORT).show()
                }
            } catch (e: Exception) {
                binding.loadingIndicator.visibility = View.GONE
                binding.swipeRefresh.isRefreshing = false
                Toast.makeText(this@DeviceDashboardActivity, "Error: ${e.localizedMessage}", Toast.LENGTH_SHORT).show()
            }
        }
    }

    private fun sendPinControl(pin: String, value: String) {
        lifecycleScope.launch {
            try {
                val apiService = ApiClient.getService(this@DeviceDashboardActivity)
                apiService.controlPin(deviceId, pin, value)
                // Instantly update UI locally
                adapter.updatePinValues(mapOf(pin to value))
            } catch (e: Exception) {
                Toast.makeText(this@DeviceDashboardActivity, "Gagal mengirim ke $pin: ${e.localizedMessage}", Toast.LENGTH_SHORT).show()
            }
        }
    }

    private fun loadChartHistory(pin: String, callback: (List<PinHistoryPoint>) -> Unit) {
        lifecycleScope.launch {
            try {
                val apiService = ApiClient.getService(this@DeviceDashboardActivity)
                val response = apiService.getPinHistory(deviceId, pin, 20)
                if (response.success && response.data != null) {
                    callback(response.data)
                }
            } catch (_: Exception) {}
        }
    }

    private fun startLivePolling() {
        stopLivePolling()
        pollingJob = lifecycleScope.launch {
            while (isActive) {
                delay(2000) // Poll every 2 seconds
                try {
                    val apiService = ApiClient.getService(this@DeviceDashboardActivity)
                    val response = apiService.getPinValues(deviceId)
                    if (response.success && response.data != null) {
                        updateOnlineBadge(response.data.isOnline)
                        response.data.pinValues?.let { pins ->
                            adapter.updatePinValues(pins)
                        }
                        binding.tvLastSync.text = "Pembaruan: ${timeFormat.format(Date())}"
                    }
                } catch (_: Exception) {
                    // Silently ignore temporary network blips during polling
                }
            }
        }
    }

    private fun stopLivePolling() {
        pollingJob?.cancel()
        pollingJob = null
    }
}
