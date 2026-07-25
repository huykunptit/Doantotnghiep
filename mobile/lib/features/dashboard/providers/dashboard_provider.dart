import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../data/models/dashboard_model.dart';
import '../data/models/transcript_model.dart';
import '../data/models/learning_path_model.dart';
import '../data/models/attendance_model.dart';
import '../data/models/portal_models.dart';
import '../data/repositories/dashboard_repository.dart';

part 'dashboard_provider.g.dart';

@riverpod
Future<DashboardModel> studentDashboard(StudentDashboardRef ref) {
  return ref.read(dashboardRepositoryProvider).getDashboard();
}

@riverpod
Future<TranscriptModel> studentTranscript(StudentTranscriptRef ref) {
  return ref.read(dashboardRepositoryProvider).getTranscript();
}

@riverpod
Future<TimetableModel> studentTimetable(StudentTimetableRef ref) {
  return ref.read(dashboardRepositoryProvider).getTimetable();
}

@riverpod
Future<TuitionListModel> studentTuition(StudentTuitionRef ref) {
  return ref.read(dashboardRepositoryProvider).getTuition();
}

@riverpod
Future<Map<String, dynamic>> studentCurriculumEvaluation(StudentCurriculumEvaluationRef ref) {
  return ref.read(dashboardRepositoryProvider).getCurriculumEvaluation();
}

@riverpod
Future<LearningPathModel> studentLearningPath(StudentLearningPathRef ref) {
  return ref.read(dashboardRepositoryProvider).getLearningPath();
}

@riverpod
Future<List<AttendanceHistoryItemModel>> studentAttendanceHistory(StudentAttendanceHistoryRef ref) {
  return ref.read(dashboardRepositoryProvider).getAttendanceHistory();
}
