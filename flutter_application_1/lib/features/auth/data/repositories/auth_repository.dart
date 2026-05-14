import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../datasources/auth_api.dart';
import '../models/auth_user.dart';

class AuthRepository {
  AuthRepository({
    required AuthApi authApi,
    FlutterSecureStorage? storage,
  })  : _authApi = authApi,
        _storage = storage ?? const FlutterSecureStorage();

  static const _accessTokenKey = 'accessToken';
  static const _refreshTokenKey = 'refreshToken';

  final AuthApi _authApi;
  final FlutterSecureStorage _storage;

  Future<AuthUser> login({
    required String email,
    required String password,
  }) async {
    final data = await _authApi.login(email: email, password: password);
    final user = AuthUser.fromJson(data);

    if (user.accessToken.isNotEmpty) {
      await _storage.write(key: _accessTokenKey, value: user.accessToken);
    }
    if (user.refreshToken != null && user.refreshToken!.isNotEmpty) {
      await _storage.write(key: _refreshTokenKey, value: user.refreshToken);
    }

    return user;
  }

  Future<String?> getAccessToken() => _storage.read(key: _accessTokenKey);
}
