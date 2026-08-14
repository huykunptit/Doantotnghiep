import '../../../../core/utils/media_url.dart';

class InstructorModel {
  const InstructorModel({
    required this.id,
    required this.name,
    this.avatar,
  });

  final int id;
  final String name;
  final String? avatar;

  factory InstructorModel.fromJson(Map<String, dynamic> json) {
    final avatar = json['avatar']?.toString().trim();
    return InstructorModel(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: json['name']?.toString() ?? '',
      avatar: resolveMediaUrl((avatar == null || avatar.isEmpty) ? null : avatar),
    );
  }
}

class LessonSummaryModel {
  const LessonSummaryModel({
    required this.id,
    required this.title,
    required this.order,
    this.duration,
  });

  final int id;
  final String title;
  final int order;
  final int? duration;

  factory LessonSummaryModel.fromJson(Map<String, dynamic> json) {
    return LessonSummaryModel(
      id: (json['id'] as num?)?.toInt() ?? 0,
      title: json['title']?.toString() ?? '',
      order: (json['order'] as num?)?.toInt() ?? 0,
      duration: (json['duration'] as num?)?.toInt(),
    );
  }
}

class CourseDetailModel {
  const CourseDetailModel({
    required this.id,
    required this.title,
    this.description,
    this.thumbnail,
    this.price = 0,
    this.courseMode = 'online',
    this.isCreditBearing = false,
    this.creditValue,
    this.lessonsCount = 0,
    this.enrollmentsCount = 0,
    this.avgRating = 0.0,
    this.isEnrolled = false,
    this.instructor,
    this.lessons = const [],
  });

  final int id;
  final String title;
  final String? description;
  final String? thumbnail;
  final int price;
  final String courseMode;
  final bool isCreditBearing;
  final int? creditValue;
  final int lessonsCount;
  final int enrollmentsCount;
  final double avgRating;
  final bool isEnrolled;
  final InstructorModel? instructor;
  final List<LessonSummaryModel> lessons;

  factory CourseDetailModel.fromJson(Map<String, dynamic> json) {
    final lessons = _parseLessons(json);
    final counted = (json['lessons_count'] as num?)?.toInt() ?? 0;
    return CourseDetailModel(
      id: (json['id'] as num?)?.toInt() ?? 0,
      title: json['title']?.toString() ?? '',
      description: json['description']?.toString(),
      thumbnail: resolveMediaUrl(json['thumbnail']?.toString()),
      price: (json['price'] as num?)?.toInt() ?? 0,
      courseMode: json['course_mode']?.toString() ?? 'online',
      isCreditBearing: json['is_credit_bearing'] as bool? ?? false,
      creditValue: (json['credit_value'] as num?)?.toInt(),
      lessonsCount: counted > 0 ? counted : lessons.length,
      enrollmentsCount: (json['enrollments_count'] as num?)?.toInt() ?? 0,
      avgRating: (json['avg_rating'] as num?)?.toDouble() ??
          (json['reviews_avg_rating'] as num?)?.toDouble() ??
          0.0,
      isEnrolled: json['is_enrolled'] as bool? ?? false,
      instructor: json['instructor'] is Map
          ? InstructorModel.fromJson(Map<String, dynamic>.from(json['instructor'] as Map))
          : null,
      lessons: lessons,
    );
  }

  static List<LessonSummaryModel> _parseLessons(Map<String, dynamic> json) {
    final fromRoot = (json['lessons'] as List<dynamic>?)
            ?.whereType<Map>()
            .map((e) => LessonSummaryModel.fromJson(Map<String, dynamic>.from(e)))
            .toList() ??
        [];
    if (fromRoot.isNotEmpty) return fromRoot;

    final fromSections = <LessonSummaryModel>[];
    for (final section in (json['sections'] as List<dynamic>?) ?? const []) {
      if (section is! Map) continue;
      final nested = section['lessons'];
      if (nested is! List) continue;
      for (final lesson in nested) {
        if (lesson is Map) {
          fromSections.add(
            LessonSummaryModel.fromJson(Map<String, dynamic>.from(lesson)),
          );
        }
      }
    }
    return fromSections;
  }
}

class CourseListItemModel {
  final int id;
  final String title;
  final String? thumbnail;
  final int price;
  final String courseMode;
  final int? creditValue;
  final int lessonsCount;
  final int enrollmentsCount;
  final int reviewsCount;
  final double avgRating;
  final InstructorModel? instructor;

  CourseListItemModel({
    required this.id,
    required this.title,
    this.thumbnail,
    this.price = 0,
    this.courseMode = 'online',
    this.creditValue,
    this.lessonsCount = 0,
    this.enrollmentsCount = 0,
    this.reviewsCount = 0,
    this.avgRating = 0.0,
    this.instructor,
  });

  factory CourseListItemModel.fromJson(Map<String, dynamic> json) {
    return CourseListItemModel(
      id: (json['id'] as num?)?.toInt() ?? 0,
      title: json['title']?.toString() ?? '',
      thumbnail: resolveMediaUrl(json['thumbnail']?.toString()),
      price: (json['price'] as num?)?.toInt() ?? 0,
      courseMode: json['course_mode']?.toString() ?? 'online',
      creditValue: (json['credit_value'] as num?)?.toInt(),
      lessonsCount: (json['lessons_count'] as num?)?.toInt() ?? 0,
      enrollmentsCount: (json['enrollments_count'] as num?)?.toInt() ?? 0,
      reviewsCount: (json['reviews_count'] as num?)?.toInt() ?? 0,
      avgRating: (json['avg_rating'] as num?)?.toDouble() ??
          (json['reviews_avg_rating'] as num?)?.toDouble() ??
          0.0,
      instructor: json['instructor'] is Map
          ? InstructorModel.fromJson(Map<String, dynamic>.from(json['instructor'] as Map))
          : null,
    );
  }
}
