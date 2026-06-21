class NotificationModel {
  final int id;
  final String title;
  final String message;
  final String? type;
  final String? link;
  final String? readAt;
  final String createdAt;

  NotificationModel({
    required this.id,
    required this.title,
    required this.message,
    this.type,
    this.link,
    this.readAt,
    required this.createdAt,
  });

  bool get isRead => readAt != null;

  factory NotificationModel.fromJson(Map<String, dynamic> json) {
    return NotificationModel(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
      message: json['message']?.toString() ?? '',
      type: json['type']?.toString(),
      link: json['link']?.toString(),
      readAt: json['read_at']?.toString(),
      createdAt: json['created_at']?.toString() ?? '',
    );
  }
}
