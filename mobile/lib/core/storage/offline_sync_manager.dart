import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../api/api_client.dart';
import 'secure_storage.dart';

part 'offline_sync_manager.g.dart';

@riverpod
OfflineSyncManager offlineSyncManager(OfflineSyncManagerRef ref) => OfflineSyncManager(
      storage: ref.read(secureStorageProvider),
      dio: ref.read(apiClientProvider),
    );

class OfflineSyncManager {
  const OfflineSyncManager({required this.storage, required this.dio});

  final SecureStorageService storage;
  final Dio dio;

  static const _mutationsKey = 'eript_offline_mutations';

  Future<void> queueMutation({
    required String method,
    required String path,
    Map<String, dynamic>? data,
  }) async {
    try {
      final listStr = await storage.read(_mutationsKey);
      final List<dynamic> list = listStr != null ? jsonDecode(listStr) : [];
      
      list.add({
        'method': method,
        'path': path,
        'data': data,
        'queued_at': DateTime.now().millisecondsSinceEpoch,
      });
      
      await storage.write(_mutationsKey, jsonEncode(list));
    } catch (_) {
      // Safe fallback
    }
  }

  Future<void> syncOfflineData() async {
    try {
      final listStr = await storage.read(_mutationsKey);
      if (listStr == null) return;

      final List<dynamic> list = jsonDecode(listStr);
      if (list.isEmpty) return;

      final List<dynamic> failedMutations = [];

      for (final mutation in list) {
        final method = mutation['method'] as String;
        final path = mutation['path'] as String;
        final data = mutation['data'] as Map<String, dynamic>?;

        try {
          if (method == 'POST') {
            await dio.post<void>(path, data: data);
          } else if (method == 'PUT') {
            await dio.put<void>(path, data: data);
          } else if (method == 'DELETE') {
            await dio.delete<void>(path, data: data);
          }
        } catch (_) {
          failedMutations.add(mutation);
        }
      }

      if (failedMutations.isEmpty) {
        await storage.delete(_mutationsKey);
      } else {
        await storage.write(_mutationsKey, jsonEncode(failedMutations));
      }
    } catch (_) {
      // Safe fallback
    }
  }
}
