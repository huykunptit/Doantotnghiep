import 'dart:math';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../providers/quiz_providers.dart';
import '../widgets/question_display.dart';
import '../../data/models/quiz_model.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';

class ExamWorkspaceScreen extends ConsumerStatefulWidget {
  const ExamWorkspaceScreen({
    super.key,
    required this.courseId,
    required this.lessonId,
    required this.examId,
  });

  final int courseId;
  final int lessonId;
  final int examId;

  @override
  ConsumerState<ExamWorkspaceScreen> createState() => _ExamWorkspaceScreenState();
}

class _ExamWorkspaceScreenState extends ConsumerState<ExamWorkspaceScreen> with WidgetsBindingObserver {
  bool _isInitialized = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    
    // Initialize target Quiz / Exam based on route variables
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadData();
    });
  }

  void _loadData() {
    if (widget.examId > 0) {
      ref.read(examAttemptProvider.notifier).startExam(widget.examId);
    } else {
      ref.read(examAttemptProvider.notifier).startLessonQuiz(widget.courseId, widget.lessonId);
    }
    _isInitialized = true;
  }

  @override
  void didUpdateWidget(ExamWorkspaceScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.examId != widget.examId ||
        oldWidget.courseId != widget.courseId ||
        oldWidget.lessonId != widget.lessonId) {
      _loadData();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    super.didChangeAppLifecycleState(state);
    if (!_isInitialized) return;

    final attemptState = ref.read(examAttemptProvider);
    if (attemptState.isLoading || attemptState.status != 'in_progress') return;

    if (state == AppLifecycleState.paused || state == AppLifecycleState.inactive) {
      // Focus lost - switched app or minimized
      ref.read(examAttemptProvider.notifier).incrementFocusLossViolation();
      
      // Post frame alert to user upon resumption
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                'CẢNH BÁO GIAN LẬN: Bạn vừa rời khỏi ứng dụng! Lần vi phạm: ${attemptState.warnings + 1}',
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
              backgroundColor: Colors.red.shade800,
              duration: const Duration(seconds: 5),
            ),
          );
        }
      });
    }
  }

  String _formatTime(int? seconds) {
    if (seconds == null) return '∞';
    if (seconds <= 0) return '00:00';
    final m = seconds ~/ 60;
    final s = seconds % 60;
    return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
  }

  bool _isAnswered(dynamic answer, String questionType) {
    if (answer == null) return false;
    if (answer is String) return answer.trim().isNotEmpty;
    if (answer is List) return answer.isNotEmpty;
    if (answer is Map) return answer.isNotEmpty;
    return true;
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(examAttemptProvider);
    final theme = Theme.of(context);

    // 1. Loading state
    if (state.isLoading) {
      return const Scaffold(
        body: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              CircularProgressIndicator(),
              AppSpacing.h16,
              Text('Đang chuẩn bị đề thi, vui lòng đợi...'),
            ],
          ),
        ),
      );
    }

    // 2. Error state
    if (state.error != null && state.status != 'submitted') {
      return Scaffold(
        appBar: AppBar(title: const Text('Lỗi')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.error_outline, size: 64, color: Colors.red),
                AppSpacing.h16,
                Text(
                  state.error!,
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyLarge,
                ),
                AppSpacing.h24,
                FilledButton(
                  onPressed: () => _loadData(),
                  child: const Text('Thử lại'),
                ),
              ],
            ),
          ),
        ),
      );
    }

    // 3. Paused state (proctor pause)
    if (state.status == 'paused') {
      return Scaffold(
        body: Container(
          color: Colors.black87,
          width: double.infinity,
          height: double.infinity,
          alignment: Alignment.center,
          padding: const EdgeInsets.all(24),
          child: Card(
            elevation: 8,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
            child: Padding(
              padding: const EdgeInsets.all(32),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.pause_circle_filled, size: 72, color: Colors.amber),
                  AppSpacing.h16,
                  Text(
                    'Bài thi đang tạm dừng',
                    style: theme.textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  AppSpacing.h12,
                  const Text(
                    'Giám thị đã tạm dừng bài thi của bạn. Vui lòng chờ đến khi hệ thống cho phép tiếp tục.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Colors.grey),
                  ),
                ],
              ),
            ),
          ),
        ),
      );
    }

    // 4. Proctor alert modal overlay
    if (state.proctorAlert != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _showProctorAlertDialog(context, state.proctorAlert!);
      });
    }

    // 5. Result state (submitted)
    if (state.status == 'submitted') {
      return _buildResultScreen(context, state);
    }

    // 6. Active exam workspace
    final currentQuestion = state.questions[state.currentIndex];
    final answeredCount = state.questions.where((q) {
      final answer = state.answers[q.id];
      return _isAnswered(answer, q.type);
    }).length;
    final totalCount = state.questions.length;
    final isUrgent = state.remainingTime != null && state.remainingTime! < 300;

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              state.isLessonQuiz ? 'Bài kiểm tra bài học' : 'Kỳ thi chính thức',
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.bold,
                color: theme.colorScheme.primary,
                letterSpacing: 1.2,
              ),
            ),
            Text(
              state.isLessonQuiz ? (state.quiz?.title ?? 'Trắc nghiệm') : (state.exam?.title ?? 'Bài thi'),
              style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
        actions: [
          if (state.autoSaveStatus != null)
            Padding(
              padding: const EdgeInsets.only(right: 8),
              child: Center(
                child: Text(
                  state.autoSaveStatus!,
                  style: TextStyle(fontSize: 11, color: Colors.blue.shade600, fontWeight: FontWeight.bold),
                ),
              ),
            ),
          Container(
            margin: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
            padding: const EdgeInsets.symmetric(horizontal: 10),
            decoration: BoxDecoration(
              color: isUrgent ? Colors.red.shade800 : Colors.grey.shade900,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.timer, size: 14, color: Colors.white),
                AppSpacing.w8,
                Text(
                  _formatTime(state.remainingTime),
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            icon: const Icon(Icons.grid_view),
            onPressed: () => _showNavigationSheet(context, state),
            tooltip: 'Danh sách câu hỏi',
          ),
        ],
      ),
      body: Column(
        children: [
          // Sub-header summary stats
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Đã làm: $answeredCount / $totalCount câu',
                  style: theme.textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w600),
                ),
                if (state.warnings > 0)
                  Text(
                    '⚠ Vi phạm: ${state.warnings} lần',
                    style: TextStyle(
                      color: Colors.red.shade800,
                      fontWeight: FontWeight.bold,
                      fontSize: 11,
                    ),
                  ),
              ],
            ),
          ),

          // Scrollable Question Panel
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Câu hỏi ${state.currentIndex + 1} / $totalCount',
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      IconButton(
                        icon: Icon(
                          state.bookmarks[currentQuestion.id] == true
                              ? Icons.bookmark
                              : Icons.bookmark_border,
                          color: state.bookmarks[currentQuestion.id] == true
                              ? Colors.amber
                              : null,
                        ),
                        onPressed: () {
                          ref.read(examAttemptProvider.notifier).toggleBookmark(currentQuestion.id);
                        },
                      ),
                    ],
                  ),
                  AppSpacing.h12,
                  QuestionDisplay(
                    question: currentQuestion,
                    currentAnswer: state.answers[currentQuestion.id],
                    onAnswerChanged: (newAnswer) {
                      ref.read(examAttemptProvider.notifier).selectAnswer(currentQuestion.id, newAnswer);
                    },
                  ),
                ],
              ),
            ),
          ),

          // Bottom Navigation Buttons
          SafeArea(
            child: Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: theme.colorScheme.surface,
                border: Border(
                  top: BorderSide(color: theme.colorScheme.outlineVariant),
                ),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  OutlinedButton.icon(
                    onPressed: state.currentIndex == 0
                        ? null
                        : () => ref.read(examAttemptProvider.notifier).selectQuestionIndex(state.currentIndex - 1),
                    icon: const Icon(Icons.arrow_back),
                    label: const Text('Câu trước'),
                  ),
                  if (state.currentIndex == totalCount - 1)
                    FilledButton.icon(
                      onPressed: state.isSubmitting
                          ? null
                          : () => _showSubmitConfirmation(context, state, answeredCount, totalCount),
                      icon: const Icon(Icons.check_circle_outline),
                      label: Text(state.isSubmitting ? 'Đang nộp...' : 'Nộp bài'),
                      style: FilledButton.styleFrom(
                        backgroundColor: AppColors.primary400,
                      ),
                    )
                  else
                    FilledButton.icon(
                      onPressed: () =>
                          ref.read(examAttemptProvider.notifier).selectQuestionIndex(state.currentIndex + 1),
                      icon: const Icon(Icons.arrow_forward),
                      label: const Text('Câu sau'),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ── Show Proctor Alert Dialog ──────────────────────────────────────

  void _showProctorAlertDialog(BuildContext context, ProctorMessageModel alert) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      showDialog<void>(
        context: context,
        barrierDismissible: false,
        builder: (ctx) {
          final isCritical = alert.type == 'exam_force_stopped' || alert.title.contains('nghiêm trọng');
          return AlertDialog(
            title: Row(
              children: [
                Icon(
                  isCritical ? Icons.gpp_bad : Icons.campaign,
                  color: isCritical ? Colors.red : Colors.amber,
                ),
                AppSpacing.w12,
                Expanded(
                  child: Text(
                    alert.title,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                ),
              ],
            ),
            content: Text(alert.message),
            actions: [
              TextButton(
                onPressed: () {
                  Navigator.pop(ctx);
                  ref.read(examAttemptProvider.notifier).dismissProctorAlert();
                },
                child: const Text('Đã hiểu'),
              ),
            ],
          );
        },
      );
    });
  }

  // ── Show Submit Confirmation Dialog ─────────────────────────────────

  void _showSubmitConfirmation(
    BuildContext context,
    ExamWorkspaceState state,
    int answeredCount,
    int totalCount,
  ) {
    showDialog<void>(
      context: context,
      builder: (ctx) {
        final unansweredCount = totalCount - answeredCount;

        return AlertDialog(
          title: const Text('Xác nhận nộp bài'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Bạn đã làm $answeredCount trên tổng số $totalCount câu hỏi.'),
              if (unansweredCount > 0) ...[
                AppSpacing.h12,
                Text(
                  'Cảnh báo: Bạn còn $unansweredCount câu hỏi chưa trả lời!',
                  style: TextStyle(
                    color: Colors.red.shade800,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Quay lại'),
            ),
            FilledButton(
              onPressed: () {
                Navigator.pop(ctx); // Close dialog
                ref.read(examAttemptProvider.notifier).submitActiveAttempt();
              },
              child: const Text('Xác nhận nộp'),
            ),
          ],
        );
      },
    );
  }

  // ── Show Navigation Bottom Sheet ───────────────────────────────────

  void _showNavigationSheet(BuildContext context, ExamWorkspaceState state) {
    showModalBottomSheet<void>(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) {
        final theme = Theme.of(ctx);

        return DraggableScrollableSheet(
          initialChildSize: 0.6,
          minChildSize: 0.4,
          maxChildSize: 0.9,
          expand: false,
          builder: (_, scrollController) {
            return SingleChildScrollView(
              controller: scrollController,
              padding: const EdgeInsets.all(24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(
                        color: theme.colorScheme.outlineVariant,
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ),
                  AppSpacing.h16,
                  Text(
                    'Điều hướng câu hỏi',
                    style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  AppSpacing.h8,
                  const Text(
                    'Chọn nhanh câu hỏi cần làm hoặc kiểm tra lại.',
                    style: TextStyle(color: Colors.grey, fontSize: 12),
                  ),
                  AppSpacing.h24,
                  GridView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 5,
                      mainAxisSpacing: 10,
                      crossAxisSpacing: 10,
                      childAspectRatio: 1,
                    ),
                    itemCount: state.questions.length,
                    itemBuilder: (context, index) {
                      final q = state.questions[index];
                      final isCurrent = index == state.currentIndex;
                      final isAnsweredQ = _isAnswered(state.answers[q.id], q.type);
                      final isBookmarked = state.bookmarks[q.id] == true;

                      Color bg;
                      Color text;
                      Border? border;

                      if (isCurrent) {
                        bg = theme.colorScheme.primaryContainer;
                        text = theme.colorScheme.onPrimaryContainer;
                        border = Border.all(color: theme.colorScheme.primary, width: 2);
                      } else if (isAnsweredQ) {
                        bg = Colors.green.shade50;
                        text = Colors.green.shade800;
                        border = Border.all(color: Colors.green.shade200);
                      } else if (isBookmarked) {
                        bg = Colors.amber.shade50;
                        text = Colors.amber.shade800;
                        border = Border.all(color: Colors.amber.shade300);
                      } else {
                        bg = theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.3);
                        text = theme.colorScheme.onSurface;
                      }

                      return InkWell(
                        onTap: () {
                          Navigator.pop(ctx);
                          ref.read(examAttemptProvider.notifier).selectQuestionIndex(index);
                        },
                        borderRadius: BorderRadius.circular(14),
                        child: Stack(
                          clipBehavior: Clip.none,
                          children: [
                            Container(
                              alignment: Alignment.center,
                              decoration: BoxDecoration(
                                color: bg,
                                borderRadius: BorderRadius.circular(14),
                                border: border,
                              ),
                              child: Text(
                                '${index + 1}',
                                style: TextStyle(
                                  fontWeight: FontWeight.bold,
                                  color: text,
                                ),
                              ),
                            ),
                            if (isBookmarked)
                              const Positioned(
                                top: -4,
                                right: -4,
                                child: Icon(
                                  Icons.bookmark,
                                  size: 14,
                                  color: Colors.amber,
                                ),
                              ),
                          ],
                        ),
                      );
                    },
                  ),
                  AppSpacing.h24,
                  Row(
                    children: [
                      _buildLegendDot(Colors.green.shade50, Colors.green.shade200, 'Đã làm'),
                      AppSpacing.w16,
                      _buildLegendDot(Colors.amber.shade50, Colors.amber.shade300, 'Đã lưu ý/bookmark'),
                    ],
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildLegendDot(Color bg, Color stroke, String text) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 14,
          height: 14,
          decoration: BoxDecoration(
            color: bg,
            borderRadius: BorderRadius.circular(4),
            border: Border.all(color: stroke),
          ),
        ),
        AppSpacing.w8,
        Text(text, style: const TextStyle(fontSize: 12, color: Colors.grey)),
      ],
    );
  }

  // ── Result Fullscreen View ─────────────────────────────────────────

  Widget _buildResultScreen(BuildContext context, ExamWorkspaceState state) {
    final theme = Theme.of(context);
    final isPassed = state.result?.passed ?? false;
    final score = state.result?.score ?? 0.0;
    
    final accentColor = isPassed ? Colors.green.shade700 : Colors.red.shade800;

    return Scaffold(
      backgroundColor: isPassed ? Colors.green.shade50 : Colors.red.shade50,
      body: SafeArea(
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              // Icon premium badge
              Container(
                width: 80,
                height: 80,
                decoration: const BoxDecoration(
                  color: Colors.white,
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black12,
                      blurRadius: 10,
                      offset: Offset(0, 4),
                    ),
                  ],
                ),
                child: Icon(
                  isPassed ? Icons.workspace_premium : Icons.task_alt,
                  size: 48,
                  color: accentColor,
                ),
              ),
              AppSpacing.h24,

              Text(
                'Kết quả bài thi',
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.bold,
                  letterSpacing: 1.5,
                  color: Colors.grey.shade700,
                ),
              ),
              AppSpacing.h8,
              Text(
                isPassed ? 'Chúc mừng, bạn đã đạt!' : 'Bạn chưa đạt điểm tối thiểu',
                textAlign: TextAlign.center,
                style: theme.textTheme.headlineMedium?.copyWith(
                  fontWeight: FontWeight.bold,
                  color: accentColor,
                ),
              ),
              AppSpacing.h32,

              // Animated Score Ring CustomPaint
              SizedBox(
                width: 160,
                height: 160,
                child: Stack(
                  alignment: Alignment.center,
                  children: [
                    CustomPaint(
                      size: const Size(160, 160),
                      painter: _ScoreRingPainter(
                        percentage: score / 100,
                        strokeColor: accentColor,
                        trackColor: accentColor.withValues(alpha: 0.12),
                      ),
                    ),
                    Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          '${score.toStringAsFixed(0)}%',
                          style: TextStyle(
                            fontSize: 32,
                            fontWeight: FontWeight.w900,
                            color: accentColor,
                          ),
                        ),
                        Text(
                          'Điểm số',
                          style: TextStyle(
                            fontSize: 11,
                            color: Colors.grey.shade700,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              AppSpacing.h32,

              // Feedback message
              if (state.result?.message != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 24),
                  child: Text(
                    state.result!.message!,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 14,
                      color: Colors.grey.shade700,
                      height: 1.5,
                    ),
                  ),
                ),

              // Return Button
              FilledButton.icon(
                onPressed: () {
                  // Exit workspace - goRouter to home or course details
                  if (state.isLessonQuiz) {
                    context.pop(true); // Return completion indicator
                  } else {
                    context.go('/home');
                  }
                },
                icon: const Icon(Icons.home),
                label: const Text('Quay lại học tập'),
                style: FilledButton.styleFrom(
                  backgroundColor: accentColor,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ── Custom Painter to render gorgeous Circular Score Ring ────────────

class _ScoreRingPainter extends CustomPainter {
  final double percentage;
  final Color strokeColor;
  final Color trackColor;

  _ScoreRingPainter({
    required this.percentage,
    required this.strokeColor,
    required this.trackColor,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2);
    final radius = (size.width - 12) / 2;

    // 1. Draw track ring
    final trackPaint = Paint()
      ..color = trackColor
      ..style = PaintingStyle.stroke
      ..strokeWidth = 10;
    canvas.drawCircle(center, radius, trackPaint);

    // 2. Draw fill arc
    final fillPaint = Paint()
      ..color = strokeColor
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeWidth = 10;
    
    // We rotate -pi/2 to start from top center
    canvas.drawArc(
      Rect.fromCircle(center: center, radius: radius),
      -pi / 2,
      2 * pi * percentage,
      false,
      fillPaint,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => true;
}
