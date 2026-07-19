import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/error/app_exception.dart';
import '../models/student_models.dart';

part 'student_repository.g.dart';

@riverpod
StudentRepository studentRepository(StudentRepositoryRef ref) =>
    StudentRepository(dio: ref.read(apiClientProvider));

class StudentRepository {
  const StudentRepository({required this.dio});
  final Dio dio;

  Future<List<TaskModel>> getMyTasks({bool? done}) async {
    try {
      final response = await dio.get<dynamic>(
        '/me/tasks',
        queryParameters: done != null ? {'done': done ? 1 : 0} : null,
      );
      final raw = response.data;
      List<dynamic> list;
      if (raw is Map<String, dynamic>) {
        list = raw['data'] as List<dynamic>? ?? raw['tasks'] as List<dynamic>? ?? [];
      } else {
        list = raw as List<dynamic>? ?? [];
      }
      return list
          .map((e) => TaskModel.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<List<CalendarExamModel>> getMyExamSchedule() async {
    try {
      final response = await dio.get<dynamic>('/me/exams');
      final raw = response.data;
      List<dynamic> list;
      if (raw is Map<String, dynamic>) {
        list = raw['data'] as List<dynamic>? ?? [];
      } else {
        list = raw as List<dynamic>? ?? [];
      }
      return list
          .map((e) => CalendarExamModel.fromJson(e as Map<String, dynamic>))
          .where((e) => e.startTime != null)
          .toList();
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<List<LibraryAttachmentModel>> getMyLibrary() async {
    try {
      // Fetch enrolled courses, then collect attachments
      final enrollResponse = await dio.get<dynamic>('/user/enrollments');
      final rawEnroll = enrollResponse.data;
      List<dynamic> enrollments;
      if (rawEnroll is Map<String, dynamic>) {
        enrollments = rawEnroll['data'] as List<dynamic>? ?? [];
      } else {
        enrollments = rawEnroll as List<dynamic>? ?? [];
      }

      final allAttachments = <LibraryAttachmentModel>[];
      for (final enroll in enrollments.take(20)) {
        final e = enroll as Map<String, dynamic>;
        final courseId = e['course_id'] as int? ?? 0;
        final courseTitle = (e['course'] as Map<String, dynamic>?)?['title']?.toString() ?? '';
        if (courseId == 0) continue;
        try {
          final attRes = await dio.get<dynamic>('/courses/$courseId/attachments');
          final rawAtt = attRes.data;
          List<dynamic> attList;
          if (rawAtt is Map<String, dynamic>) {
            attList = rawAtt['data'] as List<dynamic>? ?? rawAtt['attachments'] as List<dynamic>? ?? [];
          } else {
            attList = rawAtt as List<dynamic>? ?? [];
          }
          for (final att in attList) {
            allAttachments.add(LibraryAttachmentModel.fromJson(
              att as Map<String, dynamic>,
              courseTitle: courseTitle,
              courseId: courseId,
            ));
          }
        } catch (_) {
          // Skip courses with no attachment endpoint
        }
      }
      return allAttachments;
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }
}
