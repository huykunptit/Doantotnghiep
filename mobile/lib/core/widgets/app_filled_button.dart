import 'package:flutter/material.dart';

/// Primary button with loading state (keeps width, blocks taps while loading).
class AppFilledButton extends StatelessWidget {
  const AppFilledButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.loading = false,
    this.expanded = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool loading;
  final bool expanded;

  @override
  Widget build(BuildContext context) {
    final onPrimary = Theme.of(context).colorScheme.onPrimary;
    final child = Stack(
      alignment: Alignment.center,
      children: [
        Opacity(
          opacity: loading ? 0 : 1,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (icon != null) ...[
                Icon(icon, size: 20),
                const SizedBox(width: 8),
              ],
              Flexible(child: Text(label, overflow: TextOverflow.ellipsis)),
            ],
          ),
        ),
        if (loading)
          SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(
              strokeWidth: 2.5,
              color: onPrimary,
            ),
          ),
      ],
    );
    final cs = Theme.of(context).colorScheme;
    final button = FilledButton(
      onPressed: loading ? null : onPressed,
      // While loading keep the brand look (not greyed) so the spinner is visible.
      style: loading
          ? FilledButton.styleFrom(
              disabledBackgroundColor: cs.primary,
              disabledForegroundColor: cs.onPrimary,
            )
          : null,
      child: child,
    );
    return expanded ? SizedBox(width: double.infinity, child: button) : button;
  }
}
