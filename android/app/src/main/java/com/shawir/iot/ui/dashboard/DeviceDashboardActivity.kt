package com.shawir.iot.ui.dashboard

import android.graphics.Color
import android.graphics.drawable.ColorDrawable
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.widget.ArrayAdapter
import android.widget.Toast
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.GridLayoutManager
import com.shawir.iot.R
import com.shawir.iot.data.api.ApiClient
import com.shawir.iot.data.model.PinHistoryPoint
import com.shawir.iot.data.model.Widget
import com.shawir.iot.data.repository.SessionManager
import com.shawir.iot.databinding.ActivityDeviceDashboardBinding
import com.shawir.iot.databinding.DialogAddWidgetBinding
import com.shawir.iot.util.ThemeHelper
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

class DeviceDashboardActivity : AppCompatActivity() {

    private lateinit var binding: ActivityDeviceDashboardBinding
    private lateinit var adapter: WidgetAdapter
    private lateinit var sessionManager: SessionManager

    private var deviceId: Int = 0
    private var deviceName: String = ""
    private var deviceHardware: String = ""
    private var deviceToken: String = ""
    private var isDeviceOnline: Boolean = false

    private var livePollingJob: Job? = null
    private val timeFormat = SimpleDateFormat("HH:mm:ss", Locale.getDefault())

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityDeviceDashboardBinding.inflate(layoutInflater)
        setContentView(binding.root)

        sessionManager = SessionManager(this)

        deviceId = intent.getIntExtra("DEVICE_ID", 0)
        deviceName = intent.getStringExtra("DEVICE_NAME") ?: "Device"
        deviceHardware = intent.getStringExtra("DEVICE_HARDWARE") ?: "ESP32"
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
        updateThemeIcon()

        binding.btnBack.setOnClickListener {
            finish()
        }

        binding.btnThemeToggle.setOnClickListener {
            val isDark = ThemeHelper.toggleTheme(this)
            updateThemeIcon(isDark)
        }

        binding.btnRefresh.setOnClickListener {
            loadDashboardData()
        }

        binding.swipeRefresh.setOnRefreshListener {
            loadDashboardData(silent = true)
        }

