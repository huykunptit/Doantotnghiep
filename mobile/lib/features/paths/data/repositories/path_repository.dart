import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/api/api_constants.dart';
import '../../../../core/error/app_exception.dart';
import '../../../../core/storage/local_cache_service.dart';
import '../models/career_path_model.dart';

part 'path_repository.g.dart';

@riverpod
PathRepository pathRepository(PathRepositoryRef ref) => PathRepository(
      dio: ref.read(apiClientProvider),
      cache: ref.read(localCacheServiceProvider),
    );

class PathListResult {
  const PathListResult({required this.items, required this.total});

  final List<CareerPathListItem> items;
  final int total;
}

class PathRepository {
  const PathRepository({required this.dio, required this.cache});

  final Dio dio;
  final LocalCacheService cache;

  Future<PathListResult> listPaths({String? search}) async {
    final cacheKey = 'career_paths_${search ?? ""}';
    try {
      final response = await dio.get<dynamic>(
        ApiConstants.careerPathsPath,
        queryParameters: {
          if (search != null && search.isNotEmpty) 'search': search,
          'per_page': 50,
        },
      );
      final data = response.data;
      await cache.cacheData(cacheKey, data);
      return _parsePathList(data);
    } on DioException catch (e) {
      if (e.type == DioExceptionType.connectionError ||
          e.type == DioExceptionType.connectionTimeout ||
          e.type == DioExceptionType.receiveTimeout) {
        final cached = await cache.getCachedData(cacheKey);
        if (cached != null) {
          return _parsePathList(cached);
        }
      }
      throw AppException.fromDioException(e);
    }
  }

  Future<CareerPathDetail> getPath(String slug) async {
    final cacheKey = 'career_path_$slug';
    try {
      final response = await dio.get<Map<String, dynamic>>(
        '${ApiConstants.careerPathsPath}/$slug',
      );
      final data = response.data!;
      await cache.cacheData(cacheKey, data);
      return CareerPathDetail.fromJson(data);
    } on DioException catch (e) {
      if (e.type == DioExceptionType.connectionError ||
          e.type == DioExceptionType.connectionTimeout ||
          e.type == DioExceptionType.receiveTimeout) {
        final cached = await cache.getCachedData(cacheKey);
        if (cached != null) {
          return CareerPathDetail.fromJson(cached as Map<String, dynamic>);
        }
      }
      throw AppException.fromDioException(e);
    }
  }

  Future<Map<String, dynamic>> createPathOrder(int pathId) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        ApiConstants.ordersPath,
        data: {
          'career_path_id': pathId,
          'payment_method': 'payos',
        },
      );
      return response.data!;
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<void> followPath(int pathId) async {
    try {
      await dio.post<dynamic>('${ApiConstants.careerPathsPath}/$pathId/follow');
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  PathListResult _parsePathList(dynamic data) {
    List<dynamic> list;
    int total;
    if (data is List) {
      list = data;
      total = data.length;
    } else if (data is Map && data['data'] is List) {
      list = data['data'] as List;
      total = data['total'] as int? ?? list.length;
    } else {
      list = [];
      total = 0;
    }
    return PathListResult(
      items: list
          .map((e) => CareerPathListItem.fromJson(e as Map<String, dynamic>))
          .toList(),
      total: total,
    );
  }
}
