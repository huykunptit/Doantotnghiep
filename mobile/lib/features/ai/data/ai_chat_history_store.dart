import 'dart:convert';

import '../../../../core/storage/secure_storage.dart';
import 'models/ai_models.dart';

class AiChatHistoryStore {
  const AiChatHistoryStore(this._storage);

  final SecureStorageService _storage;

  static const _prefix = 'eript_ai_chat_';
  static const _maxMessages = 50;

  String _key(int? userId, int? courseId) =>
      '$_prefix${userId ?? 0}_${courseId ?? 0}';

  Future<List<AiChatMessage>> load({int? userId, int? courseId}) async {
    try {
      final raw = await _storage.read(_key(userId, courseId));
      if (raw == null || raw.isEmpty) return const [];
      final decoded = jsonDecode(raw);
      if (decoded is! List) return const [];
      return decoded
          .whereType<Map>()
          .map((e) => AiChatMessage.fromJson(Map<String, dynamic>.from(e)))
          .where((m) => m.text.trim().isNotEmpty)
          .toList();
    } catch (_) {
      return const [];
    }
  }

  Future<void> save({
    required List<AiChatMessage> messages,
    int? userId,
    int? courseId,
  }) async {
    final compact = messages
        .where((m) => m.text.trim().isNotEmpty)
        .toList();
    final clipped = compact.length > _maxMessages
        ? compact.sublist(compact.length - _maxMessages)
        : compact;
    await _storage.write(
      _key(userId, courseId),
      jsonEncode(clipped.map((m) => m.toJson()).toList()),
    );
  }

  Future<void> clear({int? userId, int? courseId}) async {
    await _storage.delete(_key(userId, courseId));
  }
}
