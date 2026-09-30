import 'package:flutter/material.dart';

import '../theme/theme_context.dart';

enum StatusTone { primary, success, warning, danger, info, neutral }

/// Resolves (background, foreground, solid) colors for a [StatusTone].
({Color bg, Color fg, Color solid}) toneColors(
  BuildContext context,
  StatusTone tone,
) {
  final cs = context.cs;
  final sem = context.sem;
  switch (tone) {
    case StatusTone.primary:
      return (
        bg: cs.primaryContainer,
        fg: cs.onPrimaryContainer,
        solid: cs.primary,
      );
    case StatusTone.success:
      return (bg: sem.successBg, fg: sem.successFg, solid: sem.success);
    case StatusTone.warning:
      return (bg: sem.warningBg, fg: sem.warningFg, solid: sem.warning);
    case StatusTone.danger:
      return (bg: sem.dangerBg, fg: sem.dangerFg, solid: sem.danger);
    case StatusTone.info:
      return (bg: sem.infoBg, fg: sem.infoFg, solid: sem.info);
    case StatusTone.neutral:
      return (
        bg: cs.surfaceContainerHigh,
        fg: cs.onSurfaceVariant,
        solid: cs.outline,
      );
  }
}

/// Pill badge. Always carries text so status is never conveyed by color alone.
class StatusBadge extends StatelessWidget {
  const StatusBadge({
    super.key,
    required this.label,
    this.tone = StatusTone.neutral,
    this.icon,
  });

  final String label;
  final StatusTone tone;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final c = toneColors(context, tone);
    return Container(
      constraints: const BoxConstraints(minHeight: 24),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(
        color: c.bg,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 14, color: c.fg),
            const SizedBox(width: 4),
          ],
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: context.tt.labelMedium?.copyWith(color: c.fg),
            ),
          ),
        ],
      ),
    );
  }
}
