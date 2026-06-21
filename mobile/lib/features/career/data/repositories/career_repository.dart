import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/error/app_exception.dart';
import '../models/career_model.dart';

part 'career_repository.g.dart';

@riverpod
CareerRepository careerRepository(Ref ref) =>
    CareerRepository(dio: ref.read(apiClientProvider));

class CareerRepository {
  const CareerRepository({required this.dio});

  final Dio dio;

  Future<CareerAdvisorStatusModel> getAdvisorStatus() async {
    try {
      final response = await dio.get<Map<String, dynamic>>('/career/advisor');
      return CareerAdvisorStatusModel.fromJson(response.data!);
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<CareerAdvisorStatusModel> uploadCV(String filePath, String fileName) async {
    try {
      final formData = FormData.fromMap({
        'cv': await MultipartFile.fromFile(filePath, filename: fileName),
      });

      await dio.post<Map<String, dynamic>>(
        '/career/upload-cv',
        data: formData,
      );

      // Return refreshed advisor status to update everything in the UI at once.
      return await getAdvisorStatus();
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<CareerRecommendationModel> getRecommendation(String jobTitle) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        '/career/recommend',
        data: {'job_title': jobTitle},
      );
      final data = response.data!;
      return CareerRecommendationModel.fromJson(data['recommendation'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }
}
