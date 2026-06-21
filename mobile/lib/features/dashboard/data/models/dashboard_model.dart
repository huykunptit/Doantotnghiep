import '../../../auth/data/models/user_model.dart';
import '../../../courses/data/models/enrollment_model.dart';

class TermModel {
  final int id;
  final String name;
  final String code;
  final String? startDate;
  final String? endDate;
  final bool isCurrent;

  TermModel({
    required this.id,
    required this.name,
    required this.code,
    this.startDate,
    this.endDate,
    this.isCurrent = false,
  });

  factory TermModel.fromJson(Map<String, dynamic> json) {
    return TermModel(
      id: json['id'] as int? ?? 0,
      name: json['name']?.toString() ?? '',
      code: json['code']?.toString() ?? '',
      startDate: json['start_date']?.toString(),
      endDate: json['end_date']?.toString(),
      isCurrent: json['is_current'] as bool? ?? false,
    );
  }
}

class DashboardTotals {
  final int enrollments;
  final int inProgress;
  final int completed;

  DashboardTotals({
    required this.enrollments,
    required this.inProgress,
    required this.completed,
  });

  factory DashboardTotals.fromJson(Map<String, dynamic> json) {
    return DashboardTotals(
      enrollments: json['enrollments'] as int? ?? 0,
      inProgress: json['in_progress'] as int? ?? 0,
      completed: json['completed'] as int? ?? 0,
    );
  }
}

class DashboardModel {
  final UserModel student;
  final TermModel? currentTerm;
  final List<EnrollmentModel> currentEnrollments;
  final DashboardTotals totals;

  DashboardModel({
    required this.student,
    this.currentTerm,
    required this.currentEnrollments,
    required this.totals,
  });

  factory DashboardModel.fromJson(Map<String, dynamic> json) {
    return DashboardModel(
      student: UserModel.fromJson(json['student'] as Map<String, dynamic>? ?? {}),
      currentTerm: json['current_term'] != null
          ? TermModel.fromJson(json['current_term'] as Map<String, dynamic>)
          : null,
      currentEnrollments: (json['current_enrollments'] as List<dynamic>?)
              ?.map((e) => EnrollmentModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      totals: DashboardTotals.fromJson(json['totals'] as Map<String, dynamic>? ?? {}),
    );
  }
}
