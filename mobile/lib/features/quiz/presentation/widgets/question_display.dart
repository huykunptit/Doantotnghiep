import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/widgets/status_badge.dart';
import 'package:flutter/material.dart';

import '../../data/models/quiz_model.dart';
import 'question_inputs.dart';

/// One question: type badge, prompt, and the answer input for its type.
/// `currentAnswer` shape depends on the type (int, `List<int>`, String, list of
/// options for ordering, map for matching).
class QuestionDisplay extends StatelessWidget {
  const QuestionDisplay({
    super.key,
    required this.question,
    required this.currentAnswer,
    required this.onAnswerChanged,
  });

  final QuestionModel question;
  final dynamic currentAnswer;
  final ValueChanged<dynamic> onAnswerChanged;

  static String typeLabel(String type) {
    switch (type) {
      case 'single_choice':
        return 'Trắc nghiệm';
      case 'multiple_choice':
        return 'Nhiều lựa chọn';
      case 'true_false':
        return 'Đúng / Sai';
      case 'essay':
        return 'Tự luận';
      case 'short_answer':
        return 'Trả lời ngắn';
      case 'numerical':
        return 'Điền số';
      case 'ordering':
        return 'Sắp xếp thứ tự';
      case 'matching':
        return 'Nối cặp';
      default:
        return 'Câu hỏi';
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Align(
          alignment: Alignment.centerLeft,
          child: StatusBadge(
            label: typeLabel(question.type),
            tone: StatusTone.primary,
          ),
        ),
        AppSpacing.h16,
        Container(
          width: double.infinity,
          padding: AppSpacing.p16,
          decoration: BoxDecoration(
            color: cs.surfaceContainerLowest,
            borderRadius: AppRadius.rXl,
            border: Border.all(color: cs.outlineVariant),
          ),
          child: Text(
            question.content,
            style: context.tt.bodyLarge?.copyWith(height: 1.6),
          ),
        ),
        AppSpacing.h24,
        _input(),
      ],
    );
  }

  Widget _input() {
    switch (question.type) {
      case 'single_choice':
      case 'true_false':
        return SingleChoiceInput(
          options: question.answers,
          selectedId: currentAnswer as int?,
          onChanged: onAnswerChanged,
        );
      case 'multiple_choice':
        return MultipleChoiceInput(
          options: question.answers,
          selectedIds: List<int>.from(
            currentAnswer as Iterable<dynamic>? ?? <int>[],
          ),
          onChanged: onAnswerChanged,
        );
      case 'short_answer':
      case 'numerical':
        final numeric = question.type == 'numerical';
        return TextAnswerInput(
          key: ValueKey('text-${question.id}'),
          prompt: 'Nhập câu trả lời của bạn:',
          hint: numeric ? 'Nhập số...' : 'Nhập câu trả lời...',
          initialText: currentAnswer?.toString() ?? '',
          keyboardType: numeric
              ? const TextInputType.numberWithOptions(
                  decimal: true,
                  signed: true,
                )
              : TextInputType.text,
          onChanged: onAnswerChanged,
        );
      case 'essay':
        return TextAnswerInput(
          key: ValueKey('essay-${question.id}'),
          prompt: 'Nhập bài tự luận:',
          hint: 'Viết nội dung tự luận vào đây...',
          initialText: currentAnswer?.toString() ?? '',
          minLines: 4,
          maxLines: 8,
          onChanged: onAnswerChanged,
        );
      case 'ordering':
        // Stored as a list of options in the current order.
        return OrderingInput(
          options: List<QuizAnswerOptionModel>.from(
            currentAnswer as Iterable<dynamic>? ?? question.answers,
          ),
          onChanged: onAnswerChanged,
        );
      case 'matching':
        // Submitted as Map<String, String>: { left_id: right_sub_content }
        return MatchingInput(
          options: question.answers,
          matched: Map<String, String>.from(
            currentAnswer as Map<dynamic, dynamic>? ?? <String, String>{},
          ),
          onChanged: onAnswerChanged,
        );
      default:
        return const SizedBox.shrink();
    }
  }
}
