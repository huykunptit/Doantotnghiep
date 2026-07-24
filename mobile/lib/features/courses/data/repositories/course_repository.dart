import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/api/api_constants.dart';
import '../../../../core/error/app_exception.dart';
import '../../../../core/storage/local_cache_service.dart';
import '../models/course_model.dart';
import '../models/enrollment_model.dart';
import '../models/category_model.dart';

part 'course_repository.g.dart';

@riverpod
CourseRepository courseRepository(CourseRepositoryRef ref) => CourseRepository(
      dio: ref.read(apiClientProvider),
      cache: ref.read(localCacheServiceProvider),
    );

class CourseRepository {
  const CourseRepository({required this.dio, required this.cache});

  final Dio dio;
  final LocalCacheService cache;

  Future<List<EnrollmentModel>> getEnrollments() async {
    const cacheKey = 'my_enrollments';
    try {
      final response = await dio.get<dynamic>(ApiConstants.enrollmentsPath);
      final data = response.data;
      await cache.cacheData(cacheKey, data);
      return _parseEnrollmentList(data);
    } on DioException catch (e) {
      if (e.type == DioExceptionType.connectionError ||
          e.type == DioExceptionType.connectionTimeout ||
          e.type == DioExceptionType.receiveTimeout) {
        final cached = await cache.getCachedData(cacheKey);
        if (cached != null) {
          return _parseEnrollmentList(cached);
        }
      }
      throw AppException.fromDioException(e);
    }
  }

  Future<CourseDetailModel> getCourseDetail(int courseId) async {
    final cacheKey = 'course_detail_$courseId';
    try {
      final response = await dio.get<Map<String, dynamic>>(
        '${ApiConstants.coursesPath}/$courseId',
      );
      final data = response.data!;
      await cache.cacheData(cacheKey, data);
      return CourseDetailModel.fromJson(data);
    } on DioException catch (e) {
      if (e.type == DioExceptionType.connectionError ||
          e.type == DioExceptionType.connectionTimeout ||
          e.type == DioExceptionType.receiveTimeout) {
        final cached = await cache.getCachedData(cacheKey);
        if (cached != null) {
          return CourseDetailModel.fromJson(cached as Map<String, dynamic>);
        }
      }
      throw AppException.fromDioException(e);
    }
  }

  Future<List<CourseListItemModel>> getCourses({String? search, int? categoryId}) async {
    final cacheKey = 'courses_${search ?? ""}_${categoryId ?? ""}';
    try {
      final queryParams = <String, dynamic>{
        'status': 'published',
        if (search != null && search.isNotEmpty) 'search': search,
        'category': ?categoryId,
      };

      final response = await dio.get<dynamic>(
        ApiConstants.coursesPath,
        queryParameters: queryParams,
      );

      final data = response.data;
      await cache.cacheData(cacheKey, data);
      return _parseCourseList(data);
    } on DioException catch (e) {
      if (e.type == DioExceptionType.connectionError ||
          e.type == DioExceptionType.connectionTimeout ||
          e.type == DioExceptionType.receiveTimeout) {
        final cached = await cache.getCachedData(cacheKey);
        if (cached != null) {
          return _parseCourseList(cached);
        }
      }
      throw AppException.fromDioException(e);
    }
  }

  Future<List<CategoryModel>> getCategories() async {
    const cacheKey = 'categories';
    try {
      final response = await dio.get<dynamic>('/categories');
      final data = response.data;
      await cache.cacheData(cacheKey, data);
      return _parseCategoryList(data);
    } on DioException catch (e) {
      if (e.type == DioExceptionType.connectionError ||
          e.type == DioExceptionType.connectionTimeout ||
          e.type == DioExceptionType.receiveTimeout) {
        final cached = await cache.getCachedData(cacheKey);
        if (cached != null) {
          return _parseCategoryList(cached);
        }
      }
      throw AppException.fromDioException(e);
    }
  }

  Future<Map<String, dynamic>> createOrder(int courseId) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        '/orders',
        data: {'course_id': courseId},
      );
      return response.data!;
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  List<CourseListItemModel> _parseCourseList(dynamic data) {
    List<dynamic> list;
    if (data is List) {
      list = data;
    } else if (data is Map && data['data'] is List) {
      list = data['data'] as List;
    } else {
      list = [];
    }
    return list
        .map((e) => CourseListItemModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  List<EnrollmentModel> _parseEnrollmentList(dynamic data) {
    List<dynamic> list;
    if (data is List) {
      list = data;
    } else if (data is Map && data['data'] is List) {
      list = data['data'] as List;
    } else {
      list = [];
    }
    return list
        .map((e) => EnrollmentModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  List<CategoryModel> _parseCategoryList(dynamic data) {
    List<dynamic> list;
    if (data is List) {
      list = data;
    } else if (data is Map && data['data'] is List) {
      list = data['data'] as List;
    } else {
      list = [];
    }
    return list
        .map((e) => CategoryModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }
}
