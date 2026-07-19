import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../data/models/course_model.dart';
import '../data/models/category_model.dart';
import '../data/repositories/course_repository.dart';

part 'course_catalog_provider.g.dart';

@riverpod
Future<List<CourseListItemModel>> courseCatalog(
  Ref ref, {
  String? search,
  int? categoryId,
}) {
  return ref
      .read(courseRepositoryProvider)
      .getCourses(search: search, categoryId: categoryId);
}

@riverpod
Future<List<CategoryModel>> courseCategories(CourseCategoriesRef ref) {
  return ref.read(courseRepositoryProvider).getCategories();
}
