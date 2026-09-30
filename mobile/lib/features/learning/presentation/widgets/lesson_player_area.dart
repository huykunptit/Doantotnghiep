import 'package:chewie/chewie.dart';
import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/widgets/loading_overlay.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:youtube_player_iframe/youtube_player_iframe.dart';

import '../../data/models/lesson_detail_model.dart';

/// 16:9 media area: video (YouTube / mp4), error state, or a placeholder for
/// non-video lesson types. Playback state lives in the parent State; this
/// widget only renders it. Black backdrops are intentional (media surface).
class LessonPlayerArea extends StatelessWidget {
  const LessonPlayerArea({
    super.key,
    required this.lesson,
    required this.hasError,
    required this.errorMessage,
    required this.isYoutube,
    required this.isLoading,
    required this.youtubeController,
    required this.chewieController,
    required this.onRetry,
    required this.onStartQuiz,
  });

  final LessonDetailModel lesson;
  final bool hasError;
  final String? errorMessage;
  final bool isYoutube;
  final bool isLoading;
  final YoutubePlayerController? youtubeController;
  final ChewieController? chewieController;
  final VoidCallback onRetry;
  final VoidCallback onStartQuiz;

  bool get _hasVideoUrl =>
      lesson.type == 'video' &&
      lesson.videoUrl != null &&
      lesson.videoUrl!.isNotEmpty;

  @override
  Widget build(BuildContext context) {
    return AspectRatio(aspectRatio: 16 / 9, child: _content(context));
  }

  Widget _content(BuildContext context) {
    if (!_hasVideoUrl) return _placeholder(context);
    if (hasError) return _error(context);
    if (isYoutube && youtubeController != null) {
      return Stack(
        fit: StackFit.expand,
        children: [
          const ColoredBox(color: Colors.black),
          YoutubePlayer(controller: youtubeController!),
          if (isLoading) const LoadingOverlay(),
        ],
      );
    }
    if (!isYoutube && chewieController != null) {
      return Chewie(controller: chewieController!);
    }
    return Container(color: Colors.black87, child: const LoadingOverlay());
  }

  Widget _error(BuildContext context) {
    return Container(
      color: Colors.black87,
      alignment: Alignment.center,
      padding: const EdgeInsets.symmetric(horizontal: 24),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.error_outline, size: 40, color: context.sem.danger),
          AppSpacing.h8,
          Text(
            errorMessage ?? 'Không thể phát video.',
            textAlign: TextAlign.center,
            style: context.tt.bodyMedium?.copyWith(color: Colors.white),
          ),
          AppSpacing.h12,
          OutlinedButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh, color: Colors.white),
            label: const Text('Thử lại', style: TextStyle(color: Colors.white)),
            style: OutlinedButton.styleFrom(
              side: const BorderSide(color: Colors.white54),
            ),
          ),
        ],
      ),
    );
  }

  Widget _placeholder(BuildContext context) {
    final (icon, label) = switch (lesson.type) {
      'file' ||
      'document' => (Icons.insert_drive_file_outlined, 'Tài liệu học tập'),
      'quiz' => (Icons.quiz_outlined, 'Bài thi trắc nghiệm'),
      'assignment' => (Icons.assignment_outlined, 'Bài tập về nhà'),
      _ => (Icons.menu_book_outlined, 'Bài đọc / Nội dung tự do'),
    };
    final isFile = lesson.type == 'file' || lesson.type == 'document';
    final fileUrl = lesson.videoUrl;

    return Container(
      color: Colors.black87,
      alignment: Alignment.center,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(icon, size: 48, color: Colors.white70),
          AppSpacing.h8,
          Text(
            label,
            style: context.tt.titleSmall?.copyWith(color: Colors.white),
          ),
          if (lesson.type == 'quiz') ...[
            AppSpacing.h12,
            FilledButton(
              onPressed: onStartQuiz,
              child: const Text('Bắt đầu làm bài'),
            ),
          ],
          if (isFile && fileUrl != null && fileUrl.isNotEmpty) ...[
            AppSpacing.h12,
            FilledButton.icon(
              onPressed: () async {
                final uri = Uri.tryParse(fileUrl);
                if (uri != null) {
                  await launchUrl(uri, mode: LaunchMode.externalApplication);
                }
              },
              icon: const Icon(Icons.open_in_new),
              label: const Text('Mở tài liệu'),
            ),
          ],
        ],
      ),
    );
  }
}
