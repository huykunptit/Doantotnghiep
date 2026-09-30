import 'package:flutter/material.dart';
import 'package:eript_lms/features/exams/data/models/exam_list_model.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/features/exams/presentation/widgets/result_body.dart';

class QuestionResultCardState extends State<QuestionResultCard> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final color = widget.q.isSkipped
        ? context.sem.warning
        : widget.q.isCorrect
        ? context.sem.success
        : context.cs.error;
    final icon = widget.q.isSkipped
        ? Icons.remove_circle_outline
        : widget.q.isCorrect
        ? Icons.check_circle
        : Icons.cancel;

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: _expanded
              ? color.withValues(alpha: 0.4)
              : theme.colorScheme.outlineVariant,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          InkWell(
            borderRadius: BorderRadius.circular(14),
            onTap: () => setState(() => _expanded = !_expanded),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 28,
                    height: 28,
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      '${widget.index + 1}',
                      style: TextStyle(
                        color: color,
                        fontWeight: FontWeight.w700,
                        fontSize: 13,
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      widget.q.content,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontWeight: FontWeight.w500,
                      ),
                      maxLines: _expanded ? null : 2,
                      overflow: _expanded ? null : TextOverflow.ellipsis,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Column(
                    children: [
                      Icon(icon, color: color, size: 20),
                      const SizedBox(height: 4),
                      Icon(
                        _expanded
                            ? Icons.keyboard_arrow_up
                            : Icons.keyboard_arrow_down,
                        size: 18,
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          if (_expanded) ...[
            Divider(height: 1, color: theme.colorScheme.outlineVariant),
            Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (widget.q.answers.isNotEmpty)
                    ...widget.q.answers.map((a) => AnswerRow(answer: a)),
                  if (widget.q.answers.isEmpty &&
                      widget.q.userAnswer != null) ...[
                    LabeledRow(
                      label: 'Câu trả lời của bạn:',
                      value: widget.q.userAnswer.toString(),
                      color: widget.q.isCorrect
                          ? context.sem.success
                          : context.cs.error,
                    ),
                    if (widget.q.correctAnswer != null)
                      LabeledRow(
                        label: 'Đáp án đúng:',
                        value: widget.q.correctAnswer.toString(),
                        color: context.sem.success,
                      ),
                  ],
                  if (widget.q.explanation != null) ...[
                    const SizedBox(height: 10),
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: context.cs.primary.withValues(alpha: 0.07),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(
                            Icons.lightbulb_outline,
                            size: 16,
                            color: context.cs.primary,
                          ),
                          const SizedBox(width: 6),
                          Expanded(
                            child: Text(
                              widget.q.explanation!,
                              style: TextStyle(
                                fontSize: 12,
                                color: context.cs.primary.withValues(
                                  alpha: 0.9,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Text(
                        'Điểm: ${widget.q.earnedPoints.toStringAsFixed(1)} / ${widget.q.points.toStringAsFixed(1)}',
                        style: TextStyle(
                          fontSize: 12,
                          color: color,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class AnswerRow extends StatelessWidget {
  const AnswerRow({super.key, required this.answer});
  final AnswerOptionResult answer;

  @override
  Widget build(BuildContext context) {
    Color? bg;
    Color? border;
    IconData? icon;

    if (answer.isCorrect && answer.wasSelected) {
      bg = context.sem.success.withValues(alpha: 0.1);
      border = context.sem.success;
      icon = Icons.check_circle;
    } else if (!answer.isCorrect && answer.wasSelected) {
      bg = context.cs.error.withValues(alpha: 0.1);
      border = context.cs.error;
      icon = Icons.cancel;
    } else if (answer.isCorrect && !answer.wasSelected) {
      bg = context.sem.success.withValues(alpha: 0.05);
      border = context.sem.success.withValues(alpha: 0.4);
      icon = Icons.check_circle_outline;
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 6),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: bg ?? Colors.transparent,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: border ?? Theme.of(context).colorScheme.outlineVariant,
        ),
      ),
      child: Row(
        children: [
          if (icon != null)
            Icon(
              icon,
              size: 16,
              color: answer.isCorrect ? context.sem.success : context.cs.error,
            )
          else
            const SizedBox(width: 16),
          const SizedBox(width: 8),
          Expanded(
            child: Text(answer.content, style: const TextStyle(fontSize: 13)),
          ),
        ],
      ),
    );
  }
}

class LabeledRow extends StatelessWidget {
  const LabeledRow({
    super.key,
    required this.label,
    required this.value,
    required this.color,
  });
  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: context.cs.onSurfaceVariant,
            ),
          ),
          const SizedBox(width: 6),
          Expanded(
            child: Text(
              value,
              style: TextStyle(
                fontSize: 13,
                color: color,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
