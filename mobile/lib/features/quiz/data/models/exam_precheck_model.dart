class ExamPrecheckModel {
  final int examId;
  final String title;
  final bool requiresFaceCheck;
  final bool hasFaceUrl;
  final bool facePhotoUsable;
  final bool canEnrollFace;
  final String? facePhotoUrl;
  final bool isOpen;

  const ExamPrecheckModel({
    required this.examId,
    required this.title,
    required this.requiresFaceCheck,
    required this.hasFaceUrl,
    required this.facePhotoUsable,
    required this.canEnrollFace,
    this.facePhotoUrl,
    required this.isOpen,
  });

  factory ExamPrecheckModel.fromJson(Map<String, dynamic> json) {
    final exam = json['exam'] is Map
        ? Map<String, dynamic>.from(json['exam'] as Map)
        : <String, dynamic>{};
    return ExamPrecheckModel(
      examId: (exam['id'] as num?)?.toInt() ?? 0,
      title: exam['title']?.toString() ?? '',
      requiresFaceCheck: json['requires_face_check'] == true,
      hasFaceUrl: json['has_face_url'] == true,
      facePhotoUsable: json['face_photo_usable'] == true,
      canEnrollFace: json['can_enroll_face'] == true ||
          json['face_photo_usable'] == false,
      facePhotoUrl: json['face_photo_url']?.toString(),
      isOpen: json['is_open'] != false,
    );
  }
}

class FaceVerifyResultModel {
  final bool ok;
  final String message;
  final bool enrolled;
  final double? score;

  const FaceVerifyResultModel({
    required this.ok,
    required this.message,
    this.enrolled = false,
    this.score,
  });

  factory FaceVerifyResultModel.fromJson(Map<String, dynamic> json) {
    return FaceVerifyResultModel(
      ok: json['ok'] == true,
      message: json['message']?.toString() ?? '',
      enrolled: json['enrolled'] == true,
      score: (json['score'] as num?)?.toDouble(),
    );
  }
}
