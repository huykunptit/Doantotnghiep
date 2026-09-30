// Screenshot of the lesson screen pieces with fake data.
import 'package:eript_lms/features/learning/data/models/lesson_detail_model.dart';
import 'package:eript_lms/features/learning/data/models/note_model.dart';
import 'package:eript_lms/features/learning/presentation/widgets/lesson_content_tab.dart';
import 'package:eript_lms/features/learning/presentation/widgets/lesson_notes_tab.dart';
import 'package:eript_lms/features/learning/presentation/widgets/lesson_player_area.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers/screenshot.dart';

LessonDetailModel lesson(String type) => LessonDetailModel.fromJson({
  'id': 3,
  'course_id': 1,
  'title': 'Bài 3: Chuẩn hóa dữ liệu',
  'description': '<p>Nội dung bài học về các dạng chuẩn 1NF, 2NF, 3NF.</p>',
  'type': type,
  'duration': 900,
});

Widget page(String type, {required bool notes}) {
  final l = lesson(type);
  return DefaultTabController(
    length: 3,
    initialIndex: notes ? 1 : 0,
    child: Scaffold(
      appBar: AppBar(title: const Text('Cơ sở dữ liệu nâng cao')),
      body: Column(
        children: [
          LessonPlayerArea(
            lesson: l,
            hasError: false,
            errorMessage: null,
            isYoutube: false,
            isLoading: false,
            youtubeController: null,
            chewieController: null,
            onRetry: () {},
            onStartQuiz: () {},
          ),
          const TabBar(
            tabs: [
              Tab(text: 'Bài giảng'),
              Tab(text: 'Ghi chú'),
              Tab(text: 'Tài liệu'),
            ],
          ),
          Expanded(
            child: TabBarView(
              children: [
                LessonContentTab(lesson: l, onMarkCompleted: () {}),
                LessonNotesTab(
                  notes: AsyncData([
                    NoteModel.fromJson({
                      'id': 1,
                      'lesson_id': 3,
                      'content': 'Nhớ ví dụ về phụ thuộc hàm.',
                      'position_seconds': 75,
                    }),
                    NoteModel.fromJson({
                      'id': 2,
                      'lesson_id': 3,
                      'content': 'Ôn lại 3NF trước buổi thi.',
                      'position_seconds': 312,
                    }),
                  ]),
                  controller: TextEditingController(),
                  focusNode: FocusNode(),
                  isSaving: false,
                  currentSeconds: 90,
                  onSubmit: () {},
                  onFieldTap: () {},
                  onSeek: (_) {},
                  onDelete: (_) {},
                ),
                const Center(child: Text('Tài liệu')),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

void main() {
  setUpAll(loadPreviewFonts);

  for (final mode in Brightness.values) {
    testWidgets('lesson document + content ${mode.name}', (t) async {
      await shoot(
        t,
        page('document', notes: false),
        'lesson_content_${mode.name}',
        brightness: mode,
      );
    });
    testWidgets('lesson notes ${mode.name}', (t) async {
      await shoot(
        t,
        page('quiz', notes: true),
        'lesson_notes_${mode.name}',
        brightness: mode,
      );
    });
  }
}
