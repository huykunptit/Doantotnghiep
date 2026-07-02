import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/dashboard_provider.dart';
import '../../data/models/transcript_model.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';

class TranscriptScreen extends ConsumerWidget {
  const TranscriptScreen({super.key});
  static const routeName = '/transcript';

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final transcriptAsync = ref.watch(studentTranscriptProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Bảng điểm'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(studentTranscriptProvider),
          ),
        ],
      ),
      body: transcriptAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.error_outline, size: 48, color: AppColors.error),
                AppSpacing.h12,
                Text('Lỗi: $e', textAlign: TextAlign.center),
                AppSpacing.h16,
                FilledButton.icon(
                  onPressed: () => ref.invalidate(studentTranscriptProvider),
                  icon: const Icon(Icons.refresh),
                  label: const Text('Thử lại'),
                ),
              ],
            ),
          ),
        ),
        data: (transcript) {
          if (transcript.terms.isEmpty) {
            return Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.receipt_long_outlined, size: 64, color: theme.colorScheme.outline),
                  AppSpacing.h16,
                  Text('Chưa có thông tin bảng điểm.',
                      style: theme.textTheme.bodyLarge?.copyWith(fontWeight: FontWeight.w600)),
                ],
              ),
            );
          }

          final gpa = transcript.overallGpa ?? 0.0;
          final totalCredits = transcript.terms.fold<int>(0, (sum, t) => sum + t.credits);

          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            children: [
              // GPA hero card
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
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('ĐIỂM TRUNG BÌNH TÍCH LŨY',
                              style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700,
                                  color: Colors.white70, letterSpacing: 1.2)),
                          AppSpacing.h8,
                          Text(gpa > 0 ? gpa.toStringAsFixed(2) : '—',
                              style: const TextStyle(fontSize: 40, fontWeight: FontWeight.w900,
                                  color: Colors.white, letterSpacing: -1)),
                          AppSpacing.h4,
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.2),
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Text('$totalCredits tín chỉ tích lũy',
                                style: const TextStyle(fontSize: 12, color: Colors.white,
                                    fontWeight: FontWeight.w600)),
                          ),
                        ],
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.15),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.school_rounded, size: 36, color: Colors.white),
                    ),
                  ],
                ),
              ),

              AppSpacing.h24,

              Text('Chi tiết các học kỳ',
                  style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w800, letterSpacing: -0.2)),
              AppSpacing.h12,

              ...transcript.terms.map((term) => _TermCard(term: term, theme: theme)),
            ],
          );
        },
      ),
    );
  }
}

class _TermCard extends StatelessWidget {
  const _TermCard({required this.term, required this.theme});
  final TranscriptTermModel term;
  final ThemeData theme;

  @override
  Widget build(BuildContext context) {
    final isDark = theme.brightness == Brightness.dark;

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
      child: ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: ExpansionTile(
          tilePadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
          title: Text(term.name,
              style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w700)),
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
                  child: Text('GPA: ${term.gpa?.toStringAsFixed(2) ?? "N/A"}',
                      style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700,
                          color: AppColors.primary600)),
                ),
                AppSpacing.w8,
                Text('${term.credits} TC',
                    style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant)),
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
  final TranscriptCourseModel course;
  final ThemeData theme;

  @override
  Widget build(BuildContext context) {
    final hasScore = course.finalScore != null;
    final passed = hasScore && course.finalScore! >= 50.0;

    return Column(
      children: [
        Divider(height: 1, color: theme.colorScheme.outlineVariant.withValues(alpha: 0.4)),
        ExpansionTile(
          tilePadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 2),
          title: Text(course.title,
              style: theme.textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w600)),
          trailing: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: hasScore
                      ? (passed ? AppColors.primary50 : AppColors.error.withValues(alpha: 0.08))
                      : AppColors.neutral100,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  hasScore ? course.finalScore!.toStringAsFixed(1) : '—',
                  style: TextStyle(
                    fontWeight: FontWeight.w800, fontSize: 13,
                    color: hasScore
                        ? (passed ? AppColors.primary600 : AppColors.error)
                        : AppColors.neutral400,
                  ),
                ),
              ),
              const Icon(Icons.expand_more, size: 20),
            ],
          ),
          children: [
            if (course.entries.isEmpty)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                child: Text('Chưa có điểm chi tiết.',
                    style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant)),
              )
            else
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
                child: Table(
                  columnWidths: const {
                    0: FlexColumnWidth(3),
                    1: FlexColumnWidth(1),
                    2: FlexColumnWidth(1),
                    3: FlexColumnWidth(1),
                  },
                  children: [
                    TableRow(
                      decoration: BoxDecoration(
                        border: Border(bottom: BorderSide(
                            color: theme.colorScheme.outlineVariant.withValues(alpha: 0.4))),
                      ),
                      children: ['Thành phần', 'Trọng số', 'Max', 'Điểm']
                          .map((h) => Padding(
                                padding: const EdgeInsets.symmetric(vertical: 6),
                                child: Text(h,
                                    style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700,
                                        color: AppColors.neutral400),
                                    textAlign: h == 'Thành phần' ? TextAlign.left : TextAlign.center),
                              ))
                          .toList(),
                    ),
                    ...course.entries.map((entry) {
                      final weightStr = entry.weight != null
                          ? '${(entry.weight! * 100).toStringAsFixed(0)}%' : '—';
                      return TableRow(children: [
                        _cell(entry.component ?? '—', alignLeft: true),
                        _cell(weightStr),
                        _cell(entry.maxScore?.toStringAsFixed(0) ?? '—'),
                        _cell(entry.score?.toStringAsFixed(1) ?? '—',
                            bold: true,
                            color: entry.score != null ? theme.colorScheme.primary : null),
                      ]);
                    }),
                  ],
                ),
              ),
          ],
        ),
      ],
    );
  }

  Widget _cell(String text, {bool alignLeft = false, bool bold = false, Color? color}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Text(text,
          textAlign: alignLeft ? TextAlign.left : TextAlign.center,
          style: TextStyle(fontSize: 12, fontWeight: bold ? FontWeight.w700 : FontWeight.normal, color: color)),
    );
  }
}
