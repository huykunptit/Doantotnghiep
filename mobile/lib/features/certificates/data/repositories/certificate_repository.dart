import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/error/app_exception.dart';
import '../models/certificate_model.dart';

part 'certificate_repository.g.dart';

@riverpod
CertificateRepository certificateRepository(CertificateRepositoryRef ref) =>
    CertificateRepository(dio: ref.read(apiClientProvider));

class CertificateRepository {
  const CertificateRepository({required this.dio});

  final Dio dio;

  Future<List<UserCertificateModel>> getMyCertificates() async {
    try {
      final response = await dio.get<dynamic>('/my-certificates');
      final data = response.data;
      List<dynamic> list;
      if (data is List) {
        list = data;
      } else if (data is Map && data['data'] is List) {
        list = data['data'] as List;
      } else {
        list = [];
      }
      return list.map((e) => UserCertificateModel.fromJson(e as Map<String, dynamic>)).toList();
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }
}
