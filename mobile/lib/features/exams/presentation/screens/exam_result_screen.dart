import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/theme/app_colors.dart';
import '../../data/models/exam_list_model.dart';
import '../../providers/exam_providers.dart';

class ExamResultScreen extends ConsumerWidget {
  const ExamResultScreen({super.key, required this.attemptId});
  final int attemptId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final resultAsync = ref.watch(examAttemptResultProvider(attemptId));

    return Scaffold(
      appBar: AppBar(
        title: const Text('Kết quả chi tiết',
            style: TextStyle(fontWeight: FontWeight.w700)),
        centerTitle: false,
        surfaceTintColor: Colors.transparent,
      ),
      body: resultAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.red),
              const SizedBox(height: 8),
              Text(e.toString(), textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton(
                onPressed: () =>
                    ref.invalidate(examAttemptResultProvider(attemptId)),
                child: const Text('Thử lại'),
              ),
            ],
          ),
        ),
        data: (result) => _ResultBody(result: result),
      ),
    );
  }
}

class _ResultBody extends StatelessWidget {
  const _ResultBody({required this.result});
  final ExamResultDetailModel result;

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      slivers: [
        SliverToBoxAdapter(child: _ScoreSummaryCard(result: result)),
        SliverToBoxAdapter(child: _StatsRow(result: result)),
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
          sliver: SliverToBoxAdapter(
            child: Text(
              'Chi tiết từng câu hỏi',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
            ),
          ),
        ),
        SliverList.separated(
          itemCount: result.questions.length,
          separatorBuilder: (_, __) => const SizedBox(height: 0),
          itemBuilder: (context, i) =>
              _QuestionResultCard(q: result.questions[i], index: i),
        ),
        const SliverPadding(padding: EdgeInsets.only(bottom: 32)),
      ],
    );
  }
}

class _ScoreSummaryCard extends StatelessWidget {
  const _ScoreSummaryCard({required this.result});
  final ExamResultDetailModel result;

  @override
  Widget build(BuildContext context) {
    final color = result.passed ? AppColors.success : AppColors.error;
    return Container(
      margin: const EdgeInsets.all(16),
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [color.withOpacity(0.15), color.withOpacity(0.05)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      child: Column(
        children: [
          Icon(
            result.passed ? Icons.emoji_events : Icons.sentiment_dissatisfied,
            size: 48,
            color: color,
          ),
          const SizedBox(height: 12),
          Text(
            result.passed ? 'Chúc mừng! Bạn đã đạt' : 'Chưa đạt',
            style: TextStyle(
                color: color, fontWeight: FontWeight.w600, fontSize: 15),
          ),
          const SizedBox(height: 8),
          Text(
            '${result.score.toStringAsFixed(1)}',
            style: TextStyle(
                color: color, fontWeight: FontWeight.w900, fontSize: 52),
          ),
          Text('điểm',
              style: TextStyle(color: color, fontSize: 14)),
          const SizedBox(height: 8),
          Text(
            '${result.correctCount} / ${result.totalQuestions} câu đúng',
            style: TextStyle(
                color: color.withOpacity(0.8),
                fontWeight: FontWeight.w500),
          ),
        ],
      ),
    );
  }
}

class _StatsRow extends StatelessWidget {
  const _StatsRow({required this.result});
  final ExamResultDetailModel result;

  String _fmtTime(int seconds) {
    final m = seconds ~/ 60;
    final s = seconds % 60;
    return '${m}p ${s}s';
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Row(
        children: [
          _StatItem(
              label: 'Đúng',
              value: '${result.correctCount}',
              color: AppColors.success),
          _StatItem(
              label: 'Sai',
              value: '${result.wrongCount}',
              color: AppColors.error),
          _StatItem(
              label: 'Bỏ qua',
              value: '${result.skippedCount}',
              color: AppColors.warning),
          _StatItem(
              label: 'Thời gian',
              value: _fmtTime(result.timeSpent),
              color: AppColors.primary400),
        ],
      ),
    );
  }
}

