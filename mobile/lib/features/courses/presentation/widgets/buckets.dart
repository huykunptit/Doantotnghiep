import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:eript_lms/features/courses/data/models/enrollment_model.dart';
import '../../../../core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/features/courses/presentation/widgets/enrollment_card.dart';

class Buckets {
  const Buckets({
    required this.current,
    required this.upcoming,
    required this.expired,
  });

  final List<EnrollmentModel> current;
  final List<EnrollmentModel> upcoming;
  final List<EnrollmentModel> expired;

  List<EnrollmentModel> get all => [...current, ...upcoming, ...expired];
}

Buckets myCoursesBucket(List<EnrollmentModel> enrollments) {
  final current = enrollments
      .where((e) => e.window == CourseWindow.current)
      .toList();
  final upcoming = enrollments
      .where((e) => e.window == CourseWindow.upcoming)
      .toList();
  final expired = enrollments
      .where((e) => e.window == CourseWindow.expired)
      .toList();

  int? dayMs(String? value) {
    if (value == null || value.isEmpty) return null;
    final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})').firstMatch(value);
    if (match == null) return DateTime.tryParse(value)?.millisecondsSinceEpoch;
    return DateTime(
      int.parse(match.group(1)!),
      int.parse(match.group(2)!),
      int.parse(match.group(3)!),
    ).millisecondsSinceEpoch;
  }

  current.sort((a, b) {
    final ea = dayMs(a.endsAt) ?? 1 << 62;
    final eb = dayMs(b.endsAt) ?? 1 << 62;
    if (ea != eb) return ea.compareTo(eb);
    return (dayMs(b.enrolledAt) ?? 0).compareTo(dayMs(a.enrolledAt) ?? 0);
  });
  upcoming.sort(
    (a, b) =>
        (dayMs(a.startsAt) ?? 1 << 62).compareTo(dayMs(b.startsAt) ?? 1 << 62),
  );
  expired.sort(
    (a, b) => (dayMs(b.endsAt) ?? 0).compareTo(dayMs(a.endsAt) ?? 0),
  );

  return Buckets(current: current, upcoming: upcoming, expired: expired);
}

class MyCoursesEmptyState extends StatelessWidget {
  const MyCoursesEmptyState({
    super.key,
    required this.theme,
    required this.onExplore,
  });
  final ThemeData theme;
  final VoidCallback onExplore;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 80,
              height: 80,
              decoration: BoxDecoration(
                color: context.cs.primaryContainer,
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.school_outlined,
                size: 40,
                color: context.cs.primary,
              ),
            ),
            AppSpacing.h20,
            Text(
              'Chưa có khoá học nào',
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            AppSpacing.h8,
            Text(
              'Khám phá khoá học và bắt đầu hành trình học tập của bạn.',
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
                height: 1.5,
              ),
              textAlign: TextAlign.center,
            ),
            AppSpacing.h24,
            FilledButton.icon(
              onPressed: onExplore,
              icon: const Icon(Icons.explore_outlined, size: 18),
              label: const Text('Khám phá khoá học'),
              style: FilledButton.styleFrom(
                backgroundColor: context.cs.primary,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class EnrollmentList extends StatelessWidget {
  const EnrollmentList({
    super.key,
    required this.enrollments,
    required this.emptyLabel,
    required this.onRefresh,
    this.showExplore = false,
  });

  final List<EnrollmentModel> enrollments;
  final String emptyLabel;
  final Future<void> Function() onRefresh;
  final bool showExplore;

  @override
  Widget build(BuildContext context) {
    if (enrollments.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(emptyLabel, textAlign: TextAlign.center),
              if (showExplore) ...[
                AppSpacing.h16,
                FilledButton(
                  onPressed: () => context.go('/catalog'),
                  child: const Text('Xem Marketplace'),
                ),
              ],
            ],
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        itemCount: enrollments.length,
        itemBuilder: (context, index) =>
            EnrollmentCard(enrollment: enrollments[index]),
      ),
    );
  }
}
