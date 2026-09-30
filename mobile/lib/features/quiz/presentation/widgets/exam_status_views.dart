import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/widgets/app_card.dart';
import 'package:flutter/material.dart';

/// Full-screen spinner with a message (prechecking, preparing the paper).
class ExamLoadingView extends StatelessWidget {
  const ExamLoadingView({super.key, required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const CircularProgressIndicator(),
            AppSpacing.h16,
            Text(message, style: context.tt.bodyMedium),
          ],
        ),
      ),
    );
  }
}

/// Full-screen error with retry and optional "back".
class ExamErrorView extends StatelessWidget {
  const ExamErrorView({
    super.key,
    required this.message,
    required this.onRetry,
    this.onBack,
  });

  final String message;
  final VoidCallback onRetry;
  final VoidCallback? onBack;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Lỗi')),
      body: Center(
        child: Padding(
          padding: AppSpacing.p24,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.error_outline, size: 64, color: context.sem.danger),
              AppSpacing.h16,
              Text(
                message,
                textAlign: TextAlign.center,
                style: context.tt.bodyLarge,
              ),
              AppSpacing.h24,
              FilledButton(onPressed: onRetry, child: const Text('Thử lại')),
              if (onBack != null) ...[
                AppSpacing.h8,
                TextButton(onPressed: onBack, child: const Text('Quay lại')),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// Shown while the proctor has paused the exam.
class ExamPausedView extends StatelessWidget {
  const ExamPausedView({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        color: Colors.black87,
        width: double.infinity,
        height: double.infinity,
        alignment: Alignment.center,
        padding: AppSpacing.p24,
        child: AppCard(
          padding: const EdgeInsets.all(AppSpacing.space8),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                Icons.pause_circle_filled,
                size: 72,
                color: context.sem.warning,
              ),
              AppSpacing.h16,
              Text(
                'Bài thi đang tạm dừng',
                textAlign: TextAlign.center,
                style: context.tt.headlineMedium,
              ),
              AppSpacing.h12,
              Text(
                'Giám thị đã tạm dừng bài thi của bạn. Vui lòng chờ đến khi hệ thống cho phép tiếp tục.',
                textAlign: TextAlign.center,
                style: context.tt.bodyMedium?.copyWith(
                  color: context.cs.onSurfaceVariant,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
