import '../../../../core/utils/media_url.dart';

class PathCourseInfoModel {
  const PathCourseInfoModel({
    required this.id,
    required this.title,
    this.thumbnail,
    this.price = 0,
    this.lessonsCount = 0,
  });

  final int id;
  final String title;
  final String? thumbnail;
  final int price;
  final int lessonsCount;

  factory PathCourseInfoModel.fromJson(Map<String, dynamic> json) {
    return PathCourseInfoModel(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
      thumbnail: resolveMediaUrl(json['thumbnail']?.toString()),
      price: json['price'] as int? ?? 0,
      lessonsCount: json['lessons_count'] as int? ?? 0,
    );
  }
}

class PathCourseModel {
  const PathCourseModel({
    required this.id,
    required this.courseId,
    required this.sortOrder,
    this.isRequired = true,
    this.milestoneLabel,
    this.course,
  });

  final int id;
  final int courseId;
  final int sortOrder;
  final bool isRequired;
  final String? milestoneLabel;
  final PathCourseInfoModel? course;

  factory PathCourseModel.fromJson(Map<String, dynamic> json) {
    return PathCourseModel(
      id: json['id'] as int? ?? 0,
      courseId: json['course_id'] as int? ?? 0,
      sortOrder: json['sort_order'] as int? ?? 0,
      isRequired: json['is_required'] as bool? ?? true,
      milestoneLabel: json['milestone_label']?.toString(),
      course: json['course'] != null
          ? PathCourseInfoModel.fromJson(json['course'] as Map<String, dynamic>)
          : null,
    );
  }
}

class CareerPathListItem {
  const CareerPathListItem({
    required this.id,
    required this.title,
    required this.slug,
    this.description,
    this.price = 0,
    this.coverUrl,
    this.pathCoursesCount = 0,
  });

  final int id;
  final String title;
  final String slug;
  final String? description;
  final int price;
  final String? coverUrl;
  final int pathCoursesCount;

  factory CareerPathListItem.fromJson(Map<String, dynamic> json) {
    return CareerPathListItem(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
      slug: json['slug']?.toString() ?? '',
      description: json['description']?.toString(),
      price: json['price'] as int? ?? 0,
      coverUrl: resolveMediaUrl(json['cover_url']?.toString()),
      pathCoursesCount: json['path_courses_count'] as int? ?? 0,
    );
  }
}

class CareerPathDetail {
  const CareerPathDetail({
    required this.id,
    required this.title,
    required this.slug,
    this.description,
    this.price = 0,
    this.coverUrl,
    this.pathCoursesCount = 0,
    this.isPurchased = false,
    this.isFollowing = false,
    this.pathCourses = const [],
    this.enrolledCourseIds = const [],
  });

  final int id;
  final String title;
  final String slug;
  final String? description;
  final int price;
  final String? coverUrl;
  final int pathCoursesCount;
  final bool isPurchased;
  final bool isFollowing;
  final List<PathCourseModel> pathCourses;
  final List<int> enrolledCourseIds;

  factory CareerPathDetail.fromJson(Map<String, dynamic> json) {
    return CareerPathDetail(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
      slug: json['slug']?.toString() ?? '',
      description: json['description']?.toString(),
      price: json['price'] as int? ?? 0,
      coverUrl: resolveMediaUrl(json['cover_url']?.toString()),
      pathCoursesCount: json['path_courses_count'] as int? ?? 0,
      isPurchased: json['is_purchased'] as bool? ?? false,
      isFollowing: json['is_following'] as bool? ?? false,
      pathCourses: (json['path_courses'] as List<dynamic>?)
              ?.map((e) => PathCourseModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      enrolledCourseIds: (json['enrolled_course_ids'] as List<dynamic>?)
              ?.map((e) => e as int)
              .toList() ??
          [],
    );
  }
}
