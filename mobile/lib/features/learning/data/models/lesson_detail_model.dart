class LessonDetailModel {
  final int id;
  final int courseId;
  final int? sectionId;
  final String title;
  final String? description;
  final String? videoUrl;
  final String? videoStatus;
  final int duration; // in seconds
  final String type; // e.g. 'video', 'file', 'page', 'quiz'
  final bool isCompleted;
  final int watchedSeconds;

  LessonDetailModel({
    required this.id,
    required this.courseId,
    this.sectionId,
    required this.title,
    this.description,
    this.videoUrl,
    this.videoStatus,
    this.duration = 0,
    required this.type,
    this.isCompleted = false,
    this.watchedSeconds = 0,
  });

  factory LessonDetailModel.fromJson(Map<String, dynamic> json) {
    final progress = json['progress'] as Map<String, dynamic>? ?? json['user_progress'] as Map<String, dynamic>?;
    return LessonDetailModel(
      id: json['id'] as int? ?? 0,
      courseId: json['course_id'] as int? ?? 0,
      sectionId: json['section_id'] as int?,
      title: json['title']?.toString() ?? '',
      description: json['description']?.toString(),
      videoUrl: json['video_url']?.toString() ?? json['video_path']?.toString(),
      videoStatus: json['video_status']?.toString(),
      duration: json['duration'] as int? ?? 0,
      type: json['type']?.toString() ?? 'video',
      isCompleted: progress?['completed'] as bool? ?? false,
      watchedSeconds: progress?['watched_seconds'] as int? ?? 0,
    );
  }
}
