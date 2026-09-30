import 'package:eript_lms/features/quiz/data/models/quiz_model.dart';
import 'package:eript_lms/features/quiz/presentation/widgets/question_display.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/pump_app.dart';

QuizAnswerOptionModel opt(int id, String c, [String? sub]) =>
    QuizAnswerOptionModel(id: id, content: c, subContent: sub);

QuestionModel q(String type, {List<QuizAnswerOptionModel>? answers}) =>
    QuestionModel(
      id: 7,
      content: 'Nội dung câu hỏi',
      type: type,
      answers: answers ?? [opt(1, 'A'), opt(2, 'B'), opt(3, 'C')],
    );

Future<void> show(
  WidgetTester t,
  QuestionModel question, {
  dynamic answer,
  required ValueChanged<dynamic> onChanged,
  Brightness mode = Brightness.light,
}) {
  return pumpThemed(
    t,
    SingleChildScrollView(
      child: QuestionDisplay(
        question: question,
        currentAnswer: answer,
        onAnswerChanged: onChanged,
      ),
    ),
    brightness: mode,
  );
}

void main() {
  testBothModes('shows content and type label', (t, mode) async {
    await show(t, q('single_choice'), onChanged: (_) {}, mode: mode);
    expect(find.text('Nội dung câu hỏi'), findsOneWidget);
    expect(find.text('Trắc nghiệm'), findsOneWidget);
  });

  test('typeLabel covers every type', () {
    for (final entry in {
      'single_choice': 'Trắc nghiệm',
      'multiple_choice': 'Nhiều lựa chọn',
      'true_false': 'Đúng / Sai',
      'essay': 'Tự luận',
      'short_answer': 'Trả lời ngắn',
      'numerical': 'Điền số',
      'ordering': 'Sắp xếp thứ tự',
      'matching': 'Nối cặp',
      'unknown': 'Câu hỏi',
    }.entries) {
      expect(QuestionDisplay.typeLabel(entry.key), entry.value);
    }
  });

  group('single choice / true-false', () {
    testWidgets('tapping an option emits its id', (t) async {
      dynamic got;
      await show(t, q('single_choice'), onChanged: (v) => got = v);
      await t.tap(find.text('B'));
      expect(got, 2);
    });

    testWidgets('true_false behaves the same', (t) async {
      dynamic got;
      await show(
        t,
        q('true_false', answers: [opt(10, 'Đúng'), opt(11, 'Sai')]),
        onChanged: (v) => got = v,
      );
      await t.tap(find.text('Sai'));
      expect(got, 11);
    });
  });

  group('multiple choice', () {
    testWidgets('adds and removes ids', (t) async {
      dynamic got;
      await show(
        t,
        q('multiple_choice'),
        answer: <int>[1],
        onChanged: (v) => got = v,
      );
      await t.tap(find.text('C'));
      expect(got, [1, 3]);
      await t.tap(find.text('A'));
      expect(got, <int>[]);
    });

    testWidgets('checkbox reflects selection', (t) async {
      await show(t, q('multiple_choice'), answer: <int>[2], onChanged: (_) {});
      final boxes = t.widgetList<Checkbox>(find.byType(Checkbox)).toList();
      expect(boxes.map((c) => c.value), [false, true, false]);
    });
  });

  group('text answers', () {
    testWidgets('short answer shows initial text and emits changes', (t) async {
      dynamic got;
      await show(
        t,
        q('short_answer'),
        answer: 'abc',
        onChanged: (v) => got = v,
      );
      expect(find.text('abc'), findsOneWidget);
      await t.enterText(find.byType(TextField), 'xin chào');
      expect(got, 'xin chào');
    });

    testWidgets('numerical uses a numeric keyboard', (t) async {
      await show(t, q('numerical'), onChanged: (_) {});
      final field = t.widget<TextField>(find.byType(TextField));
      expect(field.keyboardType.index, TextInputType.number.index);
    });

    testWidgets('essay is multi-line', (t) async {
      dynamic got;
      await show(t, q('essay'), onChanged: (v) => got = v);
      final field = t.widget<TextField>(find.byType(TextField));
      expect(field.maxLines, 8);
      await t.enterText(find.byType(TextField), 'bài luận');
      expect(got, 'bài luận');
    });

    testWidgets('typing keeps the cursor when the parent rebuilds', (t) async {
      String? answer;
      late StateSetter set;
      await pumpThemed(
        t,
        StatefulBuilder(
          builder: (context, setState) {
            set = setState;
            return QuestionDisplay(
              question: q('short_answer'),
              currentAnswer: answer,
              onAnswerChanged: (v) => answer = v as String,
            );
          },
        ),
      );
      await t.enterText(find.byType(TextField), 'abc');
      set(() {});
      await t.pump();
      expect(
        t.widget<TextField>(find.byType(TextField)).controller!.text,
        'abc',
      );
    });

    testWidgets('resets when the question changes', (t) async {
      await show(t, q('short_answer'), answer: 'one', onChanged: (_) {});
      expect(find.text('one'), findsOneWidget);
      await pumpThemed(
        t,
        QuestionDisplay(
          question: QuestionModel(
            id: 8,
            content: 'Câu khác',
            type: 'short_answer',
          ),
          currentAnswer: null,
          onAnswerChanged: (_) {},
        ),
      );
      expect(find.text('one'), findsNothing);
    });
  });

  group('ordering', () {
    testWidgets('move down swaps with the next item', (t) async {
      dynamic got;
      await show(t, q('ordering'), onChanged: (v) => got = v);
      await t.tap(find.byIcon(Icons.expand_more).first);
      final ids = (got as List<QuizAnswerOptionModel>).map((o) => o.id);
      expect(ids, [2, 1, 3]);
    });

    testWidgets('first item cannot move up, last cannot move down', (t) async {
      await show(t, q('ordering'), onChanged: (_) {});
      final up = t.widgetList<IconButton>(
        find.widgetWithIcon(IconButton, Icons.expand_less),
      );
      final down = t.widgetList<IconButton>(
        find.widgetWithIcon(IconButton, Icons.expand_more),
      );
      expect(up.first.onPressed, isNull);
      expect(down.last.onPressed, isNull);
    });
  });

  group('matching', () {
    testWidgets('shows one dropdown per left item', (t) async {
      await show(
        t,
        q(
          'matching',
          answers: [opt(1, 'Trái 1', 'Phải X'), opt(2, 'Trái 2', 'Phải Y')],
        ),
        onChanged: (_) {},
      );
      expect(find.byType(DropdownButtonFormField<String>), findsNWidgets(2));
      expect(find.text('Vế trái: Trái 1'), findsOneWidget);
    });

    testWidgets('selecting a value emits the map', (t) async {
      dynamic got;
      await show(
        t,
        q(
          'matching',
          answers: [opt(1, 'Trái 1', 'Phải X'), opt(2, 'Trái 2', 'Phải Y')],
        ),
        onChanged: (v) => got = v,
      );
      await t.tap(find.byType(DropdownButtonFormField<String>).first);
      await t.pumpAndSettle();
      await t.tap(find.text('Phải Y').last);
      await t.pumpAndSettle();
      expect(got, {'1': 'Phải Y'});
    });
  });

  testWidgets('unknown type renders no input', (t) async {
    await show(t, q('weird'), onChanged: (_) {});
    expect(find.byType(TextField), findsNothing);
    expect(find.text('Câu hỏi'), findsOneWidget);
  });
}
