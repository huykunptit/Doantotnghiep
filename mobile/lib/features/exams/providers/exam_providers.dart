import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../data/models/exam_list_model.dart';
import '../data/repositories/exam_list_repository.dart';

part 'exam_providers.g.dart';

@riverpod
Future<List<ExamListItemModel>> myExams(MyExamsRef ref, {String tab = ''}) async {
  return ref.read(examListRepositoryProvider).getMyExams(tab: tab);
}

@riverpod
Future<ExamResultDetailModel> examAttemptResult(ExamAttemptResultRef ref, int attemptId) async {
  return ref.read(examListRepositoryProvider).getAttemptResult(attemptId);
}

@riverpod
Future<List<OrderModel>> myOrders(MyOrdersRef ref) async {
  return ref.read(examListRepositoryProvider).getMyOrders();
}
