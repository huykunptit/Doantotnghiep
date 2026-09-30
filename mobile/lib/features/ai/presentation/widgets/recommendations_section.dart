import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/utils/format_vnd.dart';
import '../../providers/ai_providers.dart';
import 'package:eript_lms/core/theme/theme_context.dart';

class RecommendationsSection extends ConsumerWidget {
  const RecommendationsSection({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(recommendationsProvider);
    final theme = Theme.of(context);

    return async.when(
      loading: () => const Padding(
        padding: EdgeInsets.symmetric(horizontal: 20),
        child: SizedBox(
          height: 180,
          child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
        ),
      ),
      error: (_, _) => const SizedBox.shrink(),
      data: (bundle) {
        final items = bundle.items;
        if (items.isEmpty) return const SizedBox.shrink();

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 12, 0),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Gợi ý cho bạn',
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                            letterSpacing: -0.2,
                          ),
                        ),
                        Text(
                          bundle.usesCurriculumFallback
                              ? 'Theo khung CTĐT chuẩn — hồ sơ chưa có điểm'
                              : 'Cá nhân hoá theo lộ trình & kỹ năng',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ),
                  ),
                  TextButton(
                    onPressed: () => context.go('/catalog'),
                    style: TextButton.styleFrom(
                      foregroundColor: context.cs.primary,
                    ),
                    child: const Text(
                      'Catalog',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            AppSpacing.h8,
            if (bundle.usesCurriculumFallback)
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
                child: Text(
                  'Hồ sơ chưa đủ điểm — đang gợi ý theo khung chương trình đào tạo chuẩn của ngành.',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
            SizedBox(
              height: 220,
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 20),
                itemCount: items.length > 6 ? 6 : items.length,
                itemBuilder: (context, index) {
                  final item = items[index];
                  final course = item.course;
                  return GestureDetector(
                    onTap: () => context.push('/courses/${course.id}'),
                    child: Container(
                      width: 176,
                      margin: const EdgeInsets.only(right: 12),
                      decoration: BoxDecoration(
                        color: theme.cardColor,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(
                          color: theme.dividerColor.withValues(alpha: 0.4),
                        ),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          ClipRRect(
                            borderRadius: const BorderRadius.vertical(
                              top: Radius.circular(16),
                            ),
                            child: SizedBox(
                              height: 96,
                              width: double.infinity,
                              child: course.thumbnail != null
                                  ? CachedNetworkImage(
                                      imageUrl: course.thumbnail!,
                                      fit: BoxFit.cover,
                                      errorWidget: (_, _, _) =>
                                          _thumbFallback(context),
                                    )
                                  : _thumbFallback(context),
                            ),
                          ),
                          Padding(
                            padding: const EdgeInsets.all(10),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  course.title,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    fontWeight: FontWeight.w700,
                                    height: 1.3,
                                  ),
                                ),
                                AppSpacing.h8,
                                Row(
                                  children: [
                                    Icon(
                                      Icons.auto_awesome,
                                      size: 14,
                                      color: context.cs.primary,
                                    ),
                                    AppSpacing.w4,
                                    Text(
                                      'Điểm ${item.score}',
                                      style: TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.w700,
                                        color: context.cs.primary,
                                      ),
                                    ),
                                    const Spacer(),
                                    Text(
                                      formatVnd(course.price),
                                      style: TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.w800,
                                        color: course.price > 0
                                            ? context.cs.primary
                                            : context.sem.success,
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
            AppSpacing.h24,
          ],
        );
      },
    );
  }

  Widget _thumbFallback(BuildContext context) {
    return Container(
      color: context.cs.primaryContainer,
      alignment: Alignment.center,
      child: Icon(Icons.school_rounded, color: context.cs.primary),
    );
  }
}
