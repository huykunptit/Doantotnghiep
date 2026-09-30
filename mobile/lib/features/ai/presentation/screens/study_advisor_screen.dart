import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../dashboard/providers/dashboard_provider.dart';
import '../../data/models/ai_models.dart';
import '../../providers/ai_providers.dart';
import '../../../../core/theme/app_spacing.dart';
import 'package:eript_lms/features/ai/presentation/widgets/alert_tone.dart';
import 'package:eript_lms/core/widgets/error_state.dart';

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
    final recAsync = ref.watch(recommendationsProvider);
    final adviceAsync = ref.watch(studyAdvisorAdviceProvider);
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
        error: (e, _) => ErrorStateWidget(
          error: e,
          onRetry: () => ref.invalidate(studentCurriculumEvaluationProvider),
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
          final narrative =
              evaluation['narrative']?.toString() ??
              evaluation['message']?.toString() ??
              '';
          final profileSparse = evaluation['profile_sparse'] == true;
          final suggested =
              (evaluation['suggested_courses'] as List<dynamic>? ?? [])
                  .whereType<Map>()
                  .map(
                    (e) => CourseRecommendationItem.fromJson(
                      Map<String, dynamic>.from(e),
                    ),
                  )
                  .where((c) => c.course.id > 0)
                  .toList();

          final lowCompletion = hasCurriculum && completion < 40;
          final midCompletion =
              hasCurriculum && completion >= 40 && completion < 60;
          final hasWeakScores = weaknesses.isNotEmpty;

          return RefreshIndicator(
            onRefresh: () async {
              ref.invalidate(studentCurriculumEvaluationProvider);
              ref.invalidate(recommendationsProvider);
              ref.invalidate(studyAdvisorAdviceProvider);
            },
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
              children: [
                if (profileSparse) ...[
                  AlertBanner(
                    tone: AlertTone.warn,
                    title: 'Hồ sơ chưa có điểm',
                    body:
                        'Hệ thống gợi ý theo khung chương trình đào tạo chuẩn của ngành, không suy diễn từ dữ liệu rỗng.',
                  ),
                  AppSpacing.h12,
                ],
                if (lowCompletion || midCompletion || hasWeakScores) ...[
                  StudyAdvisorCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Cảnh báo học tập',
                          style: TextStyle(fontWeight: FontWeight.w800),
                        ),
                        if (lowCompletion) ...[
                          AppSpacing.h8,
                          AlertBanner(
                            tone: AlertTone.danger,
                            title: 'Tỉ lệ hoàn thành CTĐT thấp',
                            body:
                                'Bạn mới hoàn thành ${completion.round()}% môn bắt buộc. Hãy ưu tiên các môn kỳ hiện tại để không bị chậm tiến độ.',
                          ),
                        ] else if (midCompletion) ...[
                          AppSpacing.h8,
                          AlertBanner(
                            tone: AlertTone.warn,
                            title: 'Tiến độ CTĐT cần theo dõi',
                            body:
                                'Hoàn thành ${completion.round()}% môn bắt buộc. Nên duy trì nhịp học đều và hoàn tất các môn còn dang dở.',
                          ),
                        ],
                        if (hasWeakScores) ...[
                          AppSpacing.h8,
                          AlertBanner(
                            tone: AlertTone.warn,
                            title: 'Có môn điểm thấp cần củng cố',
                            body:
                                'Một số môn dưới ngưỡng an toàn. Hãy ôn lại hoặc học khóa bổ trợ trước khi thi kỳ tới.',
                            items: weaknesses
                                .map(
                                  (w) => '${w['title']} (${w['final_score']})',
                                )
                                .toList(),
                          ),
                        ],
                      ],
                    ),
                  ),
                  AppSpacing.h12,
                ],
                StudyAdvisorCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Gợi ý lộ trình',
                        style: TextStyle(fontWeight: FontWeight.w800),
                      ),
                      AppSpacing.h8,
                      adviceAsync.when(
                        loading: () => const Padding(
                          padding: EdgeInsets.symmetric(vertical: 8),
                          child: LinearProgressIndicator(),
                        ),
                        error: (_, _) => AlertBanner(
                          tone: AlertTone.warn,
                          title: 'Chưa có phần diễn giải',
                          body:
                              'Trợ lý AI tạm thời lỗi hoặc vượt hạn mức. Danh sách gợi ý bên dưới vẫn theo bộ luật CTĐT.',
                        ),
                        data: (advice) {
                          if (advice.explanationUnavailable) {
                            return AlertBanner(
                              tone: AlertTone.warn,
                              title: 'Chưa có phần diễn giải',
                              body:
                                  'Trợ lý AI tạm thời lỗi hoặc vượt hạn mức. Danh sách gợi ý bên dưới vẫn theo bộ luật CTĐT.',
                            );
                          }
                          final text = advice.narrative.isNotEmpty
                              ? advice.narrative
                              : (narrative.isNotEmpty
                                    ? narrative
                                    : 'Hãy hoàn thành các môn CTĐT kỳ hiện tại và củng cố môn điểm thấp bằng khóa bổ trợ.');
                          return Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(text),
                              if (advice.studyTips.isNotEmpty) ...[
                                AppSpacing.h8,
                                ...advice.studyTips.map(
                                  (tip) => Padding(
                                    padding: const EdgeInsets.only(bottom: 4),
                                    child: Text('• $tip'),
                                  ),
                                ),
                              ],
                            ],
                          );
                        },
                      ),
                    ],
                  ),
                ),
                AppSpacing.h12,
                if (hasCurriculum)
                  StudyAdvisorCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Tiến trình CTĐT',
                          style: TextStyle(fontWeight: FontWeight.w800),
                        ),
                        AppSpacing.h12,
                        Row(
                          children: [
                            MiniStat(
                              label: 'Hoàn thành',
                              value: '${completion.round()}%',
                            ),
                            AppSpacing.w8,
                            MiniStat(
                              label: 'GPA',
                              value: '${summary['overall_gpa'] ?? '—'}',
                            ),
                            AppSpacing.w8,
                            MiniStat(
                              label: 'Mức',
                              value: '${summary['level'] ?? '—'}',
                            ),
                          ],
                        ),
                        if (weaknesses.isNotEmpty) ...[
                          AppSpacing.h12,
                          const Text(
                            'Cần củng cố',
                            style: TextStyle(fontWeight: FontWeight.w700),
                          ),
                          AppSpacing.h4,
                          ...weaknesses.map(
                            (w) => Padding(
                              padding: const EdgeInsets.only(bottom: 4),
                              child: Text(
                                '• ${w['title']} (${w['final_score']})',
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  )
                else
                  StudyAdvisorCard(
                    child: Text(
                      evaluation['message']?.toString() ??
                          'Lớp hành chính chưa được gán CTĐT.',
                    ),
                  ),
                AppSpacing.h12,
                StudyAdvisorCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Khóa học bổ trợ gợi ý',
                        style: TextStyle(fontWeight: FontWeight.w800),
                      ),
                      AppSpacing.h4,
                      Text(
                        profileSparse
                            ? 'Gợi ý theo thứ tự môn trong CTĐT chuẩn (kỳ hiện tại và các kỳ kế).'
                            : 'Điểm thấp khi < 6.5/10 hoặc thấp hơn GPA từ 1.0; kèm môn sắp học trong CTĐT.',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                      ),
                      AppSpacing.h8,
                      if (suggested.isNotEmpty)
                        Column(
                          children: suggested.map((c) {
                            final reason = c.reasons.isNotEmpty
                                ? c.reasons.first
                                : (c.matchedSkills.isNotEmpty
                                      ? c.matchedSkills.take(3).join(', ')
                                      : 'Môn theo khung CTĐT');
                            return ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text(
                                c.course.title,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              subtitle: Text(reason),
                              trailing: const Icon(Icons.chevron_right),
                              onTap: () =>
                                  context.push('/courses/${c.course.id}'),
                            );
                          }).toList(),
                        )
                      else
                        recAsync.when(
                          loading: () => const LinearProgressIndicator(),
                          error: (_, _) =>
                              const Text('Không tải được gợi ý khóa.'),
                          data: (bundle) {
                            final courses = bundle.items;
                            if (courses.isEmpty) {
                              return const Text('Chưa có gợi ý khóa học.');
                            }
                            return Column(
                              children: courses.map((c) {
                                final reason = c.reasons.isNotEmpty
                                    ? c.reasons.first
                                    : (c.matchedSkills.isNotEmpty
                                          ? c.matchedSkills.take(3).join(', ')
                                          : 'Khóa bổ trợ');
                                return ListTile(
                                  contentPadding: EdgeInsets.zero,
                                  title: Text(
                                    c.course.title,
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                  subtitle: Text(reason),
                                  trailing: const Icon(Icons.chevron_right),
                                  onTap: () =>
                                      context.push('/courses/${c.course.id}'),
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
