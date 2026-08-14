import '../../../courses/data/models/course_model.dart';

class CourseRecommendationItem {
  const CourseRecommendationItem({
    required this.course,
    required this.score,
    this.matchedSkills = const [],
    this.reasons = const [],
    this.source,
  });

  final CourseListItemModel course;
  final int score;
  final List<String> matchedSkills;
  final List<String> reasons;
  final String? source;

  factory CourseRecommendationItem.fromJson(Map<String, dynamic> json) {
    final courseJson = json['course'] is Map
        ? Map<String, dynamic>.from(json['course'] as Map)
        : json;
    return CourseRecommendationItem(
      course: CourseListItemModel.fromJson(courseJson),
      score: (json['score'] as num?)?.toInt() ?? 0,
      matchedSkills: (json['matched_skills'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
      reasons: (json['reasons'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
      source: json['source']?.toString(),
    );
  }
}

class RecommendationsBundle {
  const RecommendationsBundle({
    required this.items,
    this.profileSparse = false,
    this.fallback,
  });

  final List<CourseRecommendationItem> items;
  final bool profileSparse;
  final String? fallback;

  bool get usesCurriculumFallback =>
      profileSparse && fallback == 'curriculum_standard';
}

class StudyAdvisorAdvice {
  const StudyAdvisorAdvice({
    this.narrative = '',
    this.studyTips = const [],
    this.source = 'rule',
    this.explanationUnavailable = false,
  });

  final String narrative;
  final List<String> studyTips;
  final String source;
  final bool explanationUnavailable;

  factory StudyAdvisorAdvice.fromJson(Map<String, dynamic> json) {
    return StudyAdvisorAdvice(
      narrative: json['narrative']?.toString() ?? '',
      studyTips: (json['study_tips'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
      source: json['source']?.toString() ?? 'rule',
      explanationUnavailable: json['explanation_unavailable'] == true,
    );
  }
}

class AiChatMessage {
  const AiChatMessage({required this.role, required this.text});

  final String role;
  final String text;

  Map<String, dynamic> toJson() => {'role': role, 'text': text};

  factory AiChatMessage.fromJson(Map<String, dynamic> json) {
    return AiChatMessage(
      role: json['role']?.toString() ?? 'assistant',
      text: json['text']?.toString() ?? json['content']?.toString() ?? '',
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
