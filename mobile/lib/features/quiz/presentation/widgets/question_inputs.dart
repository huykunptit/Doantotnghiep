import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:flutter/material.dart';

import '../../data/models/quiz_model.dart';

/// Selectable option row shared by single/multiple choice.
class _OptionBox extends StatelessWidget {
  const _OptionBox({
    required this.selected,
    required this.onTap,
    required this.child,
  });

  final bool selected;
  final VoidCallback onTap;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: selected ? cs.primaryContainer : cs.surfaceContainerLowest,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: selected ? cs.primary : cs.outlineVariant,
            width: selected ? 2 : 1,
          ),
        ),
        child: child,
      ),
    );
  }
}

/// Section prompt above an input ("Nhập câu trả lời của bạn:").
class _Prompt extends StatelessWidget {
  const _Prompt(this.text);

  final String text;

  @override
  Widget build(BuildContext context) =>
      Text(text, style: context.tt.titleSmall);
}

/// Radio-style options (single choice and true/false).
class SingleChoiceInput extends StatelessWidget {
  const SingleChoiceInput({
    super.key,
    required this.options,
    required this.selectedId,
    required this.onChanged,
  });

  final List<QuizAnswerOptionModel> options;
  final int? selectedId;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    return ListView.separated(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: options.length,
      separatorBuilder: (_, _) => AppSpacing.h12,
      itemBuilder: (_, i) {
        final option = options[i];
        final selected = selectedId == option.id;
        return _OptionBox(
          selected: selected,
          onTap: () => onChanged(option.id),
          child: Row(
            children: [
              AnimatedContainer(
                duration: const Duration(milliseconds: 150),
                width: 22,
                height: 22,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: selected ? cs.primary : cs.outline,
                    width: selected ? 6 : 2,
                  ),
                  color: selected ? cs.primary : Colors.transparent,
                ),
              ),
              AppSpacing.w12,
              Expanded(
                child: Text(
                  option.content,
                  style: context.tt.bodyMedium?.copyWith(
                    fontWeight: selected ? FontWeight.w700 : FontWeight.w400,
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

/// Checkbox options; emits the full updated id list.
class MultipleChoiceInput extends StatelessWidget {
  const MultipleChoiceInput({
    super.key,
    required this.options,
    required this.selectedIds,
    required this.onChanged,
  });

  final List<QuizAnswerOptionModel> options;
  final List<int> selectedIds;
  final ValueChanged<List<int>> onChanged;

  @override
  Widget build(BuildContext context) {
    void toggle(int id, bool on) {
      final updated = List<int>.from(selectedIds);
      if (on) {
        updated.add(id);
      } else {
        updated.remove(id);
      }
      onChanged(updated);
    }

    return ListView.separated(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: options.length,
      separatorBuilder: (_, _) => AppSpacing.h12,
      itemBuilder: (_, i) {
        final option = options[i];
        final selected = selectedIds.contains(option.id);
        return _OptionBox(
          selected: selected,
          onTap: () => toggle(option.id, !selected),
          child: Row(
            children: [
              Checkbox(
                value: selected,
                onChanged: (v) => toggle(option.id, v == true),
              ),
              AppSpacing.w12,
              Expanded(
                child: Text(
                  option.content,
                  style: context.tt.bodyMedium?.copyWith(
                    fontWeight: selected ? FontWeight.w700 : FontWeight.w400,
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

/// Free-text / numeric / essay answer. Owns its controller so cursor, IME
/// composing state and focus survive rebuilds; create it with a key tied to
/// the question id so it resets when the question changes.
class TextAnswerInput extends StatefulWidget {
  const TextAnswerInput({
    super.key,
    required this.prompt,
    required this.hint,
    required this.initialText,
    required this.onChanged,
    this.keyboardType = TextInputType.text,
    this.minLines,
    this.maxLines = 1,
  });

  final String prompt;
  final String hint;
  final String initialText;
  final ValueChanged<String> onChanged;
  final TextInputType keyboardType;
  final int? minLines;
  final int maxLines;

  @override
  State<TextAnswerInput> createState() => _TextAnswerInputState();
}

class _TextAnswerInputState extends State<TextAnswerInput> {
  late final TextEditingController _controller = TextEditingController(
    text: widget.initialText,
  );

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _Prompt(widget.prompt),
        AppSpacing.h8,
        TextField(
          controller: _controller,
          keyboardType: widget.keyboardType,
          minLines: widget.minLines,
          maxLines: widget.maxLines,
          onChanged: widget.onChanged,
          decoration: InputDecoration(
            labelText: widget.hint,
            alignLabelWithHint: widget.maxLines > 1,
          ),
        ),
      ],
    );
  }
}

/// Reorder options with up/down buttons; emits the full reordered list.
class OrderingInput extends StatelessWidget {
  const OrderingInput({
    super.key,
    required this.options,
    required this.onChanged,
  });

  final List<QuizAnswerOptionModel> options;
  final ValueChanged<List<QuizAnswerOptionModel>> onChanged;

  void _swap(int a, int b) {
    final updated = List<QuizAnswerOptionModel>.from(options);
    final tmp = updated[a];
    updated[a] = updated[b];
    updated[b] = tmp;
    onChanged(updated);
  }

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _Prompt('Nhấp các nút để sắp xếp theo thứ tự đúng:'),
        AppSpacing.h12,
        ListView.separated(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: options.length,
          separatorBuilder: (_, _) => AppSpacing.h8,
          itemBuilder: (_, i) {
            return Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
              decoration: BoxDecoration(
                color: cs.surfaceContainerLowest,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: cs.outlineVariant),
              ),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 12,
                    backgroundColor: cs.primaryContainer,
                    child: Text(
                      '${i + 1}',
                      style: context.tt.labelMedium?.copyWith(
                        color: cs.onPrimaryContainer,
                      ),
                    ),
                  ),
                  AppSpacing.w12,
                  Expanded(
                    child: Text(
                      options[i].content,
                      style: context.tt.bodyMedium,
                    ),
                  ),
                  IconButton(
                    tooltip: 'Đưa lên',
                    icon: const Icon(Icons.expand_less),
                    onPressed: i == 0 ? null : () => _swap(i, i - 1),
                  ),
                  IconButton(
                    tooltip: 'Đưa xuống',
                    icon: const Icon(Icons.expand_more),
                    onPressed: i == options.length - 1
                        ? null
                        : () => _swap(i, i + 1),
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

/// Match each left item to a right-hand value via dropdowns.
/// Answer shape: `{ leftId: rightSubContent }`.
class MatchingInput extends StatelessWidget {
  const MatchingInput({
    super.key,
    required this.options,
    required this.matched,
    required this.onChanged,
  });

  final List<QuizAnswerOptionModel> options;
  final Map<String, String> matched;
  final ValueChanged<Map<String, String>> onChanged;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    final rightSides = options
        .map((a) => a.subContent)
        .where((s) => s != null && s.isNotEmpty)
        .cast<String>()
        .toSet()
        .toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _Prompt('Chọn cặp ghép nối tương ứng:'),
        AppSpacing.h12,
        ListView.separated(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: options.length,
          separatorBuilder: (_, _) => AppSpacing.h12,
          itemBuilder: (_, i) {
            final option = options[i];
            final key = option.id.toString();
            return Container(
              padding: AppSpacing.p12,
              decoration: BoxDecoration(
                color: cs.surfaceContainerLowest,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: cs.outlineVariant),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Vế trái: ${option.content}',
                    style: context.tt.titleSmall,
                  ),
                  AppSpacing.h8,
                  DropdownButtonFormField<String>(
                    isExpanded: true,
                    initialValue: matched[key],
                    hint: const Text(
                      'Chọn vế ghép nối tương ứng...',
                      overflow: TextOverflow.ellipsis,
                    ),
                    items: [
                      for (final v in rightSides)
                        DropdownMenuItem(
                          value: v,
                          child: Text(v, overflow: TextOverflow.ellipsis),
                        ),
                    ],
                    onChanged: (v) {
                      final updated = Map<String, String>.from(matched);
                      if (v != null) {
                        updated[key] = v;
                      } else {
                        updated.remove(key);
                      }
                      onChanged(updated);
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
