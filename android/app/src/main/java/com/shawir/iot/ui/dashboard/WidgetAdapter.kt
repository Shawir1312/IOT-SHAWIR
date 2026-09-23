package com.shawir.iot.ui.dashboard

import android.annotation.SuppressLint
import android.graphics.Color
import android.view.LayoutInflater
import android.view.MotionEvent
import android.view.View
import android.view.ViewGroup
import android.widget.SeekBar
import androidx.core.content.ContextCompat
import androidx.recyclerview.widget.RecyclerView
import com.github.mikephil.charting.charts.LineChart
import com.github.mikephil.charting.components.XAxis
import com.github.mikephil.charting.data.Entry
import com.github.mikephil.charting.data.LineData
import com.github.mikephil.charting.data.LineDataSet
import com.shawir.iot.R
import com.shawir.iot.data.model.PinHistoryPoint
import com.shawir.iot.data.model.Widget
import com.shawir.iot.databinding.*

class WidgetAdapter(
    private val onSendPinValue: (pin: String, value: String) -> Unit,
    private val onLoadChartHistory: (pin: String, callback: (List<PinHistoryPoint>) -> Unit) -> Unit
) : RecyclerView.Adapter<RecyclerView.ViewHolder>() {

    private val widgets = mutableListOf<Widget>()
    private val pinValues = mutableMapOf<String, String>()

    companion object {
        const val VIEW_TYPE_SWITCH = 1
        const val VIEW_TYPE_BUTTON = 2
        const val VIEW_TYPE_SLIDER = 3
        const val VIEW_TYPE_VALUE = 4
        const val VIEW_TYPE_LED = 5
        const val VIEW_TYPE_GAUGE = 6
        const val VIEW_TYPE_CHART = 7
        const val VIEW_TYPE_OTHER = 8
    }

    @SuppressLint("NotifyDataSetChanged")
    fun submitData(newWidgets: List<Widget>, initialPinValues: Map<String, String>) {
        widgets.clear()
        widgets.addAll(newWidgets)
        pinValues.clear()
        pinValues.putAll(initialPinValues)
        notifyDataSetChanged()
    }

    fun updatePinValues(newValues: Map<String, String>) {
        var changed = false
        for ((pin, value) in newValues) {
            if (pinValues[pin] != value) {
                pinValues[pin] = value
                changed = true
            }
        }
        if (changed) {
            // Update individual bound views without disturbing touch interactions
            notifyItemRangeChanged(0, widgets.size, PAYLOAD_PIN_UPDATE)
        }
    }

    private val PAYLOAD_PIN_UPDATE = "PAYLOAD_PIN_UPDATE"

    override fun getItemCount(): Int = widgets.size

    override fun getItemViewType(position: Int): Int {
        return when (widgets[position].type) {
            "switch" -> VIEW_TYPE_SWITCH
            "button" -> VIEW_TYPE_BUTTON
            "slider" -> VIEW_TYPE_SLIDER
            "value_display" -> VIEW_TYPE_VALUE
            "led" -> VIEW_TYPE_LED
            "gauge", "radial_gauge" -> VIEW_TYPE_GAUGE
            "line_chart", "bar_chart" -> VIEW_TYPE_CHART
            else -> VIEW_TYPE_OTHER
        }
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): RecyclerView.ViewHolder {
        val inflater = LayoutInflater.from(parent.context)
        return when (viewType) {
            VIEW_TYPE_SWITCH -> SwitchViewHolder(ItemWidgetSwitchBinding.inflate(inflater, parent, false))
            VIEW_TYPE_BUTTON -> ButtonViewHolder(ItemWidgetButtonBinding.inflate(inflater, parent, false))
            VIEW_TYPE_SLIDER -> SliderViewHolder(ItemWidgetSliderBinding.inflate(inflater, parent, false))
            VIEW_TYPE_VALUE -> ValueViewHolder(ItemWidgetValueBinding.inflate(inflater, parent, false))
            VIEW_TYPE_LED -> LedViewHolder(ItemWidgetLedBinding.inflate(inflater, parent, false))
            VIEW_TYPE_GAUGE -> GaugeViewHolder(ItemWidgetGaugeBinding.inflate(inflater, parent, false))
            VIEW_TYPE_CHART -> ChartViewHolder(ItemWidgetChartBinding.inflate(inflater, parent, false))
            else -> OtherViewHolder(ItemWidgetUnsupportedBinding.inflate(inflater, parent, false))
        }
    }

    override fun onBindViewHolder(holder: RecyclerView.ViewHolder, position: Int) {
        val widget = widgets[position]
        val currentVal = widget.pin?.let { pinValues[it] } ?: ""

        when (holder) {
            is SwitchViewHolder -> holder.bind(widget, currentVal)
            is ButtonViewHolder -> holder.bind(widget)
            is SliderViewHolder -> holder.bind(widget, currentVal)
            is ValueViewHolder -> holder.bind(widget, currentVal)
            is LedViewHolder -> holder.bind(widget, currentVal)
            is GaugeViewHolder -> holder.bind(widget, currentVal)
            is ChartViewHolder -> holder.bind(widget, currentVal)
            is OtherViewHolder -> holder.bind(widget, currentVal)
        }
    }

    override fun onBindViewHolder(holder: RecyclerView.ViewHolder, position: Int, payloads: List<Any>) {
        if (payloads.contains(PAYLOAD_PIN_UPDATE)) {
            val widget = widgets[position]
            val currentVal = widget.pin?.let { pinValues[it] } ?: ""
            when (holder) {
                is SwitchViewHolder -> holder.updateValue(widget, currentVal)
                is SliderViewHolder -> holder.updateValue(widget, currentVal)
                is ValueViewHolder -> holder.updateValue(widget, currentVal)
                is LedViewHolder -> holder.updateValue(widget, currentVal)
                is GaugeViewHolder -> holder.updateValue(widget, currentVal)
                is ChartViewHolder -> holder.updateValue(widget, currentVal)
                is OtherViewHolder -> holder.updateValue(widget, currentVal)
            }
        } else {
            super.onBindViewHolder(holder, position, payloads)
        }
    }

    // ----------------------------------------------------
    // VIEW HOLDERS
    // ----------------------------------------------------

    inner class SwitchViewHolder(private val binding: ItemWidgetSwitchBinding) :
        RecyclerView.ViewHolder(binding.root) {

        private var isUserInteracting = false

        fun bind(widget: Widget, currentValue: String) {
            binding.tvPinBadge.text = widget.pin ?: "V?"
            binding.tvWidgetTitle.text = widget.label
            updateValue(widget, currentValue)

            binding.switchWidget.setOnCheckedChangeListener { _, isChecked ->
                if (!isUserInteracting) return@setOnCheckedChangeListener
                val sendVal = if (isChecked) widget.onValue ?: "1" else widget.offValue ?: "0"
                widget.pin?.let { onSendPinValue(it, sendVal) }
            }

            binding.switchWidget.setOnTouchListener { _, _ ->
                isUserInteracting = true
                false
            }
        }

        fun updateValue(widget: Widget, currentValue: String) {
            val isOn = currentValue == (widget.onValue ?: "1")
            isUserInteracting = false
            binding.switchWidget.isChecked = isOn
            binding.tvSwitchState.text = if (isOn) "ON" else "OFF"
            binding.tvSwitchState.setTextColor(
                if (isOn) ContextCompat.getColor(itemView.context, R.color.success)
                else ContextCompat.getColor(itemView.context, R.color.text_muted)
            )
        }
    }

    inner class ButtonViewHolder(private val binding: ItemWidgetButtonBinding) :
        RecyclerView.ViewHolder(binding.root) {

        @SuppressLint("ClickableViewAccessibility")
        fun bind(widget: Widget) {
            binding.tvPinBadge.text = widget.pin ?: "V?"
            binding.tvWidgetTitle.text = widget.label
            binding.btnWidgetMomentary.text = widget.label

            val onVal = widget.onValue ?: "1"
            val offVal = widget.offValue ?: "0"

            parseColor(widget.color)?.let {
                binding.btnWidgetMomentary.setBackgroundColor(it)
            }

            binding.btnWidgetMomentary.setOnTouchListener { _, event ->
                when (event.action) {
                    MotionEvent.ACTION_DOWN -> {
                        widget.pin?.let { onSendPinValue(it, onVal) }
                        binding.btnWidgetMomentary.alpha = 0.7f
                        true
                    }
                    MotionEvent.ACTION_UP, MotionEvent.ACTION_CANCEL -> {
                        widget.pin?.let { onSendPinValue(it, offVal) }
                        binding.btnWidgetMomentary.alpha = 1.0f
                        true
                    }
                    else -> false
                }
            }
        }
    }

    inner class SliderViewHolder(private val binding: ItemWidgetSliderBinding) :
        RecyclerView.ViewHolder(binding.root) {

        private var isDragging = false

        fun bind(widget: Widget, currentValue: String) {
            binding.tvPinBadge.text = widget.pin ?: "V?"
            binding.tvWidgetTitle.text = widget.label

            val minVal = widget.minValue.toInt()
            val maxVal = widget.maxValue.toInt().coerceAtLeast(minVal + 1)
            binding.seekBarWidget.max = maxVal - minVal

            updateValue(widget, currentValue)

            binding.seekBarWidget.setOnSeekBarChangeListener(object : SeekBar.OnSeekBarChangeListener {
                override fun onProgressChanged(seekBar: SeekBar?, progress: Int, fromUser: Boolean) {
                    if (fromUser) {
                        val realVal = progress + minVal
                        binding.tvSliderValue.text = "$realVal${widget.unit ?: ""}"
                    }
                }

                override fun onStartTrackingTouch(seekBar: SeekBar?) {
                    isDragging = true
                }

                override fun onStopTrackingTouch(seekBar: SeekBar?) {
                    isDragging = false
                    val progress = seekBar?.progress ?: 0
                    val realVal = progress + minVal
                    widget.pin?.let { onSendPinValue(it, realVal.toString()) }
                }
            })
        }

        fun updateValue(widget: Widget, currentValue: String) {
            if (isDragging) return
            val minVal = widget.minValue.toInt()
            val numVal = currentValue.toDoubleOrNull()?.toInt() ?: minVal
            val progress = (numVal - minVal).coerceIn(0, binding.seekBarWidget.max)
            binding.seekBarWidget.progress = progress
            binding.tvSliderValue.text = "$numVal${widget.unit ?: ""}"
        }
    }

    inner class ValueViewHolder(private val binding: ItemWidgetValueBinding) :
        RecyclerView.ViewHolder(binding.root) {

        fun bind(widget: Widget, currentValue: String) {
            binding.tvPinBadge.text = widget.pin ?: "V?"
            binding.tvWidgetTitle.text = widget.label
            binding.tvValueUnit.text = widget.unit ?: ""

            parseColor(widget.color)?.let {
                binding.tvValueDisplay.setTextColor(it)
            }

            updateValue(widget, currentValue)
        }

        fun updateValue(widget: Widget, currentValue: String) {
            binding.tvValueDisplay.text = if (currentValue.isBlank()) "-" else currentValue
        }
    }

    inner class LedViewHolder(private val binding: ItemWidgetLedBinding) :
        RecyclerView.ViewHolder(binding.root) {

        fun bind(widget: Widget, currentValue: String) {
            binding.tvPinBadge.text = widget.pin ?: "V?"
            binding.tvWidgetTitle.text = widget.label
            updateValue(widget, currentValue)
        }

        fun updateValue(widget: Widget, currentValue: String) {
            val isOn = currentValue == (widget.onValue ?: "1")
            val ledColor = parseColor(widget.color) ?: Color.parseColor("#6366F1")

            if (isOn) {
                binding.viewLedBulb.setBackgroundColor(ledColor)
                binding.tvLedStatusText.text = "HIDUP"
                binding.tvLedStatusText.setTextColor(ledColor)
            } else {
                binding.viewLedBulb.setBackgroundResource(R.drawable.bg_badge_offline)
                binding.tvLedStatusText.text = "MATI"
                binding.tvLedStatusText.setTextColor(ContextCompat.getColor(itemView.context, R.color.text_muted))
            }
        }
    }

    inner class GaugeViewHolder(private val binding: ItemWidgetGaugeBinding) :
        RecyclerView.ViewHolder(binding.root) {

        fun bind(widget: Widget, currentValue: String) {
            binding.tvPinBadge.text = widget.pin ?: "V?"
            binding.tvWidgetTitle.text = widget.label

            binding.gaugeView.setRange(widget.minValue, widget.maxValue)
            parseColor(widget.color)?.let { binding.gaugeView.setColor(it) }

            updateValue(widget, currentValue)
        }

        fun updateValue(widget: Widget, currentValue: String) {
            val num = currentValue.toFloatOrNull() ?: widget.minValue
            binding.gaugeView.setValue(num, widget.unit ?: "")
        }
    }

    inner class ChartViewHolder(private val binding: ItemWidgetChartBinding) :
        RecyclerView.ViewHolder(binding.root) {

        private var hasLoadedInitialHistory = false

        fun bind(widget: Widget, currentValue: String) {
            binding.tvPinBadge.text = widget.pin ?: "V?"
            binding.tvWidgetTitle.text = widget.label

            setupChart(binding.lineChart, widget)
            updateValue(widget, currentValue)

            if (!hasLoadedInitialHistory && widget.pin != null) {
                hasLoadedInitialHistory = true
                onLoadChartHistory(widget.pin) { points ->
                    applyChartHistory(binding.lineChart, widget, points)
                }
            }
        }

        fun updateValue(widget: Widget, currentValue: String) {
            binding.tvChartLatestValue.text = if (currentValue.isBlank()) "-" else "$currentValue ${widget.unit ?: ""}"
        }

        private fun setupChart(chart: LineChart, widget: Widget) {
            chart.description.isEnabled = false
            chart.legend.isEnabled = false
            chart.setTouchEnabled(false)
            chart.isDragEnabled = false
            chart.setScaleEnabled(false)
            chart.setPinchZoom(false)
            chart.setDrawGridBackground(false)

            val color = parseColor(widget.color) ?: Color.parseColor("#6366F1")

            chart.xAxis.apply {
                position = XAxis.XAxisPosition.BOTTOM
                textColor = Color.parseColor("#64748B")
                setDrawGridLines(false)
                setDrawLabels(false)
            }

            chart.axisLeft.apply {
                textColor = Color.parseColor("#94A3B8")
                gridColor = Color.parseColor("#1E293B")
                setDrawZeroLine(false)
            }

            chart.axisRight.isEnabled = false
        }

        private fun applyChartHistory(chart: LineChart, widget: Widget, points: List<PinHistoryPoint>) {
            if (points.isEmpty()) return
            val entries = points.mapIndexed { index, p ->
                Entry(index.toFloat(), p.value)
            }

            val color = parseColor(widget.color) ?: Color.parseColor("#6366F1")
            val dataSet = LineDataSet(entries, widget.pin).apply {
                this.color = color
                this.setCircleColor(color)
                lineWidth = 2.2f
                circleRadius = 3f
                setDrawCircleHole(false)
                setDrawValues(false)
                mode = LineDataSet.Mode.CUBIC_BEZIER
                setDrawFilled(true)
                fillColor = color
                fillAlpha = 40
            }

            chart.data = LineData(dataSet)
            chart.invalidate()
        }
    }

    inner class OtherViewHolder(private val binding: ItemWidgetUnsupportedBinding) :
        RecyclerView.ViewHolder(binding.root) {

        fun bind(widget: Widget, currentValue: String) {
            binding.tvWidgetTitle.text = "${widget.label} (${widget.type})"
            updateValue(widget, currentValue)
        }

        fun updateValue(widget: Widget, currentValue: String) {
            binding.tvWidgetInfo.text = "Pin: ${widget.pin ?: "-"} | Nilai: $currentValue"
        }
    }

    private fun parseColor(hex: String?): Int? {
        if (hex.isNullOrBlank()) return null
        return try {
            Color.parseColor(hex)
        } catch (_: Exception) {
            null
        }
    }
}
