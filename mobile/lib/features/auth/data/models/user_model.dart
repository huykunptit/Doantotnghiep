import '../../../../core/utils/media_url.dart';

class UserModel {
  const UserModel({
    required this.id,
    required this.name,
    required this.email,
    this.avatar,
    this.role = 'student',
    this.studentCode,
    this.phone,
  });

  final int id;
  final String name;
  final String email;
  final String? avatar;
  final String role;
  final String? studentCode;
  final String? phone;

  factory UserModel.fromJson(Map<String, dynamic> json) {
    final user = json['user'] as Map<String, dynamic>? ?? json;
    return UserModel(
      id: user['id'] as int? ?? 0,
      name: user['name']?.toString() ?? '',
      email: user['email']?.toString() ?? '',
      avatar: resolveMediaUrl(user['avatar']?.toString()),
      role: user['role']?.toString() ?? 'student',
      studentCode: user['student_code']?.toString(),
      phone: user['phone']?.toString(),
    );
  }

  UserModel copyWith({
    String? name,
    String? avatar,
    String? phone,
  }) {
    return UserModel(
      id: id,
      name: name ?? this.name,
      email: email,
      avatar: avatar ?? this.avatar,
      role: role,
      studentCode: studentCode,
      phone: phone ?? this.phone,
    );
  }
}
