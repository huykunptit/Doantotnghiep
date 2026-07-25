import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../data/models/career_model.dart';
import '../data/repositories/career_repository.dart';

part 'career_providers.g.dart';

@riverpod
class CareerAdvisorNotifier extends _$CareerAdvisorNotifier {
  @override
  Future<CareerAdvisorStatusModel> build() {
    return ref.read(careerRepositoryProvider).getAdvisorStatus();
  }

  Future<void> uploadCV(String filePath, String fileName) async {
    state = const AsyncValue.loading();
    try {
      final result = await ref.read(careerRepositoryProvider).uploadCV(filePath, fileName);
      state = AsyncValue.data(result);
    } catch (e, stack) {
      state = AsyncValue.error(e, stack);
      rethrow;
    }
  }

  Future<CareerEvaluateResult> saveCvForm(Map<String, dynamic> payload) async {
    final result = await ref.read(careerRepositoryProvider).saveCvForm(payload);
    final updatedStatus = await ref.read(careerRepositoryProvider).getAdvisorStatus();
    state = AsyncValue.data(updatedStatus);
    return result;
  }

  Future<CareerEvaluateResult> evaluate({
    String? targetRole,
    int? expectedSalary,
  }) async {
    final result = await ref.read(careerRepositoryProvider).evaluate(
          targetRole: targetRole,
          expectedSalary: expectedSalary,
        );
    final updatedStatus = await ref.read(careerRepositoryProvider).getAdvisorStatus();
    state = AsyncValue.data(updatedStatus);
    return result;
  }

  Future<CareerRecommendationModel> requestRecommendation(
    String jobTitle, {
    int? expectedSalary,
  }) async {
    state = const AsyncValue.loading();
    try {
      final recommendation = await ref.read(careerRepositoryProvider).getRecommendation(
            jobTitle,
            expectedSalary: expectedSalary,
          );
      final updatedStatus = await ref.read(careerRepositoryProvider).getAdvisorStatus();
      state = AsyncValue.data(updatedStatus);
      return recommendation;
    } catch (e, stack) {
      try {
        final currentStatus = await ref.read(careerRepositoryProvider).getAdvisorStatus();
        state = AsyncValue.data(currentStatus);
      } catch (_) {
        state = AsyncValue.error(e, stack);
      }
      rethrow;
    }
  }
}
