import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/error/app_exception.dart';
import '../models/dashboard_model.dart';
import '../models/transcript_model.dart';

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
}
