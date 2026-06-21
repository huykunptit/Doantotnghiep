import 'package:flutter/material.dart';
import '../../data/models/quiz_model.dart';
import '../../../../core/theme/app_spacing.dart';

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

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Question header type badge
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: theme.colorScheme.primaryContainer,
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                _getTypeText(question.type),
                style: TextStyle(
                  color: theme.colorScheme.onPrimaryContainer,
                  fontWeight: FontWeight.bold,
                  fontSize: 12,
                ),
              ),
            ),
          ],
        ),
        AppSpacing.h16,

        // Question content text
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: theme.colorScheme.surfaceContainerLow,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: theme.colorScheme.outlineVariant),
          ),
          child: Text(
            question.content,
            style: theme.textTheme.bodyLarge?.copyWith(
              height: 1.6,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
        AppSpacing.h24,

        // Answer options engine
        _buildAnswerInput(context),
      ],
    );
  }

  String _getTypeText(String type) {
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

  Widget _buildAnswerInput(BuildContext context) {
    switch (question.type) {
      case 'single_choice':
      case 'true_false':
        return _buildSingleChoice(context);
      case 'multiple_choice':
        return _buildMultipleChoice(context);
      case 'short_answer':
      case 'numerical':
        return _buildTextOrNumericalInput(context);
      case 'essay':
        return _buildEssayInput(context);
      case 'ordering':
        return _buildOrdering(context);
      case 'matching':
        return _buildMatching(context);
      default:
        return const SizedBox.shrink();
    }
  }

  // ── Single Choice / True False ─────────────────────────────────────

  Widget _buildSingleChoice(BuildContext context) {
    final theme = Theme.of(context);
    final selectedId = currentAnswer as int?;

    return ListView.separated(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: question.answers.length,
      separatorBuilder: (_, _) => AppSpacing.h12,
      itemBuilder: (context, index) {
        final option = question.answers[index];
        final isSelected = selectedId == option.id;

        return InkWell(
          onTap: () => onAnswerChanged(option.id),
          borderRadius: BorderRadius.circular(14),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            decoration: BoxDecoration(
              color: isSelected ? theme.colorScheme.primaryContainer.withValues(alpha: 0.4) : theme.colorScheme.surface,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: isSelected ? theme.colorScheme.primary : theme.colorScheme.outlineVariant,
                width: isSelected ? 2 : 1,
              ),
            ),
            child: Row(
              children: [
                Radio<int>(
                  value: option.id,
                  groupValue: selectedId,
                  activeColor: theme.colorScheme.primary,
                  onChanged: (val) {
                    if (val != null) onAnswerChanged(val);
                  },
                ),
                AppSpacing.w12,
                Expanded(
                  child: Text(
                    option.content,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  // ── Multiple Choice ────────────────────────────────────────────────

  Widget _buildMultipleChoice(BuildContext context) {
    final theme = Theme.of(context);
    final selectedIds = List<int>.from(currentAnswer as Iterable<dynamic>? ?? <int>[]);

    return ListView.separated(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: question.answers.length,
      separatorBuilder: (_, _) => AppSpacing.h12,
      itemBuilder: (context, index) {
        final option = question.answers[index];
        final isSelected = selectedIds.contains(option.id);

        return InkWell(
          onTap: () {
            final updated = List<int>.from(selectedIds);
            if (isSelected) {
              updated.remove(option.id);
            } else {
              updated.add(option.id);
            }
            onAnswerChanged(updated);
          },
          borderRadius: BorderRadius.circular(14),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            decoration: BoxDecoration(
              color: isSelected ? theme.colorScheme.primaryContainer.withValues(alpha: 0.4) : theme.colorScheme.surface,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: isSelected ? theme.colorScheme.primary : theme.colorScheme.outlineVariant,
                width: isSelected ? 2 : 1,
              ),
            ),
            child: Row(
              children: [
                Checkbox(
                  value: isSelected,
                  activeColor: theme.colorScheme.primary,
                  onChanged: (val) {
                    final updated = List<int>.from(selectedIds);
                    if (val == true) {
                      updated.add(option.id);
                    } else {
                      updated.remove(option.id);
                    }
                    onAnswerChanged(updated);
                  },
                ),
                AppSpacing.w12,
                Expanded(
                  child: Text(
                    option.content,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  // ── Text / Numerical Input ─────────────────────────────────────────

  Widget _buildTextOrNumericalInput(BuildContext context) {
    final theme = Theme.of(context);
    final initialText = currentAnswer?.toString() ?? '';
    final controller = TextEditingController(text: initialText);
    controller.selection = TextSelection.fromPosition(TextPosition(offset: controller.text.length));

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Nhập câu trả lời của bạn:',
          style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold),
        ),
        AppSpacing.h8,
        TextField(
          controller: controller,
          keyboardType: question.type == 'numerical'
              ? const TextInputType.numberWithOptions(decimal: true, signed: true)
              : TextInputType.text,
          onChanged: (val) {
            onAnswerChanged(val);
          },
          decoration: InputDecoration(
            hintText: question.type == 'numerical' ? 'Nhập số...' : 'Nhập câu trả lời...',
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
            ),
          ),
        ),
      ],
    );
  }

  // ── Essay Input ────────────────────────────────────────────────────

  Widget _buildEssayInput(BuildContext context) {
    final theme = Theme.of(context);
    final initialText = currentAnswer?.toString() ?? '';
    final controller = TextEditingController(text: initialText);
    controller.selection = TextSelection.fromPosition(TextPosition(offset: controller.text.length));

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Nhập bài tự luận:',
          style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold),
        ),
        AppSpacing.h8,
        TextField(
          controller: controller,
          maxLines: 8,
          minLines: 4,
          onChanged: (val) {
            onAnswerChanged(val);
          },
          decoration: InputDecoration(
            hintText: 'Viết nội dung tự luận vào đây...',
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
            ),
          ),
        ),
      ],
    );
  }

  // ── Ordering ───────────────────────────────────────────────────────

  Widget _buildOrdering(BuildContext context) {
    final theme = Theme.of(context);
    
    // In ordering type, currentAnswer is stored as a list of options: List<QuizAnswerOptionModel>
    final orderedList = List<QuizAnswerOptionModel>.from(
        currentAnswer as Iterable<dynamic>? ?? question.answers);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Nhấp các nút để sắp xếp theo thứ tự đúng:',
          style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold),
        ),
        AppSpacing.h12,
        ListView.separated(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: orderedList.length,
          separatorBuilder: (_, _) => AppSpacing.h8,
          itemBuilder: (context, index) {
            final option = orderedList[index];

            return Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              decoration: BoxDecoration(
                color: theme.colorScheme.surface,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: theme.colorScheme.outlineVariant),
              ),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 12,
                    backgroundColor: theme.colorScheme.primaryContainer,
                    child: Text(
                      '${index + 1}',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                        color: theme.colorScheme.onPrimaryContainer,
                      ),
                    ),
                  ),
                  AppSpacing.w12,
                  Expanded(
                    child: Text(
                      option.content,
                      style: theme.textTheme.bodyMedium,
                    ),
                  ),
                  Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      IconButton(
                        icon: const Icon(Icons.expand_less, size: 18),
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(),
                        onPressed: index == 0
                            ? null
                            : () {
                                final updated = List<QuizAnswerOptionModel>.from(orderedList);
                                final temp = updated[index];
                                updated[index] = updated[index - 1];
                                updated[index - 1] = temp;
                                onAnswerChanged(updated);
                              },
                      ),
                      IconButton(
                        icon: const Icon(Icons.expand_more, size: 18),
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(),
                        onPressed: index == orderedList.length - 1
                            ? null
                            : () {
                                final updated = List<QuizAnswerOptionModel>.from(orderedList);
                                final temp = updated[index];
                                updated[index] = updated[index + 1];
                                updated[index + 1] = temp;
                                onAnswerChanged(updated);
                              },
                      ),
                    ],
                  ),
                ],
              ),
            );
          },
        ),
      ],
    );
  }

  // ── Matching ───────────────────────────────────────────────────────

  Widget _buildMatching(BuildContext context) {
    final theme = Theme.of(context);
    
    // Matching is submitted as Map<String, String>: { left_id: right_sub_content }
    final matchedMap = Map<String, String>.from(
        currentAnswer as Map<dynamic, dynamic>? ?? <String, String>{});

    // We extract unique sub_content options (the right column) to display as dropdown items
    final rightSides = question.answers
        .map((a) => a.subContent)
        .where((s) => s != null && s.isNotEmpty)
        .cast<String>()
        .toSet()
        .toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Chọn cặp ghép nối tương ứng:',
          style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold),
        ),
        AppSpacing.h12,
        ListView.separated(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: question.answers.length,
          separatorBuilder: (_, _) => AppSpacing.h12,
          itemBuilder: (context, index) {
            final option = question.answers[index];
            final optionIdStr = option.id.toString();
            final currentSelectedRight = matchedMap[optionIdStr];

            return Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: theme.colorScheme.surface,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: theme.colorScheme.outlineVariant),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Vế trái: ${option.content}',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  AppSpacing.h8,
                  DropdownButtonFormField<String>(
                    initialValue: currentSelectedRight,
                    hint: const Text('Chọn vế ghép nối tương ứng...'),
                    decoration: InputDecoration(
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                    ),
                    items: rightSides.map((val) {
                      return DropdownMenuItem<String>(
                        value: val,
                        child: Text(val),
                      );
                    }).toList(),
                    onChanged: (val) {
                      final updated = Map<String, String>.from(matchedMap);
                      if (val != null) {
                        updated[optionIdStr] = val;
                      } else {
                        updated.remove(optionIdStr);
                      }
                      onAnswerChanged(updated);
                    },
                  ),
                ],
              ),
            );
          },
        ),
      ],
    );
  }
}
