package com.shawir.iot.ui.devices

import android.content.Context
import android.view.LayoutInflater
import android.view.ViewGroup
import android.widget.PopupMenu
import androidx.core.content.ContextCompat
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.shawir.iot.R
import com.shawir.iot.data.model.Device
import com.shawir.iot.databinding.ItemDeviceBinding

class DeviceAdapter(
    private val onDeviceClick: (Device) -> Unit,
    private val onCopyToken: (Device) -> Unit,
    private val onDeleteDevice: (Device) -> Unit
) : ListAdapter<Device, DeviceAdapter.DeviceViewHolder>(DiffCallback) {

    inner class DeviceViewHolder(private val binding: ItemDeviceBinding) :
        RecyclerView.ViewHolder(binding.root) {

        fun bind(device: Device) {
            val context = itemView.context

            binding.tvDeviceName.text = device.name
            binding.tvHardware.text = device.hardware ?: "ESP8266"
            binding.tvWidgetCount.text = "${device.widgetCount} Widget"
            binding.tvLastSeen.text = device.lastSeenRelative ?: "-"
            binding.tvToken.text = "Token: ${maskToken(device.token)}"

            if (device.isOnline) {
                binding.viewStatusDot.setBackgroundResource(R.drawable.bg_badge_online)
                binding.tvStatusBadge.setBackgroundResource(R.drawable.bg_badge_online)
                binding.tvStatusBadge.setTextColor(ContextCompat.getColor(context, R.color.success))
                binding.tvStatusBadge.text = context.getString(R.string.device_online)
            } else {
                binding.viewStatusDot.setBackgroundResource(R.drawable.bg_badge_offline)
                binding.tvStatusBadge.setBackgroundResource(R.drawable.bg_badge_offline)
                binding.tvStatusBadge.setTextColor(ContextCompat.getColor(context, R.color.text_muted))
                binding.tvStatusBadge.text = context.getString(R.string.device_offline)
            }

            binding.cardDevice.setOnClickListener {
                onDeviceClick(device)
            }

            binding.btnDeviceOptions.setOnClickListener { view ->
                val popup = PopupMenu(context, view)
                popup.menu.add(0, 1, 0, "Salin Token Device")
                popup.menu.add(0, 2, 1, "Hapus Perangkat")
                popup.setOnMenuItemClickListener { item ->
                    when (item.itemId) {
                        1 -> onCopyToken(device)
                        2 -> onDeleteDevice(device)
                    }
                    true
                }
                popup.show()
            }
        }

        private fun maskToken(token: String): String {
            if (token.length <= 8) return token
            return token.substring(0, 4) + "-****-****-" + token.takeLast(4)
        }
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): DeviceViewHolder {
        val binding = ItemDeviceBinding.inflate(LayoutInflater.from(parent.context), parent, false)
        return DeviceViewHolder(binding)
    }

    override fun onBindViewHolder(holder: DeviceViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    companion object {
        private val DiffCallback = object : DiffUtil.ItemCallback<Device>() {
            override fun areItemsTheSame(oldItem: Device, newItem: Device): Boolean =
                oldItem.id == newItem.id

            override fun areContentsTheSame(oldItem: Device, newItem: Device): Boolean =
                oldItem == newItem
        }
    }
}
