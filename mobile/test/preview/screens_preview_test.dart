// Screenshots of real screens with fake data. Run: flutter test test/preview
import 'package:eript_lms/app/theme/theme_provider.dart';
import 'package:eript_lms/features/ai/data/models/ai_models.dart';
import 'package:eript_lms/features/ai/providers/ai_providers.dart';
import 'package:eript_lms/features/auth/data/models/user_model.dart';
import 'package:eript_lms/features/auth/providers/auth_provider.dart';
import 'package:eript_lms/features/auth/presentation/pages/forgot_password_page.dart';
import 'package:eript_lms/features/auth/presentation/pages/login_page.dart';
import 'package:eript_lms/features/auth/presentation/pages/register_page.dart';
import 'package:eript_lms/features/courses/data/models/category_model.dart';
import 'package:eript_lms/features/courses/data/models/course_model.dart';
import 'package:eript_lms/features/courses/data/models/enrollment_model.dart';
import 'package:eript_lms/features/courses/presentation/course_catalog_page.dart';
import 'package:eript_lms/features/courses/presentation/my_courses_page.dart';
import 'package:eript_lms/features/courses/presentation/widgets/course_detail_view.dart';
import 'package:eript_lms/features/courses/providers/my_courses_provider.dart';
import 'package:eript_lms/features/courses/providers/course_catalog_provider.dart';
import 'package:eript_lms/features/dashboard/data/models/dashboard_model.dart';
import 'package:eript_lms/features/dashboard/providers/dashboard_provider.dart';
import 'package:eript_lms/features/home/presentation/pages/home_page.dart';
import 'package:eript_lms/features/notifications/providers/notification_providers.dart';
import 'package:eript_lms/features/profile/presentation/profile_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers/screenshot.dart';

const _user = UserModel(
  id: 1,
  name: 'Nguyễn Quốc Huy',
  email: 'huy.nq@stu.ptit.edu.vn',
  studentCode: 'B21DCCN123',
  phone: '0912345678',
);

class _FakeTheme extends ThemeNotifier {
  @override
  ThemeMode build() => ThemeMode.light;
}

class _FakeAuth extends AuthNotifier {
  @override
  Future<UserModel?> build() async => _user;
}

DashboardModel _dashboard() => DashboardModel.fromJson({
  'student': {'id': 1, 'name': 'Huy', 'email': 'a@b.c'},
  'current_term': {'id': 1, 'name': 'Học kỳ 1 — 2025/2026', 'code': 'HK1'},
  'current_enrollments': [
    {
      'id': 1,
      'course_id': 11,
      'progress': 40,
      'course': {
        'id': 11,
        'title': 'Cơ sở dữ liệu nâng cao và ứng dụng',
        'course_mode': 'online',
        'credit_value': 3,
      },
    },
    {
      'id': 2,
      'course_id': 12,
      'progress': 10,
      'course': {
        'id': 12,
        'title': 'Lập trình di động Flutter',
        'course_mode': 'offline',
        'credit_value': 2,
      },
    },
  ],
  'totals': {'enrollments': 8, 'in_progress': 3, 'completed': 4},
});

List<CourseListItemModel> _courses() => [
  for (var i = 1; i <= 4; i++)
    CourseListItemModel.fromJson({
      'id': i,
      'title': 'Khóa học mẫu số $i: Phát triển ứng dụng',
      'price': i.isEven ? 0 : 300000,
      'lessons_count': 12,
      'reviews_count': i == 1 ? 0 : 20,
      'avg_rating': 4.6,
    }),
];

List<Override> _overrides() => [
  authNotifierProvider.overrideWith(_FakeAuth.new),
  themeNotifierProvider.overrideWith(_FakeTheme.new),
  studentDashboardProvider.overrideWith((ref) async => _dashboard()),
  courseCatalogProvider().overrideWith((ref) async => _courses()),
  unreadNotificationsCountProvider.overrideWith((ref) async => 3),
  courseCategoriesProvider.overrideWith(
    (ref) async => [
      CategoryModel(id: 1, name: 'Lập trình', slug: 'lap-trinh'),
      CategoryModel(id: 2, name: 'Cơ sở dữ liệu', slug: 'csdl'),
      CategoryModel(id: 3, name: 'Mạng máy tính', slug: 'mang'),
    ],
  ),
  courseCatalogProvider(
    search: null,
    categoryId: null,
  ).overrideWith((ref) async => _courses()),
  myEnrollmentsProvider.overrideWith(
    (ref) async => _dashboard().currentEnrollments,
  ),
  recommendationsProvider.overrideWith(
    (ref) async => const RecommendationsBundle(items: []),
  ),
];

