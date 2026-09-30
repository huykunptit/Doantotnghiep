import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/widgets/app_card.dart';
import 'package:eript_lms/core/widgets/section_header.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../courses/data/models/enrollment_model.dart';

/// Horizontal strip of courses the student is currently taking.
class ContinueLearningSection extends StatelessWidget {
  const ContinueLearningSection({super.key, required this.enrollments});

  final List<EnrollmentModel> enrollments;

  @override
  Widget build(BuildContext context) {
    final scale = MediaQuery.textScalerOf(context).scale(1).clamp(1.0, 2.0);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: AppInsets.screen,
          child: const SectionHeader(title: 'Tiếp tục học tập'),
        ),
        SizedBox(
          height: 112 + (scale - 1) * 50,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: AppInsets.screen,
            itemCount: enrollments.length,
            separatorBuilder: (_, _) => AppSpacing.w12,
            itemBuilder: (_, i) => _ContinueCard(enroll: enrollments[i]),
          ),
        ),
      ],
    );
  }
}

class _ContinueCard extends StatelessWidget {
  const _ContinueCard({required this.enroll});

  final EnrollmentModel enroll;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    final course = enroll.course;
    final online = course.courseMode == 'online';
    final muted = context.tt.bodySmall?.copyWith(color: cs.onSurfaceVariant);

    return SizedBox(
      width: 248,
      child: AppCard(
        onTap: () => context.push('/courses/${enroll.courseId}'),
        padding: AppSpacing.p12,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: cs.primaryContainer,
                    borderRadius: AppRadius.rLg,
                  ),
                  child: Icon(
                    Icons.play_circle_outline_rounded,
                    color: cs.onPrimaryContainer,
                  ),
                ),
                AppSpacing.w12,
                Expanded(
                  child: Text(
                    course.title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: context.tt.titleSmall,
                  ),
                ),
              ],
            ),
            Row(
              children: [
                Icon(
                  online ? Icons.wifi_rounded : Icons.location_on_outlined,
                  size: 14,
                  color: cs.onSurfaceVariant,
                ),
                AppSpacing.w4,
                Text(online ? 'Online' : 'Offline', style: muted),
                if (course.creditValue != null) ...[
                  AppSpacing.w8,
                  Text('· ${course.creditValue} TC', style: muted),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}
