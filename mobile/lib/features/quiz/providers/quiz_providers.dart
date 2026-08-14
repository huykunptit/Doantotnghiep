import 'dart:async';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../data/models/quiz_model.dart';
import '../data/repositories/quiz_repository.dart';
import '../../../core/error/friendly_error.dart';

part 'quiz_providers.g.dart';

class ExamWorkspaceState {
  static const Object _unset = Object();
  static const int maxFocusLoss = 3;

  final bool isLoading;
  final String? error;
  final ExamModel? exam;
  final QuizDetailModel? quiz;
  final List<QuestionModel> questions;
  final int? attemptId;
  final int? remainingTime; // in seconds
  final int currentIndex;
  final Map<int, dynamic> answers;
  final Map<int, bool> bookmarks;
  final String status; // in_progress, paused, submitted, force_stopped
  final QuizAttemptModel? result;
  final String? autoSaveStatus;
  final bool isSubmitting;
  final ProctorMessageModel? proctorAlert;
  final int warnings;
  final bool isLessonQuiz;
  final int? courseId;
  final int? lessonId;

  ExamWorkspaceState({
    this.isLoading = true,
    this.error,
    this.exam,
    this.quiz,
    this.questions = const [],
    this.attemptId,
    this.remainingTime,
    this.currentIndex = 0,
    this.answers = const {},
    this.bookmarks = const {},
    this.status = 'in_progress',
    this.result,
    this.autoSaveStatus,
    this.isSubmitting = false,
    this.proctorAlert,
    this.warnings = 0,
    this.isLessonQuiz = false,
    this.courseId,
    this.lessonId,
  });

  ExamWorkspaceState copyWith({
    bool? isLoading,
    String? error,
    ExamModel? exam,
    QuizDetailModel? quiz,
    List<QuestionModel>? questions,
    int? attemptId,
    int? remainingTime,
    int? currentIndex,
    Map<int, dynamic>? answers,
    Map<int, bool>? bookmarks,
    String? status,
    QuizAttemptModel? result,
    String? autoSaveStatus,
    bool? isSubmitting,
    Object? proctorAlert = _unset,
    int? warnings,
    bool? isLessonQuiz,
    int? courseId,
    int? lessonId,
  }) {
    return ExamWorkspaceState(
      isLoading: isLoading ?? this.isLoading,
      error: error ?? this.error,
      exam: exam ?? this.exam,
      quiz: quiz ?? this.quiz,
      questions: questions ?? this.questions,
      attemptId: attemptId ?? this.attemptId,
      remainingTime: remainingTime ?? this.remainingTime,
      currentIndex: currentIndex ?? this.currentIndex,
      answers: answers ?? this.answers,
      bookmarks: bookmarks ?? this.bookmarks,
      status: status ?? this.status,
      result: result ?? this.result,
      autoSaveStatus: autoSaveStatus ?? this.autoSaveStatus,
      isSubmitting: isSubmitting ?? this.isSubmitting,
      proctorAlert: identical(proctorAlert, _unset)
          ? this.proctorAlert
          : proctorAlert as ProctorMessageModel?,
      warnings: warnings ?? this.warnings,
      isLessonQuiz: isLessonQuiz ?? this.isLessonQuiz,
      courseId: courseId ?? this.courseId,
      lessonId: lessonId ?? this.lessonId,
    );
  }
}

@riverpod
class ExamAttempt extends _$ExamAttempt {
  Timer? _timer;
  Timer? _pollTimer;
  Timer? _autoSaveDebounce;
  String? _lastMessageAt;
  DateTime? _lastFocusLossAt;
  bool _hasUnsavedChanges = false;

  @override
  ExamWorkspaceState build() {
    // Return initial state
    ref.onDispose(() {
      _cancelTimers();
    });
    return ExamWorkspaceState();
  }

  void _cancelTimers() {
    _timer?.cancel();
    _pollTimer?.cancel();
    _autoSaveDebounce?.cancel();
  }

  // ── Start Lesson-bound Quiz ────────────────────────────────────────

