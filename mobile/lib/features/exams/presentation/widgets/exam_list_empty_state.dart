import 'package:flutter/material.dart';
import 'package:eript_lms/core/theme/theme_context.dart';

class ExamListEmptyState extends StatelessWidget {
  const ExamListEmptyState({super.key, required this.tab});
  final String tab;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final copy = switch (tab) {
      'Sắp tới' => (
        'Chưa có kỳ thi sắp diễn ra',
        'Khi có lịch thi mới, kỳ thi sẽ xuất hiện tại đây.',
      ),
      'Đang mở' => (
        'Hiện không có kỳ thi đang mở',
        'Các kỳ thi trong thời gian làm bài sẽ hiện ở tab này.',
      ),
      'Đã làm' => (
        'Bạn chưa hoàn thành kỳ thi nào',
        'Kết quả các bài đã nộp sẽ được lưu tại đây.',
      ),
      _ => (
        'Chưa có kỳ thi nào',
        'Kỳ thi được phân công sẽ xuất hiện tại đây.',
      ),
    };

    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 72,
              height: 72,
              decoration: BoxDecoration(
                color: context.cs.primaryContainer,
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.assignment_outlined,
                size: 36,
                color: context.cs.primary,
              ),
            ),
            const SizedBox(height: 16),
            Text(
              copy.$1,
              textAlign: TextAlign.center,
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              copy.$2,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
                height: 1.45,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class ExamListErrorState extends StatelessWidget {
  const ExamListErrorState({
    super.key,
    required this.message,
    required this.onRetry,
  });
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.wifi_off_rounded, size: 48, color: context.cs.outline),
            const SizedBox(height: 12),
            Text(
              'Không tải được danh sách kỳ thi',
              textAlign: TextAlign.center,
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              message,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
                height: 1.4,
              ),
            ),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded, size: 18),
              label: const Text('Thử lại'),
            ),
          ],
        ),
      ),
    );
  }
}
