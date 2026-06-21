class CourseSimpleModel {
  const CourseSimpleModel({
    required this.id,
    required this.title,
    this.thumbnail,
    this.courseMode = 'online',
    this.creditValue,
  });

  final int id;
  final String title;
  final String? thumbnail;
  final String courseMode;
  final int? creditValue;

  factory CourseSimpleModel.fromJson(Map<String, dynamic> json) {
    return CourseSimpleModel(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
      thumbnail: json['thumbnail']?.toString(),
      courseMode: json['course_mode']?.toString() ?? 'online',
      creditValue: json['credit_value'] as int?,
    );
  }
}

class EnrollmentModel {
  const EnrollmentModel({
    required this.id,
    required this.courseId,
    required this.course,
    this.enrolledAt,
    this.enrollmentSource,
    this.progress = 0.0,
  });

  final int id;
  final int courseId;
  final CourseSimpleModel course;
  final String? enrolledAt;
  final String? enrollmentSource;
  final double progress;

  factory EnrollmentModel.fromJson(Map<String, dynamic> json) {
    return EnrollmentModel(
      id: json['id'] as int? ?? 0,
      courseId: json['course_id'] as int? ?? 0,
      course: CourseSimpleModel.fromJson(
        json['course'] as Map<String, dynamic>? ?? {},
      ),
      enrolledAt: json['enrolled_at']?.toString(),
      enrollmentSource: json['enrollment_source']?.toString(),
      progress: (json['progress'] as num?)?.toDouble() ?? 0.0,
    );
  }
}
