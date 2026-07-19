import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/api/api_constants.dart';
import '../../../../core/error/app_exception.dart';
import '../../../auth/data/models/user_model.dart';

part 'profile_repository.g.dart';

@riverpod
ProfileRepository profileRepository(ProfileRepositoryRef ref) =>
    ProfileRepository(dio: ref.read(apiClientProvider));

class ProfileRepository {
  const ProfileRepository({required this.dio});

  final Dio dio;

  Future<UserModel> getProfile() async {
    try {
      final response = await dio.get<Map<String, dynamic>>(ApiConstants.mePath);
      return UserModel.fromJson(response.data!);
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<UserModel> updateProfile({required String name, String? phone}) async {
    try {
      final response = await dio.put<Map<String, dynamic>>(
        ApiConstants.updateProfilePath,
        data: {'name': name, ...?phone != null ? {'phone': phone} : null},
      );
      final data = response.data!;
      return UserModel.fromJson(data['user'] as Map<String, dynamic>? ?? data);
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    try {
      await dio.put<void>(
        ApiConstants.changePasswordPath,
        data: {
          'current_password': currentPassword,
          'new_password': newPassword,
          'new_password_confirmation': newPassword,
        },
      );
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }
}
