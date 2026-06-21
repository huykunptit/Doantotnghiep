import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../data/models/lesson_detail_model.dart';
import '../data/models/note_model.dart';
import '../data/models/attachment_model.dart';
import '../data/repositories/learning_repository.dart';

part 'learning_providers.g.dart';

@riverpod
Future<LessonDetailModel> lessonDetail(Ref ref, int courseId, int lessonId) {
  return ref.read(learningRepositoryProvider).getLessonDetail(courseId, lessonId);
}

@riverpod
class LessonNotes extends _$LessonNotes {
  @override
  Future<List<NoteModel>> build(int courseId, int lessonId) {
    return ref.read(learningRepositoryProvider).getLessonNotes(courseId, lessonId);
  }

  Future<void> addNote({required String content, required int timeSeconds}) async {
    final newNote = await ref.read(learningRepositoryProvider).createLessonNote(
          courseId,
          lessonId,
          content: content,
          timeSeconds: timeSeconds,
        );
    state = AsyncData([...state.value ?? [], newNote]);
  }

  Future<void> removeNote(int noteId) async {
    await ref.read(learningRepositoryProvider).deleteLessonNote(courseId, lessonId, noteId);
    state = AsyncData((state.value ?? []).where((n) => n.id != noteId).toList());
  }
}

@riverpod
Future<List<AttachmentModel>> lessonAttachments(Ref ref, int courseId, int lessonId) {
  return ref.read(learningRepositoryProvider).getLessonAttachments(courseId, lessonId);
}
