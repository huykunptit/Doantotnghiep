import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/my_courses_provider.dart';
import '../data/models/enrollment_model.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';

class MyCoursesPage extends ConsumerWidget {
  const MyCoursesPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final enrollmentsAsync = ref.watch(myEnrollmentsProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Khoá học của tôi'),
        centerTitle: false,
      ),
      body: enrollmentsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.error_outline, size: 48, color: AppColors.error),
                AppSpacing.h12,
                Text(e.toString(), textAlign: TextAlign.center),
                AppSpacing.h16,
                FilledButton.icon(
                  onPressed: () => ref.invalidate(myEnrollmentsProvider),
                  icon: const Icon(Icons.refresh),
                  label: const Text('Thử lại'),
                ),
              ],
            ),
          ),
        ),
        data: (enrollments) {
          if (enrollments.isEmpty) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(32),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 80, height: 80,
                      decoration: BoxDecoration(
                        color: AppColors.primary50,
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.school_outlined, size: 40, color: AppColors.primary400),
                    ),
                    AppSpacing.h20,
                    Text('Chưa có khoá học nào',
                        style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                    AppSpacing.h8,
                    Text('Khám phá khoá học và bắt đầu hành trình học tập của bạn.',
                        style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant, height: 1.5),
                        textAlign: TextAlign.center),
                    AppSpacing.h24,
                    FilledButton.icon(
                      onPressed: () => context.go('/catalog'),
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
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(myEnrollmentsProvider),
            child: ListView.builder(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              itemCount: enrollments.length,
              itemBuilder: (context, index) => _EnrollmentCard(enrollment: enrollments[index]),
            ),
          );
        },
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

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: isDark ? AppColors.darkSurface : Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: isDark ? AppColors.darkBorder : AppColors.neutral200),
        boxShadow: isDark ? [] : [
          BoxShadow(color: AppColors.neutral800.withValues(alpha: 0.05),
              blurRadius: 10, offset: const Offset(0, 2)),
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
              // Thumbnail
              ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: SizedBox(
                  width: 88, height: 72,
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
                    Text(course.title,
                        style: theme.textTheme.bodyMedium?.copyWith(
                            fontWeight: FontWeight.w700, height: 1.3),
                        maxLines: 2, overflow: TextOverflow.ellipsis),
                    AppSpacing.h8,
                    Row(
                      children: [
                        Icon(
                          course.courseMode == 'online'
                              ? Icons.wifi_rounded : Icons.location_on_outlined,
                          size: 13, color: AppColors.primary400,
                        ),
                        AppSpacing.w4,
                        Text(
                          course.courseMode == 'online' ? 'Online' : 'Offline',
                          style: TextStyle(fontSize: 11, color: AppColors.primary400, fontWeight: FontWeight.w600),
                        ),
                        if (course.creditValue != null) ...[
                          AppSpacing.w8,
                          Text('• ${course.creditValue} TC',
                              style: theme.textTheme.bodySmall?.copyWith(fontSize: 11,
                                  color: theme.colorScheme.onSurfaceVariant)),
                        ],
                      ],
                    ),
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
                                  progress >= 1.0 ? AppColors.success : AppColors.primary400),
                              minHeight: 5,
                            ),
                          ),
                        ),
                        AppSpacing.w8,
                        Text('${enrollment.progress.toStringAsFixed(0)}%',
                            style: TextStyle(
                              fontSize: 11, fontWeight: FontWeight.w700,
                              color: progress >= 1.0 ? AppColors.success : AppColors.primary400,
                            )),
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
