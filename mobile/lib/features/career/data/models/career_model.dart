class UserCvModel {
  final int id;
  final int userId;
  final String filePath;
  final String fileName;
  final String? parsedText;
  final List<String> skills;
  final String createdAt;

  UserCvModel({
    required this.id,
    required this.userId,
    required this.filePath,
    required this.fileName,
    this.parsedText,
    required this.skills,
    required this.createdAt,
  });

  factory UserCvModel.fromJson(Map<String, dynamic> json) {
    return UserCvModel(
      id: json['id'] as int? ?? 0,
      userId: json['user_id'] as int? ?? 0,
      filePath: json['file_path']?.toString() ?? '',
      fileName: json['file_name']?.toString() ?? '',
      parsedText: json['parsed_text']?.toString(),
      skills: (json['skills'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? [],
      createdAt: json['created_at']?.toString() ?? '',
    );
  }
}

class ExpertAnalysisModel {
  final String overview;
  final List<String> strengths;
  final List<String> weaknesses;
  final List<String> cvAdditions;
  final List<String> cvImprovements;
  final List<String> learningPriorities;

  ExpertAnalysisModel({
    required this.overview,
    required this.strengths,
    required this.weaknesses,
    required this.cvAdditions,
    required this.cvImprovements,
    required this.learningPriorities,
  });

  factory ExpertAnalysisModel.fromJson(Map<String, dynamic> json) {
    return ExpertAnalysisModel(
      overview: json['overview']?.toString() ?? '',
      strengths: (json['strengths'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? [],
      weaknesses: (json['weaknesses'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? [],
      cvAdditions: (json['cv_additions'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? [],
      cvImprovements: (json['cv_improvements'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? [],
      learningPriorities: (json['learning_priorities'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? [],
    );
  }
}

class CareerRecommendationCourseModel {
  final int id;
  final String title;
  final String? description;
  final String? thumbnail;
  final int price;
  final String courseMode;
  final int? creditValue;
  final double avgRating;
  final String? recommendationReason;

  CareerRecommendationCourseModel({
    required this.id,
    required this.title,
    this.description,
    this.thumbnail,
    required this.price,
    required this.courseMode,
    this.creditValue,
    required this.avgRating,
    this.recommendationReason,
  });

  factory CareerRecommendationCourseModel.fromJson(Map<String, dynamic> json) {
    return CareerRecommendationCourseModel(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
      description: json['description']?.toString(),
      thumbnail: json['thumbnail']?.toString(),
      price: json['price'] as int? ?? 0,
      courseMode: json['course_mode']?.toString() ?? 'online',
      creditValue: json['credit_value'] as int?,
      avgRating: (json['avg_rating'] as num?)?.toDouble() ?? 0.0,
      recommendationReason: json['recommendation_reason']?.toString(),
    );
  }
}

class CareerRecommendationModel {
  final int id;
  final int userId;
  final int matchScore;
  final List<String> skillGaps;
  final List<int> suggestedCourses;
  final String aiSummary;
  final String createdAt;
  final ExpertAnalysisModel expertAnalysis;
  final List<CareerRecommendationCourseModel> suggestedCoursesData;

  CareerRecommendationModel({
    required this.id,
    required this.userId,
    required this.matchScore,
    required this.skillGaps,
    required this.suggestedCourses,
    required this.aiSummary,
    required this.createdAt,
    required this.expertAnalysis,
    required this.suggestedCoursesData,
  });

  factory CareerRecommendationModel.fromJson(Map<String, dynamic> json) {
    final rawCourses = json['suggested_courses'] as List<dynamic>? ?? [];
    final parsedCourses = rawCourses.map((e) {
      if (e is int) return e;
      return int.tryParse(e.toString()) ?? 0;
    }).where((e) => e > 0).toList();

    return CareerRecommendationModel(
      id: json['id'] as int? ?? 0,
      userId: json['user_id'] as int? ?? 0,
      matchScore: json['match_score'] as int? ?? 0,
      skillGaps: (json['skill_gaps'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? [],
      suggestedCourses: parsedCourses,
      aiSummary: json['ai_summary']?.toString() ?? '',
      createdAt: json['created_at']?.toString() ?? '',
      expertAnalysis: ExpertAnalysisModel.fromJson(json['expert_analysis'] as Map<String, dynamic>? ?? {}),
      suggestedCoursesData: (json['suggested_courses_data'] as List<dynamic>?)
              ?.map((e) => CareerRecommendationCourseModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }
}

class CareerAdvisorStatusModel {
  final UserCvModel? cv;
  final List<CareerRecommendationModel> recommendations;

  CareerAdvisorStatusModel({
    this.cv,
    required this.recommendations,
  });

  factory CareerAdvisorStatusModel.fromJson(Map<String, dynamic> json) {
    return CareerAdvisorStatusModel(
      cv: json['cv'] != null ? UserCvModel.fromJson(json['cv'] as Map<String, dynamic>) : null,
      recommendations: (json['recommendations'] as List<dynamic>?)
              ?.map((e) => CareerRecommendationModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }
}
