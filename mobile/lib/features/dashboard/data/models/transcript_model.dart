class GradeEntryModel {
  final String? component;
  final double? weight;
  final double? maxScore;
  final double? score;

  GradeEntryModel({this.component, this.weight, this.maxScore, this.score});

  factory GradeEntryModel.fromJson(Map<String, dynamic> json) {
    return GradeEntryModel(
      component: json['component']?.toString(),
      weight: (json['weight'] as num?)?.toDouble(),
      maxScore: (json['max_score'] as num?)?.toDouble(),
      score: (json['score'] as num?)?.toDouble(),
    );
  }
}

class TranscriptCourseModel {
  final int enrollmentId;
  final String title;
  final int? creditValue;
  final String? courseMode;
  final double? finalScore;
  final List<GradeEntryModel> entries;

  TranscriptCourseModel({
    required this.enrollmentId,
    required this.title,
    this.creditValue,
    this.courseMode,
    this.finalScore,
    this.entries = const [],
  });

  factory TranscriptCourseModel.fromJson(Map<String, dynamic> json) {
    final course = json['course'] as Map<String, dynamic>? ?? {};
    return TranscriptCourseModel(
      enrollmentId: json['enrollment_id'] as int? ?? 0,
      title: course['title']?.toString() ?? '',
      creditValue: course['credit_value'] as int?,
      courseMode: course['course_mode']?.toString(),
      finalScore: (json['final_score'] as num?)?.toDouble(),
      entries: (json['entries'] as List<dynamic>?)
              ?.map((e) => GradeEntryModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }
}

class TranscriptTermModel {
  final int id;
  final String name;
  final String code;
  final double? gpa;
  final int credits;
  final List<TranscriptCourseModel> courses;

  TranscriptTermModel({
    required this.id,
    required this.name,
    required this.code,
    this.gpa,
    required this.credits,
    required this.courses,
  });

  factory TranscriptTermModel.fromJson(Map<String, dynamic> json) {
    final term = json['term'] as Map<String, dynamic>? ?? {};
    return TranscriptTermModel(
      id: term['id'] as int? ?? 0,
      name: term['name']?.toString() ?? '',
      code: term['code']?.toString() ?? '',
      gpa: (json['gpa'] as num?)?.toDouble(),
      credits: json['credits'] as int? ?? 0,
      courses: (json['courses'] as List<dynamic>?)
              ?.map((e) => TranscriptCourseModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }
}

class TranscriptModel {
  final List<TranscriptTermModel> terms;
  final double? overallGpa;

  TranscriptModel({
    required this.terms,
    this.overallGpa,
  });

  factory TranscriptModel.fromJson(Map<String, dynamic> json) {
    return TranscriptModel(
      terms: (json['terms'] as List<dynamic>?)
              ?.map((e) => TranscriptTermModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      overallGpa: (json['overall_gpa'] as num?)?.toDouble(),
    );
  }
}