class _StatItem extends StatelessWidget {
  const _StatItem(
      {required this.label, required this.value, required this.color});
  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        margin: const EdgeInsets.only(right: 8),
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: color.withOpacity(0.1),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          children: [
            Text(value,
                style: TextStyle(
                    color: color,
                    fontWeight: FontWeight.w800,
                    fontSize: 18)),
            const SizedBox(height: 2),
            Text(label,
                style: TextStyle(
                    color: color.withOpacity(0.8),
                    fontSize: 11,
                    fontWeight: FontWeight.w500)),
          ],
        ),
      ),
    );
  }
}

class _QuestionResultCard extends StatefulWidget {
  const _QuestionResultCard({required this.q, required this.index});
  final QuestionResultModel q;
  final int index;

  @override
  State<_QuestionResultCard> createState() => _QuestionResultCardState();
}

class _QuestionResultCardState extends State<_QuestionResultCard> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final color =
        widget.q.isSkipped
            ? AppColors.warning
            : widget.q.isCorrect
                ? AppColors.success
                : AppColors.error;
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
              ? color.withOpacity(0.4)
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
                      color: color.withOpacity(0.12),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      '${widget.index + 1}',
                      style: TextStyle(
                          color: color,
                          fontWeight: FontWeight.w700,
                          fontSize: 13),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      widget.q.content,
                      style: theme.textTheme.bodyMedium
                          ?.copyWith(fontWeight: FontWeight.w500),
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
            Divider(
                height: 1, color: theme.colorScheme.outlineVariant),
            Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (widget.q.answers.isNotEmpty)
                    ...widget.q.answers.map((a) => _AnswerRow(answer: a)),
                  if (widget.q.answers.isEmpty &&
                      widget.q.userAnswer != null) ...[
                    _LabeledRow(
                      label: 'Câu trả lời của bạn:',
                      value: widget.q.userAnswer.toString(),
                      color: widget.q.isCorrect
                          ? AppColors.success
                          : AppColors.error,
                    ),
                    if (widget.q.correctAnswer != null)
                      _LabeledRow(
                        label: 'Đáp án đúng:',
                        value: widget.q.correctAnswer.toString(),
                        color: AppColors.success,
                      ),
                  ],
                  if (widget.q.explanation != null) ...[
                    const SizedBox(height: 10),
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: AppColors.primary400.withOpacity(0.07),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Icon(Icons.lightbulb_outline,
                              size: 16, color: AppColors.primary400),
                          const SizedBox(width: 6),
                          Expanded(
                            child: Text(
                              widget.q.explanation!,
                              style: TextStyle(
                                  fontSize: 12,
                                  color: AppColors.primary400
                                      .withOpacity(0.9)),
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
                            fontWeight: FontWeight.w600),
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

class _AnswerRow extends StatelessWidget {
  const _AnswerRow({required this.answer});
  final AnswerOptionResult answer;

  @override
  Widget build(BuildContext context) {
    Color? bg;
    Color? border;
    IconData? icon;

    if (answer.isCorrect && answer.wasSelected) {
      bg = AppColors.success.withOpacity(0.1);
      border = AppColors.success;
      icon = Icons.check_circle;
    } else if (!answer.isCorrect && answer.wasSelected) {
      bg = AppColors.error.withOpacity(0.1);
      border = AppColors.error;
      icon = Icons.cancel;
    } else if (answer.isCorrect && !answer.wasSelected) {
      bg = AppColors.success.withOpacity(0.05);
      border = AppColors.success.withOpacity(0.4);
      icon = Icons.check_circle_outline;
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 6),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: bg ?? Colors.transparent,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: border ??
              Theme.of(context).colorScheme.outlineVariant,
        ),
      ),
      child: Row(
        children: [
          if (icon != null)
            Icon(
              icon,
              size: 16,
              color: answer.isCorrect ? AppColors.success : AppColors.error,
            )
          else
            const SizedBox(width: 16),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              answer.content,
              style: const TextStyle(fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }
}

class _LabeledRow extends StatelessWidget {
  const _LabeledRow(
      {required this.label, required this.value, required this.color});
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
          Text(label,
              style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: Colors.grey)),
          const SizedBox(width: 6),
          Expanded(
            child: Text(value,
                style: TextStyle(
                    fontSize: 13,
                    color: color,
                    fontWeight: FontWeight.w500)),
          ),
        ],
      ),
    );
  }
}
