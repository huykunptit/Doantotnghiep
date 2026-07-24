import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/error/app_exception.dart';
import '../models/ai_models.dart';

final aiRepositoryProvider = Provider<AiRepository>((ref) {
  return AiRepository(dio: ref.watch(apiClientProvider));
});

class AiRepository {
  const AiRepository({required this.dio});

  final Dio dio;

  Future<List<CourseRecommendationItem>> getRecommendations() async {
    try {
      final response = await dio.get<Map<String, dynamic>>(
        '/me/recommendations/extensions',
      );
      final list = response.data?['recommendations'] as List<dynamic>? ?? [];
      return list
          .map((e) =>
              CourseRecommendationItem.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
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
