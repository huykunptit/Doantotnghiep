import 'package:eript_lms/core/theme/app_brand.dart';
import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/widgets/skeleton.dart';
import 'package:flutter/material.dart';

import '../../../dashboard/data/models/dashboard_model.dart';

/// Brand hero: term name + three enrollment counters. White-on-teal in both
/// themes (fixed brand surface, see AppBrand).
class ProgressHeroCard extends StatelessWidget {
  const ProgressHeroCard({super.key, required this.data});

  final DashboardModel data;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: AppSpacing.p20,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: context.isDark
              ? AppBrand.heroDeepColors
              : AppBrand.heroColors,
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: AppRadius.r2Xl,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            data.currentTerm?.name ?? 'Học kỳ hiện tại',
            style: context.tt.labelMedium?.copyWith(
              color: AppBrand.onHeroMuted,
            ),
          ),
          AppSpacing.h4,
          Text(
            'Tiến độ học tập',
            style: context.tt.headlineMedium?.copyWith(color: AppBrand.onHero),
          ),
          AppSpacing.h16,
          Row(
            children: [
              _Counter(
                icon: Icons.menu_book_rounded,
                label: 'Đã đăng ký',
                value: data.totals.enrollments,
              ),
              AppSpacing.w8,
              _Counter(
                icon: Icons.pending_actions_rounded,
                label: 'Đang học',
                value: data.totals.inProgress,
              ),
              AppSpacing.w8,
              _Counter(
                icon: Icons.check_circle_rounded,
                label: 'Hoàn thành',
                value: data.totals.completed,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class ProgressHeroSkeleton extends StatelessWidget {
  const ProgressHeroSkeleton({super.key});

  @override
  Widget build(BuildContext context) =>
      const SkeletonBox(height: 148, radius: 24);
}

class _Counter extends StatelessWidget {
  const _Counter({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final int value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Semantics(
        label: '$label: $value',
        excludeSemantics: true,
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
          decoration: BoxDecoration(
            color: AppBrand.onHero.withValues(alpha: 0.15),
            borderRadius: AppRadius.rLg,
          ),
          child: Column(
            children: [
              Icon(icon, color: AppBrand.onHero, size: 20),
              AppSpacing.h4,
              Text(
                '$value',
                style: context.tt.titleLarge?.copyWith(color: AppBrand.onHero),
              ),
              Text(
                label,
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: context.tt.bodySmall?.copyWith(
                  color: AppBrand.onHeroMuted,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
