import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:eript_lms/features/exams/data/models/exam_list_model.dart';
import 'package:eript_lms/core/theme/theme_context.dart';

class ExamCard extends StatelessWidget {
  const ExamCard({super.key, required this.exam});
  final ExamListItemModel exam;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(color: theme.colorScheme.outlineVariant),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: exam.isLive
            ? () => context.push('/exam/${exam.id}')
            : exam.isDone && exam.myAttempt != null
            ? () => context.push('/exam-result/${exam.myAttempt!.id}')
            : null,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        ExamListStatusChip(exam: exam),
                        const SizedBox(height: 8),
                        Text(
                          exam.title,
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        if (exam.course != null) ...[
                          const SizedBox(height: 4),
                          Text(
                            exam.course!.title,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: theme.colorScheme.onSurfaceVariant,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  if (exam.isDone && exam.myAttempt?.score != null)
                    ExamListScoreBadge(
                      score: exam.myAttempt!.score!,
                      passed: exam.myAttempt!.passed ?? false,
                    ),
                ],
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  ExamListInfoChip(
                    icon: Icons.timer_outlined,
                    label: exam.duration != null
                        ? '${exam.duration} phút'
                        : 'Không giới hạn',
                  ),
                  ExamListInfoChip(
                    icon: Icons.check_circle_outline,
                    label: 'Đạt: ${exam.passScore}%',
                  ),
                  if (exam.proctoringEnabled)
                    const ExamListInfoChip(
                      icon: Icons.face_outlined,
                      label: 'Check khuôn mặt',
                    ),
                ],
              ),
              if (exam.startTime != null || exam.endTime != null) ...[
                const SizedBox(height: 8),
                ExamListTimeRow(start: exam.startTime, end: exam.endTime),
              ],
              if (exam.isLive || (exam.isDone && exam.myAttempt != null)) ...[
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: exam.isLive
                        ? () => context.push('/exam/${exam.id}')
                        : () => context.push(
                            '/exam-result/${exam.myAttempt!.id}',
                          ),
                    style: FilledButton.styleFrom(
                      backgroundColor: exam.isLive
                          ? context.cs.primary
                          : theme.colorScheme.secondaryContainer,
                      foregroundColor: exam.isLive
                          ? Colors.white
                          : theme.colorScheme.onSecondaryContainer,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                    child: Text(
                      exam.isLive ? 'Vào thi ngay' : 'Xem kết quả chi tiết',
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class ExamListStatusChip extends StatelessWidget {
  const ExamListStatusChip({super.key, required this.exam});
  final ExamListItemModel exam;

  @override
  Widget build(BuildContext context) {
    late Color bg;
    late Color fg;
    late String label;

    if (exam.isLive) {
      bg = context.sem.success.withValues(alpha: 0.12);
      fg = context.sem.success;
      label = '● Đang mở';
    } else if (exam.isDone) {
      final passed = exam.myAttempt?.passed ?? false;
      bg = (passed ? context.sem.success : context.cs.error).withValues(
        alpha: 0.1,
      );
      fg = passed ? context.sem.success : context.cs.error;
      label = passed ? '✓ Đã đạt' : '✗ Chưa đạt';
    } else if (exam.isUpcoming) {
      bg = context.sem.warning.withValues(alpha: 0.12);
      fg = context.sem.warning;
      label = '○ Sắp diễn ra';
    } else {
      bg = context.cs.surfaceContainerLow;
      fg = context.cs.onSurfaceVariant;
      label = exam.status;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        label,
        style: TextStyle(color: fg, fontSize: 12, fontWeight: FontWeight.w700),
      ),
    );
  }
}

class ExamListScoreBadge extends StatelessWidget {
  const ExamListScoreBadge({
    super.key,
    required this.score,
    required this.passed,
  });
  final double score;
  final bool passed;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: (passed ? context.sem.success : context.cs.error).withValues(
          alpha: 0.1,
        ),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        children: [
          Text(
            score.toStringAsFixed(1),
            style: TextStyle(
              fontWeight: FontWeight.w800,
              fontSize: 18,
              color: passed ? context.sem.success : context.cs.error,
            ),
          ),
          Text(
            'điểm',
            style: TextStyle(
              fontSize: 12,
              color: passed ? context.sem.success : context.cs.error,
            ),
          ),
        ],
      ),
    );
  }
}

class ExamListInfoChip extends StatelessWidget {
  const ExamListInfoChip({super.key, required this.icon, required this.label});
  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: theme.colorScheme.onSurfaceVariant),
          const SizedBox(width: 4),
          Text(
            label,
            style: TextStyle(
              fontSize: 12,
              color: theme.colorScheme.onSurfaceVariant,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }
}

class ExamListTimeRow extends StatelessWidget {
  const ExamListTimeRow({super.key, this.start, this.end});
  final String? start;
  final String? end;

  String _fmt(String? iso) {
    if (iso == null) return '--';
    try {
      final dt = DateTime.parse(iso).toLocal();
      return DateFormat('dd/MM/yyyy HH:mm').format(dt);
    } catch (_) {
      return iso;
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      children: [
        Icon(
          Icons.schedule,
          size: 13,
          color: theme.colorScheme.onSurfaceVariant,
        ),
        const SizedBox(width: 4),
        Text(
          '${_fmt(start)} — ${_fmt(end)}',
          style: TextStyle(
            fontSize: 12,
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
      ],
    );
  }
}
