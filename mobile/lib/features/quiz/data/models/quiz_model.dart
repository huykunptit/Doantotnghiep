class ExamModel {
  final int id;
  final String title;
  final String? description;
  final int? duration; // in minutes
  final int passScore;
  final String? type;
  final bool proctoringEnabled;

  ExamModel({
    required this.id,
    required this.title,
    this.description,
    this.duration,
    required this.passScore,
    this.type,
    this.proctoringEnabled = false,
  });

  factory ExamModel.fromJson(Map<String, dynamic> json) {
    return ExamModel(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
      description: json['description']?.toString(),
      duration: json['duration'] as int?,
      passScore: json['pass_score'] as int? ?? 50,
      type: json['type']?.toString(),
      proctoringEnabled: json['proctoring_enabled'] is bool
          ? json['proctoring_enabled'] as bool
          : (json['proctoring_enabled'] == 1 || json['proctoring_enabled'] == '1'),
    );
  }
}

class QuizDetailModel {
  final int id;
  final String title;
  final int? timeLimit; // in minutes

  QuizDetailModel({
    required this.id,
    required this.title,
    this.timeLimit,
  });

  factory QuizDetailModel.fromJson(Map<String, dynamic> json) {
    return QuizDetailModel(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
      timeLimit: json['time_limit'] as int?,
    );
  }
}

class QuizAnswerOptionModel {
  final int id;
  final String content;
  final String? subContent;

  QuizAnswerOptionModel({
    required this.id,
    required this.content,
    this.subContent,
  });

  factory QuizAnswerOptionModel.fromJson(Map<String, dynamic> json) {
    return QuizAnswerOptionModel(
      id: json['id'] as int? ?? 0,
      content: json['content']?.toString() ?? '',
      subContent: json['sub_content']?.toString(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'content': content,
      if (subContent != null) 'sub_content': subContent,
    };
  }
}

class QuestionModel {
  final int id;
  final String content;
  final String type; // e.g. single_choice, multiple_choice, true_false, essay, short_answer, numerical, ordering, matching
  final List<QuizAnswerOptionModel> answers;

  QuestionModel({
    required this.id,
    required this.content,
    required this.type,
    this.answers = const [],
  });

  factory QuestionModel.fromJson(Map<String, dynamic> json) {
    return QuestionModel(
      id: json['id'] as int? ?? 0,
      content: json['content']?.toString() ?? '',
      type: json['type']?.toString() ?? 'single_choice',
      answers: (json['answers'] as List<dynamic>?)
              ?.map((e) => QuizAnswerOptionModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }
}

class QuizAttemptModel {
  final int id;
  final String status; // in_progress, paused, submitted, force_stopped
  final int remainingTime; // in seconds
  final double? score;
  final bool? passed;
  final String? message;

  QuizAttemptModel({
    required this.id,
    required this.status,
    required this.remainingTime,
    this.score,
    this.passed,
    this.message,
  });

  factory QuizAttemptModel.fromJson(Map<String, dynamic> json) {
    // Attempt results might be nested in 'attempt'
    final nested = json['attempt'] as Map<String, dynamic>?;
    final source = nested ?? json;

    return QuizAttemptModel(
      id: source['id'] as int? ?? json['attempt_id'] as int? ?? 0,
      status: source['status']?.toString() ?? 'in_progress',
      remainingTime: json['remaining_time'] as int? ?? 0,
      score: (source['score'] as num?)?.toDouble(),
      passed: source['passed'] as bool?,
      message: json['message']?.toString(),
    );
  }
}

class ProctorMessageModel {
  final int id;
  final String type;
  final String title;
  final String message;
  final String createdAt;

  ProctorMessageModel({
    required this.id,
    required this.type,
    required this.title,
    required this.message,
    required this.createdAt,
  });

  factory ProctorMessageModel.fromJson(Map<String, dynamic> json) {
    return ProctorMessageModel(
      id: json['id'] as int? ?? 0,
      type: json['type']?.toString() ?? '',
      title: json['title']?.toString() ?? '',
      message: json['message']?.toString() ?? '',
      createdAt: json['created_at']?.toString() ?? '',
    );
  }
}
