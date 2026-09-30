import 'package:flutter/material.dart';

import '../theme/app_spacing.dart';
import '../theme/theme_context.dart';

/// Rounded progress bar with optional label and percent text (not color only).
class AppProgressBar extends StatelessWidget {
  const AppProgressBar({
    super.key,
    required this.value,
    this.label,
    this.height = 8,
  });

  final double value;
  final String? label;
  final double height;

  @override
  Widget build(BuildContext context) {
    final v = value.clamp(0.0, 1.0);
    final pct = '${(v * 100).round()}%';
    return Semantics(
      label: label ?? 'Tiến độ',
      value: pct,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (label != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 4),
              child: Row(
                children: [
                  Expanded(child: Text(label!, style: context.tt.bodySmall)),
                  Text(pct, style: context.tt.labelMedium),
                ],
              ),
            ),
          ClipRRect(
            borderRadius: AppRadius.rFull,
            child: LinearProgressIndicator(
              value: v,
              minHeight: height,
              color: context.cs.primary,
              backgroundColor: context.cs.surfaceContainerHigh,
            ),
          ),
        ],
      ),
    );
  }
}
