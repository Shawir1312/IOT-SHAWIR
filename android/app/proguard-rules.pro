# Add project specific ProGuard rules here.
-keepattributes Signature
-keepattributes *Annotation*
-dontwarn okhttp3.**
-dontwarn okio.**
-dontwarn javax.annotation.**
-keep class com.shawir.iot.data.model.** { *; }
-keep class com.github.mikephil.charting.** { *; }
