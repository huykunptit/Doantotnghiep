class AuthUser {
  const AuthUser({
    required this.id,
    required this.email,
    required this.name,
    required this.accessToken,
    this.refreshToken,
  });

  final String id;
  final String email;
  final String name;
  final String accessToken;
  final String? refreshToken;

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    return AuthUser(
      id: json['id']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      name: json['name']?.toString() ?? json['fullName']?.toString() ?? '',
      accessToken: json['accessToken']?.toString() ?? json['token']?.toString() ?? '',
      refreshToken: json['refreshToken']?.toString(),
    );
  }
}
