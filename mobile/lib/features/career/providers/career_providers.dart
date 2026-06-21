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

  Future<CareerRecommendationModel> requestRecommendation(String jobTitle) async {
    state = const AsyncValue.loading();
    try {
      final recommendation = await ref.read(careerRepositoryProvider).getRecommendation(jobTitle);
      // Reload status to get the updated list of recommendations
      final updatedStatus = await ref.read(careerRepositoryProvider).getAdvisorStatus();
      state = AsyncValue.data(updatedStatus);
      return recommendation;
    } catch (e, stack) {
      // Re-fetch current status so we don't leave the UI in error/loading state
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
