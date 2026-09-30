import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:flutter/material.dart';

import '../../data/models/quiz_model.dart';
import '../../providers/quiz_providers.dart';
import 'exam_chrome.dart';

/// Modal alert from the proctor. [onDismissed] runs after the dialog closes.
void showProctorAlertDialog(
  BuildContext context,
  ProctorMessageModel alert, {
  required VoidCallback onDismissed,
}) {
  showDialog<void>(
    context: context,
    barrierDismissible: false,
    builder: (ctx) {
      final critical =
          alert.type == 'exam_force_stopped' ||
          alert.title.contains('nghiêm trọng');
      return AlertDialog(
        title: Row(
          children: [
            Icon(
              critical ? Icons.gpp_bad : Icons.campaign,
              color: critical ? ctx.sem.danger : ctx.sem.warning,
            ),
            AppSpacing.w12,
            Expanded(child: Text(alert.title, style: ctx.tt.titleMedium)),
          ],
        ),
        content: Text(alert.message),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.pop(ctx);
              onDismissed();
            },
            child: const Text('Đã hiểu'),
          ),
        ],
      );
    },
  );
}

/// Confirmation before submitting; [onConfirm] runs after the dialog closes.
void showSubmitConfirmDialog(
  BuildContext context, {
  required int answered,
  required int total,
  required VoidCallback onConfirm,
}) {
  showDialog<void>(
    context: context,
    builder: (ctx) {
      final unanswered = total - answered;
      return AlertDialog(
        title: const Text('Xác nhận nộp bài'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Bạn đã làm $answered trên tổng số $total câu hỏi.'),
            if (unanswered > 0) ...[
              AppSpacing.h12,
              Text(
                'Cảnh báo: Bạn còn $unanswered câu hỏi chưa trả lời!',
                style: ctx.tt.bodyMedium?.copyWith(
                  color: ctx.sem.dangerFg,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Quay lại'),
          ),
          FilledButton(
            onPressed: () {
              Navigator.pop(ctx);
              onConfirm();
            },
            child: const Text('Xác nhận nộp'),
          ),
        ],
      );
    },
  );
}

/// Bottom sheet with a grid of question numbers (answered / bookmarked / current).
void showQuestionNavigator(
  BuildContext context,
  ExamWorkspaceState state, {
  required void Function(int index) onSelect,
}) {
  showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    builder: (ctx) => DraggableScrollableSheet(
      initialChildSize: 0.6,
      minChildSize: 0.4,
      maxChildSize: 0.9,
      expand: false,
      builder: (_, scroll) => SingleChildScrollView(
        controller: scroll,
        padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Điều hướng câu hỏi', style: ctx.tt.titleMedium),
            AppSpacing.h4,
            Text(
              'Chọn nhanh câu hỏi cần làm hoặc kiểm tra lại.',
              style: ctx.tt.bodySmall?.copyWith(color: ctx.cs.onSurfaceVariant),
            ),
            AppSpacing.h24,
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 5,
                mainAxisSpacing: 10,
                crossAxisSpacing: 10,
              ),
              itemCount: state.questions.length,
              itemBuilder: (_, i) {
                final q = state.questions[i];
                return _QuestionCell(
                  number: i + 1,
                  current: i == state.currentIndex,
                  answered: isExamAnswered(state.answers[q.id]),
                  bookmarked: state.bookmarks[q.id] == true,
                  onTap: () {
                    Navigator.pop(ctx);
                    onSelect(i);
                  },
                );
              },
            ),
            AppSpacing.h24,
            Row(
              children: [
                _Legend(
                  bg: ctx.sem.successBg,
                  stroke: ctx.sem.success,
                  text: 'Đã làm',
                ),
                AppSpacing.w16,
                _Legend(
                  bg: ctx.sem.warningBg,
                  stroke: ctx.sem.warning,
                  text: 'Đã lưu ý/bookmark',
                ),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}

class _QuestionCell extends StatelessWidget {
  const _QuestionCell({
    required this.number,
    required this.current,
    required this.answered,
    required this.bookmarked,
    required this.onTap,
  });

  final int number;
  final bool current;
  final bool answered;
  final bool bookmarked;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    final sem = context.sem;
    final Color bg;
    final Color fg;
    Border? border;
    if (current) {
      bg = cs.primaryContainer;
      fg = cs.onPrimaryContainer;
      border = Border.all(color: cs.primary, width: 2);
    } else if (answered) {
      bg = sem.successBg;
      fg = sem.successFg;
      border = Border.all(color: sem.success);
    } else if (bookmarked) {
      bg = sem.warningBg;
      fg = sem.warningFg;
      border = Border.all(color: sem.warning);
    } else {
      bg = cs.surfaceContainerLow;
      fg = cs.onSurface;
    }

    return Semantics(
      button: true,
      label:
          'Câu $number${answered ? ', đã làm' : ''}${bookmarked ? ', đã đánh dấu' : ''}',
      excludeSemantics: true,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            Container(
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: bg,
                borderRadius: BorderRadius.circular(14),
                border: border,
              ),
              child: Text(
                '$number',
                style: context.tt.titleSmall?.copyWith(color: fg),
              ),
            ),
            if (bookmarked)
              Positioned(
                top: -4,
                right: -4,
                child: Icon(Icons.bookmark, size: 14, color: sem.warning),
              ),
          ],
        ),
      ),
    );
  }
}

class _Legend extends StatelessWidget {
  const _Legend({required this.bg, required this.stroke, required this.text});

  final Color bg;
  final Color stroke;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 14,
          height: 14,
          decoration: BoxDecoration(
            color: bg,
            borderRadius: BorderRadius.circular(4),
            border: Border.all(color: stroke),
          ),
        ),
        AppSpacing.w8,
        Text(
          text,
          style: context.tt.bodySmall?.copyWith(
            color: context.cs.onSurfaceVariant,
          ),
        ),
      ],
    );
  }
}