        binding.fabAddWidget.setOnClickListener {
            showAddWidgetDialog()
        }
    }

    private fun updateThemeIcon(isDark: Boolean = sessionManager.isDarkMode) {
        val iconRes = if (isDark) R.drawable.ic_sun else R.drawable.ic_moon
        binding.btnThemeToggle.setImageResource(iconRes)
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
            },
            onEditWidget = { widget ->
                showEditWidgetDialog(widget)
            },
            onDeleteWidget = { widget ->
                showDeleteWidgetDialog(widget)
            }
        )

        // Responsif 2 kolom: Chart & Gauge mengambil 2 kolom (lebar penuh), Switch/Button/Slider/Value/LED 1 kolom (side by side)
        val gridLayoutManager = GridLayoutManager(this, 2)
        gridLayoutManager.spanSizeLookup = object : GridLayoutManager.SpanSizeLookup() {
            override fun getSpanSize(position: Int): Int {
                return when (adapter.getItemViewType(position)) {
                    WidgetAdapter.VIEW_TYPE_CHART, WidgetAdapter.VIEW_TYPE_GAUGE -> 2
                    else -> 1
                }
            }
        }

        binding.rvWidgets.layoutManager = gridLayoutManager
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
                    Toast.makeText(this@DeviceDashboardActivity, response.message ?: "Gagal memuat widget", Toast.LENGTH_SHORT).show()
                }
            } catch (e: Exception) {
                binding.loadingIndicator.visibility = View.GONE
                binding.swipeRefresh.isRefreshing = false
                Toast.makeText(this@DeviceDashboardActivity, "Error: ${e.localizedMessage}", Toast.LENGTH_SHORT).show()
            }
        }
    }

    private fun startLivePolling() {
        livePollingJob?.cancel()
        livePollingJob = lifecycleScope.launch {
            while (isActive) {
                delay(2000)
                try {
                    val apiService = ApiClient.getService(this@DeviceDashboardActivity)
                    val response = apiService.getPinValues(deviceId)
                    if (response.success && response.data != null) {
                        val data = response.data
                        updateOnlineBadge(data.isOnline)
                        data.pinValues?.let { adapter.updatePinValues(it) }
                        binding.tvLastSync.text = "Pembaruan: ${timeFormat.format(Date())}"
                    }
                } catch (_: Exception) {}
            }
        }
    }

    private fun stopLivePolling() {
        livePollingJob?.cancel()
        livePollingJob = null
    }

    private fun sendPinControl(pin: String, value: String) {
        lifecycleScope.launch {
            try {
                val apiService = ApiClient.getService(this@DeviceDashboardActivity)
                apiService.controlPin(deviceId, pin, value)
            } catch (e: Exception) {
                Toast.makeText(this@DeviceDashboardActivity, "Gagal mengirim ke $pin: ${e.localizedMessage}", Toast.LENGTH_SHORT).show()
            }
        }
    }

    private fun loadChartHistory(pin: String, callback: (List<PinHistoryPoint>) -> Unit) {
        lifecycleScope.launch {
            try {
                val apiService = ApiClient.getService(this@DeviceDashboardActivity)
                val response = apiService.getPinHistory(deviceId, pin, 30)
                if (response.success && response.data != null) {
                    callback(response.data)
                }
            } catch (_: Exception) {}
        }
    }

    // ----------------------------------------------------
    // WIDGET CRUD DIALOGS
    // ----------------------------------------------------

    private fun showAddWidgetDialog() {
        val dialogBinding = DialogAddWidgetBinding.inflate(LayoutInflater.from(this))

        val typeLabels = listOf(
            "Saklar (Switch ON/OFF)",
            "Tombol (Push Button)",
            "Slider (Pengatur PWM)",
            "Nilai Sensor (Value Display)",
            "Lampu Indikator (LED)",
            "Spidometer (Gauge)",
            "Grafik Riwayat (Line Chart)"
        )
        val typeValues = listOf(
            "switch",
            "button",
            "slider",
            "value_display",
            "led",
            "gauge",
            "line_chart"
        )

        val pinList = (0..31).map { "V$it" } + (0..16).map { "D$it" }
        val colorLabels = listOf("Indigo", "Cyan", "Hijau Emerald", "Oranye Amber", "Merah Rose", "Ungu")
        val colorValues = listOf("#6366F1", "#06B6D4", "#10B981", "#F59E0B", "#EF4444", "#8B5CF6")

        dialogBinding.spWidgetType.adapter = ArrayAdapter(this, android.R.layout.simple_spinner_dropdown_item, typeLabels)
        dialogBinding.spPin.adapter = ArrayAdapter(this, android.R.layout.simple_spinner_dropdown_item, pinList)
        dialogBinding.spColor.adapter = ArrayAdapter(this, android.R.layout.simple_spinner_dropdown_item, colorLabels)

        val dialog = AlertDialog.Builder(this)
            .setView(dialogBinding.root)
            .create()

        dialog.window?.setBackgroundDrawable(ColorDrawable(Color.TRANSPARENT))

        dialogBinding.btnCancel.setOnClickListener {
            dialog.dismiss()
        }

        dialogBinding.btnSave.setOnClickListener {
            val label = dialogBinding.etWidgetLabel.text.toString().trim()
            if (label.isBlank()) {
                dialogBinding.etWidgetLabel.error = "Label widget harus diisi"
                return@setOnClickListener
            }

            val typeIndex = dialogBinding.spWidgetType.selectedItemPosition
            val type = typeValues.getOrElse(typeIndex) { "switch" }
            val pin = dialogBinding.spPin.selectedItem.toString()
            val minVal = dialogBinding.etMinValue.text.toString().toFloatOrNull() ?: 0f
            val maxVal = dialogBinding.etMaxValue.text.toString().toFloatOrNull() ?: 100f
            val unit = dialogBinding.etUnit.text.toString().trim()
            val colorIndex = dialogBinding.spColor.selectedItemPosition
            val color = colorValues.getOrElse(colorIndex) { "#6366F1" }

            dialogBinding.btnSave.isEnabled = false
            dialogBinding.btnSave.text = "Menyimpan..."

            lifecycleScope.launch {
                try {
                    val apiService = ApiClient.getService(this@DeviceDashboardActivity)
                    val response = apiService.addWidget(
                        deviceId = deviceId,
                        type = type,
                        label = label,
                        pin = pin,
                        color = color,
                        minValue = minVal,
                        maxValue = maxVal,
                        unit = unit
                    )
                    dialog.dismiss()

                    if (response.success) {
                        Toast.makeText(this@DeviceDashboardActivity, "Widget \"$label\" berhasil ditambahkan!", Toast.LENGTH_SHORT).show()
                        loadDashboardData(silent = true)
                    } else {
                        Toast.makeText(this@DeviceDashboardActivity, response.message ?: "Gagal menambah widget", Toast.LENGTH_LONG).show()
                    }
                } catch (e: Exception) {
                    dialogBinding.btnSave.isEnabled = true
                    dialogBinding.btnSave.text = "Simpan Widget"
                    Toast.makeText(this@DeviceDashboardActivity, "Error: ${e.localizedMessage}", Toast.LENGTH_LONG).show()
                }
            }
        }

        dialog.show()
    }

    private fun showEditWidgetDialog(widget: Widget) {
        val dialogBinding = DialogAddWidgetBinding.inflate(LayoutInflater.from(this))
        dialogBinding.tvDialogTitle.text = "Edit Widget: ${widget.label}"

        val pinList = (0..31).map { "V$it" } + (0..16).map { "D$it" }
        val colorLabels = listOf("Indigo", "Cyan", "Hijau Emerald", "Oranye Amber", "Merah Rose", "Ungu")
        val colorValues = listOf("#6366F1", "#06B6D4", "#10B981", "#F59E0B", "#EF4444", "#8B5CF6")

        dialogBinding.spWidgetType.visibility = View.GONE
        dialogBinding.etWidgetLabel.setText(widget.label)

        val pinAdapter = ArrayAdapter(this, android.R.layout.simple_spinner_dropdown_item, pinList)
        dialogBinding.spPin.adapter = pinAdapter
        val pinPos = pinList.indexOf(widget.pin)
        if (pinPos >= 0) dialogBinding.spPin.setSelection(pinPos)

        dialogBinding.etMinValue.setText(widget.minValue.toString())
        dialogBinding.etMaxValue.setText(widget.maxValue.toString())
        dialogBinding.etUnit.setText(widget.unit ?: "")

        val colorAdapter = ArrayAdapter(this, android.R.layout.simple_spinner_dropdown_item, colorLabels)
        dialogBinding.spColor.adapter = colorAdapter
        val colorPos = colorValues.indexOfFirst { it.equals(widget.color, ignoreCase = true) }
        if (colorPos >= 0) dialogBinding.spColor.setSelection(colorPos)

        val dialog = AlertDialog.Builder(this)
            .setView(dialogBinding.root)
            .create()

        dialog.window?.setBackgroundDrawable(ColorDrawable(Color.TRANSPARENT))

        dialogBinding.btnCancel.setOnClickListener {
            dialog.dismiss()
        }

        dialogBinding.btnSave.setOnClickListener {
            val label = dialogBinding.etWidgetLabel.text.toString().trim()
            if (label.isBlank()) {
                dialogBinding.etWidgetLabel.error = "Label widget harus diisi"
                return@setOnClickListener
            }

            val pin = dialogBinding.spPin.selectedItem.toString()
            val minVal = dialogBinding.etMinValue.text.toString().toFloatOrNull() ?: widget.minValue
            val maxVal = dialogBinding.etMaxValue.text.toString().toFloatOrNull() ?: widget.maxValue
            val unit = dialogBinding.etUnit.text.toString().trim()
            val colorIndex = dialogBinding.spColor.selectedItemPosition
            val color = colorValues.getOrElse(colorIndex) { widget.color ?: "#6366F1" }

            dialogBinding.btnSave.isEnabled = false
            dialogBinding.btnSave.text = "Memperbarui..."

            lifecycleScope.launch {
                try {
                    val apiService = ApiClient.getService(this@DeviceDashboardActivity)
                    val response = apiService.updateWidget(
                        widgetId = widget.id,
                        label = label,
                        pin = pin,
                        color = color,
                        minValue = minVal,
                        maxValue = maxVal,
                        unit = unit
                    )
                    dialog.dismiss()

                    if (response.success) {
                        Toast.makeText(this@DeviceDashboardActivity, "Widget berhasil diperbarui!", Toast.LENGTH_SHORT).show()
                        loadDashboardData(silent = true)
                    } else {
                        Toast.makeText(this@DeviceDashboardActivity, response.message ?: "Gagal memperbarui widget", Toast.LENGTH_LONG).show()
                    }
                } catch (e: Exception) {
                    dialogBinding.btnSave.isEnabled = true
                    dialogBinding.btnSave.text = "Simpan Widget"
                    Toast.makeText(this@DeviceDashboardActivity, "Error: ${e.localizedMessage}", Toast.LENGTH_LONG).show()
                }
            }
        }

        dialog.show()
    }

    private fun showDeleteWidgetDialog(widget: Widget) {
        AlertDialog.Builder(this)
            .setTitle("Hapus Widget")
            .setMessage("Apakah Anda yakin ingin menghapus widget \"${widget.label}\"?")
            .setPositiveButton("Hapus") { _, _ ->
                lifecycleScope.launch {
                    try {
                        val apiService = ApiClient.getService(this@DeviceDashboardActivity)
                        val response = apiService.deleteWidget(widget.id)
                        if (response.success) {
                            Toast.makeText(this@DeviceDashboardActivity, "Widget dihapus.", Toast.LENGTH_SHORT).show()
                            loadDashboardData(silent = true)
                        } else {
                            Toast.makeText(this@DeviceDashboardActivity, response.message ?: "Gagal menghapus", Toast.LENGTH_SHORT).show()
                        }
                    } catch (e: Exception) {
                        Toast.makeText(this@DeviceDashboardActivity, "Error: ${e.localizedMessage}", Toast.LENGTH_SHORT).show()
                    }
                }
            }
            .setNegativeButton("Batal", null)
            .show()
    }
}
