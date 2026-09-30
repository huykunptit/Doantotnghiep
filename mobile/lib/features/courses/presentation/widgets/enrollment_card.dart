import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:eript_lms/features/courses/data/models/enrollment_model.dart';
import '../../../../core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';

class EnrollmentCard extends StatelessWidget {
  const EnrollmentCard({super.key, required this.enrollment});
  final EnrollmentModel enrollment;

  @override
  Widget build(BuildContext context) {
    final course = enrollment.course;
    final theme = Theme.of(context);
    final progress = enrollment.progress / 100;
    final isAcademic = enrollment.enrollmentSource == 'academic';
    final window = enrollment.window;
    final dateFmt = DateFormat('dd/MM/yyyy');

    String? fmt(String? raw) {
      if (raw == null || raw.isEmpty) return null;
      final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})').firstMatch(raw);
      if (match == null) return raw;
      return dateFmt.format(
        DateTime(
          int.parse(match.group(1)!),
          int.parse(match.group(2)!),
          int.parse(match.group(3)!),
        ),
      );
    }

    final start = fmt(enrollment.startsAt);
    final end = fmt(enrollment.endsAt);

    final (badgeLabel, badgeBg, badgeFg) = switch (window) {
      CourseWindow.upcoming => (
        'Sắp tới',
        context.sem.warningBg,
        context.sem.warningFg,
      ),
      CourseWindow.expired => (
        'Đã hết hạn',
        context.cs.surfaceContainerHigh,
        context.cs.onSurfaceVariant,
      ),
      CourseWindow.current => (
        'Đang học',
        context.sem.successBg,
        context.sem.successFg,
      ),
    };

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: context.cs.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: context.cs.outlineVariant),
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
                          errorWidget: (_, _, _) => _placeholder(context),
                        )
                      : _placeholder(context),
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
                          padding: const EdgeInsets.symmetric(
                            horizontal: 6,
                            vertical: 2,
                          ),
                          decoration: BoxDecoration(
                            color: badgeBg,
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: Text(
                            badgeLabel,
                            style: TextStyle(
                              fontSize: 12,
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
                          padding: const EdgeInsets.symmetric(
                            horizontal: 6,
                            vertical: 2,
                          ),
                          decoration: BoxDecoration(
                            color: isAcademic
                                ? context.cs.primaryContainer
                                : context.sem.warningBg,
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            isAcademic ? 'CTĐT' : 'Marketplace',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                              color: isAcademic
                                  ? context.cs.primary
                                  : context.sem.warningFg,
                            ),
                          ),
                        ),
                        if (enrollment.termName != null &&
                            enrollment.termName!.isNotEmpty)
                          Text(
                            enrollment.termName!,
                            style: theme.textTheme.bodySmall?.copyWith(
                              fontSize: 12,
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
                          fontSize: 12,
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
                              backgroundColor: context.cs.surfaceContainerLow,
                              valueColor: AlwaysStoppedAnimation<Color>(
                                progress >= 1.0
                                    ? context.sem.success
                                    : context.cs.primary,
                              ),
                              minHeight: 5,
                            ),
                          ),
                        ),
                        AppSpacing.w8,
                        Text(
                          '${enrollment.progress.toStringAsFixed(0)}%',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: progress >= 1.0
                                ? context.sem.success
                                : context.cs.primary,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right, size: 18, color: context.cs.outline),
            ],
          ),
        ),
      ),
    );
  }

  Widget _placeholder(BuildContext context) {
    return Container(
      color: context.cs.primaryContainer,
      child: Icon(
        Icons.play_circle_outline_rounded,
        color: context.cs.primary,
        size: 28,
      ),
    );
  }
}
