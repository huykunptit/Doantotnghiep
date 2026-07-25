import 'package:dio/dio.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/api/api_constants.dart';
import '../../../../core/error/app_exception.dart';
import '../models/career_model.dart';

part 'career_repository.g.dart';

@riverpod
CareerRepository careerRepository(CareerRepositoryRef ref) =>
    CareerRepository(dio: ref.read(apiClientProvider));

class CareerRepository {
  const CareerRepository({required this.dio});

  final Dio dio;

  Future<CareerAdvisorStatusModel> getAdvisorStatus() async {
    try {
      final response = await dio.get<Map<String, dynamic>>(ApiConstants.careerAdvisorPath);
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
        ApiConstants.careerUploadCvPath,
        data: formData,
      );

      return await getAdvisorStatus();
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<CareerEvaluateResult> saveCvForm(Map<String, dynamic> payload) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        ApiConstants.careerCvFormPath,
        data: payload,
      );
      return CareerEvaluateResult.fromJson(response.data ?? {});
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<CareerEvaluateResult> evaluate({
    String? targetRole,
    int? expectedSalary,
  }) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        ApiConstants.careerEvaluatePath,
        data: {
          if (targetRole != null && targetRole.isNotEmpty) 'target_role': targetRole,
          if (expectedSalary != null) 'expected_salary': expectedSalary,
        },
      );
      return CareerEvaluateResult.fromJson(response.data ?? {});
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<CareerRecommendationModel> getRecommendation(
    String jobTitle, {
    int? expectedSalary,
  }) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        ApiConstants.careerRecommendPath,
        data: {
          'job_title': jobTitle,
          if (expectedSalary != null) 'expected_salary': expectedSalary,
        },
      );
      final data = response.data!;
      return CareerRecommendationModel.fromJson(
        data['recommendation'] as Map<String, dynamic>,
      );
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }
}
