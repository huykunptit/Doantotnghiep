import '../../../courses/data/models/course_model.dart';

class CourseRecommendationItem {
  const CourseRecommendationItem({
    required this.course,
    required this.score,
    this.matchedSkills = const [],
  });

  final CourseListItemModel course;
  final int score;
  final List<String> matchedSkills;

  factory CourseRecommendationItem.fromJson(Map<String, dynamic> json) {
    final courseJson = json['course'] as Map<String, dynamic>? ?? json;
    return CourseRecommendationItem(
      course: CourseListItemModel.fromJson(courseJson),
      score: (json['score'] as num?)?.toInt() ?? 0,
      matchedSkills: (json['matched_skills'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
    );
  }
}

class TutoringTipModel {
  const TutoringTipModel({
    this.summary = '',
    this.studyTips = const [],
    this.reviewLessons = const [],
    this.source = 'heuristic',
  });

  final String summary;
  final List<String> studyTips;
  final List<String> reviewLessons;
  final String source;

  factory TutoringTipModel.fromJson(Map<String, dynamic> json) {
    return TutoringTipModel(
      summary: json['summary']?.toString() ?? '',
      studyTips: (json['study_tips'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
      reviewLessons: (json['review_lessons'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
      source: json['source']?.toString() ?? 'heuristic',
    );
  }
}

class AiChatReply {
  const AiChatReply({required this.reply});

  final String reply;

  factory AiChatReply.fromJson(Map<String, dynamic> json) {
    return AiChatReply(
      reply: json['reply']?.toString() ??
          json['message']?.toString() ??
          'Không có phản hồi.',
    );
  }
}
