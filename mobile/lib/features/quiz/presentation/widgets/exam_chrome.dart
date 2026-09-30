import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:flutter/material.dart';

/// mm:ss, or ∞ when the exam has no time limit.
String formatExamTime(int? seconds) {
  if (seconds == null) return '∞';
  if (seconds <= 0) return '00:00';
  final m = seconds ~/ 60;
  final s = seconds % 60;
  return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
}

/// Whether an answer value counts as answered (any question type).
bool isExamAnswered(dynamic answer) {
  if (answer == null) return false;
  if (answer is String) return answer.trim().isNotEmpty;
  if (answer is List) return answer.isNotEmpty;
  if (answer is Map) return answer.isNotEmpty;
  return true;
}

/// AppBar title: small "kind" label + exam/quiz title.
class ExamTitle extends StatelessWidget {
  const ExamTitle({super.key, required this.kind, required this.title});

  final String kind;
  final String title;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          kind.toUpperCase(),
          style: context.tt.labelSmall?.copyWith(
            color: context.cs.primary,
            letterSpacing: 1.0,
          ),
        ),
        Text(
          title,
          style: context.tt.titleSmall,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
      ],
    );
  }
}

/// Countdown pill; turns red when [urgent].
class ExamTimerChip extends StatelessWidget {
  const ExamTimerChip({super.key, required this.seconds, required this.urgent});

  final int? seconds;
  final bool urgent;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'Thời gian còn lại ${formatExamTime(seconds)}',
      excludeSemantics: true,
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
        padding: const EdgeInsets.symmetric(horizontal: 10),
        decoration: BoxDecoration(
          color: urgent ? context.sem.dangerFg : context.cs.onSurfaceVariant,
          borderRadius: AppRadius.rLg,
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.timer, size: 14, color: context.cs.surface),
            AppSpacing.w4,
            Text(
              formatExamTime(seconds),
              style: context.tt.labelLarge?.copyWith(color: context.cs.surface),
            ),
          ],
        ),
      ),
    );
  }
}

/// Red banner shown after the student leaves the app during an exam.
class FocusLossBanner extends StatelessWidget {
  const FocusLossBanner({
    super.key,
    required this.warnings,
    required this.maxWarnings,
    required this.onClose,
  });

  final int warnings;
  final int maxWarnings;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final fg = context.cs.surface;
    return Material(
      color: context.sem.dangerFg,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 8, 4, 8),
        child: Row(
          children: [
            Icon(Icons.warning_amber_rounded, color: fg, size: 18),
            AppSpacing.w8,
            Expanded(
              child: Text(
                warnings >= maxWarnings
                    ? 'Đã rời ứng dụng $maxWarnings lần — hệ thống đang nộp bài.'
                    : 'Bạn vừa rời ứng dụng ($warnings/$maxWarnings). Lần thứ $maxWarnings sẽ tự nộp bài.',
                style: context.tt.labelLarge?.copyWith(color: fg, height: 1.3),
              ),
            ),
            IconButton(
              tooltip: 'Đóng cảnh báo',
              icon: Icon(Icons.close, color: fg, size: 18),
              visualDensity: VisualDensity.compact,
              onPressed: onClose,
            ),
          ],
        ),
      ),
    );
  }
}

/// "Đã làm x / y câu" strip under the AppBar.
class ExamProgressStrip extends StatelessWidget {
  const ExamProgressStrip({
    super.key,
    required this.answered,
    required this.total,
    required this.warnings,
    required this.maxWarnings,
  });

  final int answered;
  final int total;
  final int warnings;
  final int maxWarnings;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      color: context.cs.surfaceContainerLow,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Flexible(
            child: Text(
              'Đã làm: $answered / $total câu',
              overflow: TextOverflow.ellipsis,
              style: context.tt.bodySmall?.copyWith(
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          if (warnings > 0)
            Text(
              'Rời app: $warnings/$maxWarnings',
              style: context.tt.labelMedium?.copyWith(
                color: context.sem.dangerFg,
              ),
            ),
        ],
      ),
    );
  }
}

/// Previous / next / submit bar.
class ExamBottomBar extends StatelessWidget {
  const ExamBottomBar({
    super.key,
    required this.currentIndex,
    required this.total,
    required this.isSubmitting,
    required this.onPrev,
    required this.onNext,
    required this.onSubmit,
  });

  final int currentIndex;
  final int total;
  final bool isSubmitting;
  final VoidCallback onPrev;
  final VoidCallback onNext;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    final last = currentIndex == total - 1;
    return SafeArea(
      child: Container(
        padding: AppSpacing.p16,
        decoration: BoxDecoration(
          color: context.cs.surface,
          border: Border(top: BorderSide(color: context.cs.outlineVariant)),
        ),
        child: Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                onPressed: currentIndex == 0 ? null : onPrev,
                icon: const Icon(Icons.arrow_back),
                label: const FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Text('Câu trước'),
                ),
              ),
            ),
            AppSpacing.w12,
            Expanded(
              child: last
                  ? FilledButton.icon(
                      onPressed: isSubmitting ? null : onSubmit,
                      icon: const Icon(Icons.check_circle_outline),
                      label: FittedBox(
                        fit: BoxFit.scaleDown,
                        child: Text(isSubmitting ? 'Đang nộp...' : 'Nộp bài'),
                      ),
                    )
                  : FilledButton.icon(
                      onPressed: onNext,
                      icon: const Icon(Icons.arrow_forward),
                      label: const FittedBox(
                        fit: BoxFit.scaleDown,
                        child: Text('Câu sau'),
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
