import 'dart:convert';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import 'secure_storage.dart';

part 'local_cache_service.g.dart';

@riverpod
LocalCacheService localCacheService(LocalCacheServiceRef ref) =>
    LocalCacheService(storage: ref.read(secureStorageProvider));

class LocalCacheService {
  const LocalCacheService({required this.storage});

  final SecureStorageService storage;

  static const _prefix = 'eript_cache_';

  Future<void> cacheData(String key, dynamic data) async {
    final wrapper = {
      'cached_at': DateTime.now().millisecondsSinceEpoch,
      'data': data,
    };
    await storage.write('$_prefix$key', jsonEncode(wrapper));
  }

  Future<dynamic> getCachedData(String key, {Duration? maxAge}) async {
    try {
      final jsonStr = await storage.read('$_prefix$key');
      if (jsonStr == null) return null;

      final wrapper = jsonDecode(jsonStr) as Map<String, dynamic>;
      final cachedAt = wrapper['cached_at'] as int;
      final data = wrapper['data'];

      if (maxAge != null) {
        final age = DateTime.now().difference(DateTime.fromMillisecondsSinceEpoch(cachedAt));
        if (age > maxAge) {
          await deleteCache(key);
          return null;
        }
      }

      return data;
    } catch (_) {
      return null;
    }
  }

  Future<void> deleteCache(String key) async {
    await storage.delete('$_prefix$key');
  }
}
