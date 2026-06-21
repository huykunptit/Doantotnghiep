import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/error/app_exception.dart';
import '../models/quiz_model.dart';

part 'quiz_repository.g.dart';

@riverpod
QuizRepository quizRepository(Ref ref) =>
    QuizRepository(dio: ref.read(apiClientProvider));

class QuizRepository {
  const QuizRepository({required this.dio});

  final Dio dio;

  // ── Lesson-Bound Quizzes ───────────────────────────────────────────

  Future<Map<String, dynamic>> getLessonQuiz(int courseId, int lessonId) async {
    try {
      final response = await dio.get<Map<String, dynamic>>(
        '/courses/$courseId/lessons/$lessonId/quiz',
      );
      final data = response.data!;
      
      final quiz = QuizDetailModel.fromJson(data['quiz'] as Map<String, dynamic>);
      final questionsList = (data['questions'] as List<dynamic>?)
              ?.map((e) => QuestionModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [];
      
      return {
        'quiz': quiz,
        'questions': questionsList,
        'attempt_id': data['attempt_id'] as int? ?? 0,
      };
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<QuizAttemptModel> submitLessonQuiz(
    int courseId,
    int lessonId,
    int quizId, {
    required int attemptId,
    required Map<String, dynamic> answers,
  }) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        '/courses/$courseId/lessons/$lessonId/quiz/$quizId/submit',
        data: {
          'attempt_id': attemptId,
          'answers': answers,
        },
      );
      return QuizAttemptModel.fromJson(response.data!);
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  // ── Exam Workspaces ────────────────────────────────────────────────

  Future<Map<String, dynamic>> startExam(int examId) async {
    try {
      final response = await dio.get<Map<String, dynamic>>(
        '/exams/$examId/start',
      );
      final data = response.data!;
      
      final exam = ExamModel.fromJson(data['exam'] as Map<String, dynamic>);
      final quiz = QuizDetailModel.fromJson(data['quiz'] as Map<String, dynamic>);
      final questionsList = (data['questions'] as List<dynamic>?)
              ?.map((e) => QuestionModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [];
      
      return {
        'exam': exam,
        'quiz': quiz,
        'questions': questionsList,
        'attempt_id': data['attempt_id'] as int? ?? 0,
        'remaining_time': data['remaining_time'] as int? ?? 0,
        'status': data['status']?.toString() ?? 'in_progress',
        'saved_answers': data['saved_answers'] as Map<String, dynamic>? ?? {},
      };
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<Map<String, dynamic>> autoSaveExam(
    int attemptId, {
    required Map<String, dynamic> answers,
  }) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        '/attempts/$attemptId/auto-save',
        data: {
          'answers': answers,
        },
      );
      return response.data ?? {};
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<QuizAttemptModel> submitExam(
    int examId, {
    required int attemptId,
    required Map<String, dynamic> answers,
  }) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        '/exams/$examId/submit',
        data: {
          'attempt_id': attemptId,
          'answers': answers,
        },
      );
      return QuizAttemptModel.fromJson(response.data!);
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<Map<String, dynamic>> getExamStatus(
    int attemptId, {
    String? since,
  }) async {
    try {
      final response = await dio.get<Map<String, dynamic>>(
        '/attempts/$attemptId/status',
        queryParameters: since != null ? {'since': since} : null,
      );
      return response.data ?? {};
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<void> logViolation(
    int attemptId, {
    required String type,
    required String severity,
    Map<String, dynamic>? metadata,
  }) async {
    try {
      await dio.post<void>(
        '/attempts/$attemptId/violations',
        data: {
          'type': type,
          'severity': severity,
          'metadata': ?metadata,
        },
      );
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }
}
