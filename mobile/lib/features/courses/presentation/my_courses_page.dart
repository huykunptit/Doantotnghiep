import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../providers/my_courses_provider.dart';
import '../data/models/enrollment_model.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/widgets/error_state.dart';

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
          title: const Text('Khoá học của tôi'),
          centerTitle: false,
          bottom: const TabBar(
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            tabs: [
              Tab(text: 'Đang học'),
              Tab(text: 'Sắp tới'),
              Tab(text: 'Đã hết hạn'),
              Tab(text: 'Tất cả các khoá'),
            ],
          ),
        ),
        body: enrollmentsAsync.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => ErrorStateWidget(
            error: e,
            onRetry: () => ref.invalidate(myEnrollmentsProvider),
          ),
          data: (enrollments) {
            if (enrollments.isEmpty) {
              return _EmptyState(
                theme: theme,
                onExplore: () => context.go('/catalog'),
              );
            }

            final buckets = _bucket(enrollments);

            return TabBarView(
              children: [
                _EnrollmentList(
                  enrollments: buckets.current,
                  emptyLabel: 'Không có khóa đang học trong kỳ này.',
                  onRefresh: () async => ref.invalidate(myEnrollmentsProvider),
                ),
                _EnrollmentList(
                  enrollments: buckets.upcoming,
                  emptyLabel: 'Chưa có khóa sắp tới theo CTĐT.',
                  onRefresh: () async => ref.invalidate(myEnrollmentsProvider),
                ),
                _EnrollmentList(
                  enrollments: buckets.expired,
                  emptyLabel: 'Chưa có khóa đã hết hạn.',
                  onRefresh: () async => ref.invalidate(myEnrollmentsProvider),
                ),
                _EnrollmentList(
                  enrollments: buckets.all,
                  emptyLabel: 'Chưa có khoá học nào',
                  onRefresh: () async => ref.invalidate(myEnrollmentsProvider),
                  showExplore: true,
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _Buckets {
  const _Buckets({
    required this.current,
    required this.upcoming,
    required this.expired,
  });

  final List<EnrollmentModel> current;
  final List<EnrollmentModel> upcoming;
  final List<EnrollmentModel> expired;

  List<EnrollmentModel> get all => [...current, ...upcoming, ...expired];
}

_Buckets _bucket(List<EnrollmentModel> enrollments) {
  final current = enrollments.where((e) => e.window == CourseWindow.current).toList();
  final upcoming = enrollments.where((e) => e.window == CourseWindow.upcoming).toList();
  final expired = enrollments.where((e) => e.window == CourseWindow.expired).toList();

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
  upcoming.sort((a, b) => (dayMs(a.startsAt) ?? 1 << 62).compareTo(dayMs(b.startsAt) ?? 1 << 62));
  expired.sort((a, b) => (dayMs(b.endsAt) ?? 0).compareTo(dayMs(a.endsAt) ?? 0));

  return _Buckets(current: current, upcoming: upcoming, expired: expired);
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.theme, required this.onExplore});
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
              decoration: const BoxDecoration(
                color: AppColors.primary50,
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.school_outlined, size: 40, color: AppColors.primary400),
            ),
            AppSpacing.h20,
            Text(
              'Chưa có khoá học nào',
              style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
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
                backgroundColor: AppColors.primary400,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _EnrollmentList extends StatelessWidget {
  const _EnrollmentList({
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
        itemBuilder: (context, index) => _EnrollmentCard(enrollment: enrollments[index]),
      ),
    );
  }
}

class _EnrollmentCard extends StatelessWidget {
  const _EnrollmentCard({required this.enrollment});
  final EnrollmentModel enrollment;

  @override
  Widget build(BuildContext context) {
    final course = enrollment.course;
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final progress = enrollment.progress / 100;
    final isAcademic = enrollment.enrollmentSource == 'academic';
    final window = enrollment.window;
    final dateFmt = DateFormat('dd/MM/yyyy');

    String? fmt(String? raw) {
      if (raw == null || raw.isEmpty) return null;
      final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})').firstMatch(raw);
      if (match == null) return raw;
      return dateFmt.format(DateTime(
        int.parse(match.group(1)!),
        int.parse(match.group(2)!),
        int.parse(match.group(3)!),
      ));
    }

    final start = fmt(enrollment.startsAt);
    final end = fmt(enrollment.endsAt);

    final (badgeLabel, badgeBg, badgeFg) = switch (window) {
      CourseWindow.upcoming => ('Sắp tới', const Color(0xFFFEF3C7), const Color(0xFFA16207)),
      CourseWindow.expired => ('Đã hết hạn', const Color(0xFFE2E8F0), const Color(0xFF475569)),
      CourseWindow.current => ('Đang học', const Color(0xFFDCFCE7), const Color(0xFF15803D)),
    };

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: isDark ? AppColors.darkSurface : Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: isDark ? AppColors.darkBorder : AppColors.neutral200),
        boxShadow: isDark
            ? []
            : [
                BoxShadow(
                  color: AppColors.neutral800.withValues(alpha: 0.05),
                  blurRadius: 10,
                  offset: const Offset(0, 2),
                ),
              ],
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () => context.push('/courses/${course.id}'),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: SizedBox(
                  width: 88,
                  height: 72,
                  child: course.thumbnail != null
                      ? CachedNetworkImage(
                          imageUrl: course.thumbnail!,
                          fit: BoxFit.cover,
                          errorWidget: (_, _, _) => _placeholder(),
                        )
                      : _placeholder(),
                ),
              ),
              AppSpacing.w12,
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(
                            course.title,
                            style: theme.textTheme.bodyMedium?.copyWith(
                              fontWeight: FontWeight.w700,
                              height: 1.3,
                            ),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: badgeBg,
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: Text(
                            badgeLabel,
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w700,
                              color: badgeFg,
                            ),
                          ),
                        ),
                      ],
                    ),
                    AppSpacing.h8,
                    Wrap(
                      spacing: 8,
                      runSpacing: 4,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: isAcademic ? AppColors.primary50 : Colors.orange.shade50,
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            isAcademic ? 'CTĐT' : 'Marketplace',
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w700,
                              color: isAcademic ? AppColors.primary400 : Colors.orange.shade800,
                            ),
                          ),
                        ),
                        if (enrollment.termName != null && enrollment.termName!.isNotEmpty)
                          Text(
                            enrollment.termName!,
                            style: theme.textTheme.bodySmall?.copyWith(
                              fontSize: 11,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                      ],
                    ),
                    if (start != null || end != null) ...[
                      AppSpacing.h4,
                      Text(
                        'Bắt đầu: ${start ?? '—'}  ·  Kết thúc: ${end ?? '—'}',
                        style: theme.textTheme.bodySmall?.copyWith(
                          fontSize: 11,
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                      ),
                    ],
                    AppSpacing.h8,
                    Row(
                      children: [
                        Expanded(
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(4),
                            child: LinearProgressIndicator(
                              value: progress,
                              backgroundColor: AppColors.neutral100,
                              valueColor: AlwaysStoppedAnimation<Color>(
                                progress >= 1.0 ? AppColors.success : AppColors.primary400,
                              ),
                              minHeight: 5,
                            ),
                          ),
                        ),
                        AppSpacing.w8,
                        Text(
                          '${enrollment.progress.toStringAsFixed(0)}%',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                            color: progress >= 1.0 ? AppColors.success : AppColors.primary400,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right, size: 18, color: AppColors.neutral400),
            ],
          ),
        ),
      ),
    );
  }

  Widget _placeholder() {
    return Container(
      color: AppColors.primary50,
      child: const Icon(Icons.play_circle_outline_rounded, color: AppColors.primary200, size: 28),
    );
  }
}
