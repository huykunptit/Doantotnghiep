class LearningPathCourseModel {
  final int id;
  final String title;
  final String? slug;
  final String? thumbnail;
  final int credits;
  final bool isRequired;
  final String? courseMode;
  final String status;
  final double progress;
  final double? finalScore;

  LearningPathCourseModel({
    required this.id,
    required this.title,
    this.slug,
    this.thumbnail,
    required this.credits,
    required this.isRequired,
    this.courseMode,
    required this.status,
    required this.progress,
    this.finalScore,
  });

  factory LearningPathCourseModel.fromJson(Map<String, dynamic> json) {
    return LearningPathCourseModel(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
      slug: json['slug']?.toString(),
      thumbnail: json['thumbnail']?.toString(),
      credits: json['credits'] as int? ?? 0,
      isRequired: json['is_required'] as bool? ?? false,
      courseMode: json['course_mode']?.toString(),
      status: json['status']?.toString() ?? 'not_started',
      progress: (json['progress'] as num?)?.toDouble() ?? 0.0,
      finalScore: (json['final_score'] as num?)?.toDouble(),
    );
  }
}

class LearningPathTermModel {
  final int termNumber;
  final int credits;
  final List<LearningPathCourseModel> courses;

  LearningPathTermModel({
    required this.termNumber,
    required this.credits,
    required this.courses,
  });

  factory LearningPathTermModel.fromJson(Map<String, dynamic> json) {
    return LearningPathTermModel(
      termNumber: json['term_number'] as int? ?? 0,
      credits: json['credits'] as int? ?? 0,
      courses: (json['courses'] as List<dynamic>?)
              ?.map((e) => LearningPathCourseModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }
}

class LearningPathModel {
  final bool hasCurriculum;
  final String? curriculumName;
  final String? curriculumCode;
  final int totalCreditsRequired;
  final int totalCreditsEarned;
  final List<LearningPathTermModel> terms;

  LearningPathModel({
    required this.hasCurriculum,
    this.curriculumName,
    this.curriculumCode,
    required this.totalCreditsRequired,
    required this.totalCreditsEarned,
    required this.terms,
  });

  factory LearningPathModel.fromJson(Map<String, dynamic> json) {
    return LearningPathModel(
      hasCurriculum: json['has_curriculum'] as bool? ?? false,
      curriculumName: json['curriculum_name']?.toString(),
      curriculumCode: json['curriculum_code']?.toString(),
      totalCreditsRequired: json['total_credits_required'] as int? ?? 0,
      totalCreditsEarned: json['total_credits_earned'] as int? ?? 0,
      terms: (json['terms'] as List<dynamic>?)
              ?.map((e) => LearningPathTermModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }
}
