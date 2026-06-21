import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/api/api_constants.dart';
import '../../../../core/error/app_exception.dart';
import '../../../../core/storage/secure_storage.dart';
import '../models/user_model.dart';

part 'auth_repository.g.dart';

@riverpod
AuthRepository authRepository(Ref ref) => AuthRepository(
      dio: ref.read(apiClientProvider),
      storage: ref.read(secureStorageProvider),
    );

class AuthRepository {
  const AuthRepository({required this.dio, required this.storage});

  final Dio dio;
  final SecureStorageService storage;

  Future<UserModel> login({
    required String email,
    required String password,
  }) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        ApiConstants.loginPath,
        data: {'email': email, 'password': password},
      );
      final data = response.data!;
      final token = data['access_token']?.toString() ?? '';
      if (token.isEmpty) throw const AppException('Không nhận được token từ máy chủ.');
      await storage.saveToken(token);
      return UserModel.fromJson(data);
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<UserModel> getMe() async {
    try {
      final response = await dio.get<Map<String, dynamic>>(ApiConstants.mePath);
      return UserModel.fromJson(response.data!);
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<void> logout() async {
    try {
      await dio.post<void>(ApiConstants.logoutPath);
    } catch (_) {
    } finally {
      await storage.deleteToken();
    }
  }

  Future<UserModel> updateProfile({
    required String name,
    String? phone,
  }) async {
    try {
      final response = await dio.put<Map<String, dynamic>>(
        ApiConstants.updateProfilePath,
        data: {'name': name, ...?phone != null ? {'phone': phone} : null},
      );
      return UserModel.fromJson((response.data!['user'] as Map<String, dynamic>?) ?? response.data!);
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    try {
      await dio.put<void>(
        ApiConstants.changePasswordPath,
        data: {
          'current_password': currentPassword,
          'new_password': newPassword,
          'new_password_confirmation': newPassword,
        },
      );
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<String> register({
    required String name,
    required String email,
    required String password,
  }) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        ApiConstants.registerPath,
        data: {
          'name': name,
          'email': email,
          'password': password,
          'password_confirmation': password,
        },
      );
      return response.data!['message']?.toString() ?? 'Đăng ký thành công. Vui lòng kiểm tra email.';
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<String> forgotPassword({required String email}) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        ApiConstants.forgotPasswordPath,
        data: {'email': email},
      );
      return response.data!['message']?.toString() ?? 'Liên kết reset mật khẩu đã được gửi.';
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<String> resendVerificationEmail({required String email}) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        ApiConstants.resendVerificationEmailPath,
        data: {'email': email},
      );
      return response.data!['message']?.toString() ?? 'Đã gửi lại email xác nhận.';
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<String> getGoogleLoginUrl() async {
    try {
      final response = await dio.get<Map<String, dynamic>>(ApiConstants.googleLoginUrlPath);
      return response.data!['url']?.toString() ?? '';
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<UserModel> verifyGoogleCallback(String queryString) async {
    try {
      final response = await dio.get<Map<String, dynamic>>(
        '/auth/google/callback?$queryString',
      );
      final data = response.data!;
      final token = data['access_token']?.toString() ?? '';
      if (token.isEmpty) throw const AppException('Không nhận được token từ máy chủ.');
      await storage.saveToken(token);
      return UserModel.fromJson(data);
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }
}
