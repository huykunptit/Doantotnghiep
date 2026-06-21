import 'package:flutter_dotenv/flutter_dotenv.dart';

class ApiConstants {
  ApiConstants._();

  static String get baseUrl =>
      dotenv.maybeGet('API_URL') ?? 'http://10.0.2.2:8000/api';

  static const loginPath = '/auth/login';
  static const registerPath = '/auth/register';
  static const logoutPath = '/auth/logout';
  static const mePath = '/auth/me';
  static const updateProfilePath = '/auth/profile';
  static const changePasswordPath = '/auth/change-password';
  static const forgotPasswordPath = '/auth/forgot-password';
  static const resendVerificationEmailPath = '/auth/resend-verification-email';
  static const googleLoginUrlPath = '/auth/google/url';
  static const enrollmentsPath = '/enrollments';
  static const coursesPath = '/courses';
  static const myCertificatesPath = '/my-certificates';
}
