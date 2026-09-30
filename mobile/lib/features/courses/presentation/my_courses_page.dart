import 'package:flutter/material.dart';
import 'widgets/learning_segment_switch.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/my_courses_provider.dart';
import '../../../../core/widgets/error_state.dart';
import 'package:eript_lms/features/courses/presentation/widgets/buckets.dart';
import 'package:eript_lms/core/widgets/skeleton.dart';

class MyCoursesPage extends ConsumerWidget {
  const MyCoursesPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final enrollmentsAsync = ref.watch(myEnrollmentsProvider);
    final theme = Theme.of(context);

    return DefaultTabController(
      length: 4,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Học tập'),
          centerTitle: false,
          bottom: const PreferredSize(
            preferredSize: Size.fromHeight(56 + kTextTabBarHeight),
            child: Column(
              children: [
                LearningSegmentSwitch(mine: true),
                TabBar(
                  isScrollable: true,
                  tabAlignment: TabAlignment.start,
                  tabs: [
                    Tab(text: 'Đang học'),
                    Tab(text: 'Sắp tới'),
                    Tab(text: 'Đã hết hạn'),
                    Tab(text: 'Tất cả các khoá'),
                  ],
                ),
              ],
            ),
          ),
        ),
        body: Column(
          children: [
            Expanded(
              child: enrollmentsAsync.when(
                loading: () => const SkeletonList(),
                error: (e, _) => ErrorStateWidget(
                  error: e,
                  onRetry: () => ref.invalidate(myEnrollmentsProvider),
                ),
                data: (enrollments) {
                  if (enrollments.isEmpty) {
                    return MyCoursesEmptyState(
                      theme: theme,
                      onExplore: () => context.go('/catalog'),
                    );
                  }

                  final buckets = myCoursesBucket(enrollments);

                  return TabBarView(
                    children: [
                      EnrollmentList(
                        enrollments: buckets.current,
                        emptyLabel: 'Không có khóa đang học trong kỳ này.',
                        onRefresh: () async =>
                            ref.invalidate(myEnrollmentsProvider),
                      ),
                      EnrollmentList(
                        enrollments: buckets.upcoming,
                        emptyLabel: 'Chưa có khóa sắp tới theo CTĐT.',
                        onRefresh: () async =>
                            ref.invalidate(myEnrollmentsProvider),
                      ),
                      EnrollmentList(
                        enrollments: buckets.expired,
                        emptyLabel: 'Chưa có khóa đã hết hạn.',
                        onRefresh: () async =>
                            ref.invalidate(myEnrollmentsProvider),
                      ),
                      EnrollmentList(
                        enrollments: buckets.all,
                        emptyLabel: 'Chưa có khoá học nào',
                        onRefresh: () async =>
                            ref.invalidate(myEnrollmentsProvider),
                        showExplore: true,
                      ),
                    ],
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}
