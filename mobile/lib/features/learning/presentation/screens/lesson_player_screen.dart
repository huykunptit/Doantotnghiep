import 'dart:async';
import 'package:chewie/chewie.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:video_player/video_player.dart';
import 'package:youtube_player_iframe/youtube_player_iframe.dart';
import '../../providers/learning_providers.dart';
import '../../data/models/lesson_detail_model.dart';
import '../../../courses/providers/course_detail_provider.dart';
import '../../data/repositories/learning_repository.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/error/friendly_error.dart';
import '../../../../core/widgets/app_loader.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import '../widgets/lesson_attachments_tab.dart';
import '../widgets/lesson_content_tab.dart';
import '../widgets/lesson_navigation.dart';
import '../widgets/lesson_notes_tab.dart';
import '../widgets/lesson_player_area.dart';
import 'package:eript_lms/core/widgets/error_state.dart';

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

class _LessonPlayerScreenState extends ConsumerState<LessonPlayerScreen>
    with SingleTickerProviderStateMixin {
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
  final TextEditingController _noteController = TextEditingController();
  final FocusNode _noteFocusNode = FocusNode();
  bool _isSavingNote = false;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    _tabController.addListener(() {
      if (!mounted || _tabController.indexIsChanging) return;
      setState(() {});
      if (_tabController.index != 1 && _noteFocusNode.hasFocus) {
        _noteFocusNode.unfocus();
      }
    });
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
    _noteController.dispose();
    _noteFocusNode.dispose();
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
          await ref
              .read(learningRepositoryProvider)
              .updateLessonProgress(
                widget.courseId,
                widget.lessonId,
                watchedSeconds: _lastWatchedSeconds,
              );
        } catch (_) {}
      }
      return;
    }

    if (_videoPlayerController != null &&
        _videoPlayerController!.value.isInitialized) {
      final pos = _videoPlayerController!.value.position.inSeconds;
      if (pos > 0 && pos != _lastWatchedSeconds) {
        try {
          await ref
              .read(learningRepositoryProvider)
              .updateLessonProgress(
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
    _videoPlayerController = VideoPlayerController.networkUrl(
      Uri.parse(videoUrl),
    );
    _videoPlayerController!
        .initialize()
        .then((_) {
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
              playedColor: context.cs.primary,
              handleColor: context.cs.primary,
              bufferedColor: context.cs.primaryContainer.withValues(alpha: 0.5),
              backgroundColor: context.cs.onSurfaceVariant,
            ),
          );

          setState(() => _isVideoLoading = false);

          // Setup progress tracking timer (every 10 seconds)
          _progressTimer = Timer.periodic(const Duration(seconds: 10), (timer) {
            _trackProgress();
          });
        })
        .catchError((Object e) {
          if (!mounted) return;
          setState(() {
            _videoHasError = true;
            _videoErrorMessage =
                'Không thể tải video. Vui lòng kiểm tra kết nối mạng.';
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
              _videoErrorMessage =
                  'Không thể phát video YouTube này (mã lỗi: ${value.error}).';
              _isVideoLoading = false;
            });
          }
        },
        onError: (Object e) {
          if (!mounted) return;
          setState(() {
            _videoHasError = true;
            _videoErrorMessage =
                'Không thể tải video YouTube: ${friendlyErrorMessage(e)}';
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
        _videoErrorMessage =
            'Không thể khởi tạo video YouTube: ${friendlyErrorMessage(e)}';
        _isVideoLoading = false;
      });
    }
  }

  Future<void> _trackProgress() async {
    if (_videoPlayerController != null &&
        _videoPlayerController!.value.isPlaying) {
      final currentPos = _videoPlayerController!.value.position.inSeconds;
      final duration = _videoPlayerController!.value.duration.inSeconds;
      final completed = duration > 0 ? (currentPos >= duration * 0.9) : false;

      _lastWatchedSeconds = currentPos;
      try {
        await ref
            .read(learningRepositoryProvider)
            .updateLessonProgress(
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
      await ref
          .read(learningRepositoryProvider)
          .updateLessonProgress(
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
      _youtubeController!.seekTo(
        seconds: seconds.toDouble(),
        allowSeekAhead: true,
      );
    } else if (_videoPlayerController != null &&
        _videoPlayerController!.value.isInitialized) {
      _videoPlayerController!.seekTo(Duration(seconds: seconds));
    }
  }

  Future<int> _currentPlaybackSeconds() async {
    if (_isYoutubeVideo && _youtubeController != null) {
      try {
        final current = (await _youtubeController!.currentTime).round();
        if (current >= 0) {
          _lastWatchedSeconds = current;
          return current;
        }
      } catch (_) {}
      return _lastWatchedSeconds;
    }

    final player = _videoPlayerController;
    if (player != null && player.value.isInitialized) {
      final current = player.value.position.inSeconds;
      _lastWatchedSeconds = current;
      return current;
    }

    return _lastWatchedSeconds;
  }

  Future<void> _submitNote() async {
    final text = _noteController.text.trim();
    if (text.isEmpty || _isSavingNote) return;

    setState(() => _isSavingNote = true);
    try {
      final currentPos = await _currentPlaybackSeconds();
      await ref
          .read(lessonNotesProvider(widget.courseId, widget.lessonId).notifier)
          .addNote(content: text, timeSeconds: currentPos);
      _noteController.clear();
      _noteFocusNode.unfocus();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Đã lưu ghi chú tại ${formatNoteTime(currentPos)}'),
            duration: const Duration(seconds: 2),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Lỗi: ${friendlyErrorMessage(e)}'),
            backgroundColor: context.sem.danger,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isSavingNote = false);
    }
  }

  Future<void> _startQuiz() async {
    final completed = await context.push<bool>(
      '/learn/quiz/${widget.courseId}/${widget.lessonId}',
    );
    if (completed == true && mounted) {
      ref.invalidate(lessonDetailProvider(widget.courseId, widget.lessonId));
      ref.invalidate(courseDetailProvider(widget.courseId));
    }
  }

  Future<void> _markCompleted() async {
    try {
      await ref
          .read(learningRepositoryProvider)
          .updateLessonProgress(
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
      _showError(e);
    }
  }

  Future<void> _deleteNote(int noteId) async {
    try {
      await ref
          .read(lessonNotesProvider(widget.courseId, widget.lessonId).notifier)
          .removeNote(noteId);
    } catch (e) {
      _showError(e);
    }
  }

  Future<void> _refreshNoteTime() async {
    // Refresh displayed timestamp before typing.
    await _currentPlaybackSeconds();
    if (mounted) setState(() {});
  }

  void _showError(Object e) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Lỗi: ${friendlyErrorMessage(e)}'),
        backgroundColor: context.sem.danger,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final lessonAsync = ref.watch(
      lessonDetailProvider(widget.courseId, widget.lessonId),
    );
    final courseAsync = ref.watch(courseDetailProvider(widget.courseId));
    final keyboardOpen = MediaQuery.viewInsetsOf(context).bottom > 0;
    final typingNote = keyboardOpen && _tabController.index == 1;

    return Scaffold(
      resizeToAvoidBottomInset: true,
      appBar: AppBar(
        title: courseAsync.when(
          data: (course) => Text(course.title),
          loading: () => const Text('Đang tải...'),
          error: (_, _) => const Text('Bài học'),
        ),
        actions: [
          IconButton(
            tooltip: 'Trợ lý AI',
            icon: const Icon(Icons.auto_awesome_outlined),
            onPressed: () =>
                context.push('/ai-chat?courseId=${widget.courseId}'),
          ),
          IconButton(
            tooltip: 'Nội dung khóa học',
            icon: const Icon(Icons.list_alt_outlined),
            onPressed: () => courseAsync.whenData(
              (course) => showCurriculumSheet(context, course, widget.lessonId),
            ),
          ),
        ],
      ),
      body: lessonAsync.when(
        loading: () =>
            const Center(child: AppLoader(message: 'Đang tải bài học...')),
        error: (e, _) => ErrorStateWidget(
          error: e,
          onRetry: () => ref.invalidate(
            lessonDetailProvider(widget.courseId, widget.lessonId),
          ),
        ),
        data: (lesson) {
          if (lesson.type == 'video' &&
              lesson.videoUrl != null &&
              lesson.videoUrl!.isNotEmpty) {
            _initializePlayer(lesson.videoUrl!, lesson.watchedSeconds);
          }

          return Column(
            children: [
              // Hide bulky chrome when typing notes so the field stays above keyboard.
              if (!typingNote) ...[
                LessonPlayerArea(
                  lesson: lesson,
                  hasError: _videoHasError,
                  errorMessage: _videoErrorMessage,
                  isYoutube: _isYoutubeVideo,
                  isLoading: _isVideoLoading,
                  youtubeController: _youtubeController,
                  chewieController: _chewieController,
                  onRetry: _retryVideoInit,
                  onStartQuiz: _startQuiz,
                ),
                _LessonHeader(lesson: lesson),
              ] else
                _NoteTimeBanner(seconds: _lastWatchedSeconds),
              TabBar(
                controller: _tabController,
                tabs: const [
                  Tab(text: 'Bài giảng'),
                  Tab(text: 'Ghi chú'),
                  Tab(text: 'Tài liệu'),
                ],
              ),
              Expanded(
                child: TabBarView(
                  controller: _tabController,
                  children: [
                    LessonContentTab(
                      lesson: lesson,
                      onMarkCompleted: _markCompleted,
                    ),
                    LessonNotesTab(
                      notes: ref.watch(
                        lessonNotesProvider(widget.courseId, widget.lessonId),
                      ),
                      controller: _noteController,
                      focusNode: _noteFocusNode,
                      isSaving: _isSavingNote,
                      currentSeconds: _lastWatchedSeconds,
                      onSubmit: _submitNote,
                      onFieldTap: _refreshNoteTime,
                      onSeek: _seekTo,
                      onDelete: _deleteNote,
                    ),
                    LessonAttachmentsTab(
                      attachments: ref.watch(
                        lessonAttachmentsProvider(
                          widget.courseId,
                          widget.lessonId,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              if (!typingNote)
                courseAsync.maybeWhen(
                  data: (course) => LessonBottomNav(
                    course: course,
                    currentLessonId: lesson.id,
                  ),
                  orElse: () => const SizedBox.shrink(),
                ),
            ],
          );
        },
      ),
    );
  }
}

class _LessonHeader extends StatelessWidget {
  const _LessonHeader({required this.lesson});

  final LessonDetailModel lesson;

  @override
  Widget build(BuildContext context) {
    final showDescription =
        lesson.description != null &&
        lesson.description!.isNotEmpty &&
        lesson.type == 'video';
    return Padding(
      padding: AppSpacing.p16,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(lesson.title, style: context.tt.titleMedium),
          if (showDescription) ...[
            AppSpacing.h8,
            Text(
              lessonPlainText(lesson.description!),
              maxLines: 4,
              overflow: TextOverflow.ellipsis,
              style: context.tt.bodyMedium?.copyWith(
                color: context.cs.onSurfaceVariant,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _NoteTimeBanner extends StatelessWidget {
  const _NoteTimeBanner({required this.seconds});

  final int seconds;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: context.cs.surfaceContainerLow,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      child: Row(
        children: [
          const Icon(Icons.edit_note_rounded, size: 20),
          AppSpacing.w8,
          Expanded(
            child: Text(
              'Ghi chú tại ${formatNoteTime(seconds)}',
              style: context.tt.titleSmall,
            ),
          ),
        ],
      ),
    );
  }
}
