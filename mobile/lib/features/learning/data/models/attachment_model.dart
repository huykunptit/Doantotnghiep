class AttachmentModel {
  final int id;
  final String title;
  final String fileUrl;
  final String? fileType;
  final int? fileSize;

  AttachmentModel({
    required this.id,
    required this.title,
    required this.fileUrl,
    this.fileType,
    this.fileSize,
  });

  factory AttachmentModel.fromJson(Map<String, dynamic> json) {
    return AttachmentModel(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
      fileUrl: json['file_url']?.toString() ?? json['file_path']?.toString() ?? '',
      fileType: json['file_type']?.toString() ?? json['extension']?.toString(),
      fileSize: json['file_size'] as int?,
    );
  }
}
