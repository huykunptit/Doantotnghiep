import 'app_exception.dart';

/// Never trust a caught error's toString() to be user-facing. Repositories
/// convert DioException -> AppException with a friendly Vietnamese message,
/// but anything else (JSON parsing bugs, an unexpected null field, a type
/// cast failure) bypasses that and would otherwise leak a raw Dart exception
/// string straight into the UI (e.g. "type 'Null' is not a subtype of
/// type 'String'"). This is the single choke point that guarantees it can't.
String friendlyErrorMessage(Object error) {
  if (error is AppException) return _sanitize(error.message);
  // `Exception` (unlike `Error`) is reserved in this codebase for deliberately
  // thrown, already-Vietnamese, user-facing messages (see attendance_screen's
  // location checks) — safe to surface. TypeError/NoSuchMethodError/etc. are
  // `Error`, not `Exception`, so they still fall through to the generic text.
  if (error is Exception) {
    final msg = error.toString();
    const prefix = 'Exception: ';
    return _sanitize(msg.startsWith(prefix) ? msg.substring(prefix.length) : msg);
  }
  return 'Đã xảy ra lỗi. Vui lòng thử lại.';
}

String _sanitize(String message) {
  final lower = message.toLowerCase();
  if (lower.contains('payos') ||
      lower.contains('not configured') ||
      lower.contains('payment gateway')) {
    return 'Hệ thống thanh toán tạm thời chưa sẵn sàng. Vui lòng thử lại sau hoặc liên hệ hỗ trợ.';
  }
  if (RegExp(r'type .+ is not a subtype', caseSensitive: false).hasMatch(message) ||
      lower.contains('null check') ||
      lower.contains('nosuchmethod')) {
    return 'Đã xảy ra lỗi. Vui lòng thử lại.';
  }
  return message;
}
