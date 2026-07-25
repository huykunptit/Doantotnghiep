class TimetableScheduleItem {
  final int id;
  final int weekday;
  final String startTime;
  final String endTime;
  final String? room;
  final String? courseTitle;
  final String? lecturerName;

  TimetableScheduleItem({
    required this.id,
    required this.weekday,
    required this.startTime,
    required this.endTime,
    this.room,
    this.courseTitle,
    this.lecturerName,
  });

  factory TimetableScheduleItem.fromJson(Map<String, dynamic> json) {
    final course = json['course'] as Map<String, dynamic>?;
    final lecturer = json['lecturer'] as Map<String, dynamic>?;
    return TimetableScheduleItem(
      id: json['id'] as int? ?? 0,
      weekday: json['weekday'] as int? ?? 1,
      startTime: json['start_time']?.toString() ?? '',
      endTime: json['end_time']?.toString() ?? '',
      room: json['room']?.toString(),
      courseTitle: course?['title']?.toString(),
      lecturerName: lecturer?['name']?.toString(),
    );
  }
}

class TimetableExamItem {
  final int id;
  final String title;
  final String? startsAt;
  final String? endsAt;
  final int? duration;

  TimetableExamItem({
    required this.id,
    required this.title,
    this.startsAt,
    this.endsAt,
    this.duration,
  });

  factory TimetableExamItem.fromJson(Map<String, dynamic> json) {
    return TimetableExamItem(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
      startsAt: json['starts_at']?.toString(),
      endsAt: json['ends_at']?.toString(),
      duration: json['duration'] as int?,
    );
  }
}

class TimetableModel {
  final String? termName;
  final List<TimetableScheduleItem> schedules;
  final List<TimetableExamItem> exams;

  TimetableModel({
    this.termName,
    required this.schedules,
    required this.exams,
  });

  factory TimetableModel.fromJson(Map<String, dynamic> json) {
    final term = json['current_term'] as Map<String, dynamic>?;
    return TimetableModel(
      termName: term?['name']?.toString(),
      schedules: (json['schedules'] as List<dynamic>?)
              ?.map((e) => TimetableScheduleItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      exams: (json['exams'] as List<dynamic>?)
              ?.map((e) => TimetableExamItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }
}

class TuitionItem {
  final int id;
  final String? termName;
  final double amount;
  final String status;
  final String? paidAt;
  final String? note;

  TuitionItem({
    required this.id,
    this.termName,
    required this.amount,
    required this.status,
    this.paidAt,
    this.note,
  });

  bool get isPaid => status == 'paid';

  factory TuitionItem.fromJson(Map<String, dynamic> json) {
    final term = json['term'] as Map<String, dynamic>?;
    return TuitionItem(
      id: json['id'] as int? ?? 0,
      termName: term?['name']?.toString(),
      amount: (json['amount'] as num?)?.toDouble() ?? 0,
      status: json['status']?.toString() ?? 'unpaid',
      paidAt: json['paid_at']?.toString(),
      note: json['note']?.toString(),
    );
  }
}

class TuitionListModel {
  final List<TuitionItem> items;
  final double totalDue;
  final double totalPaid;

  TuitionListModel({
    required this.items,
    required this.totalDue,
    required this.totalPaid,
  });

  factory TuitionListModel.fromJson(Map<String, dynamic> json) {
    return TuitionListModel(
      items: (json['items'] as List<dynamic>?)
              ?.map((e) => TuitionItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      totalDue: (json['total_due'] as num?)?.toDouble() ?? 0,
      totalPaid: (json['total_paid'] as num?)?.toDouble() ?? 0,
    );
  }
}
