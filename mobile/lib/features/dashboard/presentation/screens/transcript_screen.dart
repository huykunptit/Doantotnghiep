import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/dashboard_provider.dart';
import '../../data/models/transcript_model.dart';
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
        title: const Text('Bảng điểm học tập'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
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
                const Icon(Icons.error_outline, size: 48, color: Colors.red),
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
                  const Text('Chưa có thông tin bảng điểm.'),
                ],
              ),
            );
          }

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              // Summary overall GPA card
              _buildOverallGpaCard(context, transcript),
              AppSpacing.h20,

              Text(
                'Chi tiết các học kỳ',
                style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
              ),
              AppSpacing.h12,

              // Terms list
              ...transcript.terms.map((term) => _buildTermCard(context, term)),
            ],
          );
        },
      ),
    );
  }

  Widget _buildOverallGpaCard(BuildContext context, TranscriptModel transcript) {
    final theme = Theme.of(context);
    final gpa = transcript.overallGpa ?? 0.0;
    
    // Determine total credits from all terms
    final totalCredits = transcript.terms.fold<int>(0, (sum, term) => sum + term.credits);

    return Card(
      color: theme.colorScheme.primaryContainer,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(color: theme.colorScheme.primary.withValues(alpha: 0.2)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'ĐIỂM TRUNG BÌNH TÍCH LŨY',
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                      color: theme.colorScheme.onPrimaryContainer,
                      letterSpacing: 1.2,
                    ),
                  ),
                  AppSpacing.h4,
                  Text(
                    gpa > 0 ? gpa.toStringAsFixed(2) : 'Chưa có',
                    style: theme.textTheme.headlineLarge?.copyWith(
                      fontWeight: FontWeight.w900,
                      color: theme.colorScheme.onPrimaryContainer,
                    ),
                  ),
                  AppSpacing.h8,
                  Text(
                    'Tổng tín chỉ tích lũy: $totalCredits',
                    style: TextStyle(
                      color: theme.colorScheme.onPrimaryContainer.withValues(alpha: 0.8),
                      fontSize: 13,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: const BoxDecoration(
                color: Colors.white24,
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.school,
                size: 32,
                color: theme.colorScheme.onPrimaryContainer,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTermCard(BuildContext context, TranscriptTermModel term) {
    final theme = Theme.of(context);

    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      elevation: 1,
      clipBehavior: Clip.antiAlias,
      child: ExpansionTile(
        title: Text(
          term.name,
          style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
        ),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: theme.colorScheme.secondaryContainer,
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(
                  'GPA: ${term.gpa?.toStringAsFixed(2) ?? "N/A"}',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                    color: theme.colorScheme.onSecondaryContainer,
                  ),
                ),
              ),
              AppSpacing.w12,
              Text(
                '${term.credits} tín chỉ',
                style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
              ),
            ],
          ),
        ),
        children: term.courses.map((course) => _buildCourseGradeTile(context, course)).toList(),
      ),
    );
  }

  Widget _buildCourseGradeTile(BuildContext context, TranscriptCourseModel course) {
    final theme = Theme.of(context);
    final hasScore = course.finalScore != null;

    return Container(
      decoration: BoxDecoration(
        border: Border(top: BorderSide(color: theme.colorScheme.outlineVariant.withValues(alpha: 0.5))),
      ),
      child: ExpansionTile(
        title: Text(
          course.title,
          style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
        ),
        trailing: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          decoration: BoxDecoration(
            color: hasScore
                ? (course.finalScore! >= 50.0 ? Colors.green.shade50 : Colors.red.shade50)
                : Colors.grey.shade100,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(
              color: hasScore
                  ? (course.finalScore! >= 50.0 ? Colors.green.shade200 : Colors.red.shade200)
                  : Colors.grey.shade300,
            ),
          ),
          child: Text(
            hasScore ? course.finalScore!.toStringAsFixed(1) : 'Chưa có',
            style: TextStyle(
              fontWeight: FontWeight.bold,
              fontSize: 13,
              color: hasScore
                  ? (course.finalScore! >= 50.0 ? Colors.green.shade800 : Colors.red.shade800)
                  : Colors.grey.shade600,
            ),
          ),
        ),
        children: [
          if (course.entries.isEmpty)
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'Chưa có điểm chi tiết cho học phần này.',
                style: TextStyle(fontSize: 12, color: Colors.grey),
              ),
            )
          else
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: Table(
                columnWidths: const {
                  0: FlexColumnWidth(3),
                  1: FlexColumnWidth(1),
                  2: FlexColumnWidth(1),
                  3: FlexColumnWidth(1),
                },
                children: [
                  // Table Header
                  TableRow(
                    children: [
                      _buildTableHeaderCell('Thành phần'),
                      _buildTableHeaderCell('Trọng số'),
                      _buildTableHeaderCell('Max'),
                      _buildTableHeaderCell('Điểm'),
                    ],
                  ),
                  // Table Data Rows
                  ...course.entries.map((entry) {
                    final weightPercent = entry.weight != null ? '${(entry.weight! * 100).toStringAsFixed(0)}%' : '-';
                    return TableRow(
                      children: [
                        _buildTableCell(entry.component ?? 'N/A', alignLeft: true),
                        _buildTableCell(weightPercent),
                        _buildTableCell(entry.maxScore?.toStringAsFixed(0) ?? '-'),
                        _buildTableCell(
                          entry.score?.toStringAsFixed(1) ?? '-',
                          bold: true,
                          color: entry.score != null ? theme.colorScheme.primary : null,
                        ),
                      ],
                    );
                  }),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildTableHeaderCell(String text) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Text(
        text,
        textAlign: text == 'Thành phần' ? TextAlign.left : TextAlign.center,
        style: const TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.bold,
          color: Colors.grey,
        ),
      ),
    );
  }

  Widget _buildTableCell(
    String text, {
    bool alignLeft = false,
    bool bold = false,
    Color? color,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Text(
        text,
        textAlign: alignLeft ? TextAlign.left : TextAlign.center,
        style: TextStyle(
          fontSize: 12,
          fontWeight: bold ? FontWeight.bold : FontWeight.normal,
          color: color,
        ),
      ),
    );
  }
}
