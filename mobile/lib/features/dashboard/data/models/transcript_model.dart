class TranscriptExamResult {
  final int examId;
  final String examTitle;
  final String? examType;
  final String? courseTitle;
  final int creditValue;
  final int? passScore;
  final double? score;
  final bool? passed;
  final String? takenAt;
  final String? examDate;

  TranscriptExamResult({
    required this.examId,
    required this.examTitle,
    this.examType,
    this.courseTitle,
    this.creditValue = 0,
    this.passScore,
    this.score,
    this.passed,
    this.takenAt,
    this.examDate,
  });

  factory TranscriptExamResult.fromJson(Map<String, dynamic> json) {
    final course = json['course'] as Map<String, dynamic>?;
    return TranscriptExamResult(
      examId: json['exam_id'] as int? ?? 0,
      examTitle: json['exam_title']?.toString() ?? '',
      examType: json['exam_type']?.toString(),
      courseTitle: course?['title']?.toString(),
      creditValue: json['credit_value'] as int? ?? 0,
      passScore: json['pass_score'] as int?,
      score: (json['score'] as num?)?.toDouble(),
      passed: json['passed'] as bool?,
      takenAt: json['taken_at']?.toString(),
      examDate: json['exam_date']?.toString(),
    );
  }
}

class TranscriptSummary {
  final int totalExams;
  final int taken;
  final int passed;
  final double? averageScore;

  TranscriptSummary({
    required this.totalExams,
    required this.taken,
    required this.passed,
    this.averageScore,
  });

  factory TranscriptSummary.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return TranscriptSummary(totalExams: 0, taken: 0, passed: 0);
    }
    return TranscriptSummary(
      totalExams: json['total_exams'] as int? ?? 0,
      taken: json['taken'] as int? ?? 0,
      passed: json['passed'] as int? ?? 0,
      averageScore: (json['average_score'] as num?)?.toDouble(),
    );
  }
}

/// BA 2026: bảng điểm chỉ gồm kết quả thi trên LMS.
class TranscriptModel {
  final List<TranscriptExamResult> results;
  final TranscriptSummary summary;

  TranscriptModel({
    required this.results,
    required this.summary,
  });

  factory TranscriptModel.fromJson(Map<String, dynamic> json) {
    return TranscriptModel(
      results: (json['results'] as List<dynamic>?)
              ?.map((e) => TranscriptExamResult.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      summary: TranscriptSummary.fromJson(json['summary'] as Map<String, dynamic>?),
    );
  }
}