  Future<void> startLessonQuiz(int courseId, int lessonId) async {
    state = ExamWorkspaceState(isLoading: true, isLessonQuiz: true, courseId: courseId, lessonId: lessonId);
    try {
      final quizData = await ref.read(quizRepositoryProvider).getLessonQuiz(courseId, lessonId);
      final QuizDetailModel quiz = quizData['quiz'] as QuizDetailModel;
      final List<QuestionModel> questions = quizData['questions'] as List<QuestionModel>;
      final int attemptId = quizData['attempt_id'] as int;

      // Initialize default answers
      final answers = <int, dynamic>{};
      for (final q in questions) {
        if (q.type == 'multiple_choice') {
          answers[q.id] = <int>[];
        } else if (q.type == 'ordering') {
          answers[q.id] = List<QuizAnswerOptionModel>.from(q.answers);
        } else {
          answers[q.id] = null;
        }
      }

      state = state.copyWith(
        isLoading: false,
        quiz: quiz,
        questions: questions,
        attemptId: attemptId,
        answers: answers,
        status: 'in_progress',
        remainingTime: quiz.timeLimit != null ? quiz.timeLimit! * 60 : null,
      );

      _startLocalTimer();
    } catch (e) {
      state = state.copyWith(isLoading: false, error: friendlyErrorMessage(e));
    }
  }

  // ── Start Exam ─────────────────────────────────────────────────────

  Future<void> startExam(int examId) async {
    state = ExamWorkspaceState(isLoading: true, isLessonQuiz: false);
    _lastMessageAt = DateTime.now().toUtc().toIso8601String();
    try {
      final examData = await ref.read(quizRepositoryProvider).startExam(examId);
      final ExamModel exam = examData['exam'] as ExamModel;
      final QuizDetailModel quiz = examData['quiz'] as QuizDetailModel;
      final List<QuestionModel> questions = examData['questions'] as List<QuestionModel>;
      final int attemptId = examData['attempt_id'] as int;
      final int remainingTime = examData['remaining_time'] as int;
      final String attemptStatus = examData['status'] as String;
      final Map<String, dynamic> savedAnswers = examData['saved_answers'] as Map<String, dynamic>;

      // Initialize answers from saved list or default
      final answers = <int, dynamic>{};
      for (final q in questions) {
        final savedVal = savedAnswers[q.id.toString()];
        if (savedVal != null) {
          if (q.type == 'multiple_choice') {
            answers[q.id] = List<int>.from(savedVal as List<dynamic>);
          } else if (q.type == 'ordering') {
            // Re-order answers list based on saved item IDs
            final List<dynamic> savedIds = savedVal as List<dynamic>;
            final ordered = <QuizAnswerOptionModel>[];
            for (final id in savedIds) {
              final opt = q.answers.firstWhere((a) => a.id == id, orElse: () => q.answers.first);
              ordered.add(opt);
            }
            // Append missing ones just in case
            for (final opt in q.answers) {
              if (!ordered.contains(opt)) ordered.add(opt);
            }
            answers[q.id] = ordered;
          } else if (q.type == 'single_choice' || q.type == 'true_false') {
            answers[q.id] = int.tryParse(savedVal.toString());
          } else {
            answers[q.id] = savedVal;
          }
        } else {
          if (q.type == 'multiple_choice') {
            answers[q.id] = <int>[];
          } else if (q.type == 'ordering') {
            answers[q.id] = List<QuizAnswerOptionModel>.from(q.answers);
          } else {
            answers[q.id] = null;
          }
        }
      }

      state = state.copyWith(
        isLoading: false,
        exam: exam,
        quiz: quiz,
        questions: questions,
        attemptId: attemptId,
        remainingTime: remainingTime,
        status: attemptStatus,
        answers: answers,
      );

      _startLocalTimer();
      _startStatusPolling();
    } catch (e) {
      state = state.copyWith(isLoading: false, error: friendlyErrorMessage(e));
    }
  }

  // ── Timer Logic ───────────────────────────────────────────────────

