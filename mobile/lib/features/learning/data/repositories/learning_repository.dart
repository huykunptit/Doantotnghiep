import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/error/app_exception.dart';
import '../../../../core/storage/local_cache_service.dart';
import '../../../../core/storage/offline_sync_manager.dart';
import '../models/lesson_detail_model.dart';
import '../models/note_model.dart';
import '../models/attachment_model.dart';

part 'learning_repository.g.dart';

@riverpod
LearningRepository learningRepository(LearningRepositoryRef ref) => LearningRepository(
      dio: ref.read(apiClientProvider),
      cache: ref.read(localCacheServiceProvider),
      syncManager: ref.read(offlineSyncManagerProvider),
    );

class LearningRepository {
  const LearningRepository({
    required this.dio,
    required this.cache,
    required this.syncManager,
  });

  final Dio dio;
  final LocalCacheService cache;
  final OfflineSyncManager syncManager;

  Future<LessonDetailModel> getLessonDetail(int courseId, int lessonId) async {
    final cacheKey = 'lesson_detail_${courseId}_$lessonId';
    try {
      // Trigger sync in background if online
      syncManager.syncOfflineData();

      final lessonRes = await dio.get<Map<String, dynamic>>(
        '/courses/$courseId/lessons/$lessonId',
      );
      
      Map<String, dynamic>? progressData;
      try {
        final progRes = await dio.get<Map<String, dynamic>>(
          '/courses/$courseId/lessons/$lessonId/progress',
        );
        progressData = progRes.data?['progress'] as Map<String, dynamic>? ?? progRes.data;
      } catch (_) {}

      final mergedJson = {
        ...lessonRes.data!,
        'progress': ?progressData,
      };

      await cache.cacheData(cacheKey, mergedJson);
      return LessonDetailModel.fromJson(mergedJson);
    } on DioException catch (e) {
      if (e.type == DioExceptionType.connectionError ||
          e.type == DioExceptionType.connectionTimeout ||
          e.type == DioExceptionType.receiveTimeout) {
        final cached = await cache.getCachedData(cacheKey);
        if (cached != null) {
          return LessonDetailModel.fromJson(cached as Map<String, dynamic>);
        }
      }
      throw AppException.fromDioException(e);
    }
  }

  Future<void> updateLessonProgress(
    int courseId,
    int lessonId, {
    required int watchedSeconds,
    bool? completed,
  }) async {
    final path = '/courses/$courseId/lessons/$lessonId/progress';
    final data = {
      'watched_seconds': watchedSeconds,
      'completed': ?completed,
    };

    try {
      await dio.post<void>(path, data: data);
      syncManager.syncOfflineData();
    } on DioException catch (e) {
      if (e.type == DioExceptionType.connectionError ||
          e.type == DioExceptionType.connectionTimeout ||
          e.type == DioExceptionType.receiveTimeout) {
        await syncManager.queueMutation(method: 'POST', path: path, data: data);
        
        final cacheKey = 'lesson_detail_${courseId}_$lessonId';
        final cached = await cache.getCachedData(cacheKey);
        if (cached != null && cached is Map<String, dynamic>) {
          final progress = cached['progress'] as Map<String, dynamic>? ?? {};
          progress['watched_seconds'] = watchedSeconds;
          if (completed != null) {
            progress['completed'] = completed;
          }
          cached['progress'] = progress;
          await cache.cacheData(cacheKey, cached);
        }
        return;
      }
      throw AppException.fromDioException(e);
    }
  }

  Future<List<NoteModel>> getLessonNotes(int courseId, int lessonId) async {
    final cacheKey = 'lesson_notes_${courseId}_$lessonId';
    try {
      final response = await dio.get<dynamic>(
        '/courses/$courseId/lessons/$lessonId/notes',
      );
      final data = response.data;
      await cache.cacheData(cacheKey, data);
      return _parseNotesList(data);
    } on DioException catch (e) {
      if (e.type == DioExceptionType.connectionError ||
          e.type == DioExceptionType.connectionTimeout ||
          e.type == DioExceptionType.receiveTimeout) {
        final cached = await cache.getCachedData(cacheKey);
        if (cached != null) {
          return _parseNotesList(cached);
        }
      }
      throw AppException.fromDioException(e);
    }
  }

