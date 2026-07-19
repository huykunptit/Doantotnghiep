import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../data/models/course_model.dart';
import '../data/repositories/course_repository.dart';

part 'course_detail_provider.g.dart';

@riverpod
Future<CourseDetailModel> courseDetail(CourseDetailRef ref, int courseId) =>
    ref.read(courseRepositoryProvider).getCourseDetail(courseId);