  void _startLocalTimer() {
    _timer?.cancel();
    if (state.remainingTime == null) return;

    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (state.status == 'paused') return;

      final currentRemaining = state.remainingTime;
      if (currentRemaining != null && currentRemaining > 0) {
        state = state.copyWith(remainingTime: currentRemaining - 1);
      } else if (currentRemaining != null && currentRemaining <= 0) {
        _timer?.cancel();
        _handleTimeExpired();
      }
    });
  }

  void _handleTimeExpired() {
    submitActiveAttempt(isAuto: true);
  }

  // ── Status Polling Logic ──────────────────────────────────────────

  void _startStatusPolling() {
    _pollTimer?.cancel();
    if (state.isLessonQuiz || state.attemptId == null) return;

    _pollTimer = Timer.periodic(const Duration(seconds: 10), (timer) async {
      final attemptId = state.attemptId;
      if (attemptId == null || state.status == 'submitted') return;

      try {
        final statusRes = await ref.read(quizRepositoryProvider).getExamStatus(
              attemptId,
              since: _lastMessageAt,
            );
        
        final newStatus = statusRes['status']?.toString() ?? state.status;
        final newTime = statusRes['remaining_time'] as int?;
        final timeExpired = statusRes['time_expired'] as bool? ?? false;
        
        // Proctor alerts / messages
        final messages = statusRes['messages'] as List<dynamic>?;
        ProctorMessageModel? alert;
        if (messages != null && messages.isNotEmpty) {
          alert = ProctorMessageModel.fromJson(messages.first as Map<String, dynamic>);
          _lastMessageAt = alert.createdAt;
        }

        state = state.copyWith(
          status: newStatus,
          remainingTime: newTime ?? state.remainingTime,
          proctorAlert: alert ?? state.proctorAlert,
        );

        if (timeExpired) {
          _handleTimeExpired();
        }
      } catch (_) {}
    });
  }

  void dismissProctorAlert() {
    state = state.copyWith(proctorAlert: null);
  }

  // ── Answer Selections ──────────────────────────────────────────────

  void selectAnswer(int questionId, dynamic value) {
    if (state.status != 'in_progress') return;

    final updatedAnswers = Map<int, dynamic>.from(state.answers);
    updatedAnswers[questionId] = value;
    state = state.copyWith(answers: updatedAnswers);

    _hasUnsavedChanges = true;
    _triggerAutoSaveDebounce();
  }

  void toggleMultipleChoice(int questionId, int choiceId) {
    if (state.status != 'in_progress') return;

    final updatedAnswers = Map<int, dynamic>.from(state.answers);
    final currentList = List<int>.from(updatedAnswers[questionId] as List<dynamic>? ?? <int>[]);
    
    if (currentList.contains(choiceId)) {
      currentList.remove(choiceId);
    } else {
      currentList.add(choiceId);
    }
    
    updatedAnswers[questionId] = currentList;
    state = state.copyWith(answers: updatedAnswers);

    _hasUnsavedChanges = true;
    _triggerAutoSaveDebounce();
  }

  void moveOrderingItem(int questionId, int oldIndex, int direction) {
    if (state.status != 'in_progress') return;

    final updatedAnswers = Map<int, dynamic>.from(state.answers);
    final currentList = List<QuizAnswerOptionModel>.from(updatedAnswers[questionId] as Iterable<dynamic>);
    
    final newIndex = oldIndex + direction;
    if (newIndex < 0 || newIndex >= currentList.length) return;

    final temp = currentList[oldIndex];
    currentList[oldIndex] = currentList[newIndex];
    currentList[newIndex] = temp;

    updatedAnswers[questionId] = currentList;
    state = state.copyWith(answers: updatedAnswers);

    _hasUnsavedChanges = true;
    _triggerAutoSaveDebounce();
  }

  // ── Bookmarks ─────────────────────────────────────────────────────

  void toggleBookmark(int questionId) {
    final updatedBookmarks = Map<int, bool>.from(state.bookmarks);
    updatedBookmarks[questionId] = !(updatedBookmarks[questionId] ?? false);
    state = state.copyWith(bookmarks: updatedBookmarks);
  }

  void selectQuestionIndex(int index) {
    if (index >= 0 && index < state.questions.length) {
      state = state.copyWith(currentIndex: index);
    }
  }

  // ── Auto-save logic ────────────────────────────────────────────────

  void _triggerAutoSaveDebounce() {
    if (state.isLessonQuiz) return; // Lesson-bound quizzes do not auto-save via poll

    _autoSaveDebounce?.cancel();
    _autoSaveDebounce = Timer(const Duration(seconds: 5), () {
      _executeAutoSave();
    });
  }

  Future<void> _executeAutoSave() async {
    final attemptId = state.attemptId;
    if (attemptId == null || state.status != 'in_progress' || !_hasUnsavedChanges) return;

    state = state.copyWith(autoSaveStatus: 'Đang lưu...');
    try {
      final formattedAnswers = _formatAnswersForSubmit();
      final res = await ref.read(quizRepositoryProvider).autoSaveExam(
            attemptId,
            answers: formattedAnswers,
          );
      _hasUnsavedChanges = false;
      state = state.copyWith(
        autoSaveStatus: 'Đã lưu tự động',
        status: res['status']?.toString() ?? state.status,
        remainingTime: res['remaining_time'] as int? ?? state.remainingTime,
      );

      Future.delayed(const Duration(seconds: 3), () {
        if (state.autoSaveStatus == 'Đã lưu tự động') {
          state = state.copyWith(autoSaveStatus: null);
        }
      });
    } catch (_) {
      state = state.copyWith(autoSaveStatus: 'Lỗi lưu tự động');
    }
  }

  // Helper to format answers dictionary to match backend structures
  Map<String, dynamic> _formatAnswersForSubmit() {
    final formatted = <String, dynamic>{};
    state.answers.forEach((qId, val) {
      if (val is List<QuizAnswerOptionModel>) {
        // Ordering: list of choice IDs in ordered positions
        formatted[qId.toString()] = val.map((e) => e.id).toList();
      } else {
        formatted[qId.toString()] = val;
      }
    });
    return formatted;
  }

  // ── Submit Attempt ────────────────────────────────────────────────

  Future<void> submitActiveAttempt({bool isAuto = false}) async {
    final attemptId = state.attemptId;
    if (attemptId == null || state.isSubmitting) return;

    _cancelTimers();
    state = state.copyWith(isSubmitting: true);

    try {
      final formattedAnswers = _formatAnswersForSubmit();
      QuizAttemptModel attemptResult;

      if (state.isLessonQuiz) {
        attemptResult = await ref.read(quizRepositoryProvider).submitLessonQuiz(
              state.courseId!,
              state.lessonId!,
              state.quiz!.id,
              attemptId: attemptId,
              answers: formattedAnswers,
            );
      } else {
        attemptResult = await ref.read(quizRepositoryProvider).submitExam(
              state.exam!.id,
              attemptId: attemptId,
              answers: formattedAnswers,
            );
      }

      state = state.copyWith(
        isSubmitting: false,
        status: 'submitted',
        result: attemptResult,
      );
    } catch (e) {
      state = state.copyWith(
        isSubmitting: false,
        error: isAuto ? 'Tự động nộp bài lỗi: ${friendlyErrorMessage(e)}' : friendlyErrorMessage(e),
      );
      // Restart timers if it wasn't auto
      if (!isAuto) {
        _startLocalTimer();
        _startStatusPolling();
      }
    }
  }

  // ── Proctoring (Cheating logs) ────────────────────────────────────

  /// Counts a real leave-app event. Returns the new warning count, or the
  /// previous count when the event is ignored (lesson quiz, cooldown, cap).
  int incrementFocusLossViolation() {
    if (state.isLessonQuiz || state.status != 'in_progress') {
      return state.warnings;
    }
    if (state.warnings >= ExamWorkspaceState.maxFocusLoss) {
      return state.warnings;
    }

    final now = DateTime.now();
    if (_lastFocusLossAt != null &&
        now.difference(_lastFocusLossAt!) < const Duration(seconds: 2)) {
      return state.warnings;
    }
    _lastFocusLossAt = now;

    final next = state.warnings + 1;
    final critical = next >= ExamWorkspaceState.maxFocusLoss;
    state = state.copyWith(warnings: next);

    final attemptId = state.attemptId;
    if (attemptId != null) {
      unawaited(() async {
        try {
          await ref.read(quizRepositoryProvider).logViolation(
                attemptId,
                type: 'focus_lost',
                severity: critical ? 'critical' : 'warning',
                metadata: {
                  'count': next,
                  'max': ExamWorkspaceState.maxFocusLoss,
                  'timestamp': now.toIso8601String(),
                },
              );
        } catch (_) {}
      }());
    }

    return next;
  }
}
