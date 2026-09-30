// Generates PNG screenshots into build/preview/ for visual review.
// Run: flutter test test/preview
import 'package:eript_lms/core/widgets/app_card.dart';
import 'package:eript_lms/core/widgets/app_filled_button.dart';
import 'package:eript_lms/core/widgets/app_list_tile.dart';
import 'package:eript_lms/core/widgets/app_progress_bar.dart';
import 'package:eript_lms/core/widgets/empty_state.dart';
import 'package:eript_lms/core/widgets/section_header.dart';
import 'package:eript_lms/core/widgets/stat_tile.dart';
import 'package:eript_lms/core/widgets/status_badge.dart';
import 'package:eript_lms/features/quiz/data/models/quiz_model.dart';
import 'package:eript_lms/features/quiz/presentation/widgets/exam_chrome.dart';
import 'package:eript_lms/features/quiz/presentation/widgets/exam_result_view.dart';
import 'package:eript_lms/features/quiz/presentation/widgets/question_display.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers/screenshot.dart';

Widget gallery() => Scaffold(
  appBar: AppBar(title: const Text('Bộ widget dùng chung')),
  body: ListView(
    padding: const EdgeInsets.all(16),
    children: [
      const SectionHeader(
        title: 'Thống kê',
        actionLabel: 'Xem tất cả',
        onAction: _noop,
      ),
      const Row(
        children: [
          Expanded(
            child: StatTile(label: 'Khóa học', value: '12', icon: Icons.school),
          ),
          SizedBox(width: 12),
          Expanded(
            child: StatTile(
              label: 'Hoàn thành',
              value: '8',
              icon: Icons.check_circle,
              tone: StatusTone.success,
            ),
          ),
        ],
      ),
      const SizedBox(height: 12),
      const Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          StatusBadge(label: 'Đang học', tone: StatusTone.primary),
          StatusBadge(label: 'Có mặt', tone: StatusTone.success),
          StatusBadge(label: 'Đi muộn', tone: StatusTone.warning),
          StatusBadge(label: 'Vắng', tone: StatusTone.danger),
          StatusBadge(label: 'Thông tin', tone: StatusTone.info),
          StatusBadge(label: 'Khác'),
        ],
      ),
      const SizedBox(height: 12),
      const AppCard(child: AppProgressBar(value: 0.62, label: 'Tiến độ học')),
      const SizedBox(height: 12),
      const AppCard(
        padding: EdgeInsets.symmetric(horizontal: 16),
        child: Column(
          children: [
            AppListTile(
              title: 'Bảng điểm',
              subtitle: 'Học kỳ 1',
              leading: Icon(Icons.analytics_outlined),
              showChevron: true,
            ),
            Divider(),
            AppListTile(
              title: 'Thời khóa biểu',
              leading: Icon(Icons.calendar_month_outlined),
              showChevron: true,
            ),
          ],
        ),
      ),
      const SizedBox(height: 12),
      AppFilledButton(
        label: 'Ghi danh miễn phí',
        expanded: true,
        onPressed: () {},
      ),
      const SizedBox(height: 8),
      const AppFilledButton(
        label: 'Đang xử lý',
        expanded: true,
        loading: true,
        onPressed: null,
      ),
      const SizedBox(height: 8),
      OutlinedButton(onPressed: () {}, child: const Text('Nút viền')),
      const SizedBox(height: 12),
      const SizedBox(
        height: 200,
        child: EmptyState(
          icon: Icons.inbox_outlined,
          title: 'Chưa có dữ liệu',
          message: 'Kéo xuống để làm mới.',
          actionLabel: 'Tải lại',
          onAction: _noop,
        ),
      ),
    ],
  ),
);

void _noop() {}

Widget examScreen(QuestionModel q, dynamic answer) => Scaffold(
  appBar: AppBar(
    title: const ExamTitle(kind: 'Kỳ thi chính thức', title: 'Cơ sở dữ liệu'),
    actions: const [ExamTimerChip(seconds: 245, urgent: true)],
  ),
  body: Column(
    children: [
      FocusLossBanner(warnings: 1, maxWarnings: 3, onClose: _noop),
      const ExamProgressStrip(
        answered: 4,
        total: 10,
        warnings: 1,
        maxWarnings: 3,
      ),
      Expanded(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: QuestionDisplay(
            question: q,
            currentAnswer: answer,
            onAnswerChanged: (_) {},
          ),
        ),
      ),
      ExamBottomBar(
        currentIndex: 3,
        total: 10,
        isSubmitting: false,
        onPrev: _noop,
        onNext: _noop,
        onSubmit: _noop,
      ),
    ],
  ),
);

QuizAnswerOptionModel o(int id, String c) =>
    QuizAnswerOptionModel(id: id, content: c);

void main() {
  setUpAll(loadPreviewFonts);

  for (final mode in Brightness.values) {
    testWidgets('gallery ${mode.name}', (t) async {
      await shoot(t, gallery(), 'gallery_${mode.name}', brightness: mode);
    });

    testWidgets('exam single choice ${mode.name}', (t) async {
      final q = QuestionModel(
        id: 1,
        content: 'Khóa chính (primary key) trong CSDL quan hệ có đặc điểm nào?',
        type: 'single_choice',
        answers: [
          o(1, 'Có thể nhận giá trị NULL'),
          o(2, 'Xác định duy nhất mỗi bản ghi'),
          o(3, 'Luôn là kiểu chuỗi'),
        ],
      );
      await shoot(
        t,
        examScreen(q, 2),
        'exam_single_${mode.name}',
        brightness: mode,
      );
    });

    testWidgets('exam result ${mode.name}', (t) async {
      await shoot(
        t,
        ExamResultView(
          passed: true,
          score: 86,
          message: 'Bạn đã hoàn thành xuất sắc bài thi.',
          onDone: _noop,
        ),
        'exam_result_pass_${mode.name}',
        brightness: mode,
      );
    });
  }

  testWidgets('exam result fail light', (t) async {
    await shoot(
      t,
      ExamResultView(passed: false, score: 32, message: null, onDone: _noop),
      'exam_result_fail_light',
    );
  });

  testWidgets('exam matching light at 1.3x text', (t) async {
    final q = QuestionModel(
      id: 2,
      content: 'Nối khái niệm với mô tả tương ứng.',
      type: 'matching',
      answers: [
        QuizAnswerOptionModel(
          id: 1,
          content: 'Primary key',
          subContent: 'Định danh duy nhất',
        ),
        QuizAnswerOptionModel(
          id: 2,
          content: 'Foreign key',
          subContent: 'Tham chiếu bảng khác',
        ),
      ],
    );
    await shoot(
      t,
      examScreen(q, null),
      'exam_matching_light_1_3x',
      textScale: 1.3,
    );
  });
}
