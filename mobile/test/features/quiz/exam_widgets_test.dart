import 'package:eript_lms/features/quiz/presentation/widgets/exam_chrome.dart';
import 'package:eript_lms/features/quiz/presentation/widgets/exam_dialogs.dart';
import 'package:eript_lms/features/quiz/presentation/widgets/exam_result_view.dart';
import 'package:eript_lms/features/quiz/presentation/widgets/exam_status_views.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/pump_app.dart';

void main() {
  group('helpers', () {
    test('formatExamTime', () {
      expect(formatExamTime(null), '∞');
      expect(formatExamTime(0), '00:00');
      expect(formatExamTime(-5), '00:00');
      expect(formatExamTime(65), '01:05');
      expect(formatExamTime(3600), '60:00');
    });

    test('isExamAnswered', () {
      expect(isExamAnswered(null), isFalse);
      expect(isExamAnswered(''), isFalse);
      expect(isExamAnswered('   '), isFalse);
      expect(isExamAnswered('x'), isTrue);
      expect(isExamAnswered(<int>[]), isFalse);
      expect(isExamAnswered([1]), isTrue);
      expect(isExamAnswered(<String, String>{}), isFalse);
      expect(isExamAnswered({'1': 'a'}), isTrue);
      expect(isExamAnswered(0), isTrue);
    });
  });

  group('chrome', () {
    testBothModes('timer chip shows time', (t, mode) async {
      await pumpThemed(
        t,
        const ExamTimerChip(seconds: 125, urgent: false),
        brightness: mode,
      );
      expect(find.text('02:05'), findsOneWidget);
    });

    testWidgets('urgent timer uses the danger color', (t) async {
      await pumpThemed(t, const ExamTimerChip(seconds: 10, urgent: true));
      final box = t.widget<Container>(find.byType(Container).first);
      expect((box.decoration as BoxDecoration).color, isNotNull);
      expect(find.text('00:10'), findsOneWidget);
    });

    testWidgets('focus banner warns and closes', (t) async {
      var closed = false;
      await pumpThemed(
        t,
        FocusLossBanner(
          warnings: 1,
          maxWarnings: 3,
          onClose: () => closed = true,
        ),
      );
      expect(find.textContaining('1/3'), findsOneWidget);
      await t.tap(find.byIcon(Icons.close));
      expect(closed, isTrue);
    });

    testWidgets('focus banner states auto-submit at the limit', (t) async {
      await pumpThemed(
        t,
        FocusLossBanner(warnings: 3, maxWarnings: 3, onClose: () {}),
      );
      expect(find.textContaining('đang nộp bài'), findsOneWidget);
    });

    testWidgets('progress strip shows counts and warnings', (t) async {
      await pumpThemed(
        t,
        const ExamProgressStrip(
          answered: 4,
          total: 10,
          warnings: 2,
          maxWarnings: 3,
        ),
      );
      expect(find.text('Đã làm: 4 / 10 câu'), findsOneWidget);
      expect(find.text('Rời app: 2/3'), findsOneWidget);
    });

    testWidgets('progress strip hides warnings when zero', (t) async {
      await pumpThemed(
        t,
        const ExamProgressStrip(
          answered: 0,
          total: 5,
          warnings: 0,
          maxWarnings: 3,
        ),
      );
      expect(find.textContaining('Rời app'), findsNothing);
    });
  });

  group('bottom bar', () {
    Widget bar({
      int index = 0,
      int total = 3,
      bool submitting = false,
      List<String>? log,
    }) => ExamBottomBar(
      currentIndex: index,
      total: total,
      isSubmitting: submitting,
      onPrev: () => log?.add('prev'),
      onNext: () => log?.add('next'),
      onSubmit: () => log?.add('submit'),
    );

    testWidgets('first question disables Previous', (t) async {
      await pumpThemed(t, bar());
      final prev = t.widget<OutlinedButton>(find.byType(OutlinedButton));
      expect(prev.onPressed, isNull);
      expect(find.text('Câu sau'), findsOneWidget);
    });

    testWidgets('middle question navigates both ways', (t) async {
      final log = <String>[];
      await pumpThemed(t, bar(index: 1, log: log));
      await t.tap(find.text('Câu trước'));
      await t.tap(find.text('Câu sau'));
      expect(log, ['prev', 'next']);
    });

    testWidgets('last question offers submit', (t) async {
      final log = <String>[];
      await pumpThemed(t, bar(index: 2, log: log));
      expect(find.text('Câu sau'), findsNothing);
      await t.tap(find.text('Nộp bài'));
      expect(log, ['submit']);
    });

    testWidgets('submitting disables the submit button', (t) async {
      final log = <String>[];
      await pumpThemed(t, bar(index: 2, submitting: true, log: log));
      expect(find.text('Đang nộp...'), findsOneWidget);
      await t.tap(find.text('Đang nộp...'), warnIfMissed: false);
      expect(log, isEmpty);
    });
  });

  group('status views', () {
    testBothModes('error view retries and goes back', (t, mode) async {
      final log = <String>[];
      await pumpThemed(
        t,
        SizedBox(
          height: 600,
          child: ExamErrorView(
            message: 'Lỗi mạng',
            onRetry: () => log.add('retry'),
            onBack: () => log.add('back'),
          ),
        ),
        brightness: mode,
      );
      expect(find.text('Lỗi mạng'), findsOneWidget);
      await t.tap(find.text('Thử lại'));
      await t.tap(find.text('Quay lại'));
      expect(log, ['retry', 'back']);
    });

    testWidgets('error view without back hides the button', (t) async {
      await pumpThemed(t, ExamErrorView(message: 'x', onRetry: () {}));
      expect(find.text('Quay lại'), findsNothing);
    });

    testWidgets('paused view explains the pause', (t) async {
      await pumpThemed(t, const ExamPausedView());
      expect(find.text('Bài thi đang tạm dừng'), findsOneWidget);
    });

    testWidgets('loading view shows its message', (t) async {
      await pumpThemed(t, const ExamLoadingView(message: 'Đang chuẩn bị'));
      expect(find.text('Đang chuẩn bị'), findsOneWidget);
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
    });
  });

  group('result view', () {
    testBothModes('passed', (t, mode) async {
      var done = false;
      await pumpThemed(
        t,
        ExamResultView(
          passed: true,
          score: 85,
          message: 'Làm tốt lắm',
          onDone: () => done = true,
        ),
        brightness: mode,
      );
      expect(find.text('Chúc mừng, bạn đã đạt!'), findsOneWidget);
      expect(find.text('85%'), findsOneWidget);
      expect(find.text('Làm tốt lắm'), findsOneWidget);
      await t.tap(find.text('Quay lại học tập'));
      expect(done, isTrue);
    });

    testWidgets('failed without message', (t) async {
      await pumpThemed(
        t,
        ExamResultView(passed: false, score: 30, message: null, onDone: () {}),
      );
      expect(find.text('Bạn chưa đạt điểm tối thiểu'), findsOneWidget);
      expect(find.text('30%'), findsOneWidget);
    });
  });

  group('submit dialog', () {
    Future<void> open(
      WidgetTester t, {
      required int answered,
      required int total,
      required VoidCallback onConfirm,
    }) async {
      await pumpThemed(
        t,
        Builder(
          builder: (context) => TextButton(
            onPressed: () => showSubmitConfirmDialog(
              context,
              answered: answered,
              total: total,
              onConfirm: onConfirm,
            ),
            child: const Text('mở'),
          ),
        ),
      );
      await t.tap(find.text('mở'));
      await t.pumpAndSettle();
    }

    testWidgets('warns about unanswered questions', (t) async {
      await open(t, answered: 7, total: 10, onConfirm: () {});
      expect(
        find.text('Bạn đã làm 7 trên tổng số 10 câu hỏi.'),
        findsOneWidget,
      );
      expect(find.textContaining('còn 3 câu hỏi chưa trả lời'), findsOneWidget);
    });

    testWidgets('no warning when everything is answered', (t) async {
      await open(t, answered: 10, total: 10, onConfirm: () {});
      expect(find.textContaining('chưa trả lời'), findsNothing);
    });

    testWidgets('cancel does not submit, confirm does', (t) async {
      var confirmed = 0;
      await open(t, answered: 1, total: 2, onConfirm: () => confirmed++);
      await t.tap(find.text('Quay lại'));
      await t.pumpAndSettle();
      expect(confirmed, 0);

      await t.tap(find.text('mở'));
      await t.pumpAndSettle();
      await t.tap(find.text('Xác nhận nộp'));
      await t.pumpAndSettle();
      expect(confirmed, 1);
    });
  });
}
