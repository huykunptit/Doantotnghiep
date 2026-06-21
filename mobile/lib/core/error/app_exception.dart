import 'package:dio/dio.dart';

class AppException implements Exception {
  const AppException(this.message, {this.statusCode, this.requiresVerification = false, this.email});

  final String message;
  final int? statusCode;
  final bool requiresVerification;
  final String? email;

  factory AppException.fromDioException(DioException e) {
    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout) {
      return const AppException('Kết nối quá thời gian chờ. Vui lòng thử lại.');
    }
    if (e.type == DioExceptionType.connectionError) {
      return const AppException('Không thể kết nối đến máy chủ. Kiểm tra kết nối mạng.');
    }
    final statusCode = e.response?.statusCode;
    final data = e.response?.data;
    String msg = 'Đã xảy ra lỗi. Vui lòng thử lại.';
    bool requiresVerification = false;
    String? email;
    
    if (data is Map) {
      if (data['message'] != null) {
        msg = data['message'].toString();
      }
      requiresVerification = data['requires_verification'] as bool? ?? false;
      email = data['email']?.toString();
    } else if (statusCode == 401) {
      msg = 'Phiên đăng nhập hết hạn. Vui lòng đăng nhập lại.';
    } else if (statusCode == 403) {
      msg = 'Bạn không có quyền thực hiện thao tác này.';
    } else if (statusCode == 422) {
      final errors = data is Map ? data['errors'] : null;
      if (errors is Map) {
        msg = (errors.values.first as List).first.toString();
      }
    } else if (statusCode == 404) {
      msg = 'Không tìm thấy dữ liệu yêu cầu.';
    } else if (statusCode != null && statusCode >= 500) {
      msg = 'Lỗi máy chủ. Vui lòng thử lại sau.';
    }
    return AppException(msg, statusCode: statusCode, requiresVerification: requiresVerification, email: email);
  }

  @override
  String toString() => message;
}