  Future<NoteModel> createLessonNote(
    int courseId,
    int lessonId, {
    required String content,
    required int timeSeconds,
  }) async {
    final path = '/courses/$courseId/lessons/$lessonId/notes';
    final requestData = {
      'content': content,
      'position_seconds': timeSeconds,
    };

    try {
      final response = await dio.post<Map<String, dynamic>>(path, data: requestData);
      syncManager.syncOfflineData();
      final body = response.data ?? <String, dynamic>{};
      final noteJson = body['note'];
      if (noteJson is Map<String, dynamic>) {
        return NoteModel.fromJson(noteJson);
      }
      if (body['id'] != null) {
        return NoteModel.fromJson(body);
      }
      return NoteModel(
        id: 0,
        lessonId: lessonId,
        content: content,
        timeSeconds: timeSeconds,
        createdAt: DateTime.now().toIso8601String(),
      );
    } on DioException catch (e) {
      if (e.type == DioExceptionType.connectionError ||
          e.type == DioExceptionType.connectionTimeout ||
          e.type == DioExceptionType.receiveTimeout) {
        await syncManager.queueMutation(method: 'POST', path: path, data: requestData);

        final localNote = NoteModel(
          id: -DateTime.now().millisecondsSinceEpoch,
          lessonId: lessonId,
          content: content,
          timeSeconds: timeSeconds,
          createdAt: DateTime.now().toIso8601String(),
        );

        final cacheKey = 'lesson_notes_${courseId}_$lessonId';
        final cached = await cache.getCachedData(cacheKey);
        final List<dynamic> currentList = cached is List ? List<dynamic>.from(cached) : <dynamic>[];
        currentList.add({
          'id': localNote.id,
          'lesson_id': localNote.lessonId,
          'content': localNote.content,
          'position_seconds': localNote.timeSeconds,
          'created_at': localNote.createdAt,
        });
        await cache.cacheData(cacheKey, currentList);

        return localNote;
      }
      throw AppException.fromDioException(e);
    }
  }

  Future<void> deleteLessonNote(int courseId, int lessonId, int noteId) async {
    final path = '/courses/$courseId/lessons/$lessonId/notes/$noteId';
    try {
      await dio.delete<void>(path);
      syncManager.syncOfflineData();
    } on DioException catch (e) {
      if (e.type == DioExceptionType.connectionError ||
          e.type == DioExceptionType.connectionTimeout ||
          e.type == DioExceptionType.receiveTimeout) {
        await syncManager.queueMutation(method: 'DELETE', path: path);

        final cacheKey = 'lesson_notes_${courseId}_$lessonId';
        final cached = await cache.getCachedData(cacheKey);
        if (cached is List) {
          final updatedList = cached.where((e) {
            final id = e['id'] as int?;
            return id != noteId;
          }).toList();
          await cache.cacheData(cacheKey, updatedList);
        }
        return;
      }
      throw AppException.fromDioException(e);
    }
  }

  Future<List<AttachmentModel>> getLessonAttachments(int courseId, int lessonId) async {
    final cacheKey = 'lesson_attachments_${courseId}_$lessonId';
    try {
      final response = await dio.get<dynamic>(
        '/courses/$courseId/lessons/$lessonId/attachments',
      );
      final data = response.data;
      await cache.cacheData(cacheKey, data);
      return _parseAttachmentsList(data);
    } on DioException catch (e) {
      if (e.type == DioExceptionType.connectionError ||
          e.type == DioExceptionType.connectionTimeout ||
          e.type == DioExceptionType.receiveTimeout) {
        final cached = await cache.getCachedData(cacheKey);
        if (cached != null) {
          return _parseAttachmentsList(cached);
        }
      }
      throw AppException.fromDioException(e);
    }
  }

  List<NoteModel> _parseNotesList(dynamic data) {
    List<dynamic> list;
    if (data is List) {
      list = data;
    } else if (data is Map && data['data'] is List) {
      list = data['data'] as List;
    } else {
      list = [];
    }
    return list.map((e) => NoteModel.fromJson(e as Map<String, dynamic>)).toList();
  }

  List<AttachmentModel> _parseAttachmentsList(dynamic data) {
    List<dynamic> list;
    if (data is List) {
      list = data;
    } else if (data is Map && data['data'] is List) {
      list = data['data'] as List;
    } else {
      list = [];
    }
    return list.map((e) => AttachmentModel.fromJson(e as Map<String, dynamic>)).toList();
  }
}
