import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../ai/presentation/widgets/recommendations_section.dart';
import '../../../ai/providers/ai_providers.dart';
import '../../../auth/providers/auth_provider.dart';
import '../../../courses/providers/course_catalog_provider.dart';
import '../../../dashboard/providers/dashboard_provider.dart';
import '../../../notifications/providers/notification_providers.dart';
import '../widgets/ai_entry_card.dart';
import '../widgets/continue_learning_section.dart';
import '../widgets/home_app_bar.dart';
import '../widgets/latest_courses_section.dart';
import '../widgets/progress_hero_card.dart';
import '../widgets/student_utilities.dart';

class HomePage extends ConsumerWidget {
  const HomePage({super.key});

  static const routeName = '/home';

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authNotifierProvider).valueOrNull;
    final dashboard = ref.watch(studentDashboardProvider);
    final unread = ref.watch(unreadNotificationsCountProvider).valueOrNull ?? 0;

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(studentDashboardProvider);
          ref.invalidate(courseCatalogProvider());
          ref.invalidate(recommendationsProvider);
        },
        child: CustomScrollView(
          slivers: [
            HomeAppBar(
              name: user?.name,
              avatarUrl: user?.avatar,
              unreadCount: unread,
            ),
            SliverToBoxAdapter(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: AppInsets.screen.copyWith(top: AppSpacing.space2),
                    child: dashboard.when(
                      loading: () => const ProgressHeroSkeleton(),
                      error: (_, _) => const SizedBox.shrink(),
                      data: (d) => ProgressHeroCard(data: d),
                    ),
                  ),
                  AppSpacing.h24,
                  Padding(
                    padding: AppInsets.screen,
                    child: const StudentUtilities(),
                  ),
                  AppSpacing.h16,
                  Padding(
                    padding: AppInsets.screen,
                    child: const AiEntryCard(),
                  ),
                  AppSpacing.h24,
                  dashboard.maybeWhen(
                    data: (d) => d.currentEnrollments.isEmpty
                        ? const SizedBox.shrink()
                        : Padding(
                            padding: const EdgeInsets.only(
                              bottom: AppSpacing.space6,
                            ),
                            child: ContinueLearningSection(
                              enrollments: d.currentEnrollments,
                            ),
                          ),
                    orElse: () => const SizedBox.shrink(),
                  ),
                  const RecommendationsSection(),
                  const LatestCoursesSection(),
                  AppSpacing.h32,
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
