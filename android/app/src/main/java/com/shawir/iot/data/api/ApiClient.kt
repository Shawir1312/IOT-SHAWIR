package com.shawir.iot.data.api

import android.content.Context
import com.shawir.iot.data.repository.SessionManager
import okhttp3.Interceptor
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

/**
 * Singleton Retrofit ApiClient with dynamic Server URL and Bearer Token Interceptor.
 */
object ApiClient {

    private var currentBaseUrl: String? = null
    private var cachedService: ShawirApiService? = null

    fun getService(context: Context): ShawirApiService {
        val sessionManager = SessionManager(context)
        val baseUrl = sessionManager.serverUrl

        if (cachedService != null && currentBaseUrl == baseUrl) {
            return cachedService!!
        }

        currentBaseUrl = baseUrl

        val authInterceptor = Interceptor { chain ->
            val originalRequest = chain.request()
            val requestBuilder = originalRequest.newBuilder()
                .header("Accept", "application/json")

            val token = sessionManager.authToken
            if (!token.isNullOrBlank()) {
                requestBuilder.header("Authorization", "Bearer $token")
            }

            chain.proceed(requestBuilder.build())
        }

        val loggingInterceptor = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BODY
        }

        val okHttpClient = OkHttpClient.Builder()
            .addInterceptor(authInterceptor)
            .addInterceptor(loggingInterceptor)
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(15, TimeUnit.SECONDS)
            .writeTimeout(15, TimeUnit.SECONDS)
            .build()

        val retrofit = Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(okHttpClient)
            .addConverterFactory(GsonConverterFactory.create())
            .build()

        cachedService = retrofit.create(ShawirApiService::class.java)
        return cachedService!!
    }

    /**
     * Call this whenever the user updates the server URL
     */
    fun resetClient() {
        cachedService = null
        currentBaseUrl = null
    }
}
