import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';

part 'secure_storage.g.dart';

@riverpod
SecureStorageService secureStorage(SecureStorageRef ref) => SecureStorageService();

class SecureStorageService {
  static const _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
    // Web uses localStorage; without webOptions some browsers can stall reads.
    webOptions: WebOptions(
      dbName: 'eript_lms_secure',
      publicKey: 'eript_lms_public',
    ),
  );
  static const _tokenKey = 'access_token';
  static const _themeModeKey = 'theme_mode';

  Future<void> saveToken(String token) =>
      _storage.write(key: _tokenKey, value: token);

  Future<String?> getToken() => _storage.read(key: _tokenKey);

  Future<void> deleteToken() => _storage.delete(key: _tokenKey);

  Future<void> saveThemeMode(String mode) =>
      _storage.write(key: _themeModeKey, value: mode);

  Future<String?> getThemeMode() => _storage.read(key: _themeModeKey);

  Future<void> write(String key, String value) =>
      _storage.write(key: key, value: value);

  Future<String?> read(String key) => _storage.read(key: key);

  Future<void> delete(String key) => _storage.delete(key: key);

  Future<void> clearAll() => _storage.deleteAll();
}
