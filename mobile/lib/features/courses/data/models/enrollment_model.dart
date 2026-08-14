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

enum CourseWindow { current, upcoming, expired }

class EnrollmentModel {
  const EnrollmentModel({
    required this.id,
    required this.courseId,
    required this.course,
    this.enrolledAt,
    this.enrollmentSource,
    this.progress = 0.0,
    this.startsAt,
    this.endsAt,
    this.windowStatus,
    this.isPlanned = false,
    this.termName,
  });

  final int id;
  final int courseId;
  final CourseSimpleModel course;
  final String? enrolledAt;
  final String? enrollmentSource;
  final double progress;
  final String? startsAt;
  final String? endsAt;
  final String? windowStatus;
  final bool isPlanned;
  final String? termName;

  factory EnrollmentModel.fromJson(Map<String, dynamic> json) {
    final course = CourseSimpleModel.fromJson(
      json['course'] as Map<String, dynamic>? ?? {},
    );
    final term = json['term'] as Map<String, dynamic>?;
    return EnrollmentModel(
      id: json['id'] as int? ?? 0,
      courseId: json['course_id'] as int? ?? course.id,
      course: course,
      enrolledAt: json['enrolled_at']?.toString(),
      enrollmentSource: json['enrollment_source']?.toString(),
      progress: (json['progress'] as num?)?.toDouble() ?? 0.0,
      startsAt: json['starts_at']?.toString() ?? term?['start_date']?.toString(),
      endsAt: json['ends_at']?.toString() ?? term?['end_date']?.toString(),
      windowStatus: json['window_status']?.toString(),
      isPlanned: json['is_planned'] == true,
      termName: (term?['display_name'] ?? term?['name'])?.toString(),
    );
  }

  CourseWindow get window {
    switch (windowStatus) {
      case 'expired':
        return CourseWindow.expired;
      case 'upcoming':
        return CourseWindow.upcoming;
      case 'current':
        return CourseWindow.current;
    }
    final today = DateTime.now();
    final start = _parseDay(startsAt);
    final end = _parseDay(endsAt);
    if (start == null && end == null) return CourseWindow.current;
    final day = DateTime(today.year, today.month, today.day);
    if (end != null && end.isBefore(day)) return CourseWindow.expired;
    if (start != null && start.isAfter(day)) return CourseWindow.upcoming;
    return CourseWindow.current;
  }
}

DateTime? _parseDay(String? value) {
  if (value == null || value.isEmpty) return null;
  final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})').firstMatch(value);
  if (match == null) return DateTime.tryParse(value);
  return DateTime(
    int.parse(match.group(1)!),
    int.parse(match.group(2)!),
    int.parse(match.group(3)!),
  );
}
