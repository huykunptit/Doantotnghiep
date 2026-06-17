import 'package:dio/dio.dart';

class AuthApi {
  AuthApi({Dio? dio})
      : _dio = dio ?? Dio(BaseOptions(baseUrl: _baseUrl));

  static const String _baseUrl = 'http://localhost:3000';
  static const String _loginPath = '/auth/login';

  final Dio _dio;

  Future<Map<String, dynamic>> login({
    required String email,
    required String password,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      _loginPath,
      data: {
        'email': email,
        'password': password,
      },
    );

    return response.data ?? <String, dynamic>{};
  }
}
