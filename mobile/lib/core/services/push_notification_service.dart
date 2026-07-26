import 'package:flutter/foundation.dart';

class PushNotificationService {
  PushNotificationService._();

  static Future<void> initialize() async {
    debugPrint("--------------------------------------------------");
    debugPrint("🌿 [Eript LMS] Khởi tạo dịch vụ thông báo Push...");
    debugPrint("🔑 Mock FCM Token: eript_fcm_token_mock_123456789");
    debugPrint("💡 Để tích hợp Firebase thật, hãy thêm cấu hình google-services.json / GoogleService-Info.plist và mở các dependencies trong pubspec.");
    debugPrint("--------------------------------------------------");
  }
}
