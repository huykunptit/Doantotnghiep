import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/error/app_exception.dart';
import '../../../../core/storage/secure_storage.dart';
import '../models/ai_models.dart';
import '../ai_chat_history_store.dart';

final aiRepositoryProvider = Provider<AiRepository>((ref) {
  return AiRepository(dio: ref.watch(apiClientProvider));
});

final aiChatHistoryStoreProvider = Provider<AiChatHistoryStore>((ref) {
  return AiChatHistoryStore(ref.watch(secureStorageProvider));
});

class AiRepository {
  const AiRepository({required this.dio});

  final Dio dio;

  Future<RecommendationsBundle> getRecommendations() async {
    try {
      final response = await dio.get<Map<String, dynamic>>(
        '/me/recommendations/extensions',
      );
      final data = response.data ?? {};
      final list = data['recommendations'] as List<dynamic>? ?? [];
      final context = data['context'] as Map<String, dynamic>? ?? {};
      return RecommendationsBundle(
        items: list
            .whereType<Map>()
            .map((e) => CourseRecommendationItem.fromJson(
                  Map<String, dynamic>.from(e),
                ))
            .where((e) => e.course.id > 0)
            .toList(),
        profileSparse: context['profile_sparse'] == true,
        fallback: context['fallback']?.toString(),
      );
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<StudyAdvisorAdvice> getStudyAdvisorAdvice() async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        '/ai/study-advisor',
        options: Options(
          receiveTimeout: const Duration(seconds: 70),
          sendTimeout: const Duration(seconds: 20),
        ),
      );
      return StudyAdvisorAdvice.fromJson(response.data ?? {});
    } on DioException {
      return const StudyAdvisorAdvice(explanationUnavailable: true);
    }
  }

  Future<TutoringTipModel> getTutoringTips({
    required int courseId,
    int? lessonId,
    String? lessonTitle,
    String? lessonType,
    double? progressPercent,
  }) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        '/ai/tutoring',
        data: {
          'course_id': courseId,
          if (lessonId != null) 'lesson_id': lessonId,
          if (lessonTitle != null) 'lesson_title': lessonTitle,
          if (lessonType != null) 'lesson_type': lessonType,
          if (progressPercent != null) 'progress_percent': progressPercent,
        },
        options: Options(
          receiveTimeout: const Duration(seconds: 45),
        ),
      );
      return TutoringTipModel.fromJson(response.data ?? {});
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<AiChatReply> chat({
    required String message,
    int? courseId,
    List<Map<String, String>> history = const [],
  }) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        '/ai/chat',
        data: {
          'message': message,
          if (courseId != null) 'course_id': courseId,
          'history': history,
        },
        options: Options(
          receiveTimeout: const Duration(seconds: 60),
          sendTimeout: const Duration(seconds: 30),
        ),
      );
      return AiChatReply.fromJson(response.data ?? {});
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }
}
