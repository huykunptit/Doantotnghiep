import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../data/models/enrollment_model.dart';
import '../data/repositories/course_repository.dart';

part 'my_courses_provider.g.dart';

@riverpod
Future<List<EnrollmentModel>> myEnrollments(MyEnrollmentsRef ref) =>
    ref.read(courseRepositoryProvider).getEnrollments();
