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
    final rawTime = json['position_seconds'] ?? json['time_seconds'] ?? 0;
    final parsedTime = rawTime is num
        ? rawTime.round()
        : int.tryParse(rawTime.toString()) ?? 0;

    return NoteModel(
      id: _asInt(json['id']),
      lessonId: _asInt(json['lesson_id']),
      content: json['content']?.toString() ?? '',
      timeSeconds: parsedTime < 0 ? 0 : parsedTime,
      createdAt: json['created_at']?.toString() ?? '',
    );
  }

  static int _asInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.round();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }
}
