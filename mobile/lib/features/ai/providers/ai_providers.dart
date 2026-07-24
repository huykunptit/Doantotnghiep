import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../data/models/ai_models.dart';
import '../data/repositories/ai_repository.dart';

final recommendationsProvider =
    FutureProvider.autoDispose<List<CourseRecommendationItem>>((ref) {
  return ref.watch(aiRepositoryProvider).getRecommendations();
});

final tutoringTipsProvider = FutureProvider.autoDispose
    .family<TutoringTipModel, TutoringTipQuery>((ref, query) {
  return ref.watch(aiRepositoryProvider).getTutoringTips(
        courseId: query.courseId,
        lessonId: query.lessonId,
        lessonTitle: query.lessonTitle,
        lessonType: query.lessonType,
        progressPercent: query.progressPercent,
      );
});

class TutoringTipQuery {
  const TutoringTipQuery({
    required this.courseId,
    this.lessonId,
    this.lessonTitle,
    this.lessonType,
    this.progressPercent,
  });

  final int courseId;
  final int? lessonId;
  final String? lessonTitle;
  final String? lessonType;
  final double? progressPercent;

  @override
  bool operator ==(Object other) {
    return other is TutoringTipQuery &&
        other.courseId == courseId &&
        other.lessonId == lessonId &&
        other.lessonTitle == lessonTitle &&
        other.lessonType == lessonType &&
        other.progressPercent == progressPercent;
  }

  @override
  int get hashCode => Object.hash(
        courseId,
        lessonId,
        lessonTitle,
        lessonType,
        progressPercent,
      );
}
