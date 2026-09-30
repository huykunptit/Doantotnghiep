import 'package:flutter/material.dart';
import 'package:eript_lms/features/exams/data/models/exam_list_model.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/features/exams/presentation/widgets/question_result_card_state.dart';

class ResultBody extends StatelessWidget {
  const ResultBody({super.key, required this.result});
  final ExamResultDetailModel result;

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      slivers: [
        SliverToBoxAdapter(child: ScoreSummaryCard(result: result)),
        SliverToBoxAdapter(child: StatsRow(result: result)),
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
          sliver: SliverToBoxAdapter(
            child: Text(
              'Chi tiết từng câu hỏi',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
            ),
          ),
        ),
        SliverList.separated(
          itemCount: result.questions.length,
          separatorBuilder: (_, _) => const SizedBox(height: 0),
          itemBuilder: (context, i) =>
              QuestionResultCard(q: result.questions[i], index: i),
        ),
        const SliverPadding(padding: EdgeInsets.only(bottom: 32)),
      ],
    );
  }
}

class ScoreSummaryCard extends StatelessWidget {
  const ScoreSummaryCard({super.key, required this.result});
  final ExamResultDetailModel result;

  @override
  Widget build(BuildContext context) {
    final color = result.passed ? context.sem.success : context.cs.error;
    return Container(
      margin: const EdgeInsets.all(16),
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            color.withValues(alpha: 0.15),
            color.withValues(alpha: 0.05),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withValues(alpha: 0.3)),
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
              color: color,
              fontWeight: FontWeight.w600,
              fontSize: 15,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            result.score.toStringAsFixed(1),
            style: TextStyle(
              color: color,
              fontWeight: FontWeight.w900,
              fontSize: 52,
            ),
          ),
          Text('điểm', style: TextStyle(color: color, fontSize: 14)),
          const SizedBox(height: 8),
          Text(
            '${result.correctCount} / ${result.totalQuestions} câu đúng',
            style: TextStyle(
              color: color.withValues(alpha: 0.8),
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }
}

class StatsRow extends StatelessWidget {
  const StatsRow({super.key, required this.result});
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
          StatItem(
            label: 'Đúng',
            value: '${result.correctCount}',
            color: context.sem.success,
          ),
          StatItem(
            label: 'Sai',
            value: '${result.wrongCount}',
            color: context.cs.error,
          ),
          StatItem(
            label: 'Bỏ qua',
            value: '${result.skippedCount}',
            color: context.sem.warning,
          ),
          StatItem(
            label: 'Thời gian',
            value: _fmtTime(result.timeSpent),
            color: context.cs.primary,
          ),
        ],
      ),
    );
  }
}

class StatItem extends StatelessWidget {
  const StatItem({
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
    return Expanded(
      child: Container(
        margin: const EdgeInsets.only(right: 8),
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          children: [
            Text(
              value,
              style: TextStyle(
                color: color,
                fontWeight: FontWeight.w800,
                fontSize: 18,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: TextStyle(
                color: color.withValues(alpha: 0.8),
                fontSize: 12,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class QuestionResultCard extends StatefulWidget {
  const QuestionResultCard({super.key, required this.q, required this.index});
  final QuestionResultModel q;
  final int index;

  @override
  State<QuestionResultCard> createState() => QuestionResultCardState();
}
