class TaskModel {
  final int id;
  final String title;
  final String? description;
  final String type; // assignment, quiz, exam, survey
  final bool isDone;
  final String? dueDate;
  final CourseTaskRef? course;

  TaskModel({
    required this.id,
    required this.title,
    this.description,
    required this.type,
    required this.isDone,
    this.dueDate,
    this.course,
  });

  factory TaskModel.fromJson(Map<String, dynamic> json) {
    return TaskModel(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
      description: json['description']?.toString(),
      type: json['type']?.toString() ?? 'assignment',
      isDone: json['is_done'] as bool? ?? false,
      dueDate: json['due_date']?.toString(),
      course: json['course'] != null
          ? CourseTaskRef.fromJson(json['course'] as Map<String, dynamic>)
          : null,
    );
  }
}

class CourseTaskRef {
  final int id;
  final String title;

  CourseTaskRef({required this.id, required this.title});

  factory CourseTaskRef.fromJson(Map<String, dynamic> json) {
    return CourseTaskRef(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
    );
  }
}

class CalendarExamModel {
  final int id;
  final String title;
  final String status;
  final String? startTime;
  final String? endTime;
  final int? duration;
  final String type;

  CalendarExamModel({
    required this.id,
    required this.title,
    required this.status,
    this.startTime,
    this.endTime,
    this.duration,
    required this.type,
  });

  factory CalendarExamModel.fromJson(Map<String, dynamic> json) {
    return CalendarExamModel(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
      status: json['status']?.toString() ?? 'scheduled',
      startTime: json['start_time']?.toString(),
      endTime: json['end_time']?.toString(),
      duration: json['duration'] as int?,
      type: json['type']?.toString() ?? 'standalone',
    );
  }

  DateTime? get startDateTime {
    if (startTime == null) return null;
    return DateTime.tryParse(startTime!)?.toLocal();
  }
}

class LibraryAttachmentModel {
  final int id;
  final String title;
  final String? fileUrl;
  final String? fileType;
  final int? fileSize;
  final String lessonTitle;
  final String courseTitle;
  final int courseId;
  final int lessonId;

  LibraryAttachmentModel({
    required this.id,
    required this.title,
    this.fileUrl,
    this.fileType,
    this.fileSize,
    required this.lessonTitle,
    required this.courseTitle,
    required this.courseId,
    required this.lessonId,
  });

  factory LibraryAttachmentModel.fromJson(
      Map<String, dynamic> json, {
      String courseTitle = '',
      String lessonTitle = '',
      int courseId = 0,
      int lessonId = 0,
  }) {
    return LibraryAttachmentModel(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? json['name']?.toString() ?? '',
      fileUrl: json['file_url']?.toString() ?? json['url']?.toString(),
      fileType: json['file_type']?.toString() ?? json['type']?.toString(),
      fileSize: json['file_size'] as int?,
      lessonTitle: json['lesson_title']?.toString() ?? lessonTitle,
      courseTitle: json['course_title']?.toString() ?? courseTitle,
      courseId: json['course_id'] as int? ?? courseId,
      lessonId: json['lesson_id'] as int? ?? lessonId,
    );
  }

  String get displayFileType {
    final ext = fileUrl?.split('.').last.toLowerCase() ?? fileType ?? '';
    if (['pdf'].contains(ext)) return 'PDF';
    if (['doc', 'docx'].contains(ext)) return 'Word';
    if (['xls', 'xlsx'].contains(ext)) return 'Excel';
    if (['ppt', 'pptx'].contains(ext)) return 'PowerPoint';
    if (['zip', 'rar'].contains(ext)) return 'Archive';
    if (['mp4', 'mov', 'avi'].contains(ext)) return 'Video';
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].contains(ext)) return 'Hình ảnh';
    return ext.toUpperCase().isNotEmpty ? ext.toUpperCase() : 'File';
  }

  String get fileSizeText {
    if (fileSize == null) return '';
    if (fileSize! < 1024) return '${fileSize}B';
    if (fileSize! < 1024 * 1024) return '${(fileSize! / 1024).toStringAsFixed(1)}KB';
    return '${(fileSize! / 1024 / 1024).toStringAsFixed(1)}MB';
  }
}
