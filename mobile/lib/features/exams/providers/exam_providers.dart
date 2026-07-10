import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../data/models/exam_list_model.dart';
import '../data/repositories/exam_list_repository.dart';

part 'exam_providers.g.dart';

@riverpod
Future<List<ExamListItemModel>> myExams(Ref ref, {String tab = ''}) async {
  return ref.read(examListRepositoryProvider).getMyExams(tab: tab);
}

@riverpod
Future<ExamResultDetailModel> examAttemptResult(Ref ref, int attemptId) async {
  return ref.read(examListRepositoryProvider).getAttemptResult(attemptId);
}

@riverpod
Future<List<OrderModel>> myOrders(Ref ref) async {
  return ref.read(examListRepositoryProvider).getMyOrders();
}
