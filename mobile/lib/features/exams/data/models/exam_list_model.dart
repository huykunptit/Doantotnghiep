class ExamListItemModel {
  final int id;
  final String title;
  final String? description;
  final String status; // draft, scheduled, active, closed, archived, completed
  final String type; // course_final, standalone
  final int? duration;
  final int passScore;
  final String? startTime;
  final String? endTime;
  final bool proctoringEnabled;
  final bool isOpen;
  final AttemptSummary? myAttempt;
  final CourseRef? course;

  ExamListItemModel({
    required this.id,
    required this.title,
    this.description,
    required this.status,
    required this.type,
    this.duration,
    required this.passScore,
    this.startTime,
    this.endTime,
    this.proctoringEnabled = false,
    this.isOpen = false,
    this.myAttempt,
    this.course,
  });

  factory ExamListItemModel.fromJson(Map<String, dynamic> json) {
    AttemptSummary? attempt;
    if (json['my_attempt'] is Map) {
      attempt = AttemptSummary.fromJson(
        Map<String, dynamic>.from(json['my_attempt'] as Map),
      );
    } else if (json['attempt_id'] != null) {
      final score = (json['best_score'] as num?)?.toDouble();
      final pass = (json['pass_score'] as num?)?.toDouble();
      attempt = AttemptSummary(
        id: (json['attempt_id'] as num?)?.toInt() ?? 0,
        status: (json['status']?.toString() == 'completed')
            ? 'submitted'
            : 'in_progress',
        score: score,
        passed: score != null && pass != null ? score >= pass : null,
      );
    }

    final status = json['status']?.toString() ?? 'scheduled';
    final isOpenFlag = json['is_open'] is bool
        ? json['is_open'] as bool
        : status == 'active';

    return ExamListItemModel(
      id: (json['id'] as num?)?.toInt() ?? 0,
      title: json['title']?.toString() ?? '',
      description: json['description']?.toString(),
      status: status,
      type: json['type']?.toString() ?? 'standalone',
      duration: (json['duration'] as num?)?.toInt(),
      passScore: (json['pass_score'] as num?)?.toInt() ?? 50,
      startTime: json['start_time']?.toString() ?? json['starts_at']?.toString(),
      endTime: json['end_time']?.toString() ?? json['ends_at']?.toString(),
      proctoringEnabled: json['proctoring_enabled'] is bool
          ? json['proctoring_enabled'] as bool
          : (json['proctoring_enabled'] == 1 ||
              json['proctoring_enabled'] == '1'),
      isOpen: isOpenFlag,
      myAttempt: attempt,
      course: json['course'] is Map
          ? CourseRef.fromJson(Map<String, dynamic>.from(json['course'] as Map))
          : null,
    );
  }

  bool get isUpcoming => status == 'scheduled';

  bool get isLive => status == 'active' || isOpen;

  bool get isDone =>
      status == 'completed' ||
      status == 'closed' ||
      myAttempt?.status == 'submitted' ||
      myAttempt?.status == 'force_stopped';
}

class AttemptSummary {
  final int id;
  final String status;
  final double? score;
  final bool? passed;
  final String? submittedAt;

  AttemptSummary({
    required this.id,
    required this.status,
    this.score,
    this.passed,
    this.submittedAt,
  });

  factory AttemptSummary.fromJson(Map<String, dynamic> json) {
    return AttemptSummary(
      id: (json['id'] as num?)?.toInt() ?? 0,
      status: json['status']?.toString() ?? '',
      score: (json['score'] as num?)?.toDouble(),
      passed: json['passed'] is bool
          ? json['passed'] as bool
          : json['passed'] == null
              ? null
              : (json['passed'] == 1 || json['passed'] == '1'),
      submittedAt: json['submitted_at']?.toString(),
    );
  }
}

class CourseRef {
  final int id;
  final String title;
  final String? thumbnail;

  CourseRef({required this.id, required this.title, this.thumbnail});

  factory CourseRef.fromJson(Map<String, dynamic> json) {
    return CourseRef(
      id: (json['id'] as num?)?.toInt() ?? 0,
      title: json['title']?.toString() ?? '',
      thumbnail: json['thumbnail']?.toString(),
    );
  }
}

class ExamResultDetailModel {
  final int attemptId;
  final double score;
  final bool passed;
  final int totalQuestions;
  final int correctCount;
  final int wrongCount;
  final int skippedCount;
  final int timeSpent; // seconds
  final List<QuestionResultModel> questions;

