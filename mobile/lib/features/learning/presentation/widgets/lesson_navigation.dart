import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../courses/data/models/course_model.dart';

/// Previous / next lesson bar shown under the tabs.
class LessonBottomNav extends StatelessWidget {
  const LessonBottomNav({
    super.key,
    required this.course,
    required this.currentLessonId,
  });

  final CourseDetailModel course;
  final int currentLessonId;

  @override
  Widget build(BuildContext context) {
    final index = course.lessons.indexWhere((l) => l.id == currentLessonId);
    final hasPrev = index > 0;
    final hasNext = index != -1 && index < course.lessons.length - 1;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: context.cs.surface,
        border: Border(top: BorderSide(color: context.cs.outlineVariant)),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          OutlinedButton.icon(
            onPressed: hasPrev
                ? () => context.replace(
                    '/learn/${course.id}/${course.lessons[index - 1].id}',
                  )
                : null,
            icon: const Icon(Icons.chevron_left),
            label: const Text('Bài trước'),
          ),
          OutlinedButton.icon(
            onPressed: hasNext
                ? () => context.replace(
                    '/learn/${course.id}/${course.lessons[index + 1].id}',
                  )
                : null,
            icon: const Icon(Icons.chevron_right),
            label: const Text('Bài tiếp theo'),
          ),
        ],
      ),
    );
  }
}

/// Bottom sheet listing every lesson of the course; tapping opens that lesson.
void showCurriculumSheet(
  BuildContext context,
  CourseDetailModel course,
  int currentLessonId,
) {
  showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    builder: (ctx) {
      final cs = ctx.cs;
      return DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.6,
        maxChildSize: 0.9,
        builder: (_, scroll) => Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Nội dung khóa học', style: ctx.tt.titleMedium),
              AppSpacing.h12,
              Expanded(
                child: ListView.builder(
                  controller: scroll,
                  itemCount: course.lessons.length,
                  itemBuilder: (_, i) {
                    final lesson = course.lessons[i];
                    final current = lesson.id == currentLessonId;
                    return ListTile(
                      selected: current,
                      leading: CircleAvatar(
                        radius: 14,
                        backgroundColor: current
                            ? cs.primary
                            : cs.primaryContainer,
                        child: Text(
                          '${lesson.order}',
                          style: ctx.tt.labelMedium?.copyWith(
                            color: current
                                ? cs.onPrimary
                                : cs.onPrimaryContainer,
                          ),
                        ),
                      ),
                      title: Text(
                        lesson.title,
                        style: ctx.tt.bodyMedium?.copyWith(
                          fontWeight: current
                              ? FontWeight.w700
                              : FontWeight.w400,
                        ),
                      ),
                      onTap: () {
                        Navigator.pop(ctx);
                        if (!current) {
                          context.replace('/learn/${course.id}/${lesson.id}');
                        }
                      },
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      );
    },
  );
}
