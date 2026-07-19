import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../../features/auth/presentation/splash_page.dart';
import '../../features/auth/presentation/pages/login_page.dart';
import '../../features/auth/presentation/pages/register_page.dart';
import '../../features/auth/presentation/pages/forgot_password_page.dart';
import '../../features/auth/presentation/pages/verify_email_prompt_page.dart';
import '../../features/auth/presentation/pages/google_oauth_webview.dart';
import '../../features/payments/presentation/pages/checkout_webview_page.dart';
import '../../features/learning/presentation/screens/lesson_player_screen.dart';
import '../../features/auth/providers/auth_provider.dart';
import '../../features/home/presentation/pages/home_page.dart';
import '../../features/courses/presentation/course_catalog_page.dart';
import '../../features/courses/presentation/my_courses_page.dart';
import '../../features/courses/presentation/course_detail_page.dart';
import '../../features/profile/presentation/profile_page.dart';
import '../shell/main_shell.dart';
import '../../features/quiz/presentation/screens/exam_workspace_screen.dart';
import '../../features/dashboard/presentation/screens/transcript_screen.dart';
import '../../features/dashboard/presentation/screens/learning_path_screen.dart';
import '../../features/dashboard/presentation/screens/attendance_screen.dart';
import '../../features/certificates/presentation/screens/certificates_screen.dart';
import '../../features/notifications/presentation/screens/notifications_screen.dart';
import '../../features/career/presentation/screens/career_advisor_screen.dart';
// Sprint 1
import '../../features/exams/presentation/screens/exam_list_screen.dart';
import '../../features/exams/presentation/screens/exam_result_screen.dart';
import '../../features/exams/presentation/screens/order_history_screen.dart';
// Sprint 2
import '../../features/student/presentation/screens/tasks_screen.dart';
import '../../features/student/presentation/screens/exam_calendar_screen.dart';
import '../../features/student/presentation/screens/library_screen.dart';
// Sprint 5 (points — wired early for routing)
import '../../features/points/presentation/screens/points_screen.dart';
import '../../features/points/presentation/screens/voucher_shop_screen.dart';
import '../../features/points/presentation/screens/my_vouchers_screen.dart';

part 'app_router.g.dart';

@riverpod
GoRouter appRouter(AppRouterRef ref) {
  final authState = ref.watch(authNotifierProvider);

  return GoRouter(
    initialLocation: '/',
    redirect: (context, state) {
      final isLoading = authState.isLoading;
      final isAuthenticated = authState.valueOrNull != null;
      final matched = state.matchedLocation;
      
      final isPublicRoute = matched == '/login' || 
                            matched == '/register' || 
                            matched == '/forgot-password' || 
                            matched == '/verify-email-prompt' ||
                            matched == '/google-login-webview';
      final isOnSplash = matched == '/';

      if (isLoading || isOnSplash) return null;
      if (!isAuthenticated && !isPublicRoute) return '/login';
      if (isAuthenticated && isPublicRoute) return '/home';
      return null;
    },
    routes: [
      GoRoute(
        path: '/',
        builder: (context, state) => const SplashPage(),
      ),
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginPage(),
      ),
      GoRoute(
        path: '/register',
        builder: (context, state) => const RegisterPage(),
      ),
      GoRoute(
        path: '/forgot-password',
        builder: (context, state) => const ForgotPasswordPage(),
      ),
      GoRoute(
        path: '/verify-email-prompt',
        builder: (context, state) {
          final email = state.extra as String? ?? '';
          return VerifyEmailPromptPage(email: email);
        },
      ),
      GoRoute(
        path: '/google-login-webview',
        builder: (context, state) => const GoogleOauthWebviewPage(),
      ),
      GoRoute(
        path: '/checkout-webview',
        builder: (context, state) {
          final checkoutUrl = state.extra as String? ?? '';
          return CheckoutWebviewPage(checkoutUrl: checkoutUrl);
        },
      ),
      GoRoute(
        path: '/learn/:courseId/:lessonId',
        builder: (context, state) {
          final courseId = int.tryParse(state.pathParameters['courseId'] ?? '') ?? 0;
          final lessonId = int.tryParse(state.pathParameters['lessonId'] ?? '') ?? 0;
          return LessonPlayerScreen(courseId: courseId, lessonId: lessonId);
        },
      ),
      GoRoute(
        path: '/learn/quiz/:courseId/:lessonId',
        builder: (context, state) {
          final courseId = int.tryParse(state.pathParameters['courseId'] ?? '') ?? 0;
          final lessonId = int.tryParse(state.pathParameters['lessonId'] ?? '') ?? 0;
          return ExamWorkspaceScreen(courseId: courseId, lessonId: lessonId, examId: 0);
        },
      ),
      GoRoute(
        path: '/exam/:examId',
        builder: (context, state) {
          final examId = int.tryParse(state.pathParameters['examId'] ?? '') ?? 0;
          return ExamWorkspaceScreen(courseId: 0, lessonId: 0, examId: examId);
        },
      ),
      ShellRoute(
        builder: (context, state, child) => MainShell(child: child),
        routes: [
          GoRoute(
            path: '/home',
            builder: (context, state) => const HomePage(),
          ),
          GoRoute(
            path: '/catalog',
            builder: (context, state) => const CourseCatalogPage(),
          ),
          GoRoute(
            path: '/my-courses',
            builder: (context, state) => const MyCoursesPage(),
          ),
          GoRoute(
            path: '/exams',
            builder: (context, state) => const ExamListScreen(),
          ),
          GoRoute(
            path: '/profile',
            builder: (context, state) => const ProfilePage(),
          ),
        ],
      ),
      GoRoute(
        path: '/courses/:id',
        builder: (_, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
          return CourseDetailPage(courseId: id);
        },
      ),
      GoRoute(
        path: '/transcript',
        builder: (context, state) => const TranscriptScreen(),
      ),
      GoRoute(
        path: '/learning-path',
        builder: (context, state) => const LearningPathScreen(),
      ),
      GoRoute(
        path: '/attendance',
        builder: (context, state) => const AttendanceScreen(),
      ),
      GoRoute(
        path: '/certificates',
        builder: (context, state) => const CertificatesScreen(),
      ),
      GoRoute(
        path: '/notifications',
        builder: (context, state) => const NotificationsScreen(),
      ),
      GoRoute(
        path: '/career',
        builder: (context, state) => const CareerAdvisorScreen(),
      ),
      // Sprint 1 routes
      GoRoute(
        path: '/exam-result/:attemptId',
        builder: (context, state) {
          final attemptId =
              int.tryParse(state.pathParameters['attemptId'] ?? '') ?? 0;
          return ExamResultScreen(attemptId: attemptId);
        },
      ),
      GoRoute(
        path: '/orders',
        builder: (context, state) => const OrderHistoryScreen(),
      ),
      // Sprint 2 routes
      GoRoute(
        path: '/tasks',
        builder: (context, state) => const TasksScreen(),
      ),
      GoRoute(
        path: '/exam-calendar',
        builder: (context, state) => const ExamCalendarScreen(),
      ),
      GoRoute(
        path: '/library',
        builder: (context, state) => const LibraryScreen(),
      ),
      // Sprint 5 routes
      GoRoute(
        path: '/points',
        builder: (context, state) => const PointsScreen(),
      ),
      GoRoute(
        path: '/voucher-shop',
        builder: (context, state) => const VoucherShopScreen(),
      ),
      GoRoute(
        path: '/my-vouchers',
        builder: (context, state) => const MyVouchersScreen(),
      ),
    ],
  );
}
