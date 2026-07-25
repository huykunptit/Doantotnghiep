import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../dashboard/providers/dashboard_provider.dart';
import '../../data/models/ai_models.dart';
import '../../data/repositories/ai_repository.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';

final extensionRecommendationsProvider =
    FutureProvider.autoDispose<List<CourseRecommendationItem>>((ref) {
  return ref.read(aiRepositoryProvider).getRecommendations();
});

class StudyAdvisorScreen extends ConsumerStatefulWidget {
  const StudyAdvisorScreen({super.key});
  static const routeName = '/study-advisor';

  @override
  ConsumerState<StudyAdvisorScreen> createState() => _StudyAdvisorScreenState();
}

class _StudyAdvisorScreenState extends ConsumerState<StudyAdvisorScreen> {
  @override
  Widget build(BuildContext context) {
    final evalAsync = ref.watch(studentCurriculumEvaluationProvider);
    final recAsync = ref.watch(extensionRecommendationsProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Cố vấn học tập AI'),
        actions: [
          IconButton(
            icon: const Icon(Icons.work_outline),
            tooltip: 'AI Career',
            onPressed: () => context.push('/career'),
          ),
        ],
      ),
      body: evalAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('Lỗi: $e', textAlign: TextAlign.center),
                AppSpacing.h12,
                FilledButton(
                  onPressed: () => ref.invalidate(studentCurriculumEvaluationProvider),
                  child: const Text('Thử lại'),
                ),
              ],
            ),
          ),
        ),
        data: (evaluation) {
          final summary = evaluation['summary'] as Map<String, dynamic>? ?? {};
          final weaknesses = (evaluation['weaknesses'] as List<dynamic>? ?? [])
              .whereType<Map>()
              .map((e) => Map<String, dynamic>.from(e))
              .toList();
          final hasCurriculum = evaluation['has_curriculum'] == true;
          final completion =
              ((summary['completion_ratio'] as num?)?.toDouble() ?? 0) * 100;
          final narrative = evaluation['narrative']?.toString() ??
              evaluation['message']?.toString() ??
              '';

          return RefreshIndicator(
            onRefresh: () async {
              ref.invalidate(studentCurriculumEvaluationProvider);
              ref.invalidate(extensionRecommendationsProvider);
            },
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
              children: [
                _Card(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Gợi ý lộ trình', style: TextStyle(fontWeight: FontWeight.w800)),
                      AppSpacing.h8,
                      Text(
                        narrative.isNotEmpty
                            ? narrative
                            : 'Hãy hoàn thành các môn CTĐT kỳ hiện tại và củng cố môn điểm thấp bằng khóa bổ trợ.',
                      ),
                    ],
                  ),
                ),
                AppSpacing.h12,
                if (hasCurriculum)
                  _Card(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Tiến trình CTĐT', style: TextStyle(fontWeight: FontWeight.w800)),
                        AppSpacing.h12,
                        Row(
                          children: [
                            _MiniStat(label: 'Hoàn thành', value: '${completion.round()}%'),
                            AppSpacing.w8,
                            _MiniStat(label: 'GPA', value: '${summary['overall_gpa'] ?? '—'}'),
                            AppSpacing.w8,
                            _MiniStat(label: 'Mức', value: '${summary['level'] ?? '—'}'),
                          ],
                        ),
                        if (weaknesses.isNotEmpty) ...[
                          AppSpacing.h12,
                          const Text('Cần củng cố', style: TextStyle(fontWeight: FontWeight.w700)),
                          AppSpacing.h4,
                          ...weaknesses.map(
                            (w) => Padding(
                              padding: const EdgeInsets.only(bottom: 4),
                              child: Text('• ${w['title']} (${w['final_score']})'),
                            ),
                          ),
                        ],
                      ],
                    ),
                  )
                else
                  _Card(
                    child: Text(
                      evaluation['message']?.toString() ??
                          'Lớp hành chính chưa được gán CTĐT.',
                    ),
                  ),
                AppSpacing.h12,
                _Card(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Khóa học bổ trợ gợi ý', style: TextStyle(fontWeight: FontWeight.w800)),
                      AppSpacing.h4,
                      Text(
                        'Có thể mua và học trên Marketplace',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                      ),
                      AppSpacing.h8,
                      recAsync.when(
                        loading: () => const LinearProgressIndicator(),
                        error: (_, _) => const Text('Không tải được gợi ý khóa.'),
                        data: (courses) {
                          if (courses.isEmpty) {
                            return const Text('Chưa có gợi ý khóa học.');
                          }
                          return Column(
                            children: courses.map((c) {
                              final skills = c.matchedSkills.isNotEmpty
                                  ? c.matchedSkills.take(3).join(', ')
                                  : 'Khóa bổ trợ';
                              return ListTile(
                                contentPadding: EdgeInsets.zero,
                                title: Text(
                                  c.course.title,
                                  style: const TextStyle(fontWeight: FontWeight.w600),
                                ),
                                subtitle: Text(skills),
                                trailing: const Icon(Icons.chevron_right),
                                onTap: () => context.push('/courses/${c.course.id}'),
                              );
                            }).toList(),
                          );
                        },
                      ),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _Card extends StatelessWidget {
  const _Card({required this.child});
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: isDark ? AppColors.darkBorder : AppColors.neutral200),
        color: isDark ? AppColors.darkSurface : Colors.white,
      ),
      child: child,
    );
  }
}

class _MiniStat extends StatelessWidget {
  const _MiniStat({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: AppColors.neutral200),
        ),
        child: Column(
          children: [
            Text(value, style: const TextStyle(fontWeight: FontWeight.w900)),
            Text(label, style: const TextStyle(fontSize: 11, color: AppColors.neutral600)),
          ],
        ),
      ),
    );
  }
}
