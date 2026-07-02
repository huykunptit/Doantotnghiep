import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/error/app_exception.dart';
import '../models/dashboard_model.dart';
import '../models/transcript_model.dart';
import '../models/learning_path_model.dart';
import '../models/attendance_model.dart';

part 'dashboard_repository.g.dart';

@riverpod
DashboardRepository dashboardRepository(Ref ref) =>
    DashboardRepository(dio: ref.read(apiClientProvider));

class DashboardRepository {
  const DashboardRepository({required this.dio});

  final Dio dio;

  Future<DashboardModel> getDashboard() async {
    try {
      final response = await dio.get<Map<String, dynamic>>('/me/dashboard');
      return DashboardModel.fromJson(response.data!);
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<TranscriptModel> getTranscript() async {
    try {
      final response = await dio.get<Map<String, dynamic>>('/me/transcript');
      return TranscriptModel.fromJson(response.data!);
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<LearningPathModel> getLearningPath() async {
    try {
      final response = await dio.get<Map<String, dynamic>>('/me/learning-path');
      return LearningPathModel.fromJson(response.data!);
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<CheckInResultModel> checkIn(int offlineSessionId, {String? deviceInfo}) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        '/me/attendance/check-in',
        data: {
          'offline_session_id': offlineSessionId,
          'device_info': deviceInfo,
        },
      );
      return CheckInResultModel.fromJson(response.data!);
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<List<AttendanceHistoryItemModel>> getAttendanceHistory() async {
    try {
      final response = await dio.get<Map<String, dynamic>>('/me/attendance');
      final data = response.data!['history'] as List<dynamic>? ?? [];
      return data
          .map((e) => AttendanceHistoryItemModel.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }
}