Future<void> screen(
  WidgetTester t,
  Widget child,
  String name, {
  Brightness mode = Brightness.light,
  double scale = 1.0,
  Size size = const Size(360, 740),
}) async {
  await shoot(
    t,
    ProviderScope(overrides: _overrides(), child: child),
    name,
    brightness: mode,
    textScale: scale,
    size: size,
  );
}

CourseDetailModel _detail({
  required bool enrolled,
}) => CourseDetailModel.fromJson({
  'id': 11,
  'title': 'Cơ sở dữ liệu nâng cao và ứng dụng',
  'description':
      '<p>Khóa học giúp bạn nắm vững thiết kế CSDL, tối ưu truy vấn và giao dịch.</p>',
  'price': enrolled ? 0 : 450000,
  'enrollments_count': 320,
  'avg_rating': 4.7,
  'credit_value': 3,
  'is_enrolled': enrolled,
  'instructor': {'id': 5, 'name': 'TS. Trần Văn Minh'},
  'lessons': [
    for (var i = 1; i <= 6; i++)
      {
        'id': i,
        'title': 'Bài $i: Chuẩn hóa dữ liệu dạng $i',
        'order': i,
        'duration': 600 + i * 60,
      },
  ],
});

void main() {
  setUpAll(loadPreviewFonts);

  testWidgets('login light', (t) async {
    await screen(t, const LoginPage(), 'login_light');
  });
  testWidgets('login dark', (t) async {
    await screen(t, const LoginPage(), 'login_dark', mode: Brightness.dark);
  });
  testWidgets('register dark', (t) async {
    await screen(
      t,
      const RegisterPage(),
      'register_dark',
      mode: Brightness.dark,
      size: const Size(360, 900),
    );
  });
  testWidgets('forgot light', (t) async {
    await screen(t, const ForgotPasswordPage(), 'forgot_light');
  });
  testWidgets('catalog light', (t) async {
    await screen(t, const CourseCatalogPage(), 'catalog_light');
  });
  testWidgets('catalog dark', (t) async {
    await screen(
      t,
      const CourseCatalogPage(),
      'catalog_dark',
      mode: Brightness.dark,
    );
  });
  testWidgets('my courses light', (t) async {
    await screen(t, const MyCoursesPage(), 'my_courses_light');
  });
  testWidgets('course detail (not enrolled)', (t) async {
    await screen(
      t,
      CourseDetailView(course: _detail(enrolled: false)),
      'course_detail_buy_light',
    );
  });
  testWidgets('course detail (enrolled) dark', (t) async {
    await screen(
      t,
      CourseDetailView(course: _detail(enrolled: true)),
      'course_detail_enrolled_dark',
      mode: Brightness.dark,
    );
  });

  for (final mode in Brightness.values) {
    testWidgets('home ${mode.name}', (t) async {
      await screen(t, const HomePage(), 'home_${mode.name}', mode: mode);
    });
    testWidgets('profile ${mode.name}', (t) async {
      await screen(
        t,
        const ProfilePage(),
        'profile_${mode.name}',
        mode: mode,
        size: const Size(360, 1100),
      );
    });
  }

  // Overflow guard: small phone + large text must not overflow (a RenderFlex
  // overflow fails the test).
  final smallCases = <String, Widget Function()>{
    'login': () => const LoginPage(),
    'register': () => const RegisterPage(),
    'forgot': () => const ForgotPasswordPage(),
    'catalog': () => const CourseCatalogPage(),
    'my_courses': () => const MyCoursesPage(),
    'profile': () => const ProfilePage(),
    'course_detail': () => CourseDetailView(course: _detail(enrolled: false)),
  };
  for (final e in smallCases.entries) {
    testWidgets('${e.key} small phone + 1.3x text', (t) async {
      await screen(
        t,
        e.value(),
        '${e.key}_small_1_3x',
        scale: 1.3,
        size: const Size(320, 640),
      );
    });
  }

  testWidgets('home small phone + 1.3x text', (t) async {
    await screen(
      t,
      const HomePage(),
      'home_small_1_3x',
      scale: 1.3,
      size: const Size(320, 640),
    );
  });
}
