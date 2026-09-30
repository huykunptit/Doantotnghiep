import 'package:flutter/material.dart';

import '../theme/app_spacing.dart';
import '../theme/theme_context.dart';

class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    required this.icon,
    required this.title,
    this.message,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final String title;
  final String? message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    return LayoutBuilder(
      builder: (context, box) => SingleChildScrollView(
        child: ConstrainedBox(
          constraints: BoxConstraints(
            minHeight: box.hasBoundedHeight ? box.maxHeight : 0,
          ),
          child: Center(child: _content(context, cs)),
        ),
      ),
    );
  }

  Widget _content(BuildContext context, ColorScheme cs) {
    return Padding(
      padding: AppSpacing.p24,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 48, color: cs.outline),
          AppSpacing.h12,
          Text(
            title,
            textAlign: TextAlign.center,
            style: context.tt.titleMedium,
          ),
          if (message != null) ...[
            AppSpacing.h4,
            Text(
              message!,
              textAlign: TextAlign.center,
              style: context.tt.bodyMedium?.copyWith(
                color: cs.onSurfaceVariant,
              ),
            ),
          ],
          if (actionLabel != null && onAction != null) ...[
            AppSpacing.h16,
            FilledButton(onPressed: onAction, child: Text(actionLabel!)),
          ],
        ],
      ),
    );
  }
}
