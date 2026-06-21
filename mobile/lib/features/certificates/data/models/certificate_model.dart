class CertificateTemplateModel {
  final int id;
  final String? name;
  final String? backgroundImageUrl;

  CertificateTemplateModel({
    required this.id,
    this.name,
    this.backgroundImageUrl,
  });

  factory CertificateTemplateModel.fromJson(Map<String, dynamic> json) {
    return CertificateTemplateModel(
      id: json['id'] as int? ?? 0,
      name: json['name']?.toString(),
      backgroundImageUrl: json['background_image_url']?.toString(),
    );
  }
}

class UserCertificateModel {
  final int id;
  final String credentialId;
  final String issuedAt;
  final String courseTitle;
  final CertificateTemplateModel? template;

  UserCertificateModel({
    required this.id,
    required this.credentialId,
    required this.issuedAt,
    required this.courseTitle,
    this.template,
  });

  factory UserCertificateModel.fromJson(Map<String, dynamic> json) {
    final course = json['course'] as Map<String, dynamic>? ?? {};
    final templateJson = json['certificate_template'] as Map<String, dynamic>? ?? json['certificateTemplate'] as Map<String, dynamic>?;

    return UserCertificateModel(
      id: json['id'] as int? ?? 0,
      credentialId: json['credential_id']?.toString() ?? '',
      issuedAt: json['issued_at']?.toString() ?? '',
      courseTitle: course['title']?.toString() ?? 'Khoá học',
      template: templateJson != null ? CertificateTemplateModel.fromJson(templateJson) : null,
    );
  }
}
