import 'package:cached_network_image/cached_network_image.dart';
import 'package:eript_lms/core/error/friendly_error.dart';
import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/utils/format_vnd.dart';
import 'package:eript_lms/core/widgets/app_card.dart';
import 'package:eript_lms/core/widgets/empty_state.dart';
import 'package:eript_lms/core/widgets/section_header.dart';
import 'package:eript_lms/core/widgets/skeleton.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../courses/data/models/course_model.dart';
import '../../../courses/providers/course_catalog_provider.dart';

/// "Khóa học mới nhất": horizontal list of the first 6 catalog courses.
class LatestCoursesSection extends ConsumerWidget {
  const LatestCoursesSection({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final catalog = ref.watch(courseCatalogProvider());
    // Cards hold text, so give them more room when the user enlarges text.
    final scale = MediaQuery.textScalerOf(context).scale(1).clamp(1.0, 2.0);
    final cardHeight = 232 + (scale - 1) * 90;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: AppInsets.screen,
          child: SectionHeader(
            title: 'Khóa học mới nhất',
            actionLabel: 'Xem tất cả',
            onAction: () => context.go('/catalog'),
          ),
        ),
        catalog.when(
          loading: () => SizedBox(
            height: cardHeight,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: AppInsets.screen,
              itemCount: 3,
              separatorBuilder: (_, _) => AppSpacing.w12,
              itemBuilder: (_, _) =>
                  SkeletonBox(width: 188, height: cardHeight, radius: 16),
            ),
          ),
          error: (e, _) => Padding(
            padding: AppInsets.screen,
            child: Text(
              friendlyErrorMessage(e),
              style: context.tt.bodyMedium?.copyWith(
                color: context.cs.onSurfaceVariant,
              ),
            ),
          ),
          data: (list) {
            if (list.isEmpty) {
              return const EmptyState(
                icon: Icons.school_outlined,
                title: 'Chưa có khóa học nào',
              );
            }
            final shown = list.length > 6 ? 6 : list.length;
            return SizedBox(
              height: cardHeight,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: AppInsets.screen,
                itemCount: shown,
                separatorBuilder: (_, _) => AppSpacing.w12,
                itemBuilder: (_, i) => HomeCourseCard(course: list[i]),
              ),
            );
          },
        ),
      ],
    );
  }
}

class HomeCourseCard extends StatelessWidget {
  const HomeCourseCard({super.key, required this.course});

  final CourseListItemModel course;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    final rated = course.reviewsCount > 0;

    return SizedBox(
      width: 188,
      child: AppCard(
        onTap: () => context.push('/courses/${course.id}'),
        padding: EdgeInsets.zero,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              height: 108,
              width: double.infinity,
              child: course.thumbnail != null
                  ? CachedNetworkImage(
                      imageUrl: course.thumbnail!,
                      fit: BoxFit.cover,
                      errorWidget: (_, _, _) => const _ThumbFallback(),
                    )
                  : const _ThumbFallback(),
            ),
            Expanded(
              child: Padding(
                padding: AppSpacing.p12,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      course.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: context.tt.titleSmall,
                    ),
                    const Spacer(),
                    Row(
                      children: [
                        Icon(
                          Icons.star_rounded,
                          size: 14,
                          color: rated ? context.sem.warning : cs.outline,
                        ),
                        AppSpacing.w4,
                        Flexible(
                          child: Text(
                            rated
                                ? '${course.avgRating.toStringAsFixed(1)} (${course.reviewsCount})'
                                : 'Chưa có đánh giá',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: context.tt.bodySmall?.copyWith(
                              color: cs.onSurfaceVariant,
                            ),
                          ),
                        ),
                      ],
                    ),
                    AppSpacing.h4,
                    Text(
                      formatVnd(course.price),
                      style: context.tt.titleSmall?.copyWith(
                        color: course.price > 0
                            ? cs.primary
                            : context.sem.successFg,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ThumbFallback extends StatelessWidget {
  const _ThumbFallback();

  @override
  Widget build(BuildContext context) => Container(
    color: context.cs.primaryContainer,
    alignment: Alignment.center,
    child: Icon(Icons.school_rounded, size: 36, color: context.cs.primary),
  );
}
