import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/dashboard_provider.dart';
import '../../data/models/transcript_model.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/error/friendly_error.dart';

class TranscriptScreen extends ConsumerWidget {
  const TranscriptScreen({super.key});
  static const routeName = '/transcript';

  String _fmtDate(String? raw) {
    if (raw == null || raw.isEmpty) return '—';
    final d = DateTime.tryParse(raw);
    if (d == null) return raw;
    return '${d.day.toString().padLeft(2, '0')}/${d.month.toString().padLeft(2, '0')}/${d.year}';
  }

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
                Text('Lỗi: ${friendlyErrorMessage(e)}', textAlign: TextAlign.center),
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
          if (transcript.results.isEmpty) {
            return Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.receipt_long_outlined, size: 64, color: theme.colorScheme.outline),
                  AppSpacing.h16,
                  Text(
                    'Chưa có kết quả thi trên LMS.',
                    style: theme.textTheme.bodyLarge?.copyWith(fontWeight: FontWeight.w600),
                  ),
                ],
              ),
            );
          }

          final s = transcript.summary;
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            children: [
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [AppColors.primary600, AppColors.primary400],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'KẾT QUẢ THI TRÊN LMS',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        color: Colors.white70,
                        letterSpacing: 1.2,
                      ),
                    ),
                    AppSpacing.h16,
                    GridView.count(
                      crossAxisCount: 2,
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      mainAxisSpacing: 8,
                      crossAxisSpacing: 8,
                      childAspectRatio: 2.4,
                      children: [
                        _StatChip(label: 'Kỳ thi', value: '${s.totalExams}'),
                        _StatChip(label: 'Đã thi', value: '${s.taken}'),
                        _StatChip(label: 'Đạt', value: '${s.passed}'),
                        _StatChip(
                          label: 'Điểm TB',
                          value: s.averageScore != null ? s.averageScore!.toStringAsFixed(1) : '—',
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              AppSpacing.h24,
              Text(
                'Chi tiết các kỳ thi',
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.2,
                ),
              ),
              AppSpacing.h12,
              ...transcript.results.map(
                (r) => _ExamResultCard(result: r, dateLabel: _fmtDate(r.examDate ?? r.takenAt)),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _StatChip extends StatelessWidget {
  const _StatChip({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(value, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 16)),
          Text(label, style: const TextStyle(color: Colors.white70, fontSize: 10, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}

class _ExamResultCard extends StatelessWidget {
  const _ExamResultCard({required this.result, required this.dateLabel});
  final TranscriptExamResult result;
  final String dateLabel;

  ({String? type, String title, String? code}) _splitTitle() {
    var rest = result.examTitle.trim();
    String? code;
    final codeMatch = RegExp(r'\s*\(([^)]+)\)\s*$').firstMatch(rest);
    if (codeMatch != null) {
      code = codeMatch.group(1);
      rest = rest.substring(0, codeMatch.start).trim();
    }
    final parts = rest.split(' - ');
    if (parts.length >= 2) {
      return (type: parts.first.trim(), title: parts.sublist(1).join(' - ').trim(), code: code);
    }
    return (type: result.examType, title: rest, code: code);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final hasScore = result.score != null;
    final passed = result.passed == true;
    final split = _splitTitle();
    final displayTitle = split.title.isNotEmpty
        ? split.title
        : (result.courseTitle ?? result.examTitle);

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDark ? AppColors.darkSurface : Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: isDark ? AppColors.darkBorder : AppColors.neutral200),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (split.type != null && split.type!.isNotEmpty) ...[
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: AppColors.primary50,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      split.type!,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: AppColors.primary600,
                      ),
                    ),
                  ),
                  AppSpacing.h8,
                ],
                Text(
                  displayTitle,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w700, height: 1.3),
                ),
                if (result.courseTitle != null && result.courseTitle != displayTitle) ...[
                  AppSpacing.h4,
                  Text(
                    result.courseTitle!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
                AppSpacing.h8,
                Row(
                  children: [
                    Icon(Icons.event_outlined, size: 13, color: theme.colorScheme.onSurfaceVariant),
                    AppSpacing.w4,
                    Text(
                      dateLabel,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          AppSpacing.w12,
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                hasScore ? result.score!.toStringAsFixed(1) : '—',
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w900,
                  color: !hasScore
                      ? AppColors.neutral400
                      : (passed ? AppColors.primary400 : AppColors.error),
                ),
              ),
              AppSpacing.h4,
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: !hasScore
                      ? AppColors.neutral100
                      : (passed ? const Color(0xFFDCFCE7) : const Color(0xFFFEE2E2)),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  !hasScore ? 'Chưa thi' : (passed ? 'Đạt' : 'Chưa đạt'),
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                    color: !hasScore
                        ? AppColors.neutral600
                        : (passed ? const Color(0xFF166534) : const Color(0xFFB91C1C)),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
