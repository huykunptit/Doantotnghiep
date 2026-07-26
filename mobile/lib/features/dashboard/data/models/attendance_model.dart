class AttendanceSessionModel {
  final int id;
  final String location;
  final String? startAt;
  final int? duration;
  final String? title;
  final String? lessonTitle;
  final String? courseTitle;

  AttendanceSessionModel({
    required this.id,
    required this.location,
    this.startAt,
    this.duration,
    this.title,
    this.lessonTitle,
    this.courseTitle,
  });

  factory AttendanceSessionModel.fromJson(Map<String, dynamic> json) {
    return AttendanceSessionModel(
      id: json['id'] as int? ?? 0,
      location: json['location']?.toString() ?? '',
      startAt: json['start_at']?.toString(),
      duration: json['duration'] as int?,
      title: json['title']?.toString(),
      lessonTitle: json['lesson_title']?.toString() ?? json['title']?.toString(),
      courseTitle: json['course_title']?.toString(),
    );
  }
}

class AttendanceHistoryItemModel {
  final int id;
  final String status;
  final String? checkedInAt;
  final String? deviceInfo;
  final double? distanceMeters;
  final AttendanceSessionModel? offlineSession;

  AttendanceHistoryItemModel({
    required this.id,
    required this.status,
    this.checkedInAt,
    this.deviceInfo,
    this.distanceMeters,
    this.offlineSession,
  });

  factory AttendanceHistoryItemModel.fromJson(Map<String, dynamic> json) {
    final sessionData = json['offline_session'] as Map<String, dynamic>?;
    return AttendanceHistoryItemModel(
      id: json['id'] as int? ?? 0,
      status: json['status']?.toString() ?? 'present',
      checkedInAt: json['checked_in_at']?.toString(),
      deviceInfo: json['device_info']?.toString(),
      distanceMeters: (json['distance_meters'] as num?)?.toDouble(),
      offlineSession: sessionData != null ? AttendanceSessionModel.fromJson(sessionData) : null,
    );
  }
}

class CheckInResultModel {
  final String message;
  final AttendanceHistoryItemModel attendance;

  CheckInResultModel({
    required this.message,
    required this.attendance,
  });

  factory CheckInResultModel.fromJson(Map<String, dynamic> json) {
    return CheckInResultModel(
      message: json['message']?.toString() ?? '',
      attendance: AttendanceHistoryItemModel.fromJson(json['attendance'] as Map<String, dynamic>? ?? {}),
    );
  }
}
