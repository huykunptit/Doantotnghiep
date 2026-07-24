import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../providers/ai_providers.dart';

class LearnTipCard extends ConsumerWidget {
  const LearnTipCard({
    super.key,
    required this.courseId,
    this.lessonId,
    this.lessonTitle,
    this.lessonType,
    this.progressPercent,
  });

  final int courseId;
  final int? lessonId;
  final String? lessonTitle;
  final String? lessonType;
  final double? progressPercent;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final query = TutoringTipQuery(
      courseId: courseId,
      lessonId: lessonId,
      lessonTitle: lessonTitle,
      lessonType: lessonType,
      progressPercent: progressPercent,
    );
    final async = ref.watch(tutoringTipsProvider(query));
    final theme = Theme.of(context);

    return async.when(
      loading: () => const SizedBox.shrink(),
      error: (_, _) => const SizedBox.shrink(),
      data: (tip) {
        if (tip.summary.isEmpty && tip.studyTips.isEmpty) {
          return const SizedBox.shrink();
        }
        return Container(
          margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.primary50,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: AppColors.primary200.withValues(alpha: 0.6),
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Icon(Icons.lightbulb_outline,
                      color: AppColors.primary600, size: 18),
                  AppSpacing.w8,
                  Text(
                    'Gợi ý học tập',
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: AppColors.primary800,
                    ),
                  ),
                  const Spacer(),
                  Text(
                    tip.source == 'ai' ? 'AI' : 'Theo tiến độ',
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      color: AppColors.primary600,
                    ),
                  ),
                ],
              ),
              if (tip.summary.isNotEmpty) ...[
                AppSpacing.h8,
                Text(
                  tip.summary,
                  style: theme.textTheme.bodySmall?.copyWith(height: 1.4),
                ),
              ],
              if (tip.studyTips.isNotEmpty) ...[
                AppSpacing.h8,
                ...tip.studyTips.take(2).map(
                      (t) => Padding(
                        padding: const EdgeInsets.only(bottom: 4),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('• ',
                                style: TextStyle(color: AppColors.primary600)),
                            Expanded(
                              child: Text(
                                t,
                                style: theme.textTheme.bodySmall
                                    ?.copyWith(height: 1.35),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
              ],
            ],
          ),
        );
      },
    );
  }
}
