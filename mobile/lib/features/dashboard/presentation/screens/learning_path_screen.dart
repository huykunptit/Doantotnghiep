import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/dashboard_provider.dart';
import '../../../../core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/theme/app_brand.dart';
import 'package:eript_lms/features/dashboard/presentation/widgets/term_accordion.dart';
import 'package:eript_lms/core/widgets/error_state.dart';

class LearningPathScreen extends ConsumerWidget {
  const LearningPathScreen({super.key});
  static const routeName = '/learning-path';

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final learningPathAsync = ref.watch(studentLearningPathProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Chương trình đào tạo'),
        actions: [
          IconButton(
            tooltip: 'Làm mới',
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(studentLearningPathProvider),
          ),
        ],
      ),
      body: learningPathAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorStateWidget(
          error: e,
          onRetry: () => ref.invalidate(studentLearningPathProvider),
        ),
        data: (path) {
          if (!path.hasCurriculum) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(32),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 80,
                      height: 80,
                      decoration: BoxDecoration(
                        color: context.cs.primaryContainer,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        Icons.layers_outlined,
                        size: 40,
                        color: context.cs.primary,
                      ),
                    ),
                    AppSpacing.h20,
                    Text(
                      'Chưa gán chương trình đào tạo',
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    AppSpacing.h8,
                    Text(
                      'Tài khoản của bạn chưa được gán lộ trình học tập hoặc lớp học hành chính.',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                        height: 1.5,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),
            );
          }

          final creditsEarned = path.totalCreditsEarned;
          final creditsRequired = path.totalCreditsRequired;
          final pct = creditsRequired > 0
              ? (creditsEarned / creditsRequired)
              : 0.0;

          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            children: [
              // Overall Progress Card
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: AppBrand.heroColors,
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'CHƯƠNG TRÌNH HỌC TẬP',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: Colors.white70,
                        letterSpacing: 1.2,
                      ),
                    ),
                    AppSpacing.h8,
                    Text(
                      path.curriculumName ?? '—',
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        color: Colors.white,
                        letterSpacing: -0.3,
                      ),
                    ),
                    if (path.curriculumCode != null) ...[
                      AppSpacing.h4,
                      Text(
                        'Mã CTĐT: ${path.curriculumCode}',
                        style: const TextStyle(
                          fontSize: 12,
                          color: Colors.white70,
                        ),
                      ),
                    ],
                    AppSpacing.h16,
                    const Divider(color: Colors.white24, height: 1),
                    AppSpacing.h16,
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Tiến độ tích lũy tín chỉ',
                          style: TextStyle(
                            fontSize: 12,
                            color: Colors.white70,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                        Text(
                          '$creditsEarned / $creditsRequired Tín chỉ',
                          style: const TextStyle(
                            fontSize: 13,
                            color: Colors.white,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                    AppSpacing.h8,
                    ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        value: pct,
                        backgroundColor: Colors.white.withValues(alpha: 0.2),
                        valueColor: const AlwaysStoppedAnimation<Color>(
                          Colors.white,
                        ),
                        minHeight: 6,
                      ),
                    ),
                    AppSpacing.h8,
                    Text(
                      '${(pct * 100).toStringAsFixed(0)}% Hoàn tất chương trình',
                      style: const TextStyle(
                        fontSize: 12,
                        color: Colors.white,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),

              AppSpacing.h24,

              Text(
                'Lộ trình các học kỳ',
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.2,
                ),
              ),
              AppSpacing.h12,

              ...path.terms.map(
                (term) => TermAccordion(term: term, theme: theme),
              ),
            ],
          );
        },
      ),
    );
  }
}
