import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/widgets/app_card.dart';
import 'package:eript_lms/core/widgets/status_badge.dart';
import 'package:flutter/material.dart';

import '../../data/models/lesson_detail_model.dart';

/// Vietnamese label for a lesson type.
String lessonTypeLabel(String type) {
  switch (type) {
    case 'video':
      return 'Video';
    case 'document':
    case 'file':
      return 'Tài liệu';
    case 'page':
      return 'Bài đọc';
    case 'quiz':
      return 'Trắc nghiệm';
    case 'assignment':
      return 'Bài tập';
    default:
      return type;
  }
}

/// Strips HTML tags for plain-text display.
String lessonPlainText(String html) {
  return html
      .replaceAll(RegExp(r'<[^>]*>'), ' ')
      .replaceAll(RegExp(r'\s+'), ' ')
      .trim();
}

/// "Bài giảng" tab: page body (for page/document lessons) + lesson info card.
class LessonContentTab extends StatelessWidget {
  const LessonContentTab({
    super.key,
    required this.lesson,
    required this.onMarkCompleted,
  });

  final LessonDetailModel lesson;
  final VoidCallback onMarkCompleted;

  @override
  Widget build(BuildContext context) {
    final body = lessonPlainText(lesson.description ?? '');
    final canMarkDone =
        !lesson.isCompleted && lesson.type != 'video' && lesson.type != 'quiz';

    return ListView(
      padding: AppSpacing.p16,
      children: [
        if (lesson.type == 'page' || lesson.type == 'document') ...[
          AppCard(
            child: Text(
              body.isEmpty ? 'Chưa có nội dung trang.' : body,
              style: context.tt.bodyMedium?.copyWith(height: 1.5),
            ),
          ),
          AppSpacing.h12,
        ],
        AppCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(Icons.info_outline, color: context.cs.primary),
                  AppSpacing.w8,
                  Expanded(
                    child: Text(
                      'Loại bài học: ${lessonTypeLabel(lesson.type)}',
                      style: context.tt.titleSmall,
                    ),
                  ),
                ],
              ),
              AppSpacing.h12,
              if (lesson.duration > 0)
                Padding(
                  padding: const EdgeInsets.only(bottom: AppSpacing.space2),
                  child: Text(
                    'Thời lượng bài học: ${lesson.duration ~/ 60} phút',
                    style: context.tt.bodyMedium,
                  ),
                ),
              StatusBadge(
                label: lesson.isCompleted ? 'Đã hoàn thành' : 'Chưa hoàn thành',
                tone: lesson.isCompleted
                    ? StatusTone.success
                    : StatusTone.warning,
                icon: lesson.isCompleted
                    ? Icons.check_circle_rounded
                    : Icons.schedule_rounded,
              ),
              if (canMarkDone) ...[
                AppSpacing.h16,
                FilledButton(
                  onPressed: onMarkCompleted,
                  child: const Text('Đánh dấu đã hoàn thành'),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}