  ExamResultDetailModel({
    required this.attemptId,
    required this.score,
    required this.passed,
    required this.totalQuestions,
    required this.correctCount,
    required this.wrongCount,
    required this.skippedCount,
    required this.timeSpent,
    required this.questions,
  });

  factory ExamResultDetailModel.fromJson(Map<String, dynamic> json) {
    final attempt = json['attempt'] as Map<String, dynamic>? ?? json;
    return ExamResultDetailModel(
      attemptId: attempt['id'] as int? ?? 0,
      score: (attempt['score'] as num?)?.toDouble() ?? 0,
      passed: attempt['passed'] as bool? ?? false,
      totalQuestions: json['total_questions'] as int? ?? 0,
      correctCount: json['correct_count'] as int? ?? 0,
      wrongCount: json['wrong_count'] as int? ?? 0,
      skippedCount: json['skipped_count'] as int? ?? 0,
      timeSpent: json['time_spent'] as int? ?? 0,
      questions: (json['questions'] as List<dynamic>?)
              ?.map((e) => QuestionResultModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }
}

class QuestionResultModel {
  final int id;
  final String content;
  final String type;
  final bool isCorrect;
  final bool isSkipped;
  final dynamic userAnswer;
  final dynamic correctAnswer;
  final String? explanation;
  final List<AnswerOptionResult> answers;
  final double points;
  final double earnedPoints;

  QuestionResultModel({
    required this.id,
    required this.content,
    required this.type,
    required this.isCorrect,
    required this.isSkipped,
    this.userAnswer,
    this.correctAnswer,
    this.explanation,
    this.answers = const [],
    required this.points,
    required this.earnedPoints,
  });

  factory QuestionResultModel.fromJson(Map<String, dynamic> json) {
    return QuestionResultModel(
      id: json['id'] as int? ?? 0,
      content: json['content']?.toString() ?? '',
      type: json['type']?.toString() ?? 'single_choice',
      isCorrect: json['is_correct'] as bool? ?? false,
      isSkipped: json['is_skipped'] as bool? ?? false,
      userAnswer: json['user_answer'],
      correctAnswer: json['correct_answer'],
      explanation: json['explanation']?.toString(),
      answers: (json['answers'] as List<dynamic>?)
              ?.map((e) => AnswerOptionResult.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      points: (json['points'] as num?)?.toDouble() ?? 1,
      earnedPoints: (json['earned_points'] as num?)?.toDouble() ?? 0,
    );
  }
}

class AnswerOptionResult {
  final int id;
  final String content;
  final bool isCorrect;
  final bool wasSelected;

  AnswerOptionResult({
    required this.id,
    required this.content,
    required this.isCorrect,
    required this.wasSelected,
  });

  factory AnswerOptionResult.fromJson(Map<String, dynamic> json) {
    return AnswerOptionResult(
      id: json['id'] as int? ?? 0,
      content: json['content']?.toString() ?? '',
      isCorrect: json['is_correct'] as bool? ?? false,
      wasSelected: json['was_selected'] as bool? ?? false,
    );
  }
}

class OrderModel {
  final int id;
  final String status;
  final double amount;
  final String? paymentMethod;
  final String? paidAt;
  final String createdAt;
  final OrderCourseRef? course;

  OrderModel({
    required this.id,
    required this.status,
    required this.amount,
    this.paymentMethod,
    this.paidAt,
    required this.createdAt,
    this.course,
  });

  factory OrderModel.fromJson(Map<String, dynamic> json) {
    return OrderModel(
      id: json['id'] as int? ?? 0,
      status: json['status']?.toString() ?? 'pending',
      amount: (json['amount'] as num?)?.toDouble() ?? 0,
      paymentMethod: json['payment_method']?.toString(),
      paidAt: json['paid_at']?.toString(),
      createdAt: json['created_at']?.toString() ?? '',
      course: json['course'] != null
          ? OrderCourseRef.fromJson(json['course'] as Map<String, dynamic>)
          : null,
    );
  }
}

class OrderCourseRef {
  final int id;
  final String title;
  final String? thumbnail;

  OrderCourseRef({required this.id, required this.title, this.thumbnail});

  factory OrderCourseRef.fromJson(Map<String, dynamic> json) {
    return OrderCourseRef(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
      thumbnail: json['thumbnail']?.toString(),
    );
  }
}
