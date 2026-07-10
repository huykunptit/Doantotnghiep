import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../data/models/student_models.dart';
import '../data/repositories/student_repository.dart';

part 'student_providers.g.dart';

@riverpod
Future<List<TaskModel>> myTasks(Ref ref, {bool? done}) async {
  return ref.read(studentRepositoryProvider).getMyTasks(done: done);
}

@riverpod
Future<List<CalendarExamModel>> examSchedule(Ref ref) async {
  return ref.read(studentRepositoryProvider).getMyExamSchedule();
}

@riverpod
Future<List<LibraryAttachmentModel>> myLibrary(Ref ref) async {
  return ref.read(studentRepositoryProvider).getMyLibrary();
}
