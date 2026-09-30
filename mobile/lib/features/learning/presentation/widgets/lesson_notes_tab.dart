import 'package:eript_lms/core/error/friendly_error.dart';
import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/widgets/app_card.dart';
import 'package:eript_lms/core/widgets/app_loader.dart';
import 'package:eript_lms/core/widgets/empty_state.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/models/note_model.dart';

/// mm:ss label for a playback position.
String formatNoteTime(int totalSeconds) {
  final safe = totalSeconds < 0 ? 0 : totalSeconds;
  final minutes = safe ~/ 60;
  final seconds = safe % 60;
  return '${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
}

/// "Ghi chú" tab: timestamped notes list + composer. All state (controller,
/// focus, saving flag, current position) is owned by the parent.
class LessonNotesTab extends StatelessWidget {
  const LessonNotesTab({
    super.key,
    required this.notes,
    required this.controller,
    required this.focusNode,
    required this.isSaving,
    required this.currentSeconds,
    required this.onSubmit,
    required this.onFieldTap,
    required this.onSeek,
    required this.onDelete,
  });

  final AsyncValue<List<NoteModel>> notes;
  final TextEditingController controller;
  final FocusNode focusNode;
  final bool isSaving;
  final int currentSeconds;
  final VoidCallback onSubmit;
  final VoidCallback onFieldTap;
  final void Function(int seconds) onSeek;
  final void Function(int noteId) onDelete;

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.viewInsetsOf(context).bottom;
    final cs = context.cs;

    return Column(
      children: [
        Expanded(
          child: notes.when(
            loading: () => const Center(
              child: AppLoader(
                compact: true,
                size: 64,
                message: 'Đang tải ghi chú...',
              ),
            ),
            error: (e, _) => EmptyState(
              icon: Icons.error_outline_rounded,
              title: 'Không tải được ghi chú',
              message: friendlyErrorMessage(e),
            ),
            data: (list) {
              if (list.isEmpty) {
                return const EmptyState(
                  icon: Icons.edit_note_rounded,
                  title: 'Chưa có ghi chú nào',
                  message:
                      'Nhập nội dung bên dưới để lưu tại thời điểm video hiện tại.',
                );
              }
              return ListView.separated(
                padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
                itemCount: list.length,
                separatorBuilder: (_, _) => AppSpacing.h8,
                itemBuilder: (context, i) {
                  final note = list[i];
                  return AppCard(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 4,
                    ),
                    child: Row(
                      children: [
                        ActionChip(
                          label: Text(formatNoteTime(note.timeSeconds)),
                          avatar: const Icon(Icons.play_arrow, size: 14),
                          onPressed: () => onSeek(note.timeSeconds),
                        ),
                        AppSpacing.w8,
                        Expanded(
                          child: Text(
                            note.content,
                            style: context.tt.bodyMedium,
                          ),
                        ),
                        IconButton(
                          tooltip: 'Xóa ghi chú',
                          icon: Icon(
                            Icons.delete_outline,
                            color: context.sem.danger,
                          ),
                          onPressed: () => onDelete(note.id),
                        ),
                      ],
                    ),
                  );
                },
              );
            },
          ),
        ),
        AnimatedPadding(
          duration: const Duration(milliseconds: 180),
          curve: Curves.easeOut,
          padding: EdgeInsets.fromLTRB(
            12,
            8,
            12,
            10 + (bottomInset > 0 ? 0 : MediaQuery.paddingOf(context).bottom),
          ),
          child: Container(
            padding: const EdgeInsets.fromLTRB(12, 10, 8, 10),
            decoration: BoxDecoration(
              color: cs.surfaceContainerLowest,
              borderRadius: AppRadius.rXl,
              border: Border.all(color: cs.outlineVariant),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Expanded(
                  child: TextField(
                    controller: controller,
                    focusNode: focusNode,
                    minLines: 1,
                    maxLines: 4,
                    textInputAction: TextInputAction.newline,
                    decoration: InputDecoration(
                      hintText:
                          'Thêm ghi chú tại ${formatNoteTime(currentSeconds)}...',
                      filled: false,
                      border: InputBorder.none,
                      enabledBorder: InputBorder.none,
                      focusedBorder: InputBorder.none,
                      isDense: true,
                    ),
                    onTap: onFieldTap,
                    onSubmitted: (_) => onSubmit(),
                  ),
                ),
                AppSpacing.w4,
                IconButton.filled(
                  tooltip: 'Lưu ghi chú',
                  onPressed: isSaving ? null : onSubmit,
                  icon: isSaving
                      ? SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: cs.onPrimary,
                          ),
                        )
                      : const Icon(Icons.add),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
