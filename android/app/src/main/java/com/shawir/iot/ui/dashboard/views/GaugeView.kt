package com.shawir.iot.ui.dashboard.views

import android.content.Context
import android.graphics.*
import android.util.AttributeSet
import android.view.View
import kotlin.math.min

/**
 * Custom Hardware-accelerated Semicircular Gauge View for Android.
 * Renders smooth arcs and live value readouts natively.
 */
class GaugeView @JvmOverloads constructor(
    context: Context,
    attrs: AttributeSet? = null,
    defStyleAttr: Int = 0
) : View(context, attrs, defStyleAttr) {

    private var minValue: Float = 0f
    private var maxValue: Float = 100f
    private var currentValue: Float = 0f
    private var unit: String = ""
    private var arcColor: Int = Color.parseColor("#6366F1")

    private val trackPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        style = Paint.Style.STROKE
        strokeCap = Paint.Cap.ROUND
        color = Color.parseColor("#334155")
    }

    private val progressPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        style = Paint.Style.STROKE
        strokeCap = Paint.Cap.ROUND
    }

    private val valuePaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        color = Color.parseColor("#F8FAFC")
        textAlign = Paint.Align.CENTER
        typeface = Typeface.create(Typeface.DEFAULT, Typeface.BOLD)
    }

    private val unitPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        color = Color.parseColor("#94A3B8")
        textAlign = Paint.Align.CENTER
    }

    private val arcBounds = RectF()

    fun setRange(min: Float, max: Float) {
        this.minValue = min
        this.maxValue = if (max > min) max else min + 1f
        invalidate()
    }

    fun setValue(value: Float, unitText: String = unit) {
        this.currentValue = value.coerceIn(minValue, maxValue)
        this.unit = unitText
        invalidate()
    }

    fun setColor(colorInt: Int) {
        this.arcColor = colorInt
        invalidate()
    }

    override fun onMeasure(widthMeasureSpec: Int, heightMeasureSpec: Int) {
        val w = MeasureSpec.getSize(widthMeasureSpec)
        val h = (w * 0.65f).toInt().coerceAtLeast(150)
        setMeasuredDimension(w, resolveSize(h, heightMeasureSpec))
    }

    override fun onDraw(canvas: Canvas) {
        super.onDraw(canvas)

        val strokeWidth = width * 0.08f
        trackPaint.strokeWidth = strokeWidth
        progressPaint.strokeWidth = strokeWidth
        progressPaint.color = arcColor

        val padding = strokeWidth / 2f + 16f
        arcBounds.set(padding, padding, width - padding, (height * 1.6f) - padding)

        val startAngle = 180f
        val sweepAngle = 180f

        // Draw background track
        canvas.drawArc(arcBounds, startAngle, sweepAngle, false, trackPaint)

        // Draw progress arc
        val fraction = ((currentValue - minValue) / (maxValue - minValue)).coerceIn(0f, 1f)
        val currentSweep = sweepAngle * fraction
        if (currentSweep > 0f) {
            canvas.drawArc(arcBounds, startAngle, currentSweep, false, progressPaint)
        }

        // Draw center text
        val centerX = width / 2f
        val centerY = height * 0.72f

        valuePaint.textSize = width * 0.14f
        val formattedVal = if (currentValue % 1f == 0f) {
            currentValue.toInt().toString()
        } else {
            String.format("%.1f", currentValue)
        }
        canvas.drawText(formattedVal, centerX, centerY, valuePaint)

        if (unit.isNotBlank()) {
            unitPaint.textSize = width * 0.065f
            canvas.drawText(unit, centerX, centerY + (width * 0.08f), unitPaint)
        }
    }
}
