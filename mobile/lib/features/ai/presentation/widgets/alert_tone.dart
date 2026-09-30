import 'package:flutter/material.dart';
import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';

class AlertTone {
  static const danger = 0;
  static const warn = 1;
}

class AlertBanner extends StatelessWidget {
  const AlertBanner({
    super.key,
    required this.tone,
    required this.title,
    required this.body,
    this.items = const [],
  });

  final int tone;
  final String title;
  final String body;
  final List<String> items;

  @override
  Widget build(BuildContext context) {
    final isDanger = tone == AlertTone.danger;
    final color = isDanger ? context.sem.danger : context.sem.warning;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.35)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                isDanger ? Icons.warning_amber_rounded : Icons.info_outline,
                color: color,
                size: 18,
              ),
              AppSpacing.w8,
              Expanded(
                child: Text(
                  title,
                  style: TextStyle(fontWeight: FontWeight.w700, color: color),
                ),
              ),
            ],
          ),
          AppSpacing.h8,
          Text(body),
          if (items.isNotEmpty) ...[
            AppSpacing.h8,
            ...items.map(
              (item) => Padding(
                padding: const EdgeInsets.only(bottom: 2),
                child: Text('• $item'),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class StudyAdvisorCard extends StatelessWidget {
  const StudyAdvisorCard({super.key, required this.child});
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: context.cs.outlineVariant),
        color: context.cs.surfaceContainerLowest,
      ),
      child: child,
    );
  }
}

class MiniStat extends StatelessWidget {
  const MiniStat({super.key, required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: context.cs.outlineVariant),
        ),
        child: Column(
          children: [
            Text(value, style: const TextStyle(fontWeight: FontWeight.w900)),
            Text(
              label,
              style: TextStyle(
                fontSize: 12,
                color: context.cs.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
