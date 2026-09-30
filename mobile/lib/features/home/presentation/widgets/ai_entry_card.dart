import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/widgets/app_card.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

/// Entry points to the two AI features: study assistant and career advisor.
class AiEntryCard extends StatelessWidget {
  const AiEntryCard({super.key});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: _Entry(
            icon: Icons.chat_bubble_outline_rounded,
            title: 'Trợ lý AI',
            subtitle: 'Hỏi đáp bài học',
            onTap: () => context.push('/ai-chat'),
          ),
        ),
        AppSpacing.w12,
        Expanded(
          child: _Entry(
            icon: Icons.auto_awesome_rounded,
            title: 'AI Career',
            subtitle: 'Định hướng nghề',
            onTap: () => context.push('/career'),
          ),
        ),
      ],
    );
  }
}

class _Entry extends StatelessWidget {
  const _Entry({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    return AppCard(
      tone: AppCardTone.tinted,
      onTap: onTap,
      padding: AppSpacing.p12,
      child: Row(
        children: [
          Icon(icon, color: cs.onPrimaryContainer),
          AppSpacing.w8,
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: context.tt.titleSmall?.copyWith(
                    color: cs.onPrimaryContainer,
                  ),
                ),
                Text(
                  subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: context.tt.bodySmall?.copyWith(
                    color: cs.onPrimaryContainer,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
