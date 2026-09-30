import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/utils/format_vnd.dart';
import 'package:eript_lms/core/widgets/app_card.dart';
import 'package:eript_lms/core/widgets/status_badge.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../data/models/career_model.dart';

/// Result of a CV evaluation: score, summary, checks, fixes and warnings.
class CareerEvaluationCard extends StatelessWidget {
  const CareerEvaluationCard({super.key, required this.evaluation});

  final CareerEvaluationModel evaluation;

  @override
  Widget build(BuildContext context) {
    final e = evaluation;
    final tt = context.tt;
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(
                '${e.score}%',
                style: tt.headlineLarge?.copyWith(color: context.cs.primary),
              ),
              AppSpacing.w12,
              Expanded(
                child: Text(
                  e.summary,
                  style: tt.bodyMedium?.copyWith(height: 1.4),
                ),
              ),
            ],
          ),
          if (e.explanationUnavailable) ...[
            AppSpacing.h12,
            Container(
              width: double.infinity,
              padding: AppSpacing.p12,
              decoration: BoxDecoration(
                color: context.sem.warningBg,
                borderRadius: AppRadius.rLg,
              ),
              child: Text(
                'Nhà cung cấp AI tạm thời lỗi hoặc vượt hạn mức. Danh sách khóa gợi ý bên dưới vẫn theo bộ luật — chưa có phần diễn giải từ mô hình.',
                style: tt.bodySmall?.copyWith(color: context.sem.warningFg),
              ),
            ),
          ],
          if (e.overview != null &&
              e.overview!.isNotEmpty &&
              !e.explanationUnavailable) ...[
            AppSpacing.h12,
            Text(e.overview!, style: tt.bodyMedium?.copyWith(height: 1.45)),
          ],
          if (e.salaryNote != null) ...[
            AppSpacing.h8,
            Text(e.salaryNote!, style: tt.bodySmall),
          ],
          if (e.checks.isNotEmpty) ...[
            AppSpacing.h12,
            for (final c in e.checks)
              ListTile(
                dense: true,
                contentPadding: EdgeInsets.zero,
                leading: Icon(
                  c.ok ? Icons.check_circle : Icons.cancel_outlined,
                  color: c.ok ? context.sem.success : context.sem.warning,
                ),
                title: Text(c.label),
              ),
          ],
          if (e.fixes.isNotEmpty) ...[
            AppSpacing.h8,
            Text('Cần cải thiện', style: tt.titleSmall),
            for (final f in e.fixes) Text('• $f'),
          ],
          if (e.warnings.isNotEmpty) ...[
            AppSpacing.h8,
            Text('Cảnh báo', style: tt.titleSmall),
            for (final w in e.warnings) Text('• $w'),
          ],
        ],
      ),
    );
  }
}

/// Title + optional dropdown to switch between past analyses.
class RecommendationHistoryHeader extends StatelessWidget {
  const RecommendationHistoryHeader({
    super.key,
    required this.recommendations,
    required this.selected,
    required this.onChanged,
  });

  final List<CareerRecommendationModel> recommendations;
  final CareerRecommendationModel? selected;
  final ValueChanged<CareerRecommendationModel> onChanged;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text('Kết quả phân tích', style: context.tt.titleMedium),
        if (recommendations.length > 1)
          DropdownButton<CareerRecommendationModel>(
            value: selected,
            underline: const SizedBox.shrink(),
            onChanged: (r) {
              if (r != null) onChanged(r);
            },
            items: [
              for (var i = 0; i < recommendations.length; i++)
                DropdownMenuItem(
                  value: recommendations[i],
                  child: Text('Lần ${i + 1}'),
                ),
            ],
          ),
      ],
    );
  }
}

/// Match score, skill gaps and suggested courses of one recommendation.
class RecommendationDetail extends StatelessWidget {
  const RecommendationDetail({super.key, required this.recommendation});

  final CareerRecommendationModel recommendation;

  @override
  Widget build(BuildContext context) {
    final rec = recommendation;
    final score = rec.matchScore;
    final tone = score >= 80
        ? StatusTone.success
        : score >= 50
        ? StatusTone.warning
        : StatusTone.danger;
    final c = toneColors(context, tone);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: AppSpacing.p16,
          decoration: BoxDecoration(color: c.bg, borderRadius: AppRadius.rXl),
          child: Row(
            children: [
              Text(
                '$score%',
                style: context.tt.headlineMedium?.copyWith(color: c.fg),
              ),
              AppSpacing.w16,
              Expanded(
                child: Text(
                  rec.expertAnalysis.overview.isNotEmpty
                      ? rec.expertAnalysis.overview
                      : rec.aiSummary,
                  style: context.tt.bodyMedium?.copyWith(color: c.fg),
                ),
              ),
            ],
          ),
        ),
        if (rec.skillGaps.isNotEmpty) ...[
          AppSpacing.h12,
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final gap in rec.skillGaps)
                StatusBadge(label: gap, tone: StatusTone.danger),
            ],
          ),
        ],
        if (rec.suggestedCoursesData.isNotEmpty) ...[
          AppSpacing.h16,
          Text('Khóa học đề xuất', style: context.tt.titleMedium),
          AppSpacing.h8,
          for (final c in rec.suggestedCoursesData) CareerCourseTile(course: c),
        ],
      ],
    );
  }
}

/// Tappable suggested course row.
class CareerCourseTile extends StatelessWidget {
  const CareerCourseTile({super.key, required this.course});

  final CareerRecommendationCourseModel course;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.space3),
      child: AppCard(
        onTap: () => context.push('/courses/${course.id}'),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        child: Row(
          children: [
            const Icon(Icons.school_outlined),
            AppSpacing.w12,
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    course.title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: context.tt.titleSmall,
                  ),
                  Text(
                    course.recommendationReason ?? formatVnd(course.price),
                    style: context.tt.bodySmall?.copyWith(
                      color: context.cs.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right),
          ],
        ),
      ),
    );
  }
}
