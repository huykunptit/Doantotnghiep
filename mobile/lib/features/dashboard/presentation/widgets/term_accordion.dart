import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:eript_lms/features/dashboard/data/models/learning_path_model.dart';
import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';

class TermAccordion extends StatelessWidget {
  const TermAccordion({super.key, required this.term, required this.theme});
  final LearningPathTermModel term;
  final ThemeData theme;

  int _getCompletedCount() {
    return term.courses.where((c) => c.status == 'completed').length;
  }

  @override
  Widget build(BuildContext context) {
    final completed = _getCompletedCount();

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: context.cs.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: context.cs.outlineVariant),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: ExpansionTile(
          tilePadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
          title: Text(
            'Học kỳ ${term.termNumber}',
            style: theme.textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.w700,
            ),
          ),
          subtitle: Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 2,
                  ),
                  decoration: BoxDecoration(
                    color: context.cs.primaryContainer,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    '${term.credits} Tín chỉ',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: context.cs.primary,
                    ),
                  ),
                ),
                AppSpacing.w8,
                Text(
                  'Đạt: $completed / ${term.courses.length} môn',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
          children: term.courses
              .map((course) => CourseRow(course: course, theme: theme))
              .toList(),
        ),
      ),
    );
  }
}

class CourseRow extends StatelessWidget {
  const CourseRow({super.key, required this.course, required this.theme});
  final LearningPathCourseModel course;
  final ThemeData theme;

  Color _getStatusColor(BuildContext context) {
    if (course.status == 'completed') return context.sem.success;
    if (course.status == 'learning') return context.sem.info;
    return context.cs.outline;
  }

  IconData _getStatusIcon() {
    if (course.status == 'completed') return Icons.check_circle_rounded;
    if (course.status == 'learning') return Icons.pending_rounded;
    return Icons.help_outline_rounded;
  }

  String _getStatusText() {
    if (course.status == 'completed') return 'Đã hoàn thành';
    if (course.status == 'learning') return 'Đang học';
    return 'Chưa đăng ký';
  }

  @override
  Widget build(BuildContext context) {
    final statusColor = _getStatusColor(context);
    final statusIcon = _getStatusIcon();
    final statusText = _getStatusText();

    return Column(
      children: [
        Divider(
          height: 1,
          color: theme.colorScheme.outlineVariant.withValues(alpha: 0.4),
        ),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(statusIcon, color: statusColor, size: 18),
                  AppSpacing.w8,
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          course.title,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                            height: 1.3,
                          ),
                        ),
                        AppSpacing.h8,
                        Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 6,
                                vertical: 2,
                              ),
                              decoration: BoxDecoration(
                                color: theme.colorScheme.outlineVariant
                                    .withValues(alpha: 0.3),
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: Text(
                                '${course.credits} TC',
                                style: const TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                            AppSpacing.w8,
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 6,
                                vertical: 2,
                              ),
                              decoration: BoxDecoration(
                                color: course.isRequired
                                    ? context.cs.tertiaryContainer
                                    : context.cs.primaryContainer,
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: Text(
                                course.isRequired ? 'Bắt buộc' : 'Tự chọn',
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.bold,
                                  color: course.isRequired
                                      ? context.cs.onTertiaryContainer
                                      : context.cs.onPrimaryContainer,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  AppSpacing.w8,
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: statusColor.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      statusText,
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: statusColor,
                      ),
                    ),
                  ),
                ],
              ),
              if (course.status != 'not_started') ...[
                AppSpacing.h12,
                Row(
                  children: [
                    Expanded(
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(4),
                        child: LinearProgressIndicator(
                          value: course.progress / 100,
                          backgroundColor: theme.colorScheme.outlineVariant
                              .withValues(alpha: 0.3),
                          valueColor: AlwaysStoppedAnimation<Color>(
                            statusColor,
                          ),
                          minHeight: 4,
                        ),
                      ),
                    ),
                    AppSpacing.w8,
                    Text(
                      '${course.progress.toStringAsFixed(0)}%',
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: statusColor,
                      ),
                    ),
                    if (course.finalScore != null) ...[
                      AppSpacing.w16,
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: context.cs.primaryContainer,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          'Điểm: ${course.finalScore!.toStringAsFixed(1)}',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                            color: context.cs.primary,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ],
              AppSpacing.h12,
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  OutlinedButton.icon(
                    onPressed: () {
                      context.push('/courses/${course.id}');
                    },
                    icon: Icon(
                      course.status == 'completed'
                          ? Icons.restart_alt_rounded
                          : course.status == 'learning'
                          ? Icons.play_arrow_rounded
                          : Icons.info_outline_rounded,
                      size: 14,
                    ),
                    label: Text(
                      course.status == 'completed'
                          ? 'Ôn tập học phần'
                          : course.status == 'learning'
                          ? 'Học tiếp'
                          : 'Xem chi tiết',
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: course.status == 'learning'
                          ? context.cs.primary
                          : null,
                      side: course.status == 'learning'
                          ? BorderSide(color: context.cs.primary)
                          : null,
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 6,
                      ),
                      minimumSize: Size.zero,
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }
}
