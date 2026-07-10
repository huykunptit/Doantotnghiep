import 'package:dio/dio.dart';
import '../../../../core/api/api_constants.dart';

class AuthApi {
  AuthApi({Dio? dio})
      : _dio = dio ?? Dio(BaseOptions(baseUrl: ApiConstants.baseUrl));

  final Dio _dio;

  Future<Map<String, dynamic>> login({
    required String email,
    required String password,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      ApiConstants.loginPath,
      data: {
        'email': email,
        'password': password,
      },
    );

    return response.data ?? <String, dynamic>{};
  }
}
