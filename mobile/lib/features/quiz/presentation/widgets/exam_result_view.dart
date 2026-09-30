import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:flutter/material.dart';

import 'exam_workspace_score_ring_painter.dart';

/// Full-screen result after submitting: pass/fail state, score ring, message.
class ExamResultView extends StatelessWidget {
  const ExamResultView({
    super.key,
    required this.passed,
    required this.score,
    required this.message,
    required this.onDone,
  });

  final bool passed;
  final double score;
  final String? message;
  final VoidCallback onDone;

  @override
  Widget build(BuildContext context) {
    final sem = context.sem;
    final cs = context.cs;
    final accent = passed ? sem.successFg : sem.dangerFg;

    return Scaffold(
      backgroundColor: passed ? sem.successBg : sem.dangerBg,
      body: SafeArea(
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 80,
                height: 80,
                decoration: BoxDecoration(
                  color: cs.surfaceContainerLowest,
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  passed ? Icons.workspace_premium : Icons.task_alt,
                  size: 48,
                  color: accent,
                ),
              ),
              AppSpacing.h24,
              Text(
                'KẾT QUẢ BÀI THI',
                style: context.tt.labelLarge?.copyWith(
                  letterSpacing: 1.5,
                  color: cs.onSurfaceVariant,
                ),
              ),
              AppSpacing.h8,
              Text(
                passed
                    ? 'Chúc mừng, bạn đã đạt!'
                    : 'Bạn chưa đạt điểm tối thiểu',
                textAlign: TextAlign.center,
                style: context.tt.headlineMedium?.copyWith(color: accent),
              ),
              AppSpacing.h32,
              SizedBox(
                width: 160,
                height: 160,
                child: Stack(
                  alignment: Alignment.center,
                  children: [
                    CustomPaint(
                      size: const Size(160, 160),
                      painter: ExamWorkspaceScoreRingPainter(
                        percentage: score / 100,
                        strokeColor: accent,
                        trackColor: accent.withValues(alpha: 0.12),
                      ),
                    ),
                    Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          '${score.toStringAsFixed(0)}%',
                          style: context.tt.displayLarge?.copyWith(
                            color: accent,
                          ),
                        ),
                        Text(
                          'Điểm số',
                          style: context.tt.labelMedium?.copyWith(
                            color: cs.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              AppSpacing.h32,
              if (message != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 24),
                  child: Text(
                    message!,
                    textAlign: TextAlign.center,
                    style: context.tt.bodyMedium?.copyWith(
                      color: cs.onSurfaceVariant,
                      height: 1.5,
                    ),
                  ),
                ),
              FilledButton.icon(
                onPressed: onDone,
                icon: const Icon(Icons.home),
                label: const Text('Quay lại học tập'),
                style: FilledButton.styleFrom(
                  backgroundColor: accent,
                  foregroundColor: cs.surfaceContainerLowest,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
