import 'app_exception.dart';

/// Never trust a caught error's toString() to be user-facing. Repositories
/// convert DioException -> AppException with a friendly Vietnamese message,
/// but anything else (JSON parsing bugs, an unexpected null field, a type
/// cast failure) bypasses that and would otherwise leak a raw Dart exception
/// string straight into the UI (e.g. "type 'Null' is not a subtype of
/// type 'String'"). This is the single choke point that guarantees it can't.
String friendlyErrorMessage(Object error) {
  if (error is AppException) return error.message;
  return 'Đã xảy ra lỗi. Vui lòng thử lại.';
}
