package id.netclean.netclean_staff

import android.content.Intent
import android.provider.Settings
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            "id.netclean.netclean_staff/settings",
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "openBluetoothSettings" -> {
                    try {
                        startActivity(Intent(Settings.ACTION_BLUETOOTH_SETTINGS))
                        result.success(null)
                    } catch (error: Exception) {
                        result.error(
                            "UNAVAILABLE",
                            "Bluetooth settings are not available on this device.",
                            null,
                        )
                    }
                }
                else -> result.notImplemented()
            }
        }
    }
}
