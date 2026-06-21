class NoteModel {
  final int id;
  final int lessonId;
  final String content;
  final int timeSeconds;
  final String createdAt;

  NoteModel({
    required this.id,
    required this.lessonId,
    required this.content,
    required this.timeSeconds,
    required this.createdAt,
  });

  factory NoteModel.fromJson(Map<String, dynamic> json) {
    return NoteModel(
      id: json['id'] as int? ?? 0,
      lessonId: json['lesson_id'] as int? ?? 0,
      content: json['content']?.toString() ?? '',
      timeSeconds: json['time_seconds'] as int? ?? 0,
      createdAt: json['created_at']?.toString() ?? '',
    );
  }
}
