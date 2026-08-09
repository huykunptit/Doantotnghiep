import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../providers/dashboard_provider.dart';
import '../../data/models/learning_path_model.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/error/friendly_error.dart';

class LearningPathScreen extends ConsumerWidget {
  const LearningPathScreen({super.key});
  static const routeName = '/learning-path';

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final learningPathAsync = ref.watch(studentLearningPathProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Chương trình đào tạo'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(studentLearningPathProvider),
          ),
        ],
      ),
      body: learningPathAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.error_outline, size: 48, color: AppColors.error),
                AppSpacing.h12,
                Text('Lỗi: ${friendlyErrorMessage(e)}', textAlign: TextAlign.center),
                AppSpacing.h16,
                FilledButton.icon(
                  onPressed: () => ref.invalidate(studentLearningPathProvider),
                  icon: const Icon(Icons.refresh),
                  label: const Text('Thử lại'),
                ),
              ],
            ),
          ),
        ),
        data: (path) {
          if (!path.hasCurriculum) {
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
                      child: const Icon(Icons.layers_outlined, size: 40, color: AppColors.primary400),
                    ),
                    AppSpacing.h20,
                    Text(
                      'Chưa gán chương trình đào tạo',
                      style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                      textAlign: TextAlign.center,
                    ),
                    AppSpacing.h8,
                    Text(
                      'Tài khoản của bạn chưa được gán lộ trình học tập hoặc lớp học hành chính.',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                        height: 1.5,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),
            );
          }

          final creditsEarned = path.totalCreditsEarned;
          final creditsRequired = path.totalCreditsRequired;
          final pct = creditsRequired > 0 ? (creditsEarned / creditsRequired) : 0.0;

          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            children: [
              // Overall Progress Card
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [AppColors.primary600, AppColors.primary400],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(20),
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.primary400.withValues(alpha: 0.3),
                      blurRadius: 20,
                      offset: const Offset(0, 6),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'CHƯƠNG TRÌNH HỌC TẬP',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        color: Colors.white70,
                        letterSpacing: 1.2,
                      ),
                    ),
                    AppSpacing.h8,
                    Text(
                      path.curriculumName ?? '—',
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        color: Colors.white,
                        letterSpacing: -0.3,
                      ),
                    ),
                    if (path.curriculumCode != null) ...[
                      AppSpacing.h4,
                      Text(
                        'Mã CTĐT: ${path.curriculumCode}',
                        style: const TextStyle(fontSize: 12, color: Colors.white70),
                      ),
                    ],
                    AppSpacing.h16,
                    const Divider(color: Colors.white24, height: 1),
                    AppSpacing.h16,
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Tiến độ tích lũy tín chỉ',
                          style: TextStyle(fontSize: 12, color: Colors.white70, fontWeight: FontWeight.w500),
                        ),
                        Text(
                          '$creditsEarned / $creditsRequired Tín chỉ',
                          style: const TextStyle(fontSize: 13, color: Colors.white, fontWeight: FontWeight.w700),
                        ),
                      ],
                    ),
                    AppSpacing.h8,
                    ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        value: pct,
                        backgroundColor: Colors.white.withValues(alpha: 0.2),
                        valueColor: const AlwaysStoppedAnimation<Color>(Colors.white),
                        minHeight: 6,
                      ),
                    ),
                    AppSpacing.h8,
                    Text(
                      '${(pct * 100).toStringAsFixed(0)}% Hoàn tất chương trình',
                      style: const TextStyle(fontSize: 11, color: Colors.white, fontWeight: FontWeight.w600),
                    ),
                  ],
                ),
              ),

              AppSpacing.h24,

              Text(
                'Lộ trình các học kỳ',
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.2,
                ),
              ),
              AppSpacing.h12,

              ...path.terms.map((term) => _TermAccordion(term: term, theme: theme)),
            ],
          );
        },
      ),
    );
  }
}

class _TermAccordion extends StatelessWidget {
  const _TermAccordion({required this.term, required this.theme});
  final LearningPathTermModel term;
  final ThemeData theme;

  int _getCompletedCount() {
    return term.courses.where((c) => c.status == 'completed').length;
  }

  @override
  Widget build(BuildContext context) {
    final isDark = theme.brightness == Brightness.dark;
    final completed = _getCompletedCount();

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
      child: ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: ExpansionTile(
          tilePadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
          title: Text(
            'Học kỳ ${term.termNumber}',
            style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w700),
          ),
          subtitle: Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: AppColors.primary50,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    '${term.credits} Tín chỉ',
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      color: AppColors.primary600,
                    ),
                  ),
                ),
                AppSpacing.w8,
                Text(
                  'Đạt: $completed / ${term.courses.length} môn',
                  style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                ),
              ],
            ),
          ),
          children: term.courses.map((course) => _CourseRow(course: course, theme: theme)).toList(),
        ),
      ),
    );
  }
}

class _CourseRow extends StatelessWidget {
  const _CourseRow({required this.course, required this.theme});
  final LearningPathCourseModel course;
  final ThemeData theme;

  Color _getStatusColor() {
    if (course.status == 'completed') return AppColors.success;
    if (course.status == 'learning') return AppColors.info;
    return AppColors.neutral400;
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
    final statusColor = _getStatusColor();
    final statusIcon = _getStatusIcon();
    final statusText = _getStatusText();

    return Column(
      children: [
        Divider(height: 1, color: theme.colorScheme.outlineVariant.withValues(alpha: 0.4)),
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
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: theme.colorScheme.outlineVariant.withValues(alpha: 0.3),
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: Text(
                                '${course.credits} TC',
                                style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold),
                              ),
                            ),
                            AppSpacing.w8,
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: course.isRequired
                                    ? AppColors.accent50
                                    : AppColors.primary50,
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: Text(
                                course.isRequired ? 'Bắt buộc' : 'Tự chọn',
                                style: TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.bold,
                                  color: course.isRequired ? AppColors.accent800 : AppColors.primary800,
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
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: statusColor.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      statusText,
                      style: TextStyle(
                        fontSize: 10,
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
                          backgroundColor: theme.colorScheme.outlineVariant.withValues(alpha: 0.3),
                          valueColor: AlwaysStoppedAnimation<Color>(statusColor),
                          minHeight: 4,
                        ),
                      ),
                    ),
                    AppSpacing.w8,
                    Text(
                      '${course.progress.toStringAsFixed(0)}%',
                      style: theme.textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w700, color: statusColor),
                    ),
                    if (course.finalScore != null) ...[
                      AppSpacing.w16,
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: AppColors.primary50,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          'Điểm: ${course.finalScore!.toStringAsFixed(1)}',
                          style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            color: AppColors.primary600,
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
                      style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
                    ),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: course.status == 'learning' ? AppColors.primary600 : null,
                      side: course.status == 'learning' ? const BorderSide(color: AppColors.primary400) : null,
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
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
