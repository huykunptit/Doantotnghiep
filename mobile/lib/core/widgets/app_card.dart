import 'package:flutter/material.dart';

import '../theme/app_spacing.dart';
import '../theme/theme_context.dart';

enum AppCardTone { normal, tinted }

/// Flat bordered card. Tappable when [onTap] is set (ripple clipped to radius).
class AppCard extends StatelessWidget {
  const AppCard({
    super.key,
    required this.child,
    this.onTap,
    this.padding = AppSpacing.p16,
    this.tone = AppCardTone.normal,
    this.margin,
  });

  final Widget child;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry padding;
  final AppCardTone tone;
  final EdgeInsetsGeometry? margin;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    final tinted = tone == AppCardTone.tinted;
    return Container(
      margin: margin,
      decoration: BoxDecoration(
        color: tinted ? cs.primaryContainer : cs.surfaceContainerLowest,
        borderRadius: AppRadius.rXl,
        border: Border.all(
          color: tinted ? Colors.transparent : cs.outlineVariant,
        ),
      ),
      child: Material(
        type: MaterialType.transparency,
        borderRadius: AppRadius.rXl,
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Padding(padding: padding, child: child),
        ),
      ),
    );
  }
}
