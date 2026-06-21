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
    return InstructorModel(
      id: json['id'] as int? ?? 0,
      name: json['name']?.toString() ?? '',
      avatar: json['avatar']?.toString(),
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
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
      order: json['order'] as int? ?? 0,
      duration: json['duration'] as int?,
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
    return CourseDetailModel(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
      description: json['description']?.toString(),
      thumbnail: json['thumbnail']?.toString(),
      price: json['price'] as int? ?? 0,
      courseMode: json['course_mode']?.toString() ?? 'online',
      isCreditBearing: json['is_credit_bearing'] as bool? ?? false,
      creditValue: json['credit_value'] as int?,
      lessonsCount: json['lessons_count'] as int? ?? 0,
      enrollmentsCount: json['enrollments_count'] as int? ?? 0,
      avgRating: (json['avg_rating'] as num?)?.toDouble() ?? 0.0,
      isEnrolled: json['is_enrolled'] as bool? ?? false,
      instructor: json['instructor'] != null
          ? InstructorModel.fromJson(json['instructor'] as Map<String, dynamic>)
          : null,
      lessons: (json['lessons'] as List<dynamic>?)
              ?.map((e) => LessonSummaryModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
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
    this.avgRating = 0.0,
    this.instructor,
  });

  factory CourseListItemModel.fromJson(Map<String, dynamic> json) {
    return CourseListItemModel(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
      thumbnail: json['thumbnail']?.toString(),
      price: json['price'] as int? ?? 0,
      courseMode: json['course_mode']?.toString() ?? 'online',
      creditValue: json['credit_value'] as int?,
      lessonsCount: json['lessons_count'] as int? ?? 0,
      enrollmentsCount: json['enrollments_count'] as int? ?? 0,
      avgRating: (json['avg_rating'] as num?)?.toDouble() ?? 0.0,
      instructor: json['instructor'] != null
          ? InstructorModel.fromJson(json['instructor'] as Map<String, dynamic>)
          : null,
    );
  }
}
