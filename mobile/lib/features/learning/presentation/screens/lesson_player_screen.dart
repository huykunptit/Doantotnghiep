import 'dart:async';
import 'package:chewie/chewie.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:video_player/video_player.dart';
import 'package:youtube_player_iframe/youtube_player_iframe.dart';
import '../../providers/learning_providers.dart';
import '../../data/models/lesson_detail_model.dart';
import '../../../courses/providers/course_detail_provider.dart';
import '../../../courses/data/models/course_model.dart';
import '../../data/repositories/learning_repository.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/error/friendly_error.dart';
import '../../../../core/widgets/loading_overlay.dart';
class LessonPlayerScreen extends ConsumerStatefulWidget {
  const LessonPlayerScreen({
    super.key,
    required this.courseId,
    required this.lessonId,
  });

  final int courseId;
  final int lessonId;

  static const routeName = '/learn/:courseId/:lessonId';

  @override
  ConsumerState<LessonPlayerScreen> createState() => _LessonPlayerScreenState();
}

class _LessonPlayerScreenState extends ConsumerState<LessonPlayerScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  VideoPlayerController? _videoPlayerController;
  ChewieController? _chewieController;
  YoutubePlayerController? _youtubeController;
  Timer? _progressTimer;
  int _lastWatchedSeconds = 0;
  bool _isPlayerInitialized = false;
  bool _isYoutubeVideo = false;
  bool _isVideoLoading = false;
  bool _videoHasError = false;
  String? _videoErrorMessage;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
  }

  @override
  void didUpdateWidget(LessonPlayerScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.lessonId != widget.lessonId) {
      _disposePlayer();
      _isPlayerInitialized = false;
      _isYoutubeVideo = false;
      _isVideoLoading = false;
      _videoHasError = false;
      _videoErrorMessage = null;
    }
  }

  @override
  void dispose() {
    _disposePlayer();
    _tabController.dispose();
    super.dispose();
  }

  void _disposePlayer() {
    _progressTimer?.cancel();
    _saveProgressOnQuit();
    _chewieController?.dispose();
    _videoPlayerController?.dispose();
    _youtubeController?.close();
    _chewieController = null;
    _videoPlayerController = null;
    _youtubeController = null;
  }

  Future<void> _saveProgressOnQuit() async {
    if (_isYoutubeVideo) {
      if (_lastWatchedSeconds > 0) {
        try {
          await ref.read(learningRepositoryProvider).updateLessonProgress(
                widget.courseId,
                widget.lessonId,
                watchedSeconds: _lastWatchedSeconds,
              );
        } catch (_) {}
      }
      return;
    }

    if (_videoPlayerController != null && _videoPlayerController!.value.isInitialized) {
      final pos = _videoPlayerController!.value.position.inSeconds;
      if (pos > 0 && pos != _lastWatchedSeconds) {
        try {
          await ref.read(learningRepositoryProvider).updateLessonProgress(
                widget.courseId,
                widget.lessonId,
                watchedSeconds: pos,
              );
        } catch (_) {}
      }
    }
  }

  void _initializePlayer(String videoUrl, int startSeconds) {
    if (_isPlayerInitialized) return;
    _isPlayerInitialized = true;
    _lastWatchedSeconds = startSeconds;
    _isVideoLoading = true;
    _videoHasError = false;
    _videoErrorMessage = null;

    final youtubeId = YoutubePlayerController.convertUrlToId(videoUrl);
    if (youtubeId != null) {
      _isYoutubeVideo = true;
      _initializeYoutubePlayer(youtubeId, startSeconds);
    } else {
      _isYoutubeVideo = false;
      _initializeMp4Player(videoUrl, startSeconds);
    }
  }

  /// Resets player state so the next [build] re-triggers [_initializePlayer].
  void _retryVideoInit() {
    _disposePlayer();
    setState(() {
      _isPlayerInitialized = false;
      _isVideoLoading = false;
      _videoHasError = false;
      _videoErrorMessage = null;
    });
  }

  void _initializeMp4Player(String videoUrl, int startSeconds) {
    _videoPlayerController = VideoPlayerController.networkUrl(Uri.parse(videoUrl));
    _videoPlayerController!.initialize().then((_) {
      if (!mounted) return;

      // Seek to last watched position
      if (startSeconds > 0) {
        _videoPlayerController!.seekTo(Duration(seconds: startSeconds));
      }

      _chewieController = ChewieController(
        videoPlayerController: _videoPlayerController!,
        autoPlay: false,
        looping: false,
        aspectRatio: 16 / 9,
        placeholder: Container(color: Colors.black),
        materialProgressColors: ChewieProgressColors(
          playedColor: AppColors.primary400,
          handleColor: AppColors.primary400,
          bufferedColor: AppColors.primary100.withValues(alpha: 0.5),
          backgroundColor: Colors.grey.shade800,
        ),
      );

      setState(() => _isVideoLoading = false);

      // Setup progress tracking timer (every 10 seconds)
      _progressTimer = Timer.periodic(const Duration(seconds: 10), (timer) {
        _trackProgress();
      });
    }).catchError((Object e) {
      if (!mounted) return;
      setState(() {
        _videoHasError = true;
        _videoErrorMessage = 'Không thể tải video. Vui lòng kiểm tra kết nối mạng.';
        _isVideoLoading = false;
      });
    });
  }

  void _initializeYoutubePlayer(String videoId, int startSeconds) {
    try {
      _youtubeController = YoutubePlayerController.fromVideoId(
        videoId: videoId,
        autoPlay: false,
        startSeconds: startSeconds.toDouble(),
        params: const YoutubePlayerParams(
          showControls: true,
          showFullscreenButton: true,
          strictRelatedVideos: true,
        ),
      );

      _youtubeController!.stream.listen(
        (value) {
          if (!mounted) return;
          if (_isVideoLoading &&
              (value.playerState == PlayerState.playing ||
                  value.playerState == PlayerState.paused ||
                  value.playerState == PlayerState.cued)) {
            setState(() => _isVideoLoading = false);
          }
          if (value.hasError && !_videoHasError) {
            setState(() {
              _videoHasError = true;
              _videoErrorMessage = 'Không thể phát video YouTube này (mã lỗi: ${value.error}).';
              _isVideoLoading = false;
            });
          }
        },
        onError: (Object e) {
          if (!mounted) return;
          setState(() {
            _videoHasError = true;
            _videoErrorMessage = 'Không thể tải video YouTube: ${friendlyErrorMessage(e)}';
            _isVideoLoading = false;
          });
        },
      );

      // Fallback: clear the loading overlay after a few seconds even if the
      // player never emits an intermediate state (e.g. autoplay blocked).
      Future.delayed(const Duration(seconds: 4), () {
        if (mounted && _isVideoLoading) setState(() => _isVideoLoading = false);
      });

      _progressTimer = Timer.periodic(const Duration(seconds: 10), (timer) {
        _trackYoutubeProgress();
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _videoHasError = true;
        _videoErrorMessage = 'Không thể khởi tạo video YouTube: ${friendlyErrorMessage(e)}';
        _isVideoLoading = false;
      });
    }
  }

  Future<void> _trackProgress() async {
    if (_videoPlayerController != null && _videoPlayerController!.value.isPlaying) {
      final currentPos = _videoPlayerController!.value.position.inSeconds;
      final duration = _videoPlayerController!.value.duration.inSeconds;
      final completed = duration > 0 ? (currentPos >= duration * 0.9) : false;

      _lastWatchedSeconds = currentPos;
      try {
        await ref.read(learningRepositoryProvider).updateLessonProgress(
              widget.courseId,
              widget.lessonId,
              watchedSeconds: currentPos,
              completed: completed,
            );
        // Invalidate course progress or details to update checkboxes
        ref.invalidate(courseDetailProvider(widget.courseId));
      } catch (_) {}
    }
  }

  Future<void> _trackYoutubeProgress() async {
    final controller = _youtubeController;
    if (controller == null) return;
    try {
      final state = await controller.playerState;
      if (state != PlayerState.playing) return;

      final currentPos = (await controller.currentTime).round();
      final duration = (await controller.duration).round();
      final completed = duration > 0 ? (currentPos >= duration * 0.9) : false;

      _lastWatchedSeconds = currentPos;
      await ref.read(learningRepositoryProvider).updateLessonProgress(
            widget.courseId,
            widget.lessonId,
            watchedSeconds: currentPos,
            completed: completed,
          );
      ref.invalidate(courseDetailProvider(widget.courseId));
    } catch (_) {}
  }

  void _seekTo(int seconds) {
    if (_isYoutubeVideo && _youtubeController != null) {
      _youtubeController!.seekTo(seconds: seconds.toDouble(), allowSeekAhead: true);
    } else if (_videoPlayerController != null && _videoPlayerController!.value.isInitialized) {
      _videoPlayerController!.seekTo(Duration(seconds: seconds));
    }
  }

  @override
  Widget build(BuildContext context) {
    final lessonAsync = ref.watch(lessonDetailProvider(widget.courseId, widget.lessonId));
    final courseAsync = ref.watch(courseDetailProvider(widget.courseId));
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: courseAsync.when(
          data: (course) => Text(course.title, style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
          loading: () => const Text('Đang tải...'),
          error: (_, _) => const Text('Bài học'),
        ),
        actions: [
          IconButton(
            tooltip: 'Trợ lý AI',
            icon: const Icon(Icons.auto_awesome_outlined),
            onPressed: () => context.push('/ai-chat?courseId=${widget.courseId}'),
          ),
          IconButton(
            icon: const Icon(Icons.list_alt_outlined),
            onPressed: () {
              // Open curriculum bottom drawer
              courseAsync.whenData((course) => _showCurriculumDrawer(context, course));
            },
          ),
        ],
      ),
      body: lessonAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.red),
              AppSpacing.h12,
              Text('Lỗi tải bài học: ${friendlyErrorMessage(e)}'),
              AppSpacing.h16,
              ElevatedButton(
                onPressed: () => ref.invalidate(lessonDetailProvider(widget.courseId, widget.lessonId)),
                child: const Text('Thử lại'),
              ),
            ],
          ),
        ),
        data: (lesson) {
          // Initialize player if it is video type
          if (lesson.type == 'video' && lesson.videoUrl != null && lesson.videoUrl!.isNotEmpty) {
            _initializePlayer(lesson.videoUrl!, lesson.watchedSeconds);
          }

          return Column(
            children: [
              // Media / Player Area
              _buildPlayerArea(lesson),

              // Title Area
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      lesson.title,
                      style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                    ),
                    if (lesson.description != null &&
                        lesson.description!.isNotEmpty &&
                        lesson.type == 'video') ...[
                      AppSpacing.h8,
                      Text(
                        _plainText(lesson.description!),
                        maxLines: 4,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                      ),
                    ],
                  ],
                ),
              ),

              // Tabs
              TabBar(
                controller: _tabController,
                indicatorColor: theme.colorScheme.primary,
                labelColor: theme.colorScheme.primary,
                unselectedLabelColor: theme.colorScheme.onSurfaceVariant,
                tabs: const [
                  Tab(text: 'Bài giảng'),
                  Tab(text: 'Ghi chú'),
                  Tab(text: 'Tài liệu'),
                ],
              ),

              // Tab View
              Expanded(
                child: TabBarView(
                  controller: _tabController,
                  children: [
                    _buildContentTab(lesson),
                    _buildNotesTab(lesson),
                    _buildAttachmentsTab(lesson),
                  ],
                ),
              ),

              // Bottom Navigation bar
              courseAsync.when(
                data: (course) => _buildBottomNavBar(course, lesson),
                loading: () => const SizedBox.shrink(),
                error: (_, _) => const SizedBox.shrink(),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildPlayerArea(LessonDetailModel lesson) {
    final hasVideoUrl = lesson.type == 'video' && lesson.videoUrl != null && lesson.videoUrl!.isNotEmpty;

    if (hasVideoUrl) {
      if (_videoHasError) {
        return AspectRatio(
          aspectRatio: 16 / 9,
          child: Container(
            color: Colors.black87,
            alignment: Alignment.center,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.error_outline, size: 40, color: Colors.redAccent),
                  AppSpacing.h8,
                  Text(
                    _videoErrorMessage ?? 'Không thể phát video.',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: Colors.white),
                  ),
                  AppSpacing.h12,
                  OutlinedButton.icon(
                    onPressed: _retryVideoInit,
                    icon: const Icon(Icons.refresh, color: Colors.white),
                    label: const Text('Thử lại', style: TextStyle(color: Colors.white)),
                    style: OutlinedButton.styleFrom(side: const BorderSide(color: Colors.white54)),
                  ),
                ],
              ),
            ),
          ),
        );
      }

      if (_isYoutubeVideo && _youtubeController != null) {
        return AspectRatio(
          aspectRatio: 16 / 9,
          child: Stack(
            fit: StackFit.expand,
            children: [
              const ColoredBox(color: Colors.black),
              YoutubePlayer(controller: _youtubeController!),
              if (_isVideoLoading) const LoadingOverlay(),
            ],
          ),
        );
      }

      if (!_isYoutubeVideo && _chewieController != null) {
        return AspectRatio(
          aspectRatio: 16 / 9,
          child: Chewie(controller: _chewieController!),
        );
      }

      // Still initializing the player.
      return AspectRatio(
        aspectRatio: 16 / 9,
        child: Container(
          color: Colors.black87,
          child: const LoadingOverlay(),
        ),
      );
    }

    // Standard media placeholder for non-video lessons
    IconData placeholderIcon;
    String typeLabel;
    switch (lesson.type) {
      case 'file':
      case 'document':
        placeholderIcon = Icons.insert_drive_file_outlined;
        typeLabel = 'Tài liệu học tập';
        break;
      case 'quiz':
        placeholderIcon = Icons.quiz_outlined;
        typeLabel = 'Bài thi trắc nghiệm';
        break;
      case 'assignment':
        placeholderIcon = Icons.assignment_outlined;
        typeLabel = 'Bài tập về nhà';
        break;
      default:
        placeholderIcon = Icons.menu_book_outlined;
        typeLabel = 'Bài đọc / Nội dung tự do';
    }

    return AspectRatio(
      aspectRatio: 16 / 9,
      child: Container(
        color: Colors.black87,
        alignment: Alignment.center,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(placeholderIcon, size: 48, color: Colors.white70),
            AppSpacing.h8,
            Text(
              typeLabel,
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
            ),
            if (lesson.type == 'quiz') ...[
              AppSpacing.h12,
              ElevatedButton(
                onPressed: () async {
                  final completed = await context.push<bool>(
                    '/learn/quiz/${widget.courseId}/${widget.lessonId}',
                  );
                  if (completed == true && mounted) {
                    ref.invalidate(lessonDetailProvider(widget.courseId, widget.lessonId));
                    ref.invalidate(courseDetailProvider(widget.courseId));
                  }
                },
                child: const Text('Bắt đầu làm bài'),
              ),
            ],
            if ((lesson.type == 'file' || lesson.type == 'document') &&
                lesson.videoUrl != null &&
                lesson.videoUrl!.isNotEmpty) ...[
              AppSpacing.h12,
              ElevatedButton.icon(
                onPressed: () async {
                  final uri = Uri.tryParse(lesson.videoUrl!);
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
      ),
    );
  }

  String _plainText(String html) {
    return html
        .replaceAll(RegExp(r'<[^>]*>'), ' ')
        .replaceAll(RegExp(r'\s+'), ' ')
        .trim();
  }

  Widget _buildContentTab(LessonDetailModel lesson) {
    final theme = Theme.of(context);
    final body = _plainText(lesson.description ?? '');

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (lesson.type == 'page' || lesson.type == 'document') ...[
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Text(
                body.isEmpty ? 'Chưa có nội dung trang.' : body,
                style: theme.textTheme.bodyMedium?.copyWith(height: 1.5),
              ),
            ),
          ),
          AppSpacing.h12,
        ],
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(Icons.info_outline, color: theme.colorScheme.primary),
                    AppSpacing.w8,
                    Text(
                      'Loại bài học: ${lesson.type.toUpperCase()}',
                      style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold),
                    ),
                  ],
                ),
                AppSpacing.h12,
                if (lesson.duration > 0)
                  Text('Thời lượng bài học: ${lesson.duration ~/ 60} phút'),
                AppSpacing.h4,
                Text(
                  lesson.isCompleted ? 'Trạng thái: Đã hoàn thành ✅' : 'Trạng thái: Chưa hoàn thành ⏳',
                ),
                if (!lesson.isCompleted && lesson.type != 'video' && lesson.type != 'quiz') ...[
                  AppSpacing.h16,
                  FilledButton(
                    onPressed: () async {
                      try {
                        await ref.read(learningRepositoryProvider).updateLessonProgress(
                              widget.courseId,
                              widget.lessonId,
                              watchedSeconds: 0,
                              completed: true,
                            );
                        ref.invalidate(lessonDetailProvider(widget.courseId, widget.lessonId));
                        ref.invalidate(courseDetailProvider(widget.courseId));
                        if (mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Đã đánh dấu hoàn thành bài học!')),
                          );
                        }
                      } catch (e) {
                        if (mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(content: Text('Lỗi: ${friendlyErrorMessage(e)}'), backgroundColor: Colors.red),
                          );
                        }
                      }
                    },
                    child: const Text('Đánh dấu đã hoàn thành'),
                  ),
                ],
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildNotesTab(LessonDetailModel lesson) {
    final notesAsync = ref.watch(lessonNotesProvider(widget.courseId, widget.lessonId));
    final noteCtrl = TextEditingController();

    return Column(
      children: [
        // Create note section
        Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Expanded(
                child: TextFormField(
                  controller: noteCtrl,
                  decoration: const InputDecoration(
                    hintText: 'Thêm ghi chú tại giây hiện tại...',
                  ),
                ),
              ),
              AppSpacing.w12,
              IconButton.filled(
                icon: const Icon(Icons.add),
                onPressed: () async {
                  final text = noteCtrl.text.trim();
                  if (text.isEmpty) return;

                  final currentPos = _videoPlayerController?.value.position.inSeconds ?? 0;
                  try {
                    await ref
                        .read(lessonNotesProvider(widget.courseId, widget.lessonId).notifier)
                        .addNote(content: text, timeSeconds: currentPos);
                    noteCtrl.clear();
                  } catch (e) {
                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(content: Text('Lỗi: ${friendlyErrorMessage(e)}'), backgroundColor: Colors.red),
                      );
                    }
                  }
                },
              ),
            ],
          ),
        ),

        // Notes List
        Expanded(
          child: notesAsync.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) => Center(child: Text('Lỗi: ${friendlyErrorMessage(e)}')),
            data: (notes) {
              if (notes.isEmpty) {
                return const Center(child: Text('Chưa có ghi chú nào.'));
              }
              return ListView.builder(
                itemCount: notes.length,
                itemBuilder: (context, index) {
                  final note = notes[index];
                  final minutes = note.timeSeconds ~/ 60;
                  final seconds = note.timeSeconds % 60;
                  final timeLabel = '${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';

                  return ListTile(
                    leading: ActionChip(
                      label: Text(timeLabel),
                      onPressed: () => _seekTo(note.timeSeconds),
                      avatar: const Icon(Icons.play_arrow, size: 14),
                    ),
                    title: Text(note.content),
                    trailing: IconButton(
                      icon: const Icon(Icons.delete_outline, color: Colors.red),
                      onPressed: () async {
                        try {
                          await ref
                              .read(lessonNotesProvider(widget.courseId, widget.lessonId).notifier)
                              .removeNote(note.id);
                        } catch (e) {
                          if (context.mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(content: Text('Lỗi: ${friendlyErrorMessage(e)}'), backgroundColor: Colors.red),
                            );
                          }
                        }
                      },
                    ),
                  );
                },
              );
            },
          ),
        ),
      ],
    );
  }

  Widget _buildAttachmentsTab(LessonDetailModel lesson) {
    final attachmentsAsync = ref.watch(lessonAttachmentsProvider(widget.courseId, widget.lessonId));

    return attachmentsAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => Center(child: Text('Lỗi tải tài liệu: ${friendlyErrorMessage(e)}')),
      data: (attachments) {
        if (attachments.isEmpty) {
          return const Center(child: Text('Không có tài liệu đính kèm cho bài học này.'));
        }
        return ListView.builder(
          itemCount: attachments.length,
          itemBuilder: (context, index) {
            final file = attachments[index];
            return ListTile(
              leading: const Icon(Icons.download_for_offline_outlined, color: Colors.blue),
              title: Text(file.title),
              subtitle: file.fileSize != null ? Text('${(file.fileSize! / 1024).toStringAsFixed(1)} KB') : null,
              onTap: () async {
                final uri = Uri.parse(file.fileUrl);
                if (await canLaunchUrl(uri)) {
                  await launchUrl(uri, mode: LaunchMode.externalApplication);
                } else {
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Không thể mở liên kết tải file.')),
                    );
                  }
                }
              },
            );
          },
        );
      },
    );
  }

  Widget _buildBottomNavBar(CourseDetailModel course, LessonDetailModel currentLesson) {
    final currentIndex = course.lessons.indexWhere((l) => l.id == currentLesson.id);
    final hasPrev = currentIndex > 0;
    final hasNext = currentIndex != -1 && currentIndex < course.lessons.length - 1;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        border: Border(top: BorderSide(color: Theme.of(context).colorScheme.outlineVariant)),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          OutlinedButton.icon(
            onPressed: hasPrev
                ? () {
                    final prevId = course.lessons[currentIndex - 1].id;
                    context.replace('/learn/${course.id}/$prevId');
                  }
                : null,
            icon: const Icon(Icons.chevron_left),
            label: const Text('Bài trước'),
          ),
          OutlinedButton.icon(
            onPressed: hasNext
                ? () {
                    final nextId = course.lessons[currentIndex + 1].id;
                    context.replace('/learn/${course.id}/$nextId');
                  }
                : null,
            icon: const Icon(Icons.chevron_right),
            label: const Text('Bài tiếp theo'),
          ),
        ],
      ),
    );
  }

  void _showCurriculumDrawer(BuildContext context, CourseDetailModel course) {
    final theme = Theme.of(context);
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return Container(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Nội dung khóa học',
                style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
              ),
              AppSpacing.h16,
              Expanded(
                child: ListView.builder(
                  itemCount: course.lessons.length,
                  itemBuilder: (context, index) {
                    final lesson = course.lessons[index];
                    final isCurrent = lesson.id == widget.lessonId;

                    return ListTile(
                      leading: CircleAvatar(
                        radius: 12,
                        backgroundColor: isCurrent ? theme.colorScheme.primary : theme.colorScheme.primaryContainer,
                        child: Text(
                          '${lesson.order}',
                          style: TextStyle(
                            color: isCurrent ? theme.colorScheme.onPrimary : theme.colorScheme.onPrimaryContainer,
                            fontSize: 10,
                          ),
                        ),
                      ),
                      title: Text(
                        lesson.title,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontWeight: isCurrent ? FontWeight.bold : FontWeight.normal,
                          color: isCurrent ? theme.colorScheme.primary : null,
                        ),
                      ),
                      onTap: () {
                        Navigator.pop(ctx);
                        if (!isCurrent) {
                          context.replace('/learn/${course.id}/${lesson.id}');
                        }
                      },
                    );
                  },
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
