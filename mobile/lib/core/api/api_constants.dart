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
  static const careerPathsPath = '/career-paths';
  static const ordersPath = '/orders';
  static const myCertificatesPath = '/my-certificates';

  // Student portal (BA 2026)
  static const meTranscriptPath = '/me/transcript';
  static const meTimetablePath = '/me/timetable';
  static const meTuitionPath = '/me/tuition';
  static const meCurriculumEvaluationPath = '/me/curriculum-evaluation';
  static const meLearnerProfilePath = '/me/learner-profile';
  static const meRecommendationsExtensionsPath = '/me/recommendations/extensions';

  // AI Career
  static const careerAdvisorPath = '/career/advisor';
  static const careerUploadCvPath = '/career/upload-cv';
  static const careerCvFormPath = '/career/cv-form';
  static const careerEvaluatePath = '/career/evaluate';
  static const careerRecommendPath = '/career/recommend';
}
