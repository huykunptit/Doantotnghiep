import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/error/app_exception.dart';
import '../models/exam_list_model.dart';

part 'exam_list_repository.g.dart';

@riverpod
ExamListRepository examListRepository(ExamListRepositoryRef ref) =>
    ExamListRepository(dio: ref.read(apiClientProvider));

class ExamListRepository {
  const ExamListRepository({required this.dio});

  final Dio dio;

  Future<List<ExamListItemModel>> getMyExams({String? tab}) async {
    try {
      final response = await dio.get<Map<String, dynamic>>(
        '/me/exams',
        queryParameters: tab != null && tab.isNotEmpty ? {'tab': tab} : null,
      );
      final data = response.data!;
      final list = data['data'] as List<dynamic>? ?? data as List<dynamic>? ?? [];
      return list
          .map((e) => ExamListItemModel.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<ExamResultDetailModel> getAttemptResult(int attemptId) async {
    try {
      final response = await dio.get<Map<String, dynamic>>(
        '/attempts/$attemptId/result',
      );
      return ExamResultDetailModel.fromJson(response.data!);
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<List<OrderModel>> getMyOrders() async {
    try {
      final response = await dio.get<dynamic>('/orders');
      final raw = response.data;
      List<dynamic> list;
      if (raw is Map<String, dynamic>) {
        list = raw['data'] as List<dynamic>? ?? [];
      } else {
        list = raw as List<dynamic>? ?? [];
      }
      return list
          .map((e) => OrderModel.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }
}
