import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../providers/quiz_providers.dart';
import '../widgets/question_display.dart';
import '../widgets/face_verification_gate.dart';
import '../../data/models/exam_precheck_model.dart';
import '../../data/repositories/quiz_repository.dart';
import '../../../../core/error/friendly_error.dart';
import '../../../../core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import '../widgets/exam_chrome.dart';
import '../widgets/exam_dialogs.dart';
import '../widgets/exam_result_view.dart';
import '../widgets/exam_status_views.dart';

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
  ConsumerState<ExamWorkspaceScreen> createState() =>
      _ExamWorkspaceScreenState();
}

class _ExamWorkspaceScreenState extends ConsumerState<ExamWorkspaceScreen>
    with WidgetsBindingObserver {
  bool _isInitialized = false;
  bool _leftApp = false;
  bool _showFocusBanner = false;
  DateTime? _leftAt;
  Timer? _bannerHideTimer;
  String? _shownProctorAlertKey;

  bool _prechecking = false;
  bool _faceVerified = false;
  ExamPrecheckModel? _precheck;
  String? _precheckError;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    // Initialize target Quiz / Exam based on route variables
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadData();
    });
  }

  Future<void> _loadData() async {
    if (widget.examId > 0) {
      setState(() {
        _prechecking = true;
        _precheckError = null;
        _precheck = null;
      });
      try {
        final precheck = await ref
            .read(quizRepositoryProvider)
            .preCheckExam(widget.examId);
        if (!mounted) return;
        setState(() {
          _precheck = precheck;
          _prechecking = false;
        });
        if (precheck.requiresFaceCheck && !_faceVerified) {
          _isInitialized = true;
          return;
        }
        await ref.read(examAttemptProvider.notifier).startExam(widget.examId);
      } catch (e) {
        if (!mounted) return;
        setState(() {
          _prechecking = false;
          _precheckError = friendlyErrorMessage(e);
        });
      }
    } else {
      ref
          .read(examAttemptProvider.notifier)
          .startLessonQuiz(widget.courseId, widget.lessonId);
    }
    _isInitialized = true;
  }

  void _onFaceVerified() {
    setState(() => _faceVerified = true);
    ref.read(examAttemptProvider.notifier).startExam(widget.examId);
  }

  @override
  void didUpdateWidget(ExamWorkspaceScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.examId != widget.examId ||
        oldWidget.courseId != widget.courseId ||
        oldWidget.lessonId != widget.lessonId) {
      _faceVerified = false;
      _precheck = null;
      _precheckError = null;
      _loadData();
    }
  }

  @override
  void dispose() {
    _bannerHideTimer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    super.didChangeAppLifecycleState(state);
    if (!_isInitialized) return;

    final attemptState = ref.read(examAttemptProvider);
    if (attemptState.isLoading ||
        attemptState.isLessonQuiz ||
        attemptState.status != 'in_progress') {
      return;
    }

    // Only treat a real background as leaving. `inactive` fires for the
    // notification shade, keyboard, permission dialogs and even some
    // in-app overlays — counting it makes the warning jump while the
    // student is still answering.
    if (state == AppLifecycleState.hidden ||
        state == AppLifecycleState.paused) {
      if (!_leftApp) {
        _leftApp = true;
        _leftAt = DateTime.now();
      }
      return;
    }

    if (state != AppLifecycleState.resumed || !_leftApp) return;
    _leftApp = false;

    final awayFor = DateTime.now().difference(_leftAt ?? DateTime.now());
    if (awayFor < const Duration(milliseconds: 900)) return;

    final before = ref.read(examAttemptProvider).warnings;
    final count = ref
        .read(examAttemptProvider.notifier)
        .incrementFocusLossViolation();
    if (count <= before) return;

    _revealFocusBanner();

    if (count >= ExamWorkspaceState.maxFocusLoss) {
      Future<void>.delayed(const Duration(milliseconds: 700), () {
        if (!mounted) return;
        ref
            .read(examAttemptProvider.notifier)
            .submitActiveAttempt(isAuto: true);
      });
    }
  }

  void _revealFocusBanner() {
    ScaffoldMessenger.maybeOf(context)?.clearSnackBars();
    _bannerHideTimer?.cancel();
    if (!mounted) return;
    setState(() => _showFocusBanner = true);
    _bannerHideTimer = Timer(const Duration(seconds: 5), () {
      if (mounted) setState(() => _showFocusBanner = false);
    });
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(examAttemptProvider);

    if (_prechecking) {
      return const ExamLoadingView(message: 'Đang kiểm tra quyền vào thi…');
    }

    if (_precheckError != null) {
      return ExamErrorView(
        message: _precheckError!,
        onRetry: _loadData,
        onBack: () => context.pop(),
      );
    }

    if (widget.examId > 0 &&
        _precheck != null &&
        _precheck!.requiresFaceCheck &&
        !_faceVerified) {
      return FaceVerificationGate(
        examId: widget.examId,
        precheck: _precheck!,
        onVerified: _onFaceVerified,
        onCancel: () => context.pop(),
      );
    }

    // 1. Loading state
    if (state.isLoading) {
      return const ExamLoadingView(
        message: 'Đang chuẩn bị đề thi, vui lòng đợi...',
      );
    }

    // 2. Error state
    if (state.error != null && state.status != 'submitted') {
      return ExamErrorView(message: state.error!, onRetry: _loadData);
    }

    // 3. Paused state (proctor pause)
    if (state.status == 'paused') return const ExamPausedView();

    // 4. Proctor alert — show once per message, not on every rebuild
    final alert = state.proctorAlert;
    if (alert != null) {
      final key = '${alert.id}:${alert.createdAt}';
      if (_shownProctorAlertKey != key) {
        _shownProctorAlertKey = key;
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (!mounted) return;
          showProctorAlertDialog(
            context,
            alert,
            onDismissed: () =>
                ref.read(examAttemptProvider.notifier).dismissProctorAlert(),
          );
        });
      }
    }

    // 5. Result state (submitted)
    if (state.status == 'submitted') {
      return ExamResultView(
        passed: state.result?.passed ?? false,
        score: state.result?.score ?? 0.0,
        message: state.result?.message,
        onDone: () {
          if (state.isLessonQuiz) {
            context.pop(true); // Return completion indicator
          } else {
            context.go('/home');
          }
        },
      );
    }

    // 6. Active exam workspace
    final notifier = ref.read(examAttemptProvider.notifier);
    final currentQuestion = state.questions[state.currentIndex];
    final totalCount = state.questions.length;
    final answeredCount = state.questions
        .where((q) => isExamAnswered(state.answers[q.id]))
        .length;
    final isUrgent = state.remainingTime != null && state.remainingTime! < 300;
    final bookmarked = state.bookmarks[currentQuestion.id] == true;

    return Scaffold(
      resizeToAvoidBottomInset: true,
      appBar: AppBar(
        title: ExamTitle(
          kind: state.isLessonQuiz
              ? 'Bài kiểm tra bài học'
              : 'Kỳ thi chính thức',
          title: state.isLessonQuiz
              ? (state.quiz?.title ?? 'Trắc nghiệm')
              : (state.exam?.title ?? 'Bài thi'),
        ),
        actions: [
          if (state.autoSaveStatus != null)
            Padding(
              padding: const EdgeInsets.only(right: 8),
              child: Center(
                child: Text(
                  state.autoSaveStatus!,
                  style: context.tt.labelMedium?.copyWith(
                    color: context.sem.infoFg,
                  ),
                ),
              ),
            ),
          ExamTimerChip(seconds: state.remainingTime, urgent: isUrgent),
          IconButton(
            icon: const Icon(Icons.grid_view),
            tooltip: 'Danh sách câu hỏi',
            onPressed: () => showQuestionNavigator(
              context,
              state,
              onSelect: notifier.selectQuestionIndex,
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          if (_showFocusBanner)
            FocusLossBanner(
              warnings: state.warnings,
              maxWarnings: ExamWorkspaceState.maxFocusLoss,
              onClose: () {
                _bannerHideTimer?.cancel();
                setState(() => _showFocusBanner = false);
              },
            ),
          ExamProgressStrip(
            answered: answeredCount,
            total: totalCount,
            warnings: state.warnings,
            maxWarnings: ExamWorkspaceState.maxFocusLoss,
          ),
          Expanded(
            child: SingleChildScrollView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              padding: EdgeInsets.fromLTRB(
                16,
                16,
                16,
                // Extra bottom padding so fields near the end (e.g. essay
                // answers) can still scroll fully above the keyboard even
                // with the fixed bottom nav bar taking up space.
                16 + MediaQuery.of(context).viewInsets.bottom,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Câu hỏi ${state.currentIndex + 1} / $totalCount',
                        style: context.tt.titleMedium,
                      ),
                      IconButton(
                        tooltip: bookmarked
                            ? 'Bỏ đánh dấu'
                            : 'Đánh dấu câu hỏi',
                        icon: Icon(
                          bookmarked ? Icons.bookmark : Icons.bookmark_border,
                          color: bookmarked ? context.sem.warning : null,
                        ),
                        onPressed: () =>
                            notifier.toggleBookmark(currentQuestion.id),
                      ),
                    ],
                  ),
                  AppSpacing.h12,
                  QuestionDisplay(
                    question: currentQuestion,
                    currentAnswer: state.answers[currentQuestion.id],
                    onAnswerChanged: (newAnswer) =>
                        notifier.selectAnswer(currentQuestion.id, newAnswer),
                  ),
                ],
              ),
            ),
          ),
          ExamBottomBar(
            currentIndex: state.currentIndex,
            total: totalCount,
            isSubmitting: state.isSubmitting,
            onPrev: () => notifier.selectQuestionIndex(state.currentIndex - 1),
            onNext: () => notifier.selectQuestionIndex(state.currentIndex + 1),
            onSubmit: () => showSubmitConfirmDialog(
              context,
              answered: answeredCount,
              total: totalCount,
              onConfirm: () => notifier.submitActiveAttempt(),
            ),
          ),
        ],
      ),
    );
  }
}
